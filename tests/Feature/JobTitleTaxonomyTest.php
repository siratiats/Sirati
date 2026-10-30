<?php

namespace Tests\Feature;

use App\Models\CvAnalysis;
use App\Models\JobTitle;
use App\Models\User;
use App\Services\AtsScoringService;
use Database\Seeders\JobTitleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class JobTitleTaxonomyTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_succeeds_with_valid_job_title_id(): void
    {
        Notification::fake();
        $title = $this->seedSoftwareTitle();

        $response = $this->postJson('/api/auth/register', [
            'name' => 'Salem Sayer',
            'email' => 'salem@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'job_title_id' => $title->id,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.user.job_title_id', $title->id)
            ->assertJsonPath('data.user.job_title.slug', 'software-engineer')
            ->assertJsonPath('data.user.job_title_other', null);

        $this->assertDatabaseHas('users', [
            'email' => 'salem@example.com',
            'job_title_id' => $title->id,
            'job_title_other' => null,
        ]);
    }

    public function test_registration_succeeds_without_job_title(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/auth/register', [
            'name' => 'Legacy User',
            'email' => 'legacy@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.user.job_title_id', null)
            ->assertJsonPath('data.user.job_title_other', null);

        $this->assertDatabaseHas('users', [
            'email' => 'legacy@example.com',
            'job_title_id' => null,
            'job_title_other' => null,
        ]);
    }

    public function test_other_without_job_title_other_returns_422(): void
    {
        Notification::fake();
        $other = $this->seedOtherTitle();

        $this->postJson('/api/auth/register', [
            'name' => 'Other User',
            'email' => 'other@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'job_title_id' => $other->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('job_title_other');

        $this->assertDatabaseMissing('users', ['email' => 'other@example.com']);
    }

    public function test_other_with_job_title_other_succeeds(): void
    {
        Notification::fake();
        $other = $this->seedOtherTitle();

        $this->postJson('/api/auth/register', [
            'name' => 'Other User',
            'email' => 'other-ok@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'job_title_id' => $other->id,
            'job_title_other' => 'مستشار لوجستي',
        ])
            ->assertCreated()
            ->assertJsonPath('data.user.job_title_other', 'مستشار لوجستي');

        $this->assertDatabaseHas('users', [
            'email' => 'other-ok@example.com',
            'job_title_id' => $other->id,
            'job_title_other' => 'مستشار لوجستي',
        ]);
    }

    public function test_job_titles_endpoint_returns_only_active_rows_ordered(): void
    {
        Cache::flush();

        JobTitle::query()->create([
            'slug' => 'zeta-role',
            'name_ar' => 'زائد',
            'name_en' => 'Zeta',
            'category' => 'software',
            'keywords' => ['software'],
            'is_active' => true,
            'sort_order' => 20,
        ]);
        JobTitle::query()->create([
            'slug' => 'alpha-role',
            'name_ar' => 'ألف',
            'name_en' => 'Alpha',
            'category' => 'marketing',
            'keywords' => ['marketing'],
            'is_active' => true,
            'sort_order' => 10,
        ]);
        JobTitle::query()->create([
            'slug' => 'inactive-role',
            'name_ar' => 'معطل',
            'name_en' => 'Inactive',
            'category' => 'sales',
            'keywords' => ['sales'],
            'is_active' => false,
            'sort_order' => 1,
        ]);

        $response = $this->getJson('/api/mobile/job-titles');

        $response->assertOk();
        $slugs = collect($response->json('data'))->pluck('slug')->all();

        $this->assertSame(['alpha-role', 'zeta-role'], $slugs);
        $this->assertNotContains('inactive-role', $slugs);
    }

    public function test_job_title_seeder_is_idempotent_and_categories_match_scorer(): void
    {
        $this->seed(JobTitleSeeder::class);
        $firstCount = JobTitle::query()->count();
        $this->assertGreaterThanOrEqual(100, $firstCount);

        $this->seed(JobTitleSeeder::class);
        $this->assertSame($firstCount, JobTitle::query()->count());

        $validCategories = array_keys(AtsScoringService::jobKeywords());
        $seededCategories = JobTitle::query()->pluck('category')->unique()->values()->all();

        foreach ($seededCategories as $category) {
            $this->assertContains(
                $category,
                $validCategories,
                "Seeded category [{$category}] is not a valid AtsScoringService::JOB_KEYWORDS key.",
            );
        }

        $this->assertDatabaseHas('job_titles', [
            'slug' => 'other',
            'name_ar' => 'أخرى',
            'name_en' => 'Other',
            'sort_order' => 999,
        ]);
    }

    public function test_every_scorer_category_has_labels_and_sector_categories_are_populated(): void
    {
        $this->seed(JobTitleSeeder::class);

        $fallbackAr = AtsScoringService::categoryLabel('__none__');
        $fallbackAnalysis = (new CvAnalysis)->forceFill(['category' => '__none__']);

        foreach (array_keys(AtsScoringService::jobKeywords()) as $category) {
            if ($category === 'general') {
                continue;
            }
            $analysis = (new CvAnalysis)->forceFill(['category' => $category]);
            $this->assertNotSame($fallbackAr, AtsScoringService::categoryLabel($category), "No scorer label for [{$category}].");
            $this->assertNotSame($fallbackAnalysis->category_label, $analysis->category_label, "No Arabic analysis label for [{$category}].");
            $this->assertNotSame($fallbackAnalysis->category_label_en, $analysis->category_label_en, "No English analysis label for [{$category}].");
        }

        foreach (['pharmacy', 'healthcare', 'engineering'] as $category) {
            $this->assertGreaterThanOrEqual(
                10,
                JobTitle::query()->active()->where('category', $category)->count(),
                "Category [{$category}] should offer at least 10 titles.",
            );
        }

        $slugs = JobTitle::query()->pluck('slug');
        $this->assertSame($slugs->count(), $slugs->unique()->count());
        $this->assertSame('other', JobTitle::query()->orderByDesc('sort_order')->value('slug'));
    }

    public function test_scorer_infers_sector_category_from_every_seeded_sector_title(): void
    {
        $this->seed(JobTitleSeeder::class);
        $scorer = new AtsScoringService;
        $sectors = ['pharmacy', 'healthcare', 'engineering'];
        // Categories whose own titles must always be recognised from the headline.
        $selfInferring = [...$sectors, 'software', 'data'];

        foreach (JobTitle::query()->where('slug', '!=', 'other')->get() as $title) {
            foreach ([$title->name_en, $title->name_ar, mb_strtoupper($title->name_en)] as $headline) {
                $inferred = $scorer->score("Summary\n{$headline}", $headline)['category'];
                $this->assertSame($inferred, AtsScoringService::inferCategory($headline));

                if (in_array($title->category, $selfInferring, true)) {
                    $this->assertSame($title->category, $inferred, "Headline [{$headline}] should infer [{$title->category}].");
                } else {
                    $this->assertNotContains($inferred, $sectors, "Headline [{$headline}] ({$title->category}) was pulled into [{$inferred}].");
                }
            }
        }
    }

    public function test_sector_cv_scores_better_against_its_own_keywords_than_another_sector(): void
    {
        $scorer = new AtsScoringService;
        $keywords = AtsScoringService::jobKeywords();
        unset($keywords['general']);

        // Category-bank coverage; job_match is reserved for job descriptions (issue #16).
        $coverage = fn (string $cv, string $category): float => count($scorer->score($cv, 'Specialist', $category)['keywords_found'])
            / count($keywords[$category]);

        foreach ($keywords as $category => $terms) {
            $cv = "Summary\nProfessional experienced in ".implode(', ', $terms).'.';
            $own = $coverage($cv, $category);

            foreach (array_keys($keywords) as $other) {
                $otherTerms = $keywords[$other];
                if ($other === $category || array_diff($otherTerms, $terms) === []) {
                    continue;
                }
                $this->assertGreaterThan(
                    $coverage($cv, $other),
                    $own,
                    "A [{$category}] CV should match its own keyword bank better than [{$other}].",
                );
            }
        }
    }

    public function test_update_profile_can_set_job_title(): void
    {
        $title = $this->seedSoftwareTitle();
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
            'email_verified_at' => now(),
        ]);
        Sanctum::actingAs($user);

        $this->putJson('/api/auth/profile', [
            'name' => $user->name,
            'job_title_id' => $title->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.job_title_id', $title->id)
            ->assertJsonPath('data.job_title.slug', 'software-engineer');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'job_title_id' => $title->id,
        ]);
    }

    private function seedSoftwareTitle(): JobTitle
    {
        return JobTitle::query()->create([
            'slug' => 'software-engineer',
            'name_ar' => 'مهندس برمجيات',
            'name_en' => 'Software Engineer',
            'category' => 'software',
            'keywords' => ['software', 'engineer', 'api'],
            'is_active' => true,
            'sort_order' => 1,
        ]);
    }

    private function seedOtherTitle(): JobTitle
    {
        return JobTitle::query()->create([
            'slug' => 'other',
            'name_ar' => 'أخرى',
            'name_en' => 'Other',
            'category' => 'management',
            'keywords' => ['other'],
            'is_active' => true,
            'sort_order' => 999,
        ]);
    }
}
