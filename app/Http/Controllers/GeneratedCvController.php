<?php

namespace App\Http\Controllers;

use App\Contracts\CvAiProvider;
use App\Cv\CvDocument;
use App\Cv\TemplateSwitch;
use App\Enums\AiStatus;
use App\Http\Resources\GeneratedCvResource;
use App\Jobs\GenerateCvContentJob;
use App\Models\CvAnalysis;
use App\Models\GeneratedCv;
use App\Services\Ai\CachedCvAiProvider;
use App\Services\AtsScoringService;
use App\Services\CvTemplateRenderer;
use App\Services\ErrorReporter;
use App\Support\CvMarkdownIdentityBlock;
use App\Support\Idempotency;
use App\Support\SignedRecordAccess;
use Illuminate\Http\Request;
use Throwable;

class GeneratedCvController extends Controller
{
    public function indexApi(Request $request)
    {
        $perPage = min(50, max(1, (int) $request->integer('per_page', 20)));

        return GeneratedCvResource::collection(
            $request->user()->generatedCvs()->latest()->paginate($perPage)
        );
    }

    public function create()
    {
        return view('generated-cvs.create');
    }

    public function store(Request $request, CvAiProvider $openAi, AtsScoringService $scorer)
    {
        $generatedCv = $this->createGeneratedCv($this->validatedPayload($request), $openAi, $scorer, $request->user()?->id, false, $request);

        return redirect()->to(SignedRecordAccess::temporaryUrl('generated-cvs.show', [
            'generatedCv' => $generatedCv,
        ]));
    }

    public function storeApi(Request $request, CvAiProvider $openAi, AtsScoringService $scorer)
    {
        $existing = Idempotency::find($request, GeneratedCv::query());
        if ($existing instanceof GeneratedCv) {
            return new GeneratedCvResource($existing);
        }

        $queueAi = $request->header('X-Sirati-Async') === '1';
        $generatedCv = $this->createGeneratedCv(
            $this->validatedPayload($request),
            $openAi,
            $scorer,
            $request->user()->id,
            $queueAi,
            $request,
        );

        if ($generatedCv->wasRecentlyCreated && $generatedCv->ai_status === AiStatus::Queued) {
            GenerateCvContentJob::dispatch($generatedCv->id);
        }

        // TODO: Remove the synchronous compatibility path only after old app
        // installs that omit X-Sirati-Async have drained from production.

        return (new GeneratedCvResource($generatedCv))
            ->response()
            ->setStatusCode(201);
    }

    public function updateApi(GeneratedCv $generatedCv, Request $request, CvAiProvider $openAi, AtsScoringService $scorer)
    {
        $this->authorizeApiAccess($request, $generatedCv);

        $queueAi = $request->header('X-Sirati-Async') === '1';
        $this->updateGeneratedCv($generatedCv, $this->validatedPayload($request), $openAi, $scorer, $queueAi);

        if ($queueAi && $generatedCv->ai_status === AiStatus::Queued) {
            GenerateCvContentJob::dispatch($generatedCv->id);
        }

        return new GeneratedCvResource($generatedCv->refresh());
    }

    public function destroyApi(GeneratedCv $generatedCv)
    {
        $this->authorizeApiAccess(request(), $generatedCv);

        $generatedCv->delete();

        return response()->json(['message' => 'Deleted']);
    }

    public function duplicateApi(GeneratedCv $generatedCv, Request $request)
    {
        $this->authorizeApiAccess($request, $generatedCv);

        $copy = $generatedCv->duplicateForUser($request->user()->id);

        return (new GeneratedCvResource($copy))
            ->response()
            ->setStatusCode(201);
    }

    public function previewTemplateSwitch(
        GeneratedCv $generatedCv,
        Request $request,
        CvTemplateRenderer $renderer,
    ) {
        $this->authorizeApiAccess($request, $generatedCv);

        $validated = $request->validate([
            'template' => ['required', 'string', 'max:120'],
        ]);

        $language = $generatedCv->language === 'en' ? 'en' : 'ar';
        $template = $renderer->resolve($validated['template'], $language);
        $result = (new TemplateSwitch)->evaluate($generatedCv->cvDocument(), $template);

        return response()->json(['data' => $result->toArray($language)]);
    }

