<?php

namespace Tests\Feature;

use App\Models\EducationContent;
use App\Models\JobNews;
use App\Models\JobTitle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Abobker85/Sirati#15: the content API never invents or overstates data.
 * Rows are random, so the assertions hold for any data, not a fixture.
 */
class ContentApiHonestyTest extends TestCase
{
    use RefreshDatabase;

    private const SOURCE_TYPES = ['gov', 'enterprise', 'tamheer', 'startup', 'general'];

    private const VERIFIED_WORDS = ['verif', 'موثق', 'موثوق', 'معتمد'];

    public function test_verified_filter_returns_exactly_the_verified_rows_for_any_rows(): void
    {
        foreach ([11, 23, 47] as $seed) {
            mt_srand($seed);
            JobNews::query()->delete();
            $rows = $this->randomJobRows(24);

            foreach (['ar', 'en'] as $lang) {
                $verifiedIds = $this->visibleIn($rows, $lang)->filter(fn (JobNews $row) => $row->is_verified)
                    ->pluck('id')->sort()->values()->all();
                $items = collect($this->getJson("/api/mobile/job-news?lang={$lang}&is_verified=1")->assertOk()->json('data.items'));

                $this->assertSame($verifiedIds, $items->pluck('id')->sort()->values()->all(), "seed {$seed}, {$lang}");
                $this->assertTrue($items->every(fn (array $item) => $item['is_verified'] === true));
            }
        }
    }

    public function test_no_response_claims_verified_for_an_unverified_row(): void
    {
        foreach ([5, 8] as $seed) {
            mt_srand($seed);
            JobNews::query()->delete();
            $rows = $this->randomJobRows(24)->keyBy('id');

            foreach (['ar', 'en'] as $lang) {
                $data = $this->getJson("/api/mobile/job-news?lang={$lang}")->assertOk()->json('data');

                // The feed mixes verified and unverified rows, so its own copy never claims it.
                $this->assertNoVerifiedClaim($data['title']);
                $this->assertNoVerifiedClaim($data['subtitle']);

                $this->assertCount($this->visibleIn($rows, $lang)->count(), $data['items']);
                foreach ($data['items'] as $item) {
                    $row = $rows->get($item['id']);
                    $this->assertSame((bool) $row->is_verified, $item['is_verified']);

                    $shown = $this->getJson("/api/mobile/job-news/{$row->id}?lang={$lang}")->assertOk()->json('data');
                    $this->assertSame((bool) $row->is_verified, $shown['is_verified']);

                    if (! $row->is_verified) {
                        foreach ([$item, $shown] as $payload) {
                            foreach (['source_type_label', 'source_badge', 'valid_until_label', 'published_label'] as $field) {
                                $this->assertNoVerifiedClaim((string) ($payload[$field] ?? ''));
                            }
                        }
                    }
                }
            }
        }
    }

    public function test_dashboard_latest_news_is_the_newest_visible_job_or_null(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        foreach (['ar', 'en'] as $lang) {
            $this->assertNull($this->getJson("/api/mobile/dashboard?lang={$lang}")->assertOk()->json('data.latest_news'));
        }

        foreach ([3, 19, 77] as $seed) {
            mt_srand($seed);
            JobNews::query()->delete();
            $rows = $this->randomJobRows(15, mixVisibility: true);

            foreach (['ar', 'en'] as $lang) {
                $visible = $rows->filter(fn (JobNews $row) => $row->is_published
                    && ($row->language === $lang || $row->external_source !== null)
                    && $row->published_at !== null);
                $expected = $visible->sortBy([['published_at', 'desc'], ['id', 'desc']])->first();

                $news = $this->getJson("/api/mobile/dashboard?lang={$lang}")->assertOk()->json('data.latest_news');

                if ($expected === null) {
                    $this->assertNull($news, "seed {$seed}, {$lang}");

                    continue;
                }

                $this->assertSame($expected->id, $news['id'], "seed {$seed}, {$lang}");
                $this->assertSame($expected->title, $news['title']);
                $this->assertSame($expected->location, $news['location']);
                $this->assertSame($expected->published_at->toISOString(), $news['published_at']);
                // Real fields only: no pre-written "x hours ago · city" copy.
                $this->assertSame(['id', 'title', 'location', 'published_at'], array_keys($news));
            }
        }
    }

    public function test_education_returns_an_honest_empty_response_when_there_is_no_content(): void
    {
        foreach (['ar', 'en'] as $lang) {
            foreach (['study', 'news', 'certificate'] as $type) {
                $data = $this->getJson("/api/mobile/education?lang={$lang}&type={$type}")->assertOk()->json('data');

                $this->assertSame([], $data['study_cards']);
                $this->assertNull($data['featured_course']);
                $this->assertNull($data['target_role']);
                $this->assertArrayNotHasKey('profile', $data);
            }
        }
    }

