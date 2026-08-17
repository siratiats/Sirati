<?php

namespace App\Services\Ai\Prompts;

use App\Services\AtsScoringService;
use Yethee\Tiktoken\EncoderProvider;

/**
 * Versioned static system prefix for the analysis_advice operation.
 *
 * Version: {@see self::VERSION}
 *
 * This prefix is intentionally STATIC and byte-identical on every request so
 * OpenAI automatic prompt caching can engage (prefix must be >= 1,024 tokens;
 * target band 1,400–1,800). Variable data (CV text, score JSON, job title)
 * belongs ONLY in the user message.
 *
 * Phase 2 interaction — Structured Output schemas:
 * Phase 2 schemas (App\Services\Ai\Schemas\*) are frozen relative to this
 * prefix. Editing a schema changes the request shape and is a cache-affecting
 * deploy: warm-up misses are expected until the new prefix is re-cached.
 * Also bump CachedCvAiProvider::PROMPT_VERSION whenever this prefix changes so
 * Phase 1 response-level cache does not serve answers from the old prompt.
 *
 * Acceptance: if usage.prompt_tokens_details.cached_tokens stays 0 for a full
 * day of traffic, caching is not engaging — re-check token length with
 * {@see self::tokenCount()} and confirm no per-request values leak into
 * {@see self::build()}.
 */
final class AnalysisAdviceSystemPrompt
{
    /**
     * Bump when any section of this prefix changes (role text, rubric
     * explanations, few-shots, register guidance, or runtime-rendered scorer
     * metadata layout).
     */
    public const VERSION = '1';

    /**
     * Existing role instruction — keep VERBATIM; do not paraphrase.
     */
    public const ROLE_INSTRUCTION = 'You are an Arabic-first ATS CV advisor. Return only valid JSON. Do not invent experience, dates, certifications, employers, metrics, or skills not present in the CV. Suggest wording improvements and missing keywords only.';

    /**
     * High/low score guidance keyed to AtsScoringService::CRITERIA_META.
     * Explanations only — max scores and labels are read from the scorer.
     *
     * @var array<string, array{high: string, low: string}>
     */
    private const CRITERIA_GUIDANCE = [
        'format' => [
            'high' => 'Scannable plain text: clear breaks, short bullets, ATS-friendly layout.',
            'low' => 'Dense or sparse layout that parsers and recruiters struggle to scan.',
        ],
        'keywords' => [
            'high' => 'Strong role vocabulary in summary, skills, and experience.',
            'low' => 'Weak keyword coverage; suggest missing terms only if CV evidence supports them.',
        ],
        'structure' => [
            'high' => 'Core sections present with contact details near the top.',
            'low' => 'Missing sections or poor order; fix summary/experience/skills first.',
        ],
        'experience' => [
            'high' => 'Dated roles, strong verbs, quantified outcomes.',
            'low' => 'Task lists without metrics; rewrite to impact + number without inventing figures.',
        ],
        'education' => [
            'high' => 'Degree, field, years, and certifications are easy to find.',
            'low' => 'Education incomplete; request facts only—never invent credentials.',
        ],
        'summary' => [
            'high' => 'Concise 3–5 line summary with role focus from existing facts.',
            'low' => 'Missing or vague summary; rewrite from CV evidence only.',
        ],
        'contact' => [
            'high' => 'Email, phone, and LinkedIn visible in the header.',
            'low' => 'Missing contact channels; recommend fields the candidate can supply.',
        ],
    ];

