<?php

namespace Tests\Feature;

use App\Cv\CvExportGuard;
use App\Cv\LegacySectionParser;
use App\Enums\AiStatus;
use App\Models\GeneratedCv;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The app's CV wizard collects experience, education and certificates as
 * separate entries and sends them as text in the shape below (writer:
 * flutter_app/lib/features/cv_builder/domain/cv_entries.dart, pinned by
 * flutter_app/test/features/cv_builder/cv_entries_test.dart). These are the
 * same strings: the server must read them back as the same entries.
 */
class WizardEntriesContractTest extends TestCase
{
    use RefreshDatabase;

    private const EXPERIENCE = "E-Commerce Specialist | Al-Dawaa | Al Khobar | 11/2025 - Present\n"
        ."- Ran Google Ads campaigns across product categories\n"
        ."- Raised ROAS by 30%\n"
        ."\n"
        ."مسؤول مبيعات | شركة النخبة | 03/2021 - 10/2025\n"
        .'- إدارة حسابات كبار العملاء';

    private const EDUCATION = "بكالوريوس إدارة أعمال | جامعة الملك فيصل | 09/2015 - 06/2019\n"
        .'- مرتبة الشرف الثانية';

    private const CERTIFICATIONS = "- Google Ads Search | Google | 05/2024\n"
        .'- إدارة المشاريع الاحترافية | PMI';

    public function test_wizard_entries_parse_into_the_same_cv_entries(): void
    {
        foreach (['ar', 'en'] as $language) {
            $experience = LegacySectionParser::experience(self::EXPERIENCE, $language);
            $this->assertCount(2, $experience);

            [$current, $previous] = $experience;
            $this->assertSame('E-Commerce Specialist', $current->title->{$language});
            $this->assertSame('Al-Dawaa', $current->company->{$language});
            $this->assertSame('Al Khobar', $current->location->{$language});
            $this->assertSame('11/2025', $current->startDate);
            $this->assertNull($current->endDate);
            $this->assertTrue($current->isCurrent);
            $this->assertSame(
                ['Ran Google Ads campaigns across product categories', 'Raised ROAS by 30%'],
                array_map(fn ($bullet) => $bullet->{$language}, $current->bullets),
            );

            $this->assertSame('مسؤول مبيعات', $previous->title->{$language});
            $this->assertSame('شركة النخبة', $previous->company->{$language});
            $this->assertSame('', $previous->location->{$language});
            $this->assertSame(['03/2021', '10/2025', false], [$previous->startDate, $previous->endDate, $previous->isCurrent]);

            [$degree] = LegacySectionParser::education(self::EDUCATION, $language);
            $this->assertSame('بكالوريوس إدارة أعمال', $degree->degree->{$language});
            $this->assertSame('جامعة الملك فيصل', $degree->institution->{$language});
            $this->assertSame(['09/2015', '06/2019'], [$degree->startDate, $degree->endDate]);
            $this->assertSame('مرتبة الشرف الثانية', $degree->narrative->{$language});

            $certifications = LegacySectionParser::certifications(self::CERTIFICATIONS, $language);
            $this->assertSame(
                [['Google Ads Search', 'Google', '05/2024'], ['إدارة المشاريع الاحترافية', 'PMI', null]],
                array_map(fn ($c) => [$c->name->{$language}, $c->issuer->{$language}, $c->date], $certifications),
            );
        }
    }

    /**
     * Entries from the wizard are structured on their own, so a CV whose AI
     * step failed or never ran can still be exported (Salem: "not ready").
     */
    public function test_wizard_entries_make_the_cv_exportable_without_ai(): void
    {
        $user = User::factory()->create();

        foreach ([AiStatus::Failed, AiStatus::NotConfigured] as $status) {
            $cv = GeneratedCv::create([
                'user_id' => $user->id,
                'full_name' => 'Salem Alshammari',
                'email' => 'salem@example.com',
                'phone' => '+966500000000',
                'target_job_title' => 'E-Commerce Specialist',
                'language' => 'en',
                'skills_input' => 'Google Ads, SEO',
                'experience_input' => self::EXPERIENCE,
                'education_input' => self::EDUCATION,
                'certifications_input' => self::CERTIFICATIONS,
                'generated_markdown' => '',
                'form_payload' => [],
                'ai_status' => $status,
                'score_total' => 60,
                'grade' => 'C',
            ]);

            $this->assertTrue($cv->cvDocument()->hasStructuredExperience(), $status->value);
            $this->assertTrue($cv->cvDocument()->hasStructuredEducation(), $status->value);
            $this->assertNull(CvExportGuard::blockedReason($cv), $status->value);
        }
    }
}