    public function test_education_returns_exactly_the_published_rows_for_any_content(): void
    {
        foreach ([2, 31, 64] as $seed) {
            mt_srand($seed);
            EducationContent::query()->delete();
            $rows = collect(range(1, mt_rand(0, 14)))->map(fn () => EducationContent::create([
                'language' => $this->pick(['ar', 'en']),
                'type' => $this->pick(['study', 'news', 'certificate']),
                'title' => 'T '.Str::random(8),
                'body' => 'B '.Str::random(20),
                'duration_label' => $this->pick([null, 'D '.Str::random(4)]),
                'target_role' => $this->pick([null, 'R '.Str::random(5)]),
                'badge' => $this->pick([null, 'G '.Str::random(4)]),
                'button_label' => $this->pick([null, 'L '.Str::random(4)]),
                'icon' => $this->pick([null, 'school']),
                'sort_order' => mt_rand(0, 3),
                'is_featured' => (bool) mt_rand(0, 1),
                'is_published' => mt_rand(0, 4) > 0,
            ]));

            foreach (['ar', 'en'] as $lang) {
                foreach (['study', 'news', 'certificate'] as $type) {
                    $data = $this->getJson("/api/mobile/education?lang={$lang}&type={$type}")->assertOk()->json('data');
                    $eligible = $rows->filter(fn (EducationContent $row) => $row->is_published && $row->language === $lang && $row->type === $type);

                    $cards = collect($data['study_cards']);
                    $this->assertSame(
                        min(6, $eligible->where('is_featured', false)->count()),
                        $cards->count(),
                        "seed {$seed}, {$lang}, {$type}",
                    );
                    foreach ($cards as $card) {
                        $row = $eligible->firstWhere('id', $card['id']);
                        $this->assertNotNull($row, 'A card must be a stored row.');
                        $this->assertFalse($row->is_featured);
                        $this->assertSame($row->title, $card['title']);
                        $this->assertSame($row->body, $card['body']);
                        $this->assertSame($row->duration_label, $card['duration']);
                    }

                    $featured = $data['featured_course'];
                    if ($eligible->where('is_featured', true)->isEmpty()) {
                        $this->assertNull($featured);
                    } else {
                        $row = $eligible->firstWhere('id', $featured['id']);
                        $this->assertNotNull($row);
                        $this->assertTrue($row->is_featured);
                        $this->assertSame($row->title, $featured['title']);
                        $this->assertSame($row->badge, $featured['badge']);
                    }

                    // No signed-in user, so there is no target role to tailor to.
                    $this->assertNull($data['target_role']);
                }
            }
        }
    }

    public function test_education_target_role_is_the_users_own_role_never_a_default(): void
    {
        EducationContent::create([
            'language' => 'en', 'type' => 'study', 'title' => 'x', 'body' => 'y',
            'target_role' => 'Some Content Role', 'is_published' => true,
        ]);

        $user = User::factory()->create(['job_title_id' => null]);
        Sanctum::actingAs($user);
        $this->assertNull($this->getJson('/api/mobile/education?lang=en')->assertOk()->json('data.target_role'));

        foreach ([['Nurse '.Str::random(3), 'ممرض '.Str::random(3)], ['Welder '.Str::random(3), 'لحام '.Str::random(3)]] as [$en, $ar]) {
            $title = JobTitle::create([
                'name_en' => $en, 'name_ar' => $ar, 'slug' => Str::slug($en),
                'category' => 'general', 'is_active' => true,
            ]);
            $user->forceFill(['job_title_id' => $title->id])->save();
            $user->unsetRelation('jobTitle');

            $this->assertSame($en, $this->getJson('/api/mobile/education?lang=en')->assertOk()->json('data.target_role'));
            $this->assertSame($ar, $this->getJson('/api/mobile/education?lang=ar')->assertOk()->json('data.target_role'));
        }
    }

    /** @return \Illuminate\Support\Collection<int, JobNews> */
    private function randomJobRows(int $count, bool $mixVisibility = false)
    {
        $base = Carbon::parse('2026-10-01 08:00:00');

        return collect(range(1, $count))->map(function (int $i) use ($base, $mixVisibility) {
            $aggregated = mt_rand(0, 2) === 0;

            return JobNews::create([
                'language' => $this->pick(['ar', 'en']),
                'title' => 'Job '.Str::random(10),
                'company' => $this->pick([null, 'Co '.Str::random(5)]),
                'location' => $this->pick([null, 'Loc '.Str::random(5)]),
                'body' => 'Body '.Str::random(30),
                'url' => 'https://example.com/'.Str::random(8),
                'external_source' => $aggregated ? 'telegram' : null,
                'source_type' => $this->pick(self::SOURCE_TYPES),
                'employment_type' => $this->pick(['full_time', 'tamheer', 'part_time']),
                'is_verified' => (bool) mt_rand(0, 1),
                'published_at' => $mixVisibility && mt_rand(0, 5) === 0 ? null : $base->copy()->addMinutes(mt_rand(0, 60 * 24 * 20)),
                'is_published' => $mixVisibility ? mt_rand(0, 3) > 0 : true,
                'sort_order' => 0,
            ]);
        });
    }

    /** Curated rows show in their own language; aggregated posts show in both. */
    private function visibleIn($rows, string $lang)
    {
        return $rows->filter(fn (JobNews $row) => $row->language === $lang || $row->external_source !== null);
    }

    private function assertNoVerifiedClaim(string $text): void
    {
        foreach (self::VERIFIED_WORDS as $word) {
            $this->assertStringNotContainsStringIgnoringCase($word, $text);
        }
    }

    private function pick(array $values): mixed
    {
        return $values[mt_rand(0, count($values) - 1)];
    }
}
