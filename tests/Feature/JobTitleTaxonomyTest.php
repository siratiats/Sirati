<?php

namespace Tests\Feature;

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
        $this->assertGreaterThanOrEqual(60, $firstCount);
        $this->assertLessThanOrEqual(80, $firstCount);

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
