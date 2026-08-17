$utf8 = [System.Text.UTF8Encoding]::new($false)

function Read-RepoFile([string] $path) {
    return [System.IO.File]::ReadAllText((Resolve-Path $path))
}

function Write-RepoFile([string] $path, [string] $text) {
    [System.IO.File]::WriteAllText((Resolve-Path $path), $text, $utf8)
}

$path = 'app\Contracts\CvAiProvider.php'
$text = Read-RepoFile $path
$needle = "    public function enhanceJobDescription(string `$jobTitle, ?string `$jobDescription, string `$language): array;"
$addition = @'
    /**
     * @return array<string, mixed>
     */
    public function enhanceCvField(string $field, string $draft, string $jobTitle, string $language): array;

'@
$text = $text.Replace($needle, $addition + $needle)
Write-RepoFile $path $text

$path = 'app\Services\Ai\Schemas\OperationSchemas.php'
$text = Read-RepoFile $path
$text = $text.Replace(
    '            EnhanceJobDescriptionSchema::NAME => EnhanceJobDescriptionSchema::responseFormat(),',
    "            EnhanceJobDescriptionSchema::NAME => EnhanceJobDescriptionSchema::responseFormat(),`r`n            EnhanceCvFieldSchema::NAME => EnhanceCvFieldSchema::responseFormat(),"
)
$text = $text.Replace(
    '            EnhanceJobDescriptionSchema::NAME => EnhanceJobDescriptionSchema::MAX_TOKENS,',
    "            EnhanceJobDescriptionSchema::NAME => EnhanceJobDescriptionSchema::MAX_TOKENS,`r`n            EnhanceCvFieldSchema::NAME => EnhanceCvFieldSchema::MAX_TOKENS,"
)
$text = $text.Replace(
    '            EnhanceJobDescriptionSchema::NAME => EnhanceJobDescriptionSchema::schema(),',
    "            EnhanceJobDescriptionSchema::NAME => EnhanceJobDescriptionSchema::schema(),`r`n            EnhanceCvFieldSchema::NAME => EnhanceCvFieldSchema::schema(),"
)
$text = $text.Replace(
    '            EnhanceJobDescriptionSchema::class,',
    "            EnhanceJobDescriptionSchema::class,`r`n            EnhanceCvFieldSchema::class,"
)
Write-RepoFile $path $text

$path = 'app\Services\Ai\CachedCvAiProvider.php'
$text = Read-RepoFile $path
$text = $text.Replace("public const PROMPT_VERSION = '2';", "public const PROMPT_VERSION = '3';")
$needle = '    public function enhanceJobDescription(string $jobTitle, ?string $jobDescription, string $language): array'
$method = @'
    public function enhanceCvField(string $field, string $draft, string $jobTitle, string $language): array
    {
        return $this->remember(
            operation: 'enhance_cv_field',
            payload: [
                'field' => $field,
                'draft' => $draft,
                'job_title' => $jobTitle,
                'language' => $language,
            ],
            callback: fn (): array => $this->inner->enhanceCvField($field, $draft, $jobTitle, $language),
        );
    }

'@
$text = $text.Replace($needle, $method + $needle)
Write-RepoFile $path $text

foreach ($path in @('app\Services\OpenAiCvService.php', 'app\Services\ClaudeCvService.php')) {
    $text = Read-RepoFile $path
    $text = $text.Replace(
        'use App\Services\Ai\Prompts\AnalysisAdviceSystemPrompt;',
        "use App\Services\Ai\EnhanceCvFieldResultGuard;`r`nuse App\Services\Ai\Prompts\AnalysisAdviceSystemPrompt;`r`nuse App\Services\Ai\Prompts\EnhanceCvFieldSystemPrompt;"
    )
    $needle = '    public function enhanceJobDescription(string $jobTitle, ?string $jobDescription, string $language): array'
    $method = @'
    public function enhanceCvField(string $field, string $draft, string $jobTitle, string $language): array
    {
        $languageName = $language === 'en' ? 'English' : 'Arabic';
        $result = $this->requestJson(
            'enhance_cv_field',
            EnhanceCvFieldSystemPrompt::for($field),
            "Rewrite the {$field} CV field in {$languageName} for the target role. Keep every fact grounded in the draft.\n\nTarget job title: {$jobTitle}\n\nDraft:\n".mb_substr($draft, 0, 12000)."\n\nReturn enhanced_text, changes_made, missing_facts, and ats_keywords_added.",
        );

        return (new EnhanceCvFieldResultGuard)->enforce($result, $draft, $language);
    }

'@
    $text = $text.Replace($needle, $method + $needle)
    Write-RepoFile $path $text
}

$path = 'app\Http\Controllers\GeneratedCvController.php'
$text = Read-RepoFile $path
$needle = '    public function show(GeneratedCv $generatedCv)'
$method = @'
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
                ],
            ]);
        }

        $result = $openAi->enhanceCvField(
            $validated['field'],
            $validated['draft'],
            $validated['job_title'],
            $validated['language'],
        );

        return response()->json(['data' => $result]);
    }

'@
$text = $text.Replace($needle, $method + $needle)
Write-RepoFile $path $text

$path = 'routes\api.php'
$text = Read-RepoFile $path
$needle = "		Route::post('/generated-cvs/enhance-job-description', [GeneratedCvController::class, 'enhanceJobDescription']);"
$replacement = $needle + "`r`n`t`tRoute::post('/generated-cvs/enhance-field', [GeneratedCvController::class, 'enhanceField'])`r`n`t`t`t->middleware('throttle:20,1');"
$text = $text.Replace($needle, $replacement)
Write-RepoFile $path $text

$path = 'config\services.php'
$text = Read-RepoFile $path
$needle = "            'enhance_job_description' => (int) env('CV_AI_CACHE_TTL_ENHANCE', 60 * 60 * 24),"
$text = $text.Replace($needle, $needle + "`r`n            'enhance_cv_field' => (int) env('CV_AI_CACHE_TTL_ENHANCE_FIELD', 60 * 60 * 24),")
Write-RepoFile $path $text

$path = 'app\Services\Ai\Prompts\AnalysisAdviceSystemPrompt.php'
$text = Read-RepoFile $path
$pattern = '(?s)    private function registerSection\(\): string\s*\{.*?\n    \}\s*\n\}'
$replacement = @'
    private function registerSection(): string
    {
        return ArabicRegisterRules::section();
    }
}
'@
$text = [regex]::Replace($text, $pattern, $replacement)
Write-RepoFile $path $text
