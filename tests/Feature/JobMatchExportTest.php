<?php

namespace Tests\Feature;

use App\Contracts\CvAiProvider;
use App\Cv\CvDocument;
use App\Models\User;
use App\Services\AtsScoringService;
use App\Support\ArabicPdfText;
use Database\Seeders\CvTemplateSeeder;
use Faker\Factory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Smalot\PdfParser\Parser;
use Tests\TestCase;

class JobMatchExportTest extends TestCase
{
    use RefreshDatabase;

    public static function exportCases(): iterable
    {
        yield 'English' => ['en', ['C++', 'SQL']];
        yield 'Arabic' => ['ar', ['تحليل البيانات', 'إدارة المخاطر']];
        yield 'Arabic with Latin and punctuation' => ['ar', ['C++', 'SQL']];
        yield 'Arabic with other Latin terms' => ['ar', ['C#', 'PostgreSQL']];
    }

    #[DataProvider('exportCases')]
    public function test_selected_grounded_edits_reach_the_downloaded_copy_without_losing_or_reordering_facts(string $language, array $skills): void
    {
        $this->seed(CvTemplateSeeder::class);
        config(['queue.default' => 'sync', 'monetization.enabled' => true, 'monetization.job_match.daily_limit' => 1]);
        Sanctum::actingAs(User::factory()->create(['premium_until' => now()->addMonth()]));

        // Values vary independently of the review examples; every expected fact
        // comes from the submitted document rather than a hard-coded PDF sample.
        $faker = Factory::create($language === 'ar' ? 'ar_SA' : 'en_US');
        $ar = $language === 'ar';
        $local = fn (string $value): array => [$language => $value];
        $name = $faker->name();
        $headline = $ar
            ? fake()->randomElement(['مهندس أنظمة', 'محلل بيانات', 'مطور برمجيات', 'مدير مشاريع'])
            : $faker->unique()->jobTitle();
        $role = $ar ? 'مسؤول '.fake()->randomElement(['المنصات', 'التطبيقات', 'الخدمات']) : $faker->unique()->jobTitle();
        $company = $ar ? 'شركة '.fake()->randomElement(['التطوير', 'الابتكار', 'الحلول']).' '.$faker->lastName() : $faker->company();
        $secondRole = $ar ? 'مستشار '.fake()->randomElement(['الجودة', 'التقنية', 'العمليات']) : $faker->unique()->jobTitle();
        $secondCompany = $ar ? 'مؤسسة '.$faker->lastName() : $faker->company();
        $degree = $ar ? 'بكالوريوس '.fake()->randomElement(['الحاسب', 'الهندسة', 'الإدارة']) : 'Bachelor of '.$faker->word();
        $institution = $ar ? 'جامعة '.$faker->lastName() : $faker->lastName().' University';
        $summary = $ar
            ? 'خبرة في '.fake()->randomElement(['تطوير الأنظمة', 'إدارة التطبيقات', 'تحليل العمليات']).' وتحسين '.fake()->randomElement(['الخدمات', 'الجودة', 'المنتجات'])
            : 'Experienced in '.fake()->randomElement(['building systems', 'managing applications', 'analyzing operations']).' and improving '.$faker->word();
        $bullet = $ar ? 'تطوير تطبيقات لخدمة '.$faker->lastName() : 'Built applications for '.$faker->word();
        $untouched = $ar ? 'توثيق إجراءات '.fake()->randomElement(['العمل', 'الجودة', 'التطوير']).' وتدريب الفريق' : 'Documented '.$faker->word().' procedures and trained the team';
        $secondBullet = $ar ? 'متابعة جودة الخدمات ودعم '.$faker->lastName() : 'Monitored '.$faker->word().' quality and supported users';
        shuffle($skills);
        $acceptedSummary = $summary.($ar ? ' باستخدام ' : ' using ').implode($ar ? ' و ' : ' and ', $skills);
        $acceptedBullet = $bullet.($ar ? ' باستخدام ' : ' using ').$skills[0];
        $rejectedBullet = ($ar ? 'تحسين ' : 'Improved ').$untouched;
        $email = $faker->safeEmail();
        $phone = '+9665'.random_int(10000000, 99999999);
        $startYear = random_int(2005, 2010);
        $entryId = fake()->uuid();
        $document = CvDocument::fromArray([
            'export_language' => $language,
            'personal' => ['full_name' => $local($name), 'headline' => $local($headline), 'email' => $email, 'phone' => $phone],
            'summary' => $local($summary),
            'experience' => [
                ['id' => $entryId, 'title' => $local($role), 'company' => $local($company),
                    'start_date' => (string) ($startYear + 6), 'end_date' => (string) ($startYear + 9),
                    'bullets' => [$local($bullet), $local($untouched)]],
                ['id' => fake()->uuid(), 'title' => $local($secondRole), 'company' => $local($secondCompany),
                    'start_date' => (string) ($startYear + 3), 'end_date' => (string) ($startYear + 5),
                    'bullets' => [$local($secondBullet)]],
            ],
            'education' => [['id' => fake()->uuid(), 'degree' => $local($degree), 'institution' => $local($institution),
                'start_date' => (string) $startYear, 'end_date' => (string) ($startYear + 3)]],
            'skills' => array_map(fn (string $skill): array => ['id' => fake()->uuid(), 'name' => $local($skill)], $skills),
        ]);
        $suggestion = fn (string $key, string $section, string $before, string $after, ?int $index = null): array => [
            'key' => $key,
            'target' => ['section' => $section, 'field' => $section === 'summary' ? 'text' : 'bullets',
                'entry_id' => $section === 'summary' ? null : $entryId, 'bullet_index' => $index],
            'before' => $before, 'after' => $after,
            'rationale' => $summary, 'jd_terms' => $skills, 'needs_user_fact' => false,
        ];
        $summaryKey = fake()->uuid();
        $bulletKey = fake()->uuid();
        $rejectedKey = fake()->uuid();
        $suggestions = [
            $suggestion($summaryKey, 'summary', $summary, $acceptedSummary),
            $suggestion($bulletKey, 'experience', $bullet, $acceptedBullet, 0),
            $suggestion($rejectedKey, 'experience', $untouched, $rejectedBullet, 1),
        ];
        $jd = $headline.' '.implode(' ', $skills);
        $provider = Mockery::mock(CvAiProvider::class);
        $provider->shouldReceive('isConfigured')->twice()->andReturn(true);
        $provider->shouldReceive('generateCv')->once()->andReturn(['cv_markdown' => $document->resolve()->toMarkdown()]);
        $provider->shouldReceive('jobMatchAdvice')->once()
            ->with(Mockery::on(fn (array $snapshot): bool => $snapshot['summary'][$language] === $summary), $jd, $language)
            ->andReturn(['executive_summary' => $summary, 'suggestions' => $suggestions]);
        $this->app->instance(CvAiProvider::class, $provider);

        $sourceId = $this->postJson('/api/generated-cvs', [
            ...$document->legacySnapshot(), 'document' => $document->toArray(),
        ])->assertCreated()->json('data.id');
        $input = ['generated_cv_id' => $sourceId, 'job_description_input' => $jd, 'language' => $language];
        $headers = ['Idempotency-Key' => fake()->uuid()];
        $created = $this->postJson('/api/job-matches', $input, $headers)->assertCreated()
            ->assertJsonPath('data.ai_status', 'completed')->assertJsonCount(3, 'data.ai_feedback.suggestions');
        $matchId = $created->json('data.id');
        foreach ($created->json('data.ai_feedback.suggestions') as $advice) {
            $this->assertFalse($advice['needs_user_fact'], 'Grounded advice must remain selectable through the real guard.');
        }
        $originalScore = $created->json('data.job_match');
        $applied = $this->postJson('/api/job-matches/'.$matchId.'/apply-suggestions', [
            'suggestion_keys' => [$summaryKey, $bulletKey],
        ])->assertOk();
        $copyId = $applied->json('data.generated_cv.id');
        $this->assertNotSame($sourceId, $copyId);
        $this->assertGreaterThanOrEqual($originalScore, $applied->json('data.job_match.job_match'));
        $this->getJson('/api/job-matches/'.$matchId)->assertOk()
            ->assertJsonPath('data.applied_generated_cv_id', $copyId);
        $this->postJson('/api/job-matches', $input, $headers)->assertOk()->assertJsonPath('data.id', $matchId);
        $this->postJson('/api/job-matches', $input)->assertStatus(429);

        // Production consumer: GeneratedCvController::downloadPdfApi ->
        // CvTemplateRenderer::downloadResponse. Assert on the bytes sent to users.
        $download = $this->get('/api/generated-cvs/'.$copyId.'/download?template=ats-classic-professional&language='.$language);
        $download->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $download->getContent());
        $raw = (new Parser)->parseContent($download->getContent())->getText();
        // The sole Arabic visual-to-logical boundary; never normalize input or expectations.
        $text = $ar ? ArabicPdfText::normalizeExtracted($raw, 'rtl') : $raw;
        $text = $this->collapseWhitespace($text);
        $this->assertStringContainsString($email, $text);
        $this->assertStringContainsString($phone, $text);
        foreach ([$acceptedSummary, $acceptedBullet, ...$skills] as $accepted) {
            $this->assertStringContainsString($accepted, $text);
        }
        $this->assertStringNotContainsString($rejectedBullet, $text);
        // Every fact is required; missing sections cannot silently bypass order assertions.
        $previous = -1;
        foreach ([$name, $headline, $role, $company, $untouched, $secondRole, $secondCompany, $secondBullet, $degree, $institution] as $fact) {
            // The English template intentionally uppercases the candidate's name.
            $position = mb_stripos($text, $fact, $previous + 1);
            $this->assertNotFalse($position, 'Missing exported fact: '.$fact.' in '.$text);
            $this->assertGreaterThan($previous, $position, 'Export reordered fact: '.$fact);
            $previous = $position;
        }

        // Score the actual exported content using the production scorer,
        // so a correct database copy cannot mask a stale or incomplete PDF.
        $exportScore = app(AtsScoringService::class)->score($text, $headline, null, $jd);
        $this->assertGreaterThanOrEqual($originalScore, $exportScore['job_match']);
    }

    private function collapseWhitespace(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', $text));
    }
}
