<?php

namespace Database\Seeders;

use App\Models\CvTemplate;
use Illuminate\Database\Seeder;

class CvTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'name_ar' => 'كلاسيكي احترافي',
                'name_en' => 'ATS Classic Professional',
                'slug' => 'ats-classic-professional',
                'renderer_key' => 'classic_rtl',
                'preview_image_path' => null,
                'language_direction' => 'both',
                'supported_languages' => ['ar', 'en'],
                'supported_sections' => ['summary', 'skills', 'experience', 'education', 'certifications', 'projects', 'languages'],
                'color_tokens' => ['primary' => '#1f2937', 'accent' => '#2563eb'],
                'config_json' => [
                    'category' => 'professional',
                    'description' => 'Single-column ATS-optimized template for broad professional use.',
                    'ats_score_expectation' => 'high',
                    'target_audience' => ['freshers', 'mid_level', 'career_switchers'],
                ],
                'is_active' => true,
                'is_default' => true,
                'sort_order' => 1,
            ],
            [
                'name_ar' => 'انطلاقة الخريجين',
                'name_en' => 'Graduate Launchpad',
                'slug' => 'graduate-launchpad',
                'renderer_key' => 'classic_rtl',
                'preview_image_path' => null,
                'language_direction' => 'both',
                'supported_languages' => ['ar', 'en'],
                'supported_sections' => ['career_objective', 'education', 'projects', 'internships', 'skills', 'certifications', 'activities', 'languages'],
                'color_tokens' => ['primary' => '#0f172a', 'accent' => '#0ea5e9'],
                'config_json' => [
                    'category' => 'entry_level',
                    'description' => 'Education and project-forward template for graduates and interns.',
                    'ats_score_expectation' => 'high',
                    'target_audience' => ['students', 'fresh_graduates', 'internship_seekers'],
                ],
                'is_active' => true,
                'is_default' => false,
                'sort_order' => 2,
            ],
            [
                'name_ar' => 'المتخصص التقني',
                'name_en' => 'Technical Specialist Matrix',
                'slug' => 'technical-specialist-matrix',
                'renderer_key' => 'modern_rtl',
                'preview_image_path' => null,
                'language_direction' => 'ltr',
                'supported_languages' => ['en'],
                'supported_sections' => ['summary', 'technical_skills', 'experience', 'projects', 'tools', 'certifications', 'education', 'open_source'],
                'color_tokens' => ['primary' => '#111827', 'accent' => '#0284c7'],
                'config_json' => [
                    'category' => 'technical',
                    'description' => 'Keyword-dense technical template with skill matrix and project outcomes.',
                    'ats_score_expectation' => 'high',
                    'target_audience' => ['engineers', 'data_professionals', 'cybersecurity'],
                ],
                'is_active' => true,
                'is_default' => false,
                'sort_order' => 3,
            ],
            [
                'name_ar' => 'القيادة التنفيذية',
                'name_en' => 'Executive Leadership Brief',
                'slug' => 'executive-leadership-brief',
                'renderer_key' => 'classic_rtl',
                'preview_image_path' => null,
                'language_direction' => 'both',
                'supported_languages' => ['ar', 'en'],
                'supported_sections' => ['executive_profile', 'core_competencies', 'leadership_experience', 'achievements', 'board_roles', 'education', 'certifications'],
                'color_tokens' => ['primary' => '#1e293b', 'accent' => '#0d9488'],
                'config_json' => [
                    'category' => 'executive',
                    'description' => 'Leadership-focused template for strategic and senior management roles.',
                    'ats_score_expectation' => 'high',
                    'target_audience' => ['senior_managers', 'directors', 'executives'],
                ],
                'is_active' => true,
                'is_default' => false,
                'sort_order' => 4,
            ],
            [
                'name_ar' => 'أداء المبيعات',
                'name_en' => 'Sales Impact Performer',
                'slug' => 'sales-impact-performer',
                'renderer_key' => 'classic_rtl',
                'preview_image_path' => null,
                'language_direction' => 'both',
                'supported_languages' => ['ar', 'en'],
                'supported_sections' => ['summary', 'skills', 'experience', 'revenue_highlights', 'certifications', 'education', 'tools', 'languages'],
                'color_tokens' => ['primary' => '#172554', 'accent' => '#dc2626'],
                'config_json' => [
                    'category' => 'sales',
                    'description' => 'Performance template centered on revenue, quota, and pipeline outcomes.',
                    'ats_score_expectation' => 'high',
                    'target_audience' => ['sales_reps', 'account_managers', 'business_development'],
                ],
                'is_active' => true,
                'is_default' => false,
                'sort_order' => 5,
            ],
            [
                'name_ar' => 'محترف ثنائي اللغة',
                'name_en' => 'Bilingual Global Professional',
                'slug' => 'bilingual-global-professional',
                'renderer_key' => 'modern_rtl',
                'preview_image_path' => null,
                'language_direction' => 'both',
                'supported_languages' => ['ar', 'en'],
                'supported_sections' => ['summary', 'skills', 'experience', 'education', 'certifications', 'languages', 'projects', 'volunteering'],
                'color_tokens' => ['primary' => '#0f172a', 'accent' => '#2563eb'],
                'config_json' => [
                    'category' => 'international',
                    'description' => 'Locale-aware ATS template designed for bilingual and global applicants.',
                    'ats_score_expectation' => 'high',
                    'target_audience' => ['multilingual_candidates', 'international_applicants', 'mena_market'],
                ],
                'is_active' => true,
                'is_default' => false,
                'sort_order' => 6,
            ],
        ];

        foreach ($templates as $template) {
            CvTemplate::query()->updateOrCreate(
                ['slug' => $template['slug']],
                $template,
            );
        }
    }
}
