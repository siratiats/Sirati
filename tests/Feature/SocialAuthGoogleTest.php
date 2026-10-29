<?php

namespace Tests\Feature;

use App\Models\SocialAccount;
use App\Models\User;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\SocialAuthKeys;
use Tests\TestCase;

class SocialAuthGoogleTest extends TestCase
{
    use RefreshDatabase;
    use SocialAuthKeys;

    private const CERTS_URL = 'https://www.googleapis.com/oauth2/v3/certs';

    private const WEB_CLIENT = 'web-client.apps.googleusercontent.com';

    private const ANDROID_CLIENT = 'android-client.apps.googleusercontent.com';

    /** @var array{private: string, public: string, n: string, e: string} */
    private static array $signingKey;

    /** @var array{private: string, public: string, n: string, e: string} */
    private static array $otherKey;

    /** Answers the faked Google certs URL; tests swap it to simulate rotation or outages. */
    private \Closure $certs;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$signingKey = self::rsaKey();
        self::$otherKey = self::rsaKey();
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.google.client_ids' => [self::WEB_CLIENT, self::ANDROID_CLIENT]]);
        $this->fakeCerts();
        Http::fake([self::CERTS_URL => fn () => ($this->certs)()]);
    }

    public function test_valid_token_creates_a_verified_password_less_user_and_a_working_session(): void
    {
        $email = 'new.'.Str::lower(Str::random(8)).'@example.com';

        $response = $this->signIn($this->idToken(['email' => $email, 'name' => 'سالم Sayer']), ['device_name' => 'pixel']);

        $response->assertOk()
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.email', $email)
            ->assertJsonPath('data.user.name', 'سالم Sayer')
            ->assertJsonPath('data.user.email_verified', true)
            ->assertJsonPath('data.user.has_password', false)
            ->assertJsonPath('data.user.social_providers', ['google']);

        $user = User::where('email', $email)->sole();
        $this->assertFalse($user->hasPassword());
        $this->assertDatabaseHas('social_accounts', ['user_id' => $user->id, 'provider' => 'google', 'provider_subject' => 'sub-1']);
        $this->assertDatabaseHas('personal_access_tokens', ['tokenable_id' => $user->id, 'name' => 'pixel']);

        $this->withToken($response->json('data.token'))
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', $email);
    }

    public function test_answer_has_the_same_shape_as_email_login(): void
    {
        User::factory()->create(['email' => 'password.user@example.com', 'password' => Hash::make('password123')]);
        $login = $this->postJson('/api/auth/login', ['email' => 'password.user@example.com', 'password' => 'password123'])->assertOk();

        $social = $this->signIn($this->idToken())->assertOk();

        $this->assertSame($this->keyPaths($login->json()), $this->keyPaths($social->json()));
    }

    public function test_every_configured_client_id_is_an_accepted_audience(): void
    {
        foreach ([self::WEB_CLIENT, self::ANDROID_CLIENT, [self::ANDROID_CLIENT, 'someone-else']] as $i => $aud) {
            $this->signIn($this->idToken(['aud' => $aud, 'sub' => "sub-aud-$i", 'email' => "aud$i@example.com"]))->assertOk();
        }
    }

    /** @return array<string, array{0: array<string, mixed>}> */
    public static function rejectedClaims(): array
    {
        $now = time();

        return [
            'audience of another app' => [['aud' => 'attacker.apps.googleusercontent.com']],
            'audience list without ours' => [['aud' => ['a.apps.googleusercontent.com', 'b']]],
            'audience missing' => [['aud' => null]],
            'issuer of another provider' => [['iss' => 'https://appleid.apple.com']],
            'issuer look-alike' => [['iss' => 'https://accounts.google.com.evil.test']],
            'issuer missing' => [['iss' => null]],
            'expired beyond leeway' => [['exp' => $now - 120]],
            'expiry missing' => [['exp' => null]],
            'issued in the future' => [['iat' => $now + 3600, 'exp' => $now + 7200]],
            'subject missing' => [['sub' => null]],
            'subject empty' => [['sub' => '']],
            'subject not a string' => [['sub' => 12345]],
        ];
    }

    /** @param array<string, mixed> $claims */
    #[DataProvider('rejectedClaims')]
    public function test_token_with_invalid_claims_is_rejected_without_a_session(array $claims): void
    {
        $this->signIn($this->idToken($claims))
            ->assertUnauthorized()
            ->assertJsonPath('reason', 'invalid_token')
            ->assertJsonMissingPath('data.token');

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_expiry_within_the_leeway_is_accepted(): void
    {
        $this->signIn($this->idToken(['exp' => time() - 20]))->assertOk();
    }

    public function test_token_signed_by_another_key_under_our_kid_is_rejected(): void
    {
        $forged = JWT::encode($this->claims(), self::$otherKey['private'], 'RS256', 'kid-1');

        $this->signIn($forged)->assertUnauthorized();
        $this->assertDatabaseCount('users', 0);
    }

    /** @return array<string, array{0: callable(self): string}> */
    public static function malformedTokens(): array
    {
        return [
            'not a jwt' => [fn () => 'definitely-not-a-token'],
            'two segments' => [fn () => 'abc.def'],
            'unsigned (alg none)' => [fn (self $t) => self::b64(['alg' => 'none', 'kid' => 'kid-1']).'.'.self::b64($t->claims()).'.'],
            'hmac with public data as secret' => [fn (self $t) => JWT::encode($t->claims(), self::$signingKey['n'], 'HS256', 'kid-1')],
            'header without kid' => [fn (self $t) => JWT::encode($t->claims(), self::$signingKey['private'], 'RS256')],
            'tampered payload' => [function (self $t) {
                [$h, , $s] = explode('.', $t->idToken());

                return $h.'.'.self::b64($t->claims(['email' => 'victim@example.com'])).'.'.$s;
            }],
        ];
    }

    /** @param callable(self): string $make */
    #[DataProvider('malformedTokens')]
    public function test_malformed_or_forged_token_is_rejected(callable $make): void
    {
        $this->signIn($make($this))->assertUnauthorized();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_unknown_kid_refetches_keys_at_most_once_a_minute(): void
    {
        $this->signIn($this->idToken())->assertOk();
        Http::assertSentCount(1);

        $unknown = JWT::encode($this->claims(['sub' => 'sub-2']), self::$signingKey['private'], 'RS256', 'rotated-kid');
        $this->signIn($unknown)->assertUnauthorized();
        $this->signIn($unknown)->assertUnauthorized();
        $this->signIn(JWT::encode($this->claims(), self::$signingKey['private'], 'RS256', Str::random()))->assertUnauthorized();

        Http::assertSentCount(2);
    }

    public function test_rotated_key_is_picked_up_from_a_refetch(): void
    {
        $this->signIn($this->idToken())->assertOk();

        $this->fakeCerts(['kid-1' => self::$signingKey, 'kid-2' => self::$otherKey]);
        $rotated = JWT::encode($this->claims(['sub' => 'sub-rotated', 'email' => 'rotated@example.com']), self::$otherKey['private'], 'RS256', 'kid-2');

        $this->signIn($rotated)->assertOk()->assertJsonPath('data.user.email', 'rotated@example.com');
    }

    /** @return array<string, array{0: mixed}> */
    public static function unverifiedEmailClaims(): array
    {
        return [
            'email_verified false' => [false],
            'email_verified "false"' => ['false'],
            'email_verified missing' => [null],
            'email_verified 1' => [1],
        ];
    }

    #[DataProvider('unverifiedEmailClaims')]
    public function test_unverified_google_email_never_creates_or_links_an_account(mixed $verified): void
    {
        $existing = User::factory()->create(['email' => 'owner@example.com']);

        foreach (['owner@example.com', 'fresh@example.com'] as $email) {
            $this->signIn($this->idToken(['email' => $email, 'email_verified' => $verified]))
                ->assertStatus(422)
                ->assertJsonPath('reason', 'email_unverified')
                ->assertJsonMissingPath('data.token');
        }

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('social_accounts', 0);
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertTrue($existing->fresh()->hasPassword());
    }

    public function test_token_without_email_is_rejected_for_an_unlinked_subject(): void
    {
        $this->signIn($this->idToken(['email' => null]))->assertStatus(422);
        $this->assertDatabaseCount('users', 0);
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function emailCasings(): array
    {
        return [
            'same case' => ['salem.sayer@example.com', 'salem.sayer@example.com'],
            'upper in token' => ['salem.sayer@example.com', 'SALEM.Sayer@Example.COM'],
            'upper in database' => ['Salem.Sayer@Example.com', 'salem.sayer@example.com'],
            'spaces around token email' => ['n.k@sirati.test', '  N.K@sirati.test '],
        ];
    }

    #[DataProvider('emailCasings')]
    public function test_existing_verified_user_with_that_email_is_linked_not_duplicated(string $stored, string $inToken): void
    {
        $user = User::factory()->create(['email' => $stored, 'password' => Hash::make('password123')]);

        $this->signIn($this->idToken(['email' => $inToken]))
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.has_password', true);

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas('social_accounts', ['user_id' => $user->id, 'provider_subject' => 'sub-1']);
        // Their password keeps working.
        $this->postJson('/api/auth/login', ['email' => $stored, 'password' => 'password123'])->assertOk();
    }

    public function test_linking_an_unverified_squatted_account_drops_the_squatters_password_and_sessions(): void
    {
        $squatted = User::factory()->unverified()->create([
            'email' => 'victim@example.com',
            'password' => Hash::make('attacker-knows-this'),
        ]);
        $attackerToken = $squatted->createToken('attacker')->plainTextToken;

        $this->signIn($this->idToken(['email' => 'victim@example.com']))
            ->assertOk()
            ->assertJsonPath('data.user.id', $squatted->id)
            ->assertJsonPath('data.user.email_verified', true)
            ->assertJsonPath('data.user.has_password', false);

        $this->assertFalse($squatted->fresh()->hasPassword());
        $this->postJson('/api/auth/login', ['email' => 'victim@example.com', 'password' => 'attacker-knows-this'])->assertStatus(422);
        $this->app['auth']->forgetGuards();
        $this->withToken($attackerToken)->getJson('/api/auth/me')->assertUnauthorized();
    }

    public function test_linked_subject_keeps_its_user_when_the_google_email_changes(): void
    {
        $first = $this->signIn($this->idToken(['email' => 'before@example.com']))->assertOk();
        User::factory()->create(['email' => 'after@example.com']);

        $this->signIn($this->idToken(['email' => 'after@example.com']))
            ->assertOk()
            ->assertJsonPath('data.user.id', $first->json('data.user.id'))
            ->assertJsonPath('data.user.email', 'before@example.com');

        $this->assertSame(1, SocialAccount::count());
    }

    /** @return array<string, array{0: mixed}> */
    public static function missingClientIds(): array
    {
        return [
            'empty list' => [[]],
            'null' => [null],
            'only non-strings' => [[null, 0, false]],
        ];
    }

    #[DataProvider('missingClientIds')]
    public function test_missing_client_ids_fail_closed_with_503_and_a_critical_log(mixed $config): void
    {
        config(['services.google.client_ids' => $config]);
        Log::spy();

        $this->signIn($this->idToken())
            ->assertStatus(503)
            ->assertJsonPath('reason', 'not_configured')
            ->assertJsonMissingPath('data.token');

        Log::shouldHaveReceived('critical')->once();
        Http::assertNothingSent();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_config_parses_comma_separated_env_ids(): void
    {
        putenv('GOOGLE_CLIENT_IDS= a.apps , ,b.apps,');
        try {
            $config = require base_path('config/services.php');
        } finally {
            putenv('GOOGLE_CLIENT_IDS');
        }

        $this->assertSame(['a.apps', 'b.apps'], $config['google']['client_ids']);
    }

    /** @return array<string, array{0: callable(): mixed}> */
    public static function brokenKeyServers(): array
    {
        return [
            'server error' => [fn () => Http::response('down', 500)],
            'not found' => [fn () => Http::response('', 404)],
            'json without keys' => [fn () => Http::response(['no' => 'keys'])],
            'keys not a list' => [fn () => Http::response(['keys' => 'x'])],
            'html page' => [fn () => Http::response('<html></html>', 200, ['Content-Type' => 'text/html'])],
            'connection failure' => [fn () => throw new ConnectionException('timeout')],
        ];
    }

    /** @param callable(): mixed $responder */
    #[DataProvider('brokenKeyServers')]
    public function test_key_server_failures_answer_503_not_500(callable $responder): void
    {
        $this->certs = \Closure::fromCallable($responder);

        $this->signIn($this->idToken())
            ->assertStatus(503)
            ->assertJsonPath('reason', 'provider_unavailable')
            ->assertJsonMissingPath('data.token');

        $this->assertDatabaseCount('users', 0);
    }

    public function test_unsupported_provider_is_not_routed(): void
    {
        foreach (['facebook', 'twitter', 'GOOGLE', 'Apple', 'google2'] as $provider) {
            $this->postJson("/api/auth/social/$provider", ['id_token' => $this->idToken()])->assertNotFound();
        }
    }

    public function test_id_token_is_required(): void
    {
        $this->postJson('/api/auth/social/google', [])->assertStatus(422)->assertJsonValidationErrors('id_token');
    }

    public function test_password_less_user_cannot_sign_in_with_email_and_any_password(): void
    {
        $this->signIn($this->idToken(['email' => 'nopass@example.com']))->assertOk();

        foreach (['', 'password', Str::random(12)] as $password) {
            $this->postJson('/api/auth/login', ['email' => 'nopass@example.com', 'password' => $password])->assertStatus(422);
        }
    }

    public function test_password_less_user_deletes_the_account_by_signing_in_with_google_again(): void
    {
        $token = $this->signIn($this->idToken(['email' => 'leaving@example.com']))->json('data.token');

        $this->withToken($token)->deleteJson('/api/auth/account', [])->assertStatus(422)->assertJsonValidationErrors(['provider', 'id_token']);

        $other = $this->idToken(['sub' => 'someone-else', 'email' => 'someone@example.com']);
        $this->signIn($other)->assertOk();
        $this->withToken($token)->deleteJson('/api/auth/account', ['provider' => 'google', 'id_token' => $other])
            ->assertStatus(422)->assertJsonValidationErrors('id_token');
        $this->withToken($token)->deleteJson('/api/auth/account', ['provider' => 'google', 'id_token' => $this->idToken(['aud' => 'x'])])
            ->assertUnauthorized();
        $this->assertDatabaseHas('users', ['email' => 'leaving@example.com']);

        $this->withToken($token)->deleteJson('/api/auth/account', ['provider' => 'google', 'id_token' => $this->idToken()])->assertOk();

        $this->assertDatabaseMissing('users', ['email' => 'leaving@example.com']);
        $this->assertDatabaseMissing('social_accounts', ['provider_subject' => 'sub-1']);
    }

    public function test_user_with_a_password_still_deletes_with_the_password_only(): void
    {
        $token = $this->signIn($this->idToken(['email' => 'haspass@example.com']))->json('data.token');
        User::where('email', 'haspass@example.com')->sole()->forceFill(['password' => Hash::make('secret-123')])->save();

        $this->withToken($token)->deleteJson('/api/auth/account', ['provider' => 'google', 'id_token' => $this->idToken()])
            ->assertStatus(422)->assertJsonValidationErrors('password');
        $this->withToken($token)->deleteJson('/api/auth/account', ['password' => 'secret-123'])->assertOk();
    }

    // ---- helpers -------------------------------------------------------

    /** @param array<string, mixed> $extra */
    private function signIn(string $idToken, array $extra = []): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/auth/social/google', ['id_token' => $idToken] + $extra);
    }

    /** @param array<string, mixed> $overrides null removes a claim */
    public function claims(array $overrides = []): array
    {
        $now = time();
        $claims = array_merge([
            'iss' => 'https://accounts.google.com',
            'aud' => self::WEB_CLIENT,
            'sub' => 'sub-1',
            'email' => 'salem@example.com',
            'email_verified' => true,
            'name' => 'Salem Sayer',
            'iat' => $now,
            'exp' => $now + 3600,
        ], $overrides);

        return array_filter($claims, fn ($v) => $v !== null);
    }

    /** @param array<string, mixed> $overrides */
    public function idToken(array $overrides = []): string
    {
        return JWT::encode($this->claims($overrides), self::$signingKey['private'], 'RS256', 'kid-1');
    }

    /** @param array<string, array{n: string, e: string}>|null $keys */
    private function fakeCerts(?array $keys = null): void
    {
        $jwks = self::jwks($keys ?? ['kid-1' => self::$signingKey]);
        $this->certs = fn () => Http::response($jwks, 200, ['Cache-Control' => 'public, max-age=21600']);
    }

    /** @return list<string> */
    private function keyPaths(array $json, string $prefix = ''): array
    {
        $paths = [];
        foreach ($json as $key => $value) {
            $path = $prefix.$key;
            $paths[] = $path;
            // Lists (e.g. social_providers) differ by account, not by shape.
            if (is_array($value) && ! array_is_list($value) && $key !== 'subscription') {
                array_push($paths, ...$this->keyPaths($value, $path.'.'));
            }
        }
        sort($paths);

        return $paths;
    }
}
