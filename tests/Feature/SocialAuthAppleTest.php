<?php

namespace Tests\Feature;

use App\Models\SocialAccount;
use App\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\SocialAuthKeys;
use Tests\TestCase;

class SocialAuthAppleTest extends TestCase
{
    use RefreshDatabase;
    use SocialAuthKeys;

    private const KEYS_URL = 'https://appleid.apple.com/auth/keys';

    private const TOKEN_URL = 'https://appleid.apple.com/auth/token';

    private const REVOKE_URL = 'https://appleid.apple.com/auth/revoke';

    private const BUNDLE_ID = 'com.sirati.sirati';

    private const SERVICES_ID = 'com.sirati.signin';

    /** @var array{private: string, public: string, n: string, e: string} */
    private static array $appleKey;

    /** @var array{private: string, public: string} */
    private static array $p8;

    /** Answers Apple's token endpoint; tests swap it. */
    private \Closure $tokenEndpoint;

    private \Closure $revokeEndpoint;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$appleKey = self::rsaKey();
        self::$p8 = self::ecKey();
    }

    protected function setUp(): void
    {
        parent::setUp();
        // The refresh token column is encrypted; don't depend on a local .env APP_KEY.
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        config(['services.apple' => [
            'client_ids' => [self::BUNDLE_ID, self::SERVICES_ID],
            'team_id' => 'TEAM123456',
            'key_id' => 'KEY1234567',
            'private_key' => self::$p8['private'],
            'private_key_path' => null,
            'android_package' => 'com.sirati.sirati',
        ]]);

        $this->tokenEndpoint = fn () => Http::response(['access_token' => 'a', 'refresh_token' => 'apple-refresh-1', 'id_token' => 'x']);
        $this->revokeEndpoint = fn () => Http::response('', 200);
        $jwks = self::jwks(['apple-kid' => self::$appleKey]);
        Http::fake([
            self::KEYS_URL => Http::response($jwks),
            self::TOKEN_URL => fn (Request $r) => ($this->tokenEndpoint)($r),
            self::REVOKE_URL => fn (Request $r) => ($this->revokeEndpoint)($r),
        ]);
    }

    public function test_first_sign_in_creates_the_user_and_keeps_a_revocable_refresh_token(): void
    {
        $nonce = Str::random(32);

        $this->signIn($this->idToken($nonce), ['nonce' => $nonce, 'name' => 'سالم Sayer'])
            ->assertOk()
            ->assertJsonPath('data.user.email', 'relay123@privaterelay.appleid.com')
            ->assertJsonPath('data.user.name', 'سالم Sayer')
            ->assertJsonPath('data.user.email_verified', true)
            ->assertJsonPath('data.user.has_password', false);

        $account = SocialAccount::where('provider', 'apple')->sole();
        $this->assertSame('apple-refresh-1', $account->refresh_token);
        $this->assertSame(self::BUNDLE_ID, $account->client_id);
        $this->assertNotSame('apple-refresh-1', DB::table('social_accounts')->value('refresh_token'), 'stored encrypted');
        $this->assertArrayNotHasKey('refresh_token', $account->toArray());
    }

    public function test_code_exchange_uses_a_client_secret_signed_with_the_p8_key(): void
    {
        $sent = null;
        $this->tokenEndpoint = function (Request $r) use (&$sent) {
            $sent = $r;

            return Http::response(['refresh_token' => 'r']);
        };

        foreach ([self::BUNDLE_ID, self::SERVICES_ID] as $i => $aud) {
            $nonce = Str::random(16);
            $this->signIn($this->idToken($nonce, ['aud' => $aud, 'sub' => "sub-$i"]), ['nonce' => $nonce, 'authorization_code' => "code-$i"])->assertOk();

            $this->assertSame('authorization_code', $sent['grant_type']);
            $this->assertSame("code-$i", $sent['code']);
            $this->assertSame($aud, $sent['client_id']);
            $secret = JWT::decode($sent['client_secret'], new Key(self::$p8['public'], 'ES256'));
            $header = JWT::jsonDecode(JWT::urlsafeB64Decode(explode('.', $sent['client_secret'])[0]));
            $this->assertSame('KEY1234567', $header->kid);
            $this->assertSame('ES256', $header->alg);
            $this->assertSame('TEAM123456', $secret->iss);
            $this->assertSame($aud, $secret->sub);
            $this->assertSame('https://appleid.apple.com', $secret->aud);
            $this->assertLessThanOrEqual(300, $secret->exp - $secret->iat);
        }
    }

    /** @return array<string, array{0: ?string, 1: callable(string): ?string}> */
    public static function badNonces(): array
    {
        return [
            'app sent no nonce' => [null, fn (string $raw) => hash('sha256', $raw)],
            'app sent an empty nonce' => ['', fn (string $raw) => hash('sha256', $raw)],
            'token carries no nonce' => ['raw', fn () => null],
            'token carries the raw nonce, not its hash' => ['raw', fn (string $raw) => $raw],
            'token was minted for another attempt' => ['raw', fn () => hash('sha256', 'other-attempt')],
            'hash in upper case' => ['raw', fn (string $raw) => strtoupper(hash('sha256', $raw))],
        ];
    }

    /** @param callable(string): ?string $inToken */
    #[DataProvider('badNonces')]
    public function test_replayed_or_unbound_tokens_are_rejected_before_any_exchange(?string $sentNonce, callable $inToken): void
    {
        $claims = ['nonce' => $inToken($sentNonce ?? 'raw')];
        $body = $sentNonce === null ? [] : ['nonce' => $sentNonce];

        $this->signIn($this->token($claims), $body)
            ->assertUnauthorized()
            ->assertJsonPath('reason', 'invalid_token');

        Http::assertNotSent(fn (Request $r) => $r->url() === self::TOKEN_URL);
        $this->assertDatabaseCount('users', 0);
    }

    /** @return array<string, array{0: array<string, mixed>}> */
    public static function rejectedClaims(): array
    {
        return [
            'google issuer' => [['iss' => 'https://accounts.google.com']],
            'audience of another app' => [['aud' => 'com.attacker.app']],
            'google client as audience' => [['aud' => 'web-client.apps.googleusercontent.com']],
            'expired' => [['exp' => time() - 300]],
            'no subject' => [['sub' => null]],
        ];
    }

    /** @param array<string, mixed> $claims */
    #[DataProvider('rejectedClaims')]
    public function test_tokens_with_invalid_claims_are_rejected(array $claims): void
    {
        $nonce = Str::random(20);
        $this->signIn($this->idToken($nonce, $claims), ['nonce' => $nonce])->assertUnauthorized();
        $this->assertDatabaseCount('users', 0);
    }

    /** @return array<string, array{0: mixed, 1: int}> */
    public static function emailVerifiedValues(): array
    {
        return [
            'boolean true' => [true, 200],
            'string "true"' => ['true', 200],
            'boolean false' => [false, 422],
            'string "false"' => ['false', 422],
            'missing' => [null, 422],
        ];
    }

    #[DataProvider('emailVerifiedValues')]
    public function test_apple_email_verified_is_read_as_bool_or_string(mixed $value, int $status): void
    {
        $nonce = Str::random(20);
        $this->signIn($this->idToken($nonce, ['email_verified' => $value]), ['nonce' => $nonce])->assertStatus($status);
        $this->assertDatabaseCount('users', $status === 200 ? 1 : 0);
    }

    public function test_later_sign_ins_without_email_find_the_linked_user(): void
    {
        $nonce = Str::random(20);
        $first = $this->signIn($this->idToken($nonce), ['nonce' => $nonce])->assertOk();

        $nonce = Str::random(20);
        $this->signIn($this->idToken($nonce, ['email' => null, 'email_verified' => null]), ['nonce' => $nonce])
            ->assertOk()
            ->assertJsonPath('data.user.id', $first->json('data.user.id'));

        $this->assertDatabaseCount('users', 1);
    }

    public function test_existing_user_with_the_same_email_is_linked(): void
    {
        $user = User::factory()->create(['email' => 'Salem@Example.com', 'password' => Hash::make('password123')]);
        $nonce = Str::random(20);

        $this->signIn($this->idToken($nonce, ['email' => 'salem@example.com']), ['nonce' => $nonce, 'name' => 'Ignored'])
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.name', $user->name)
            ->assertJsonPath('data.user.has_password', true)
            ->assertJsonPath('data.user.social_providers', ['apple']);
    }

    public function test_the_user_payload_lists_each_linked_provider_once(): void
    {
        $user = User::factory()->create(['email' => 'both@example.com']);
        $session = $user->createToken('t')->plainTextToken;
        $this->withToken($session)->getJson('/api/auth/me')->assertJsonPath('data.social_providers', []);

        $user->socialAccounts()->create(['provider' => 'google', 'provider_subject' => 'g-1']);
        $user->socialAccounts()->create(['provider' => 'google', 'provider_subject' => 'g-2']);
        $nonce = Str::random(20);
        $this->signIn($this->idToken($nonce, ['email' => 'both@example.com']), ['nonce' => $nonce])
            ->assertOk()
            ->assertJsonPath('data.user.social_providers', ['apple', 'google']);
    }

    public function test_authorization_code_is_required(): void
    {
        $nonce = Str::random(20);
        $this->signIn($this->idToken($nonce), ['nonce' => $nonce, 'authorization_code' => null])
            ->assertStatus(422)
            ->assertJsonValidationErrors('authorization_code');
    }

    /** @return array<string, array{0: callable(): mixed, 1: int}> */
    public static function tokenEndpointFailures(): array
    {
        return [
            'code already used' => [fn () => Http::response(['error' => 'invalid_grant'], 400), 401],
            'wrong client' => [fn () => Http::response(['error' => 'invalid_client'], 401), 401],
            'apple down' => [fn () => Http::response('', 503), 503],
            'network failure' => [fn () => throw new ConnectionException('timeout'), 503],
        ];
    }

    /** @param callable(): mixed $responder */
    #[DataProvider('tokenEndpointFailures')]
    public function test_a_failed_code_exchange_creates_nothing(callable $responder, int $status): void
    {
        $this->tokenEndpoint = \Closure::fromCallable($responder);
        $nonce = Str::random(20);

        $this->signIn($this->idToken($nonce), ['nonce' => $nonce])
            ->assertStatus($status)
            ->assertJsonMissingPath('data.token');

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('social_accounts', 0);
    }

    /** @return array<string, array{0: array<string, mixed>}> */
    public static function incompleteConfigs(): array
    {
        return [
            'no client ids' => [['client_ids' => []]],
            'no team id' => [['team_id' => null]],
            'empty team id' => [['team_id' => '']],
            'no key id' => [['key_id' => null]],
            'no private key' => [['private_key' => null]],
            'unreadable key path' => [['private_key' => null, 'private_key_path' => '/nowhere/AuthKey.p8']],
            'garbage key' => [['private_key' => "-----BEGIN PRIVATE KEY-----\nnot a key\n-----END PRIVATE KEY-----"]],
            'rsa key instead of the p8' => [['private_key' => 'RSA']],
        ];
    }

    /** @param array<string, mixed> $override */
    #[DataProvider('incompleteConfigs')]
    public function test_incomplete_apple_config_fails_closed_with_503(array $override): void
    {
        if (($override['private_key'] ?? null) === 'RSA') {
            $override['private_key'] = self::$appleKey['private'];
        }
        config(['services.apple' => array_merge(config('services.apple'), $override)]);
        Log::spy();
        $nonce = Str::random(20);

        $this->signIn($this->idToken($nonce), ['nonce' => $nonce])
            ->assertStatus(503)
            ->assertJsonPath('reason', 'not_configured')
            ->assertJsonMissingPath('data.token');

        Log::shouldHaveReceived('critical')->once();
        Http::assertNothingSent();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_the_p8_key_is_accepted_with_escaped_newlines_or_from_a_file(): void
    {
        $escaped = str_replace("\n", '\n', self::$p8['private']);
        $path = tempnam(sys_get_temp_dir(), 'p8');
        file_put_contents($path, self::$p8['private']);

        try {
            foreach ([['private_key' => $escaped], ['private_key' => null, 'private_key_path' => $path]] as $i => $override) {
                config(['services.apple' => array_merge(config('services.apple'), $override)]);
                $nonce = Str::random(20);
                $this->signIn($this->idToken($nonce, ['sub' => "sub-$i", 'email' => "p8-$i@example.com"]), ['nonce' => $nonce])->assertOk();
            }
        } finally {
            @unlink($path);
        }
    }

    public function test_deleting_the_account_revokes_the_apple_token(): void
    {
        $revoked = [];
        $this->revokeEndpoint = function (Request $r) use (&$revoked) {
            $revoked[] = $r->data();

            return Http::response('', 200);
        };
        $nonce = Str::random(20);
        $session = $this->signIn($this->idToken($nonce, ['aud' => self::SERVICES_ID]), ['nonce' => $nonce])->json('data.token');

        $nonce = Str::random(20);
        $this->withToken($session)->deleteJson('/api/auth/account', [
            'provider' => 'apple',
            'id_token' => $this->idToken($nonce, ['aud' => self::SERVICES_ID]),
            'nonce' => $nonce,
        ])->assertOk();

        $this->assertCount(1, $revoked);
        $this->assertSame('apple-refresh-1', $revoked[0]['token']);
        $this->assertSame('refresh_token', $revoked[0]['token_type_hint']);
        $this->assertSame(self::SERVICES_ID, $revoked[0]['client_id']);
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('social_accounts', 0);
    }

    public function test_deletion_still_succeeds_when_apple_revocation_fails(): void
    {
        $this->revokeEndpoint = fn () => Http::response('', 500);
        $nonce = Str::random(20);
        $session = $this->signIn($this->idToken($nonce), ['nonce' => $nonce])->json('data.token');
        User::query()->sole()->forceFill(['password' => Hash::make('pw-123456')])->save();
        Log::spy();

        $this->withToken($session)->deleteJson('/api/auth/account', ['password' => 'pw-123456'])->assertOk();

        Log::shouldHaveReceived('error')->atLeast()->once();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_deleting_with_an_apple_token_needs_this_attempts_nonce(): void
    {
        $nonce = Str::random(20);
        $session = $this->signIn($this->idToken($nonce), ['nonce' => $nonce])->json('data.token');

        // The sign-in token, replayed with its old nonce missing.
        $this->withToken($session)->deleteJson('/api/auth/account', ['provider' => 'apple', 'id_token' => $this->idToken($nonce)])
            ->assertUnauthorized();
        $this->assertDatabaseCount('users', 1);
    }

    /** @return array<string, array{0: array<string, string>}> */
    public static function callbackBodies(): array
    {
        return [
            'success' => [['code' => 'c.0.abc', 'id_token' => 'h.p.s', 'state' => 'st', 'user' => '{"name":{"firstName":"سالم"}}']],
            'cancelled' => [['error' => 'user_cancelled_authorize', 'state' => 'st']],
            'values with url and intent syntax' => [['code' => 'a&b=c#Intent;package=evil;end', 'state' => "x y\n;end"]],
        ];
    }

    /** @param array<string, string> $body */
    #[DataProvider('callbackBodies')]
    public function test_android_web_flow_callback_hands_the_result_to_the_app(array $body): void
    {
        $response = $this->post('/api/auth/apple/callback', $body + ['extra' => 'dropped']);

        $response->assertStatus(307);
        $location = $response->headers->get('Location');
        $this->assertStringStartsWith('intent://callback?', $location);
        $this->assertStringEndsWith('#Intent;package=com.sirati.sirati;scheme=signinwithapple;end', $location);
        $this->assertSame(1, substr_count($location, '#'), 'values cannot open a second fragment');

        parse_str(parse_url($location, PHP_URL_QUERY), $query);
        ksort($body);
        ksort($query);
        $this->assertSame($body, $query);
    }

    // ---- helpers -------------------------------------------------------

    /** @param array<string, mixed> $extra null removes a field */
    private function signIn(string $idToken, array $extra = []): \Illuminate\Testing\TestResponse
    {
        $body = array_merge(['id_token' => $idToken, 'authorization_code' => 'auth-code'], $extra);

        return $this->postJson('/api/auth/social/apple', array_filter($body, fn ($v) => $v !== null));
    }

    /** A token bound to [rawNonce]. @param array<string, mixed> $overrides */
    private function idToken(string $rawNonce, array $overrides = []): string
    {
        return $this->token(['nonce' => hash('sha256', $rawNonce)] + $overrides);
    }

    /** @param array<string, mixed> $overrides null removes a claim */
    private function token(array $overrides): string
    {
        $now = time();
        $claims = array_merge([
            'iss' => 'https://appleid.apple.com',
            'aud' => self::BUNDLE_ID,
            'sub' => '001234.abcdef.1234',
            'email' => 'relay123@privaterelay.appleid.com',
            'email_verified' => 'true',
            'is_private_email' => 'true',
            'iat' => $now,
            'exp' => $now + 600,
        ], $overrides);

        return JWT::encode(array_filter($claims, fn ($v) => $v !== null), self::$appleKey['private'], 'RS256', 'apple-kid');
    }
}