    public function storeFromAnalysisApi(CvAnalysis $analysis, Request $request, CvAiProvider $openAi, AtsScoringService $scorer)
    {
        abort_unless($analysis->user_id === $request->user()->id, 404);

        $overrides = $request->validate([
            'full_name' => ['nullable', 'string', 'max:160'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'linkedin' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:160'],
            'language' => ['nullable', 'in:ar,en'],
        ]);

        $existing = Idempotency::find($request, GeneratedCv::query());
        if ($existing instanceof GeneratedCv) {
            return new GeneratedCvResource($existing);
        }

        $queueAi = $request->header('X-Sirati-Async') === '1';
        $generatedCv = $this->createGeneratedCv(
            $this->payloadFromAnalysis($analysis, $overrides),
            $openAi,
            $scorer,
            $request->user()->id,
            $queueAi,
            $request,
        );

        if ($generatedCv->wasRecentlyCreated && $generatedCv->ai_status === AiStatus::Queued) {
            GenerateCvContentJob::dispatch($generatedCv->id);
        }

        return (new GeneratedCvResource($generatedCv))
            ->response()
            ->setStatusCode(201);
    }

    public function enhanceJobDescription(Request $request, CvAiProvider $openAi)
    {
        $validated = $request->validate([
            'target_job_title' => ['required', 'string', 'max:160'],
            'job_description' => ['nullable', 'string', 'max:4000'],
            'language' => ['required', 'in:ar,en'],
        ]);

        $aiStatus = AiStatus::NotConfigured;
        $aiError = null;
        $result = $this->localEnhancedJobDescription($validated);

        if ($openAi->isConfigured()) {
            $startedAt = hrtime(true);

            try {
                $aiResult = call_user_func(
                    [$openAi, 'enhanceJobDescription'],
                    $validated['target_job_title'],
                    $validated['job_description'] ?? null,
                    $validated['language'],
                );

                $result = [
                    'enhanced_description' => (string) ($aiResult['enhanced_description'] ?? $result['enhanced_description']),
                    'suggested_keywords' => array_values(array_filter(array_map('strval', $aiResult['suggested_keywords'] ?? $result['suggested_keywords']))),
                    'responsibilities' => array_values(array_filter(array_map('strval', $aiResult['responsibilities'] ?? []))),
                    'requirements' => array_values(array_filter(array_map('strval', $aiResult['requirements'] ?? []))),
                ];
                $aiStatus = AiStatus::Completed;
            } catch (Throwable $exception) {
                $aiStatus = AiStatus::Failed;
                $aiError = $exception->getMessage();
                $this->reportAiFailure($exception, 'enhance_job_description', $startedAt, $request->user()?->id);
            }
        }

        return response()->json([
            'data' => [
                ...$result,
                'ai_status' => $aiStatus->value,
                'ai_error' => $aiError,
            ],
        ]);
    }

    public function enhanceField(Request $request, CvAiProvider $openAi)
    {
        $validated = $request->validate([
            'field' => ['required', 'in:summary,skills,experience,education,certifications'],
            'draft' => ['required', 'string', 'min:10', 'max:12000'],
            'job_title' => ['required', 'string', 'max:160'],
            'language' => ['required', 'in:ar,en'],
        ]);

        if (! $openAi->isConfigured()) {
            return response()->json([
                'data' => [
                    'enhanced_text' => $validated['draft'],
                    'changes_made' => [],
                    'missing_facts' => [
                        $validated['language'] === 'en'
                            ? 'AI enhancement is temporarily unavailable.'
                            : 'تحسين الذكاء الاصطناعي غير متاح مؤقتاً.',
                    ],
                    'ats_keywords_added' => [],
                    'unverified_claims' => [],
                ],
            ]);
        }

        $startedAt = hrtime(true);

        try {
            $result = $openAi->enhanceCvField(
                $validated['field'],
                $validated['draft'],
                $validated['job_title'],
                $validated['language'],
            );

            return response()->json(['data' => $result]);
        } catch (Throwable $exception) {
            $this->reportAiFailure($exception, 'enhance_field', $startedAt, $request->user()?->id);

            return response()->json([
                'data' => [
                    'enhanced_text' => $validated['draft'],
                    'changes_made' => [],
                    'missing_facts' => [
                        $validated['language'] === 'en'
                            ? 'AI enhancement encountered an issue. Your draft was preserved.'
                            : 'حدث خطأ أثناء تحسين الحقل بالذكاء الاصطناعي. تم الاحتفاظ بمسودتك.',
                    ],
                    'ats_keywords_added' => [],
                    'unverified_claims' => [],
                ],
            ]);
        }
    }

    public function show(Request $request, GeneratedCv $generatedCv)
    {
        SignedRecordAccess::authorize($request, $generatedCv);

        $pdfUrl = SignedRecordAccess::temporaryUrl('generated-cvs.pdf', [
            'generatedCv' => $generatedCv,
        ]);

        return view('generated-cvs.show', compact('generatedCv', 'pdfUrl'));
    }

    public function showApi(Request $request, GeneratedCv $generatedCv)
    {
        $this->authorizeApiAccess($request, $generatedCv);

        return new GeneratedCvResource($generatedCv);
    }

    public function downloadPdf(Request $request, GeneratedCv $generatedCv, CvTemplateRenderer $renderer)
    {
        return $renderer->downloadResponse($generatedCv, $request->query('template'));
    }

    public function downloadPdfApi(Request $request, GeneratedCv $generatedCv, CvTemplateRenderer $renderer)
    {
        $this->authorizeApiAccess($request, $generatedCv);

        return $renderer->downloadResponse($generatedCv, $request->query('template'));
    }

    private function validatedPayload(Request $request): array
    {
        return array_merge([
            'email' => null,
            'phone' => null,
            'linkedin' => null,
            'location' => null,
            'job_description_input' => null,
            'summary_input' => null,
            'certifications_input' => null,
        ], $request->validate([
            'full_name' => ['required', 'string', 'max:160'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'linkedin' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:160'],
            'target_job_title' => ['required', 'string', 'max:160'],
            'job_description_input' => ['nullable', 'string', 'max:4000'],
            'language' => ['required', 'in:ar,en'],
            'summary_input' => ['nullable', 'string', 'max:2000'],
            'skills_input' => ['required', 'string', 'max:3000'],
            'experience_input' => ['required', 'string', 'min:80', 'max:12000'],
            'education_input' => ['required', 'string', 'max:3000'],
            'certifications_input' => ['nullable', 'string', 'max:3000'],
            'document' => ['nullable', 'array'],
        ]));
    }

    private function createGeneratedCv(
        array $validated,
        CvAiProvider $openAi,
        AtsScoringService $scorer,
        ?int $userId = null,
        bool $queueAi = false,
        ?Request $request = null,
    ): GeneratedCv {
        $create = function () use ($validated, $openAi, $scorer, $queueAi, $userId, $request) {
            return GeneratedCv::create([
                ...$this->generatedCvAttributes($validated, $openAi, $scorer, $queueAi),
                'user_id' => $userId,
                'idempotency_key' => $request ? Idempotency::key($request) : null,
            ]);
        };

        if ($request === null) {
            return $create();
        }

        $generatedCv = Idempotency::firstOrCreate($request, GeneratedCv::query(), $create);
        assert($generatedCv instanceof GeneratedCv);

        return $generatedCv;
    }

    private function updateGeneratedCv(
        GeneratedCv $generatedCv,
        array $validated,
        CvAiProvider $openAi,
        AtsScoringService $scorer,
        bool $queueAi = false,
    ): void {
        $generatedCv->update($this->generatedCvAttributes($validated, $openAi, $scorer, $queueAi));
    }

    private function authorizeApiAccess(Request $request, GeneratedCv $generatedCv): void
    {
        abort_unless($generatedCv->user_id === $request->user()->id, 404);
    }

    private function generatedCvAttributes(
        array $validated,
        CvAiProvider $openAi,
        AtsScoringService $scorer,
        bool $queueAi = false,
    ): array {
        $documentInput = $validated['document'] ?? null;
        unset($validated['document']);

        $aiStatus = AiStatus::NotConfigured;
        $aiOutput = null;
        $aiError = null;
        $markdown = $this->localTemplate($validated);

        if ($openAi->isConfigured()) {
            if ($queueAi) {
                $aiStatus = AiStatus::Queued;
            } else {
                $startedAt = hrtime(true);

                try {
                    $aiOutput = $openAi->generateCv($validated);
                    $markdown = (string) ($aiOutput['cv_markdown'] ?? $markdown);
                    $aiStatus = AiStatus::Completed;
                } catch (Throwable $exception) {
                    $aiStatus = AiStatus::Failed;
                    $aiError = $exception->getMessage();
                    $this->reportAiFailure($exception, 'generate_cv', $startedAt);
                }
            }
        }

        $markdown = CvMarkdownIdentityBlock::strip(
            $markdown,
            (string) $validated['full_name'],
            (string) $validated['target_job_title'],
            [
                (string) ($validated['email'] ?? ''),
                (string) ($validated['phone'] ?? ''),
                (string) ($validated['linkedin'] ?? ''),
            ],
        );

        $score = $scorer->score($markdown, $validated['target_job_title']);

        $document = is_array($documentInput)
            ? CvDocument::fromArray($documentInput)->withExportLanguage($validated['language'])
            : CvDocument::fromLegacy($validated);

        $snapshot = array_filter(
            $document->legacySnapshot(),
            fn (mixed $value): bool => $value !== null && $value !== '',
        );

        return [
            ...$validated,
            ...$snapshot,
            'generated_markdown' => $markdown,
            'form_payload' => $validated,
            'document' => $document->toArray(),
            'ai_status' => $aiStatus,
            'ai_output' => $aiOutput,
            'ai_error' => $aiError,
            'score_total' => $score['total'],
            'grade' => $score['grade'],
            'criteria' => $score['criteria'],
        ];
    }

    private function payloadFromAnalysis(CvAnalysis $analysis, array $overrides): array
    {
        $resumeText = $analysis->resume_text;
        $skills = array_values(array_unique(array_filter([
            ...($analysis->keywords_found ?? []),
            ...array_slice($analysis->keywords_missing ?? [], 0, 5),
        ])));

        return [
            'full_name' => $this->filledValue($overrides['full_name'] ?? null)
                ?? $this->guessFullName($resumeText),
            'email' => $this->filledValue($overrides['email'] ?? null)
                ?? $this->firstMatch('/[\w.+-]+@[\w-]+\.\w+/u', $resumeText),
            'phone' => $this->filledValue($overrides['phone'] ?? null)
                ?? $this->firstMatch('/\+?\d[\d\s\-().]{7,}\d/u', $resumeText),
            'linkedin' => $this->filledValue($overrides['linkedin'] ?? null)
                ?? $this->firstMatch('/linkedin\.com\/in\/[^\s|]+/iu', $resumeText),
            'location' => $this->filledValue($overrides['location'] ?? null),
            'target_job_title' => $analysis->target_job_title,
            'job_description_input' => null,
            'language' => $this->filledValue($overrides['language'] ?? null)
                ?? $this->detectLanguage($resumeText),
            'summary_input' => data_get($analysis->ai_feedback, 'rewritten_summary'),
            'skills_input' => $skills ? implode(', ', $skills) : $analysis->target_job_title,
            'experience_input' => $this->analysisExperienceInput($analysis),
            'education_input' => $this->sectionSnippet($resumeText, '/\b(education|university|college|degree|bachelor|master|phd|التعليم|جامعة|بكالوريوس|ماجستير|دكتوراه)\b/iu')
                ?? 'غير محدد في النص الأصلي',
            'certifications_input' => $this->sectionSnippet($resumeText, '/\b(certif|license|credential|award|الشهادات|شهادات|اعتماد|جوائز)\b/iu'),
        ];
    }

    private function analysisExperienceInput(CvAnalysis $analysis): string
    {
        $quickWins = $analysis->quick_wins ?? [];

        if ($quickWins === []) {
            return $analysis->resume_text;
        }

        return $analysis->resume_text."\n\nتحسينات مطلوبة:\n- ".implode("\n- ", $quickWins);
    }

    private function guessFullName(string $resumeText): string
    {
        $firstLine = collect(preg_split('/\R/u', $resumeText) ?: [])
            ->map(fn (string $line) => trim($line))
            ->first(fn (string $line) => $line !== '' && mb_strlen($line) <= 160);

        return $firstLine ?: 'صاحب السيرة';
    }

    private function firstMatch(string $pattern, string $text): ?string
    {
        preg_match($pattern, $text, $matches);

        return $matches[0] ?? null;
    }

    private function detectLanguage(string $text): string
    {
        return preg_match('/\p{Arabic}/u', $text) ? 'ar' : 'en';
    }

    private function sectionSnippet(string $text, string $headingPattern): ?string
    {
        $lines = preg_split('/\R/u', $text) ?: [];

        foreach ($lines as $index => $line) {
            if (! preg_match($headingPattern, $line)) {
                continue;
            }

            $snippet = array_slice($lines, $index, 7);

            return trim(implode("\n", $snippet)) ?: null;
        }

        return null;
    }

    private function filledValue(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function reportAiFailure(
        Throwable $exception,
        string $operation,
        int $startedAt,
        ?int $userId = null,
    ): void {
        $provider = CachedCvAiProvider::activeProvider();

        app(ErrorReporter::class)->captureAiFailure(
            exception: $exception,
            operation: $operation,
            model: CachedCvAiProvider::modelForProvider($provider),
            durationMs: (int) max(0, round((hrtime(true) - $startedAt) / 1_000_000)),
            userId: $userId ?? request()->user()?->id,
        );
    }

    private function localEnhancedJobDescription(array $data): array
    {
        $title = $data['target_job_title'];
        $description = $this->filledValue($data['job_description'] ?? null);

        if ($data['language'] === 'en') {
            $base = $description ?: "We are looking for a {$title} who can deliver measurable outcomes, collaborate with stakeholders, and apply modern tools to solve business problems.";

            return [
                'enhanced_description' => trim($base)."\n\nKey focus areas:\n- Own core {$title} responsibilities with clear business impact.\n- Use relevant tools, data, and communication skills to improve outcomes.\n- Work with cross-functional teams and document measurable achievements.\n- Prioritize quality, reliability, and continuous improvement.",
                'suggested_keywords' => [$title, 'stakeholder communication', 'measurable impact', 'process improvement', 'reporting', 'quality'],
                'responsibilities' => [],
                'requirements' => [],
            ];
        }

        $base = $description ?: "نبحث عن {$title} قادر على تحقيق نتائج قابلة للقياس، والتعاون مع أصحاب المصلحة، واستخدام الأدوات الحديثة لحل مشكلات العمل.";

        return [
            'enhanced_description' => trim($base)."\n\nمحاور التركيز:\n- تنفيذ مسؤوليات {$title} مع أثر واضح على العمل.\n- استخدام الأدوات والبيانات ومهارات التواصل لتحسين النتائج.\n- التعاون مع الفرق المختلفة وتوثيق الإنجازات القابلة للقياس.\n- التركيز على الجودة والاعتمادية والتحسين المستمر.",
            'suggested_keywords' => [$title, 'التواصل مع أصحاب المصلحة', 'أثر قابل للقياس', 'تحسين العمليات', 'التقارير', 'الجودة'],
            'responsibilities' => [],
            'requirements' => [],
        ];
    }

    private function localTemplate(array $data): string
    {
        if ($data['language'] === 'ar') {
            $summary = $data['summary_input'] ?: "متخصص في {$data['target_job_title']} مع خبرة عملية في تنفيذ مشاريع تقنية ونتائج قابلة للقياس.";
            $certifications = $data['certifications_input'] ? "\n\n## الشهادات والدورات\n{$data['certifications_input']}" : '';

            return <<<MARKDOWN
## الملخص المهني
{$summary}

## المهارات الأساسية
{$data['skills_input']}

## الخبرات العملية
{$data['experience_input']}

## التعليم
{$data['education_input']}{$certifications}
MARKDOWN;
        }

        $summary = $data['summary_input'] ?: "Professional {$data['target_job_title']} with hands-on experience and measurable impact across relevant projects.";
        $certifications = $data['certifications_input'] ? "\n\n## Certifications\n{$data['certifications_input']}" : '';

        return <<<MARKDOWN
## Professional Summary
{$summary}

## Core Skills
{$data['skills_input']}

## Experience
{$data['experience_input']}

## Education
{$data['education_input']}{$certifications}
MARKDOWN;
    }
}
