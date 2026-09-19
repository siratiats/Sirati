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
                'color_tokens' => ['primary' => '#1f3864', 'accent' => '#2e4a7d'],
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
        ];

        foreach ($templates as $template) {
            CvTemplate::query()->updateOrCreate(
                ['slug' => $template['slug']],
                $template,
            );
        }
    }
}
