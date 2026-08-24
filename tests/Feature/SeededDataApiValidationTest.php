<?php

namespace Tests\Feature;

use App\Models\CvTemplate;
use App\Models\JobTitle;
use App\Models\User;
use Database\Seeders\CvTemplateSeeder;
use Database\Seeders\JobTitleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeededDataApiValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            CvTemplateSeeder::class,
            JobTitleSeeder::class,
        ]);
    }

    public function test_job_titles_api_returns_all_seeded_taxonomy(): void
    {
        $response = $this->getJson('/api/mobile/job-titles');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'slug',
                        'name_ar',
                        'name_en',
                        'category',
                        'keywords',
                        'sort_order',
                    ],
                ],
            ]);

        $data = $response->json('data');
        $this->assertIsArray($data);
        $this->assertGreaterThanOrEqual(70, count($data), 'Expected at least 70 seeded job titles.');

        $slugs = collect($data)->pluck('slug')->all();
        $this->assertContains('software-engineer', $slugs);
        $this->assertContains('data-analyst', $slugs);
        $this->assertContains('accountant', $slugs);
        $this->assertContains('hr-specialist', $slugs);
        $this->assertContains('other', $slugs);

        foreach ($data as $item) {
            $this->assertNotEmpty($item['name_ar'], "Missing Arabic name for slug: {$item['slug']}");
            $this->assertNotEmpty($item['name_en'], "Missing English name for slug: {$item['slug']}");
        }
    }

    public function test_cv_templates_api_returns_all_seeded_templates(): void
    {
        $responseEn = $this->getJson('/api/mobile/cv-templates?lang=en');

        $responseEn->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'items' => [
                        '*' => [
                            'id',
                            'slug',
                            'name',
                            'name_ar',
                            'name_en',
                            'language_direction',
                            'supported_languages',
                            'is_default',
                        ],
                    ],
                ],
            ]);

        $itemsEn = $responseEn->json('data.items');
        $this->assertCount(6, $itemsEn, 'Expected 6 seeded CV templates for English.');

        $slugsEn = collect($itemsEn)->pluck('slug')->all();
        $expectedSlugs = [
            'ats-classic-professional',
            'graduate-launchpad',
            'technical-specialist-matrix',
            'executive-leadership-brief',
            'sales-impact-performer',
            'bilingual-global-professional',
        ];

        foreach ($expectedSlugs as $expected) {
            $this->assertContains($expected, $slugsEn, "Expected template slug {$expected} was not found.");
        }

        $defaultCount = collect($itemsEn)->where('is_default', true)->count();
        $this->assertSame(1, $defaultCount, 'Expected exactly one default CV template.');

        // Test Arabic filters correctly (5 templates support Arabic)
        $responseAr = $this->getJson('/api/mobile/cv-templates?lang=ar');
        $responseAr->assertOk();
        $this->assertCount(5, $responseAr->json('data.items'));
    }

    public function test_education_api_returns_valid_structure(): void
    {
        $responseAr = $this->getJson('/api/mobile/education?lang=ar&type=study');
        $responseAr->assertOk()->assertJsonStructure(['data']);

        $responseEn = $this->getJson('/api/mobile/education?lang=en&type=career');
        $responseEn->assertOk()->assertJsonStructure(['data']);
    }

    public function test_job_news_api_returns_valid_structure(): void
    {
        $response = $this->getJson('/api/mobile/job-news?lang=ar');
        $response->assertOk()->assertJsonStructure(['data']);
    }

    public function test_register_api_accepts_seeded_job_title(): void
    {
        $title = JobTitle::where('slug', 'software-engineer')->firstOrFail();

        $response = $this->postJson('/api/auth/register', [
            'name' => 'Sara Developer',
            'email' => 'sara@example.com',
            'password' => 'SecurePass123!@#',
            'password_confirmation' => 'SecurePass123!@#',
            'job_title_id' => $title->id,
            'phone' => '+966500000001',
            'location' => 'Riyadh',
        ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'data' => [
                    'token',
                    'user' => ['id', 'name', 'email', 'job_title_id'],
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'sara@example.com',
            'job_title_id' => $title->id,
        ]);
    }
}