    /**
     * Few-shot bullet rewrites (weak → improved + reason), Arabic-first advice.
     * Spread across the 8 AtsScoringService job categories.
     *
     * @var list<array{category: string, before: string, after: string, reason: string}>
     */
    private const FEW_SHOTS = [
        [
            'category' => 'software',
            'before' => 'عملت على تطوير أنظمة الويب.',
            'after' => 'طورت واجهات API بـ Laravel تخدم 25 مستخدماً وخفضت زمن التقارير 35%.',
            'reason' => 'مهمة عامة → إنجاز بأداة ونطاق ورقم دون اختلاق.',
        ],
        [
            'category' => 'software',
            'before' => 'Responsible for backend tasks and bug fixing.',
            'after' => 'نفذت إصلاحات backend لخدمات API ضمن دورة Agile/Scrum.',
            'reason' => 'صياغة مسؤولية تقنية بدل قائمة مهام.',
        ],
        [
            'category' => 'marketing',
            'before' => 'إدارة حسابات التواصل الاجتماعي.',
            'after' => 'أدرت حملات content ورفعت التفاعل عبر SEO والرسائل الإعلانية.',
            'reason' => 'ربط النشاط بقناة وهدف قابل للقياس.',
        ],
        [
            'category' => 'marketing',
            'before' => 'Worked on ads and brand stuff.',
            'after' => 'نفذت حملات Google Ads وMeta Ads وراقبت analytics لتحسين التكلفة.',
            'reason' => 'استبدال غموض بكلمات مفتاحية للدور.',
        ],
        [
            'category' => 'data',
            'before' => 'قمت بتحليل البيانات وإعداد التقارير.',
            'after' => 'بنيت dashboard بـ SQL وPower BI لتسريع reporting التشغيلي.',
            'reason' => 'توضيح الأدوات والمخرجات لمطابقة ATS.',
        ],
        [
            'category' => 'data',
            'before' => 'Did some machine learning experiments.',
            'after' => 'نفذت تجارب data analysis وmachine learning ووثقت النتائج للفريق.',
            'reason' => 'تجنب المبالغة؛ الإبقاء على نطاق التجربة فقط.',
        ],
        [
            'category' => 'sales',
            'before' => 'تحقيق مستهدفات المبيعات والتواصل مع العملاء.',
            'after' => 'أدرت pipeline في CRM وتابعت الفرص عبر prospecting نحو quota.',
            'reason' => 'مصطلحات مبيعات قياسية دون اختلاق إيراد.',
        ],
        [
            'category' => 'sales',
            'before' => 'Good at negotiation and closing.',
            'after' => 'قدت مفاوضات عقود ووسّعت pipeline عبر CRM (Salesforce/HubSpot).',
            'reason' => 'صفة عامة → سلوك بيع ملموس.',
        ],
        [
            'category' => 'finance',
            'before' => 'مسؤول عن الميزانيات والتقارير المالية.',
            'after' => 'أعددت forecast وتابع P&L وcash flow لدعم قرارات budget.',
            'reason' => 'مفردات مالية دقيقة بدل وصف فضفاض.',
        ],
        [
            'category' => 'finance',
            'before' => 'Helped with accounting and audits.',
            'after' => 'دعمت accounting وشاركت في ملفات audit عبر Excel/SQL.',
            'reason' => 'توضيح الدعم والأدوات دون ادعاء شهادات.',
        ],
        [
            'category' => 'hr',
            'before' => 'التوظيف ومتابعة الموظفين الجدد.',
            'after' => 'قدت recruitment وtalent acquisition وأدرت onboarding للموظفين الجدد.',
            'reason' => 'مراحل التوظيف بمصطلحات HR المعيارية.',
        ],
        [
            'category' => 'hr',
            'before' => 'Handled employee issues and performance.',
            'after' => 'دعمت employee relations ودورات performance management وفق سياسات HR.',
            'reason' => 'مسؤوليات علاقات موظفين قابلة للفحص.',
        ],
        [
            'category' => 'management',
            'before' => 'إدارة الفريق ووضع الخطط.',
            'after' => 'قدت فريقاً متعدد التخصصات ووضعت roadmap مرتبطة بـ stakeholders.',
            'reason' => 'leadership وتخطيط تشغيلي بدل إدارة عامة.',
        ],
        [
            'category' => 'management',
            'before' => 'Owned operations and budget decisions.',
            'after' => 'أشرفت على operations وربطت قرارات budget بنتائج الأداء.',
            'reason' => 'مسؤولية إدارية بحدود قرار واضحة.',
        ],
        [
            'category' => 'ecommerce',
            'before' => 'إدارة المتجر الإلكتروني والمنتجات.',
            'after' => 'حسّنت product listing على Shopify/WooCommerce ورفعت conversion عند checkout.',
            'reason' => 'منصة + مؤشر تحويل عندما تدعمه السيرة.',
        ],
    ];

    public function build(): string
    {
        return implode("\n\n", [
            $this->roleSection(),
            $this->rubricSection(),
            $this->keywordBanksSection(),
            $this->fewShotSection(),
            $this->registerSection(),
        ]);
    }

    /**
     * Token count via tiktoken for the model family used by Sirati (gpt-4.1-mini → o200k_base).
     */
    public function tokenCount(?string $model = null): int
    {
        $model ??= (string) config('services.openai.model', 'gpt-4.1-mini');
        $provider = new EncoderProvider;
        $encoder = $provider->getForModel($model);

        return count($encoder->encode($this->build()));
    }

    private function roleSection(): string
    {
        return self::ROLE_INSTRUCTION;
    }

    private function rubricSection(): string
    {
        $lines = [
            '## ATS scoring rubric (Sirati deterministic scorer)',
            'Interpret the provided score JSON with this rubric. Do not re-score from scratch. Prioritize fixes for the weakest high-weight criteria first.',
            'Criteria (key | max | Arabic label | high | low):',
        ];

        foreach (AtsScoringService::criteriaMeta() as $key => $meta) {
            $guidance = self::CRITERIA_GUIDANCE[$key] ?? [
                'high' => 'Strong evidence.',
                'low' => 'Weak evidence.',
            ];

            $lines[] = sprintf(
                '- %s | max %d | %s | HIGH: %s | LOW: %s',
                $key,
                $meta['max'],
                $meta['label'],
                $guidance['high'],
                $guidance['low'],
            );
        }

        $lines[] = 'Prefer priorities that lift keywords, quantified experience, structure, and summary before low-impact polish.';

        return implode("\n", $lines);
    }

    private function keywordBanksSection(): string
    {
        $lines = [
            '## Job category keyword banks',
            'Each target job maps to one category. Recommend missing keywords only when CV evidence supports them; never invent tools or employment.',
        ];

        foreach (AtsScoringService::jobKeywords() as $category => $keywords) {
            $lines[] = sprintf(
                '- %s: %s',
                $category,
                implode(', ', $keywords),
            );
        }

        return implode("\n", $lines);
    }

    private function fewShotSection(): string
    {
        $lines = [
            '## Few-shot bullet improvements (Arabic-first)',
            'Pattern for bullet_improvements: before → after → reason. Ground every rewrite in CV facts. Examples:',
        ];

        foreach (self::FEW_SHOTS as $index => $example) {
            $n = $index + 1;
            $lines[] = sprintf(
                '%d) [%s] before: %s | after: %s | reason: %s',
                $n,
                $example['category'],
                $example['before'],
                $example['after'],
                $example['reason'],
            );
        }

        return implode("\n", $lines);
    }

    private function registerSection(): string
    {
        return ArabicRegisterRules::section();
    }
}
