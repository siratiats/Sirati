<?php

namespace App\Services\Ai;

use App\Exceptions\AiRefusalException;
use App\Services\Ai\Prompts\JobMatchSystemPrompt;
use App\Services\AtsScoringService;
use App\Support\ArabicIndicDigits;
use UnexpectedValueException;

final class JobMatchResultGuard
{
    private const ALLOWED_SECTIONS = [
        'summary',
        'skills',
        'experience',
        'education',
        'certifications',
        'projects',
        'languages',
        'personal',
        'custom_sections',
    ];

    /**
     * Common English sentence-starter / CV prose words that may appear capitalized
     * at the beginning of a sentence or clause without being proper nouns or skills.
     *
     * @var list<string>
     */
    private const COMMON_ENGLISH_PROSE_WORDS = [
        'a', 'an', 'the', 'in', 'on', 'at', 'for', 'with', 'by', 'to', 'from', 'of', 'and', 'or',
        'as', 'into', 'over', 'across', 'through', 'during', 'while', 'within', 'without', 'under',
        'using', 'leveraging', 'utilizing', 'including', 'via', 'per',
        'led', 'managed', 'developed', 'built', 'created', 'increased', 'reduced', 'improved',
        'launched', 'optimized', 'implemented', 'analyzed', 'trained', 'supervised', 'directed',
        'designed', 'automated', 'saved', 'negotiated', 'delivered', 'administered', 'resolved',
        'instructed', 'coordinated', 'monitored', 'maintained', 'configured', 'integrated',
        'prepared', 'supported', 'streamlined', 'architected', 'engineered', 'refactored',
        'spearheaded', 'established', 'executed', 'facilitated', 'conducted', 'performed',
        'assisted', 'worked', 'collaborated', 'contributed', 'authored', 'deployed', 'migrated',
        'owned', 'drove', 'achieved', 'accelerated', 'expanded', 'enhanced', 'modernized',
        'standardized', 'consolidated', 'orchestrated', 'Reviewed', 'reviewed', 'tested',
        'handled', 'oversaw', 'guided', 'mentored', 'partnered', 'aligned', 'defined',
        'strong', 'solid', 'proven', 'experienced', 'skilled', 'proficient', 'dedicated',
        'senior', 'junior', 'lead', 'principal', 'staff', 'associate', 'professional',
        'comprehensive', 'extensive', 'demonstrated', 'effective', 'clear', 'concise',
        'missing', 'lacks', 'lack', 'no', 'limited', 'weak', 'absent', 'insufficient',
        'candidate', 'applicant', 'resume', 'cv', 'profile', 'summary', 'experience',
        'education', 'skills', 'certifications', 'projects', 'languages', 'section',
        'bullet', 'role', 'position', 'job', 'description', 'requirement', 'requirements',
        'evidence', 'background', 'history', 'track', 'record', 'ability', 'capabilities',
        'add', 'adding', 'highlight', 'highlighting', 'emphasize', 'emphasizing', 'clarify',
        'rewrite', 'rewriting', 'align', 'aligning', 'tailor', 'tailoring', 'update', 'include',
        'quantify', 'quantifying', 'specify', 'specifying', 'confirm', 'supply', 'provide',
    ];

    /**
     * Known lowercase technical and domain skills (supplements AtsScoringService::jobKeywords()).
     *
     * @var list<string>
     */
    private const KNOWN_SKILLS = [
        'docker', 'kubernetes', 'k8s', 'terraform', 'ansible', 'jenkins', 'gitlab', 'github',
        'circleci', 'travis', 'helm', 'prometheus', 'grafana', 'datadog', 'splunk', 'newrelic',
        'aws', 'azure', 'gcp', 'cloudflare', 'heroku', 'digitalocean', 'vercel', 'netlify',
        'linux', 'unix', 'bash', 'powershell', 'nginx', 'apache', 'caddy',
        'redis', 'kafka', 'rabbitmq', 'activemq', 'celery', 'sidekiq',
        'postgresql', 'postgres', 'mysql', 'mariadb', 'sqlite', 'oracle', 'mssql', 'mongodb',
        'dynamodb', 'cassandra', 'couchdb', 'neo4j', 'elasticsearch', 'opensearch', 'snowflake',
        'bigquery', 'redshift', 'clickhouse', 'supabase', 'firebase', 'firestore',
        'graphql', 'grpc', 'websocket', 'websockets', 'microservices', 'serverless', 'oauth',
        'jwt', 'openid', 'saml', 'ldap', 'rest', 'soap', 'openapi', 'swagger',
        'php', 'laravel', 'symfony', 'wordpress', 'drupal', 'magento', 'livewire', 'filament',
        'javascript', 'typescript', 'node', 'nodejs', 'express', 'nestjs', 'nextjs', 'nuxt',
        'react', 'vue', 'vuejs', 'angular', 'svelte', 'jquery', 'redux', 'tailwind', 'bootstrap',
        'sass', 'less', 'webpack', 'vite', 'babel', 'eslint', 'jest', 'cypress', 'playwright',
        'selenium', 'vitest', 'storybook',
        'python', 'django', 'flask', 'fastapi', 'pandas', 'numpy', 'scipy', 'scikit',
        'pytorch', 'tensorflow', 'keras', 'huggingface', 'langchain', 'llamaindex', 'openai',
        'spark', 'pyspark', 'hadoop', 'airflow', 'dbt', 'mlflow', 'kubeflow',
        'java', 'spring', 'springboot', 'hibernate', 'maven', 'gradle', 'kotlin', 'scala',
        'golang', 'rust', 'ruby', 'rails', 'elixir', 'phoenix', 'erlang', 'haskell', 'clojure',
        'swift', 'swiftui', 'objective-c', 'flutter', 'dart', 'react native', 'ionic', 'xamarin',
        'c++', 'c#', 'dotnet', '.net', 'blazor', 'unity', 'unreal', 'matlab', 'r-lang',
        'tableau', 'powerbi', 'power bi', 'looker', 'metabase', 'superset', 'qlik', 'alteryx',
        'excel', 'vba', 'spss', 'sas', 'stata',
        'salesforce', 'hubspot', 'zoho', 'pipedrive', 'zendesk', 'freshdesk', 'intercom',
        'sap', 'netsuite', 'workday', 'peoplesoft', 'dynamics', 'quickbooks', 'xero',
        'jira', 'confluence', 'trello', 'asana', 'notion', 'linear', 'monday',
        'figma', 'sketch', 'invision', 'zeplin', 'photoshop', 'illustrator', 'indesign', 'canva',
        'shopify', 'woocommerce', 'bigcommerce', 'prestashop', 'stripe', 'paypal',
        'seo', 'sem', 'ppc', 'adsense', 'adwords', 'mailchimp', 'klaviyo', 'marketo',
        'pmp', 'prince2', 'scrum', 'agile', 'kanban', 'itil', 'cobit', 'togaf', 'six sigma',
        'cfa', 'cpa', 'cma', 'acca', 'cia', 'ifrs', 'gaap', 'sox', 'aml', 'kyc',
        'cissp', 'ceh', 'oscp', 'comptia', 'iso27001', 'soc2', 'gdpr', 'hipaa', 'pci-dss',
        'cybersecurity', 'penetration testing', 'devsecops', 'siem', 'zero trust',
        'blockchain', 'solidity', 'ethereum', 'web3', 'smart contracts',
        'كوبرنيتس', 'دوكر', 'بايثون', 'جافا', 'أوراكل', 'ساب', 'سيلزفورس', 'فلاتر', 'ريأكت',
        'لارافل', 'الذكاء الاصطناعي', 'تعلم الآلة', 'الأمن السيبراني', 'الحوسبة السحابية',
        'بلوكتشين', 'البيانات الضخمة', 'تحليل البيانات', 'إدارة المخاطر', 'المعايير الدولية',
        'جافاسكريبت', 'تايب سكريبت', 'إكسل', 'تابلوه', 'باور بي آي', 'أجايل', 'سكرم', 'تيرفورم',
        'لينكس', 'ووردبريس', 'شوبيفاي', 'ماجنتو', 'فيجما', 'فوتوشوب', 'بوستجري', 'ريديس', 'كافكا',
    ];

    /**
     * Validate raw provider output before sanitizing. Throws when the response
     * is a refusal or completely lacks the job_match_advice structure.
     *
     * @param  array<array-key, mixed>  $raw
     * @param  array<string, mixed>  $cvSnapshot
     * @return array{
     *     executive_summary: string,
     *     strengths: list<string>,
     *     gaps: list<string>,
     *     suggestions: list<array{
     *         key: string,
     *         target: array{section: string, entry_id: string|null, field: string|null, bullet_index: int|null},
     *         before: string|null,
     *         after: string,
     *         rationale: string,
     *         jd_terms: list<string>,
     *         needs_user_fact: bool
     *     }>
     * }
     *
     * @throws AiRefusalException
     * @throws UnexpectedValueException
     */
    public static function enforceProviderOutput(
        array $raw,
        array $cvSnapshot,
        string $jobDescription,
        string $provider = 'AI',
    ): array {
        if (is_string($raw['refusal'] ?? null) && trim($raw['refusal']) !== '' && ! is_string($raw['executive_summary'] ?? null)) {
            throw new AiRefusalException(trim($raw['refusal']));
        }

        if ($raw === [] || array_is_list($raw)) {
            throw new UnexpectedValueException("{$provider} job_match_advice reply must be a JSON object.");
        }

        $hasKnownKey = array_key_exists('executive_summary', $raw)
            || array_key_exists('suggestions', $raw)
            || array_key_exists('strengths', $raw)
            || array_key_exists('gaps', $raw);

        if (! $hasKnownKey) {
            throw new UnexpectedValueException(
                "{$provider} job_match_advice reply is missing required schema keys (got: ".implode(',', array_keys($raw)).').'
            );
        }

        if (
            (! array_key_exists('executive_summary', $raw) || ! is_string($raw['executive_summary']))
            && (! array_key_exists('suggestions', $raw) || ! is_array($raw['suggestions']))
        ) {
            throw new UnexpectedValueException("{$provider} job_match_advice reply has malformed top-level fields.");
        }

        $truncatedCv = JobMatchSystemPrompt::truncateCvSnapshot($cvSnapshot);
        $truncatedJd = JobMatchSystemPrompt::truncateJobDescription($jobDescription);

        return self::sanitize(
            $raw,
            $truncatedCv,
            self::extractJdTerms($truncatedJd),
        );
    }

    /**
     * Instance alias for {@see self::sanitize()}.
     *
     * @param  array<array-key, mixed>  $raw
     * @param  array<array-key, mixed>  $cvTerms
     * @param  array<array-key, mixed>  $jdTerms
     * @return array{
     *     executive_summary: string,
     *     strengths: list<string>,
     *     gaps: list<string>,
     *     suggestions: list<array{
     *         key: string,
     *         target: array{section: string, entry_id: string|null, field: string|null, bullet_index: int|null},
     *         before: string|null,
     *         after: string,
     *         rationale: string,
     *         jd_terms: list<string>,
     *         needs_user_fact: bool
     *     }>
     * }
     */
    public function enforce(array $raw, array $cvTerms = [], array $jdTerms = []): array
    {
        return self::sanitize($raw, $cvTerms, $jdTerms);
    }

    /**
     * Sanitize AI job match output:
     * - Drops malformed, refusal, prompt-injected, and ungrounded content.
     * - Demotes any suggestion adding a date, employer, number, or skill not grounded
     *   in the CV (or JD) to needs_user_fact = true (non-appliable).
     *
     * @param  array<array-key, mixed>  $raw
     * @param  array<array-key, mixed>  $cvTerms
     * @param  array<array-key, mixed>  $jdTerms
     * @return array{
     *     executive_summary: string,
     *     strengths: list<string>,
     *     gaps: list<string>,
     *     suggestions: list<array{
     *         key: string,
     *         target: array{section: string, entry_id: string|null, field: string|null, bullet_index: int|null},
     *         before: string|null,
     *         after: string,
     *         rationale: string,
     *         jd_terms: list<string>,
     *         needs_user_fact: bool
     *     }>
     * }
     */
    public static function sanitize(array $raw, array $cvTerms = [], array $jdTerms = []): array
    {
        if ($raw === [] || array_is_list($raw)) {
            return self::emptyResult();
        }

        if (is_string($raw['refusal'] ?? null) && trim($raw['refusal']) !== '' && ! is_string($raw['executive_summary'] ?? null)) {
            return self::emptyResult();
        }

        $cvSnapshot = null;
        if ($cvTerms !== [] && ! array_is_list($cvTerms)) {
            $cvSnapshot = JobMatchSystemPrompt::truncateCvSnapshot($cvTerms);
            $cvStrings = self::extractCvTerms($cvSnapshot);
        } else {
            $cvStrings = self::flattenTerms($cvTerms, filterInjections: false);
        }

        $jdStrings = self::flattenTerms($jdTerms, filterInjections: true);

        $cvEvidence = self::buildEvidence($cvStrings);
        $jdEvidence = self::buildEvidence($jdStrings);
        $combinedEvidence = self::buildEvidence([...$cvStrings, ...$jdStrings]);

        $executiveSummary = self::sanitizeExecutiveSummary(
            $raw['executive_summary'] ?? null,
            $combinedEvidence,
        );

        $strengths = self::sanitizeStrengths($raw['strengths'] ?? null, $cvEvidence);
        $gaps = self::sanitizeGaps($raw['gaps'] ?? null, $combinedEvidence);
        $suggestions = self::sanitizeSuggestions(
            $raw['suggestions'] ?? null,
            $cvEvidence,
            $jdEvidence,
            $combinedEvidence,
            $cvSnapshot,
        );

        return [
            'executive_summary' => $executiveSummary,
            'strengths' => $strengths,
            'gaps' => $gaps,
            'suggestions' => $suggestions,
        ];
    }

    /**
     * Extract grounding terms and phrases from a CV snapshot array.
     *
     * @param  array<string, mixed>  $cvSnapshot
     * @return list<string>
     */
    public static function extractCvTerms(array $cvSnapshot): array
    {
        $truncated = $cvSnapshot !== [] && ! array_is_list($cvSnapshot)
            ? JobMatchSystemPrompt::truncateCvSnapshot($cvSnapshot)
            : $cvSnapshot;
        $strings = self::flattenTerms($truncated, filterInjections: false);
        $terms = [];

        foreach ($strings as $str) {
            $terms[] = $str;
            foreach (preg_split('/[,،;؛|\n•·]+/u', $str) ?: [] as $chunk) {
                $trimmed = trim($chunk);
                if ($trimmed !== '' && mb_strlen($trimmed) <= 80) {
                    $terms[] = $trimmed;
                }
            }
        }

        return array_values(array_unique(array_filter($terms, static fn (string $t): bool => $t !== '')));
    }

    /**
     * Extract grounding terms from a raw job description string (excluding injected directives).
     *
     * @return list<string>
     */
    public static function extractJdTerms(string $jobDescription): array
    {
        $truncatedJd = JobMatchSystemPrompt::truncateJobDescription($jobDescription);
        $cleanJd = self::stripInjectedSegments($truncatedJd);
        if (trim($cleanJd) === '') {
            return [];
        }

        $extracted = (new AtsScoringService)->extractJobTerms($cleanJd);
        $tokens = preg_split('/[^\p{L}\p{N}+#.\-]+/u', $cleanJd) ?: [];
        foreach ($tokens as $token) {
            $trimmed = trim($token, '.-_');
            if (mb_strlen($trimmed) >= 2 && ! self::isInjectionOrRefusal($trimmed)) {
                $extracted[] = $trimmed;
            }
        }

        return array_values(array_unique(array_filter($extracted, static fn (string $t): bool => trim($t) !== '')));
    }

    /**
     * @return array{
     *     executive_summary: string,
     *     strengths: list<string>,
     *     gaps: list<string>,
     *     suggestions: list<never>
     * }
     */
    private static function emptyResult(): array
    {
        return [
            'executive_summary' => '',
            'strengths' => [],
            'gaps' => [],
            'suggestions' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $combinedEvidence
     */
    private static function sanitizeExecutiveSummary(mixed $value, array $combinedEvidence): string
    {
        if (! is_string($value)) {
            return '';
        }

        $cleaned = self::stripTagsAndControlChars($value);
        if ($cleaned === '') {
            return '';
        }

        $sentences = preg_split('/(?<=[.!?۔؟])\s+|\R+/u', $cleaned) ?: [$cleaned];
        $kept = [];

        foreach ($sentences as $sentence) {
            $sentence = trim($sentence);
            if ($sentence === '') {
                continue;
            }
            if (self::isInjectionOrRefusal($sentence)) {
                continue;
            }
            if (
                self::hasUngroundedDate($sentence, $combinedEvidence)
                || self::hasUngroundedNumber($sentence, $combinedEvidence)
                || self::hasUngroundedEmployer($sentence, $combinedEvidence)
                || self::hasUngroundedSkill($sentence, $combinedEvidence)
            ) {
                continue;
            }
            $kept[] = $sentence;
        }

        return implode(' ', $kept);
    }

    /**
     * @param  array<string, mixed>  $cvEvidence
     * @return list<string>
     */
    private static function sanitizeStrengths(mixed $values, array $cvEvidence): array
    {
        if (! is_array($values)) {
            return [];
        }

        $out = [];
        foreach ($values as $item) {
            if (! is_string($item)) {
                continue;
            }

            $text = self::stripTagsAndControlChars($item);
            if ($text === '' || self::isInjectionOrRefusal($text)) {
                continue;
            }

            if (
                self::hasUngroundedDate($text, $cvEvidence)
                || self::hasUngroundedNumber($text, $cvEvidence)
                || self::hasUngroundedEmployer($text, $cvEvidence)
                || self::hasUngroundedSkill($text, $cvEvidence)
            ) {
                continue;
            }

            if (! $cvEvidence['isEmpty'] && ! self::hasEvidenceOverlap($text, $cvEvidence)) {
                continue;
            }

            $out[] = $text;
        }

        return array_values(array_unique($out));
    }

    /**
     * @param  array<string, mixed>  $combinedEvidence
     * @return list<string>
     */
    private static function sanitizeGaps(mixed $values, array $combinedEvidence): array
    {
        if (! is_array($values)) {
            return [];
        }

        $out = [];
        foreach ($values as $item) {
            if (! is_string($item)) {
                continue;
            }

            $text = self::stripTagsAndControlChars($item);
            if ($text === '' || self::isInjectionOrRefusal($text)) {
                continue;
            }

            if (
                self::hasUngroundedDate($text, $combinedEvidence)
                || self::hasUngroundedNumber($text, $combinedEvidence)
                || self::hasUngroundedEmployer($text, $combinedEvidence)
                || self::hasUngroundedSkill($text, $combinedEvidence)
            ) {
                continue;
            }

            if (! $combinedEvidence['isEmpty'] && ! self::hasEvidenceOverlap($text, $combinedEvidence)) {
                continue;
            }

            $out[] = $text;
        }

        return array_values(array_unique($out));
    }

    /**
     * @param  array<string, mixed>  $cvEvidence
     * @param  array<string, mixed>  $jdEvidence
     * @param  array<string, mixed>  $combinedEvidence
     * @param  array<string, mixed>|null  $cvSnapshot
     * @return list<array{
     *     key: string,
     *     target: array{section: string, entry_id: string|null, field: string|null, bullet_index: int|null},
     *     before: string|null,
     *     after: string,
     *     rationale: string,
     *     jd_terms: list<string>,
     *     needs_user_fact: bool
     * }>
     */
    private static function sanitizeSuggestions(
        mixed $values,
        array $cvEvidence,
        array $jdEvidence,
        array $combinedEvidence,
        ?array $cvSnapshot = null,
    ): array {
        if (! is_array($values)) {
            return [];
        }

        $out = [];
        $usedKeys = [];
        $usedTargets = [];

        foreach ($values as $index => $item) {
            if (! is_array($item)) {
                continue;
            }

            if (! is_string($item['after'] ?? null)) {
                continue;
            }

            $after = self::stripTagsAndControlChars($item['after']);
            if ($after === '' || preg_match('/\R/u', $item['after']) || self::isInjectionOrRefusal($after)) {
                continue;
            }

            $before = null;
            if (is_string($item['before'] ?? null)) {
                $cleanedBefore = self::stripTagsAndControlChars($item['before']);
                if (self::isInjectionOrRefusal($cleanedBefore)) {
                    continue;
                }
                $before = $cleanedBefore;
            }

            $rationale = is_string($item['rationale'] ?? null)
                ? self::stripTagsAndControlChars($item['rationale'])
                : '';

            if (self::isInjectionOrRefusal($rationale)) {
                continue;
            }

            $target = self::sanitizeTarget($item['target'] ?? null);
            if ($target === null) {
                continue;
            }

            $kind = $item['kind'] ?? null;
            if (! in_array($kind, ['replace', 'append_skill', 'append_bullet'], true)
                || ! self::hasValidAfterShape($kind, $target, $after, $combinedEvidence)
                || self::normalizeComparableText($after) === self::normalizeComparableText($before ?? '')) {
                continue;
            }
            // Additions have no previous text. Keep the apply endpoint's empty-string contract.
            if ($kind !== 'replace') {
                if ($before !== null && $before !== '') {
                    continue;
                }
                $before = '';
            }
            $targetKey = self::suggestionTargetKey($kind, $target, $after);
            if (isset($usedTargets[$targetKey])) {
                continue;
            }
            $usedTargets[$targetKey] = true;

            $rawKey = is_string($item['key'] ?? null) ? self::stripTagsAndControlChars($item['key']) : '';
            $baseKey = $rawKey !== '' ? $rawKey : 'sug_'.((int) $index + 1);
            $key = $baseKey;
            $suffix = (int) $index + 1;
            while (isset($usedKeys[$key])) {
                $key = $baseKey.'_'.$suffix;
                $suffix++;
            }
            $usedKeys[$key] = true;

            $rawJdTerms = [];
            if (is_array($item['jd_terms'] ?? null)) {
                foreach ($item['jd_terms'] as $term) {
                    if (is_string($term)) {
                        $cleanedTerm = self::stripTagsAndControlChars($term);
                        if ($cleanedTerm !== '' && ! self::isInjectionOrRefusal($cleanedTerm)) {
                            $rawJdTerms[] = $cleanedTerm;
                        }
                    }
                }
            }

            $groundedJdTerms = [];
            foreach ($rawJdTerms as $term) {
                $termAllowed = $jdEvidence['isEmpty']
                    ? self::isTermInEvidence($term, $combinedEvidence)
                    : self::isTermInEvidence($term, $jdEvidence);

                if ($termAllowed) {
                    $groundedJdTerms[] = $term;
                }
            }
            $groundedJdTerms = array_values(array_unique($groundedJdTerms));

            $needsUserFact = (bool) ($item['needs_user_fact'] ?? false) || $kind === 'append_bullet';

            if (
                $cvEvidence['isEmpty']
                || self::hasUngroundedDate($after, $cvEvidence)
                || self::hasUngroundedNumber($after, $cvEvidence)
                || self::hasUngroundedEmployer($after, $cvEvidence)
                || self::hasUngroundedSkill(
                    $after,
                    $cvEvidence,
                    $jdEvidence,
                    $groundedJdTerms,
                    $target['section'],
                )
                || ! self::hasEvidenceOverlap($after, $cvEvidence)
            ) {
                $needsUserFact = true;
            }

            if ($cvSnapshot !== null && ! self::canApplyToSnapshot($kind, $target, $before, $after, $cvSnapshot)) {
                $needsUserFact = true;
            }

            if ($kind === 'replace' && (
                $before === null
                || self::hasUngroundedDate($before, $cvEvidence)
                || self::hasUngroundedNumber($before, $cvEvidence)
                || self::hasUngroundedEmployer($before, $cvEvidence)
                || self::hasUngroundedSkill($before, $cvEvidence)
                || ! self::isBeforeGroundedInEvidence($before, $cvEvidence)
            )) {
                $needsUserFact = true;
            }

            $out[] = [
                'key' => $key,
                'kind' => $kind,
                'target' => $target,
                'before' => $before,
                'after' => $after,
                'rationale' => $rationale,
                'jd_terms' => $groundedJdTerms,
                'needs_user_fact' => $needsUserFact,
            ];
        }

        return $out;
    }

    /** Validate data shapes, without guessing the language or direction of prose. */
    private static function hasValidAfterShape(string $kind, array $target, string $after, array $evidence): bool
    {
        $limit = $kind === 'append_skill' || $target['section'] === 'skills'
            ? 60 : ($target['section'] === 'summary' ? 2000 : 500);
        if (mb_strlen($after) > $limit) {
            return false;
        }
        if ($kind === 'append_skill' || $target['section'] === 'skills') {
            // A literal name must match an evidence term or a catalogued skill,
            // rather than arbitrary prose containing that skill somewhere within it.
            if (preg_match('/[\r\n,;:!?????]|[.]$/u', $after)) {
                return false;
            }
            $name = self::normalizeComparableText($after);
            $names = [...$evidence['phrases'], ...self::allKnownSkills()];
            if (! in_array($name, $names, true)) {
                return false;
            }
        }
        if ($kind === 'append_skill') {
            return $target['section'] === 'skills' && $target['entry_id'] === null
                && in_array($target['field'], ['name', 'append'], true) && $target['bullet_index'] === null;
        }
        if ($kind === 'append_bullet') {
            return $target['section'] === 'experience' && $target['entry_id'] !== null
                && in_array($target['field'], ['bullet', 'bullets'], true) && $target['bullet_index'] === null;
        }

        return true;
    }

    private static function suggestionTargetKey(string $kind, array $target, string $after): string
    {
        if ($kind === 'append_skill') {
            return 'skill:'.self::normalizeComparableText($after);
        }
        if ($target['section'] === 'summary') {
            return 'summary';
        }
        $field = $target['field'] === 'bullet' ? 'bullets' : $target['field'];

        return json_encode([$target['section'], $target['entry_id'], $field, $target['bullet_index']], JSON_THROW_ON_ERROR);
    }

    /** Mirror the supported apply targets against the frozen, requested-language text. */
    private static function canApplyToSnapshot(string $kind, array $target, ?string $before, string $after, array $snapshot): bool
    {
        $language = $snapshot['export_language'] ?? 'ar';
        if ($kind === 'append_skill') {
            foreach ($snapshot['skills'] ?? [] as $skill) {
                $name = \App\Cv\LocalizedText::fromArray($skill['name'] ?? [])->resolve($language);
                if (self::normalizeComparableText($name) === self::normalizeComparableText($after)) {
                    return false;
                }
            }

            return true;
        }
        if ($kind !== 'replace' || $before === null) {
            return false;
        }
        if ($target['section'] === 'summary' && in_array($target['field'], ['summary', 'text'], true)
            && in_array($target['entry_id'], [null, 'summary'], true) && $target['bullet_index'] === null) {
            return \App\Cv\LocalizedText::fromArray($snapshot['summary'] ?? [])->resolve($language) === $before;
        }
        if ($target['section'] === 'experience' && in_array($target['field'], ['bullet', 'bullets'], true)
            && $target['entry_id'] !== null && $target['bullet_index'] !== null) {
            $entries = array_values(array_filter($snapshot['experience'] ?? [],
                static fn (array $entry): bool => ($entry['id'] ?? null) === $target['entry_id']));
            if (count($entries) === 1) {
                $bullet = $entries[0]['bullets'][$target['bullet_index']] ?? null;

                return is_array($bullet) && \App\Cv\LocalizedText::fromArray($bullet)->resolve($language) === $before;
            }
        }

        return false;
    }

    private static function normalizeComparableText(string $text): string
    {
        $normalized = AtsScoringService::normalizeArabic(mb_strtolower(self::normalizeDigits($text), 'UTF-8'));
        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;

        return self::trimPunctuation($normalized);
    }

    /**
     * @param  array<string, mixed>  $cvEvidence
     */
    private static function isBeforeGroundedInEvidence(string $before, array $cvEvidence): bool
    {
        if ($cvEvidence['isEmpty']) {
            return false;
        }

        $normBefore = self::normalizeComparableText($before);
        if ($normBefore === '') {
            return false;
        }

        if (in_array($normBefore, $cvEvidence['phrases'], true) || self::isTermInNormalizedString($normBefore, $cvEvidence['normalized'])) {
            return true;
        }

        $stopWords = [
            'و', 'في', 'من', 'على', 'الى', 'إلى', 'عن', 'مع', 'او', 'أو', 'ب', 'ل', 'ال',
            'in', 'on', 'at', 'to', 'of', 'for', 'with', 'and', 'or', 'the', 'a', 'an', 'as', 'by',
        ];
        $stopSet = array_flip($stopWords);

        $tokens = preg_split('/[^\p{L}\p{N}+#]+/u', $normBefore) ?: [];
        $checkedCount = 0;

        foreach ($tokens as $tok) {
            $tok = trim($tok, '.-_');
            if ($tok === '' || mb_strlen($tok) < 2 || isset($stopSet[$tok])) {
                continue;
            }

            $checkedCount++;
            $found = false;
            foreach (self::expandTokenVariants($tok) as $variant) {
                if (isset($cvEvidence['tokens'][$variant])) {
                    $found = true;
                    break;
                }
            }

            if (! $found) {
                return false;
            }
        }

        return $checkedCount > 0;
    }

    /**
     * @return array{section: string, entry_id: string|null, field: string|null, bullet_index: int|null}|null
     */
    private static function sanitizeTarget(mixed $target): ?array
    {
        if (! is_array($target)) {
            return null;
        }

        $section = is_string($target['section'] ?? null)
            ? strtolower(trim($target['section']))
            : '';

        if (! in_array($section, self::ALLOWED_SECTIONS, true)) {
            return null;
        }

        $entryId = isset($target['entry_id']) && is_scalar($target['entry_id']) && trim((string) $target['entry_id']) !== ''
            ? trim((string) $target['entry_id'])
            : null;

        $field = isset($target['field']) && is_scalar($target['field']) && trim((string) $target['field']) !== ''
            ? trim((string) $target['field'])
            : null;

        $bulletIndex = null;
        if (isset($target['bullet_index']) && (is_int($target['bullet_index']) || is_numeric($target['bullet_index']))) {
            $parsed = (int) $target['bullet_index'];
            if ($parsed >= 0) {
                $bulletIndex = $parsed;
            }
        }

        return [
            'section' => $section,
            'entry_id' => $entryId,
            'field' => $field,
            'bullet_index' => $bulletIndex,
        ];
    }

    /**
     * Flatten an arbitrary array of terms or nested CV snapshot into a list of clean strings.
     *
     * @param  array<array-key, mixed>  $input
     * @return list<string>
     */
    private static function flattenTerms(array $input, bool $filterInjections): array
    {
        $result = [];
        $ignoredKeys = ['schema_version', 'is_current', 'id', 'key', 'export_language'];

        $walk = static function (mixed $node, string|int|null $key = null) use (&$walk, &$result, $ignoredKeys, $filterInjections): void {
            if (is_string($key) && in_array($key, $ignoredKeys, true)) {
                return;
            }

            if (is_bool($node) || $node === null) {
                return;
            }

            if (is_scalar($node)) {
                $str = trim((string) $node);
                if ($str === '') {
                    return;
                }
                if ($filterInjections) {
                    $str = self::stripInjectedSegments($str);
                    if ($str === '' || self::isInjectionOrRefusal($str)) {
                        return;
                    }
                }
                $result[] = $str;

                return;
            }

            if (is_array($node)) {
                foreach ($node as $childKey => $childValue) {
                    $walk($childValue, $childKey);
                }
            }
        };

        $walk($input);

        return array_values(array_unique($result));
    }

    /**
     * @param  list<string>  $strings
     * @return array{
     *     isEmpty: bool,
     *     raw: string,
     *     lower: string,
     *     normalized: string,
     *     dates: array<string, true>,
     *     numbers: array<string, true>,
     *     tokens: array<string, true>,
     *     phrases: list<string>
     * }
     */
    private static function buildEvidence(array $strings): array
    {
        $cleanStrings = array_values(array_filter(array_map('trim', $strings), static fn (string $s): bool => $s !== ''));
        $raw = implode(" \n ", $cleanStrings);
        $lower = mb_strtolower(self::normalizeDigits($raw), 'UTF-8');
        $normalized = AtsScoringService::normalizeArabic($lower);

        $dates = [];
        foreach (self::extractDates($raw) as $d) {
            $dates[$d] = true;
        }

        $numbers = [];
        foreach (self::extractNumbers($raw) as $n) {
            $numbers[$n] = true;
        }

        $tokens = [];
        $rawTokens = array_merge(
            preg_split('/[^\p{L}\p{N}+#.\-_]+/u', $normalized) ?: [],
            preg_split('/[^\p{L}\p{N}+#]+/u', $normalized) ?: [],
        );

        foreach ($rawTokens as $tok) {
            $tok = trim($tok, '.-_');
            if ($tok === '') {
                continue;
            }
            foreach (self::expandTokenVariants($tok) as $variant) {
                $tokens[$variant] = true;
            }
        }

        $phrases = [];
        foreach ($cleanStrings as $s) {
            $normPhrase = trim(AtsScoringService::normalizeArabic(mb_strtolower(self::normalizeDigits($s), 'UTF-8')));
            if ($normPhrase !== '') {
                $phrases[] = $normPhrase;
            }
        }

        return [
            'isEmpty' => $cleanStrings === [],
            'raw' => $raw,
            'lower' => $lower,
            'normalized' => $normalized,
            'dates' => $dates,
            'numbers' => $numbers,
            'tokens' => $tokens,
            'phrases' => array_values(array_unique($phrases)),
        ];
    }

    /**
     * @return list<string>
     */
    private static function expandTokenVariants(string $token): array
    {
        $variants = [$token];

        if (preg_match('/^[a-z0-9+#.\-_]+$/u', $token)) {
            if (str_ends_with($token, 'ies') && strlen($token) > 4) {
                $variants[] = substr($token, 0, -3).'y';
            }
            if (str_ends_with($token, 'es') && strlen($token) > 4) {
                $variants[] = substr($token, 0, -2);
            }
            if (str_ends_with($token, 's') && strlen($token) > 3) {
                $variants[] = substr($token, 0, -1);
            } else {
                $variants[] = $token.'s';
            }

            if (in_array($token, ['api', 'apis', 'rest', 'restful'], true)) {
                array_push($variants, 'api', 'apis', 'rest', 'restful');
            }
            if (in_array($token, ['sql', 'mysql', 'postgresql', 'postgres', 'sqlite'], true)) {
                array_push($variants, 'sql');
            }
            if (in_array($token, ['js', 'javascript'], true)) {
                array_push($variants, 'js', 'javascript');
            }
            if (in_array($token, ['ts', 'typescript'], true)) {
                array_push($variants, 'ts', 'typescript');
            }
            if (in_array($token, ['k8s', 'kubernetes'], true)) {
                array_push($variants, 'k8s', 'kubernetes');
            }
        }

        if (preg_match('/\p{Arabic}/u', $token)) {
            $stripped = preg_replace('/^(?:وال|بال|لل|ال|و|ب|ل|ك)(?=.{2,})/u', '', $token) ?? $token;
            if ($stripped !== '' && $stripped !== $token) {
                $variants[] = $stripped;
                $variants[] = 'ال'.$stripped;
            } else {
                $variants[] = 'ال'.$token;
            }
        }

        return array_values(array_unique($variants));
    }

    /**
     * @param  array<string, mixed>  $evidence
     */
    private static function hasUngroundedDate(string $text, array $evidence): bool
    {
        foreach (self::extractDates($text) as $date) {
            if (! isset($evidence['dates'][$date])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private static function extractDates(string $text): array
    {
        $normalized = self::normalizeDigits($text);
        $dates = [];

        // 1. Compound numeric dates (YYYY-MM-DD, YYYY-MM, DD/MM/YYYY, MM/YYYY)
        preg_match_all(
            '/(?<!\d)(?:(?:19|20)\d{2}[\/-]\d{1,2}(?:[\/-]\d{1,2})?|\d{1,2}[\/-]\d{1,2}[\/-]\d{2,4}|\d{1,2}[\/-](?:19|20)\d{2})(?!\d)/u',
            $normalized,
            $compoundMatches,
        );
        foreach ($compoundMatches[0] ?? [] as $match) {
            $dates[] = mb_strtolower(trim($match), 'UTF-8');
        }

        // 2. Standalone 4-digit years (1900-2099) on strict non-digit/non-letter boundaries
        preg_match_all(
            '/(?<![\p{L}\d])(?:19|20)\d{2}(?![\p{L}\d])/u',
            $normalized,
            $yearMatches,
        );
        foreach ($yearMatches[0] ?? [] as $year) {
            $dates[] = mb_strtolower(trim($year), 'UTF-8');
        }

        // 3. Quarters (Q1-Q4)
        preg_match_all('/\bQ[1-4]\b/iu', $normalized, $quarterMatches);
        foreach ($quarterMatches[0] ?? [] as $quarter) {
            $dates[] = mb_strtolower(trim($quarter), 'UTF-8');
        }

        // 4. English month tokens
        preg_match_all(
            '/\b(?:January|February|March|April|June|July|August|September|October|November|December|Jan|Feb|Mar|Apr|Jun|Jul|Aug|Sep|Sept|Oct|Nov|Dec)\b|\bMay(?=\s+\d{1,4}|\s*,?\s*(?:19|20)\d{2})/iu',
            $normalized,
            $enMonths,
        );
        foreach ($enMonths[0] ?? [] as $month) {
            $dates[] = mb_strtolower(trim($month), 'UTF-8');
        }

        // 5. Arabic month tokens on strict word/token boundaries
        $arNorm = AtsScoringService::normalizeArabic($normalized);
        preg_match_all(
            '/(?<![\p{L}\p{N}])(يناير|فبراير|مارس|ابريل|مايو|يونيو|يوليو|اغسطس|سبتمبر|اكتوبر|نوفمبر|ديسمبر|محرم|صفر|ربيع الاول|ربيع الثاني|ربيع الاخر|جمادى الاولى|جمادى الاخره|رجب|شعبان|رمضان|شوال|ذو القعده|ذو الحجه)(?![\p{L}\p{N}])/u',
            $arNorm,
            $arMonths,
        );
        foreach ($arMonths[1] ?? [] as $month) {
            $dates[] = trim($month);
        }

        return array_values(array_unique($dates));
    }

    /**
     * @param  array<string, mixed>  $evidence
     */
    private static function hasUngroundedNumber(string $text, array $evidence): bool
    {
        foreach (self::extractNumbers($text) as $number) {
            if (! isset($evidence['numbers'][$number])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private static function extractNumbers(string $text): array
    {
        $normalized = self::normalizeDigits($text);
        $numbers = [];

        // Strip compound numeric dates and standalone non-percentage 4-digit years first so
        // month/day/year components inside dates do not leak as standalone numbers.
        $working = preg_replace(
            '/(?<!\d)(?:(?:19|20)\d{2}[\/-]\d{1,2}(?:[\/-]\d{1,2})?|\d{1,2}[\/-]\d{1,2}[\/-]\d{2,4}|\d{1,2}[\/-](?:19|20)\d{2})(?!\d)/u',
            ' ',
            $normalized,
        ) ?? $normalized;

        $working = preg_replace(
            '/(?<![\p{L}\d.])(?:19|20)\d{2}(?![\p{L}\d.])(?!\s*(?:%|percent\b|بالمئة|بالمئه|في\s+المئة|في\s+المائة|في\s+المئه))/iu',
            ' ',
            $working,
        ) ?? $working;

        // 1. Suffix percentages (e.g. "5%", "5 %", "5 percent", "5 بالمئة")
        preg_match_all(
            '/(?<![\p{L}\d.])(\d+(?:,\d{3})*(?:\.\d+)?)\s*(?:%|percent\b|بالمئة|بالمئه|في\s+المئة|في\s+المائة|في\s+المئه)/iu',
            $working,
            $suffixPctMatches,
        );
        foreach ($suffixPctMatches[1] ?? [] as $num) {
            $clean = str_replace(',', '', trim($num));
            if ($clean !== '') {
                $numbers[] = $clean.'%';
            }
        }
        $working = preg_replace(
            '/(?<![\p{L}\d.])(\d+(?:,\d{3})*(?:\.\d+)?)\s*(?:%|percent\b|بالمئة|بالمئه|في\s+المئة|في\s+المائة|في\s+المئه)/iu',
            ' ',
            $working,
        ) ?? $working;

        // 2. Prefix percentages (e.g. "%5" in Arabic text)
        preg_match_all(
            '/(?<![\p{L}\d.])%\s*(\d+(?:,\d{3})*(?:\.\d+)?)(?![\p{L}\d.])/u',
            $working,
            $prefixPctMatches,
        );
        foreach ($prefixPctMatches[1] ?? [] as $num) {
            $clean = str_replace(',', '', trim($num));
            if ($clean !== '') {
                $numbers[] = $clean.'%';
            }
        }
        $working = preg_replace(
            '/(?<![\p{L}\d.])%\s*(\d+(?:,\d{3})*(?:\.\d+)?)(?![\p{L}\d.])/u',
            ' ',
            $working,
        ) ?? $working;

        // 3. Standalone numeric tokens on strict token boundaries
        preg_match_all(
            '/(?<![\p{L}\d.])\d+(?:,\d{3})*(?:\.\d+)?(?!(?:d|fa|g)\b)(?![\p{L}\d.])/iu',
            $working,
            $digitMatches,
        );
        foreach ($digitMatches[0] ?? [] as $rawNum) {
            $clean = str_replace(',', '', trim($rawNum));
            if ($clean !== '') {
                $numbers[] = $clean;
            }
        }

        // 4. English number words on strict word boundaries
        preg_match_all(
            '/\b(two|three|four|five|six|seven|eight|nine|ten|eleven|twelve|thirteen|fourteen|fifteen|sixteen|seventeen|eighteen|nineteen|twenty|thirty|forty|fifty|sixty|seventy|eighty|ninety|hundred|thousand|million|billion)\b/iu',
            $normalized,
            $enWordNums,
        );
        foreach ($enWordNums[1] ?? [] as $wordNum) {
            $numbers[] = mb_strtolower(trim($wordNum), 'UTF-8');
        }

        // 5. Arabic number words on strict token boundaries
        $arNorm = AtsScoringService::normalizeArabic($normalized);
        preg_match_all(
            '/(?<![\p{L}\p{N}])(سنتين|عامين|شهرين|ثلاثه?|اربعه?|خمسه?|سته?|سبعه?|ثماني?ه?|تسعه?|عشره?|عشرين|ثلاثين|اربعين|خمسين|ستين|سبعين|ثمانين|تسعين|مئه|مائه|الف|الاف|مليون|مليار)(?![\p{L}\p{N}])/u',
            $arNorm,
            $arWordNums,
        );
        foreach ($arWordNums[1] ?? [] as $wordNum) {
            $numbers[] = trim($wordNum);
        }

        return array_values(array_unique($numbers));
    }

    /**
     * @param  array<string, mixed>  $evidence
     */
    private static function hasUngroundedEmployer(string $text, array $evidence): bool
    {
        foreach (self::extractEmployers($text) as $employer) {
            if (! self::isTermInEvidence($employer, $evidence)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private static function extractEmployers(string $text): array
    {
        $employers = [];

        preg_match_all(
            '/(?:\bat|\bfor|\bwith|\bjoined|\bfrom)\s+((?:\p{Lu}[\p{L}0-9&.\-]*)(?:\s+\p{Lu}[\p{L}0-9&.\-]*){0,3})/u',
            $text,
            $enPrepMatches,
        );
        foreach ($enPrepMatches[1] ?? [] as $emp) {
            $employers[] = trim($emp, " \t\n\r\0\x0B.,;:");
        }

        preg_match_all(
            '/\b((?:\p{Lu}[\p{L}0-9&.\-]*\s+){1,4}(?:Corp|Corporation|Inc|LLC|Ltd|Limited|Group|Holdings|Technologies|Labs|Systems|Solutions|Company|Co|Bank|University|Institute|Agency|Consulting|Partners|Ventures|Studio|Studios|Enterprises))\b/u',
            $text,
            $enSuffixMatches,
        );
        foreach ($enSuffixMatches[1] ?? [] as $emp) {
            $employers[] = trim($emp, " \t\n\r\0\x0B.,;:");
        }

        preg_match_all(
            '/(?:في\s+|لدى\s+|مع\s+|بـ?\s*)?(?:شركة|شركه|مؤسسة|مؤسسه|مجموعة|مجموعه|بنك|مصرف|جامعة|جامعه|معهد|هيئة|هيئه|وزارة|وزاره)\s+([^\s،,.؛;\r\n()]+)/u',
            $text,
            $arMatches,
        );
        foreach ($arMatches[1] ?? [] as $emp) {
            $emp = self::trimPunctuation($emp);
            $genericArabicNouns = ['تقنية', 'تقنيه', 'برمجيات', 'تطوير', 'محلية', 'محليه', 'عالمية', 'عالميه', 'كبرى', 'رائدة', 'رائده', 'خاصة', 'خاصه', 'حكومية', 'حكوميه'];
            if ($emp !== '' && ! in_array($emp, $genericArabicNouns, true)) {
                $employers[] = $emp;
            }
        }

        return array_values(array_unique(array_filter($employers, static fn (string $e): bool => $e !== '')));
    }

    /**
     * @param  array<string, mixed>  $evidence
     * @param  array<string, mixed>  $jdEvidence
     * @param  list<string>  $claimedJdTerms
     */
    private static function hasUngroundedSkill(
        string $text,
        array $evidence,
        array $jdEvidence = [],
        array $claimedJdTerms = [],
        ?string $section = null,
    ): bool {
        if ($section === 'skills') {
            $cleanedSkillsText = preg_replace('/^[^:：]+[:：]\s*/u', '', $text) ?? $text;
            $items = preg_split('/[,،;؛|\n•·\/]+/u', $cleanedSkillsText) ?: [];
            foreach ($items as $item) {
                $item = trim((string) preg_replace('/^[\-\*\s]+/u', '', $item));
                if ($item !== '' && ! self::isTermInEvidence($item, $evidence)) {
                    return true;
                }
            }
        }

        foreach ($claimedJdTerms as $term) {
            if ($term !== '' && ! self::isTermInEvidence($term, $evidence)) {
                return true;
            }
        }

        foreach ($jdEvidence['phrases'] ?? [] as $jdPhrase) {
            if (mb_strlen($jdPhrase) >= 3 && mb_strlen($jdPhrase) <= 40) {
                if (self::isTermInText($jdPhrase, $text) && ! self::isTermInEvidence($jdPhrase, $evidence)) {
                    return true;
                }
            }
        }

        $hasArabic = (bool) preg_match('/\p{Arabic}/u', $text);
        if ($hasArabic) {
            preg_match_all('/[A-Za-z][A-Za-z0-9+#.\-_]*/u', $text, $latinTokens);
            foreach ($latinTokens[0] ?? [] as $latinToken) {
                $cleanTok = trim($latinToken, '.-_');
                if ($cleanTok === '' || in_array(strtolower($cleanTok), ['ats', 'cv'], true)) {
                    continue;
                }
                if (! self::isTermInEvidence($cleanTok, $evidence)) {
                    return true;
                }
            }

            preg_match_all(
                '/(?:باستخدام|بواسطة|بتقنية|بأداة|في\s+برنامج|في\s+نظام|على\s+منصة|(?:خبرة|خبره)(?:\s+عملية|\s+عمليه|\s+قوية|\s+قويه|\s+واسعة|\s+واسعه)?\s+في|توافق(?:\s+جيد|\s+قوي|\s+ممتاز)?\s+في|(?:نقص|ضعف|غياب)(?:\s+خبرة|\s+خبره)?\s+في|(?:مذكورة|مذكوره)\s+في|(?:إشارة|اشاره)\s+(?:إلى|الى)|(?:إتقان|اتقان)|(?:مهارة|مهاره|مهارات)|(?:معرفة|معرفه)\s+بـ?|(?:متخصص|تخصص)\s+في)\s+([^\s،,.؛;\r\n()]+)/u',
                $text,
                $arSkillPhrases,
            );
            $genericArWords = [
                'تطوير', 'بناء', 'تحسين', 'إدارة', 'ادارة', 'تنظيم', 'تنفيذ', 'تحليل', 'تصميم',
                'أدوات', 'ادوات', 'أساليب', 'اساليب', 'منهجية', 'منهجيه', 'طرق', 'فعالة', 'فعاله',
                'حديثة', 'حديثه', 'متقدمة', 'متقدمه', 'أفضل', 'افضل', 'تقنيات', 'أنظمة', 'انظمه',
                'السيرة', 'السيره', 'الذاتية', 'الذاتيه', 'العمل', 'المجال', 'الوظيفة', 'الوظيفه',
                'الدور', 'المشاريع', 'الأداء', 'الاداء', 'الفريق', 'الإدارة', 'الاداره', 'التطوير',
                'هذا', 'هذه', 'ذلك', 'تلك', 'بشكل', 'عام', 'شركة', 'شركه', 'مؤسسة', 'مؤسسه',
            ];
            foreach ($arSkillPhrases[1] ?? [] as $arSkill) {
                $arSkill = self::trimPunctuation($arSkill);
                if ($arSkill !== '' && ! in_array($arSkill, $genericArWords, true) && ! self::isTermInEvidence($arSkill, $evidence)) {
                    return true;
                }
            }
        }

        // Check technical tokens, acronyms, CamelCase, and proper nouns in Latin text
        preg_match_all('/(?<![\p{L}\d])(?:[A-Za-z0-9+#.\-_]*[A-Za-z][A-Za-z0-9+#.\-_]*)(?![\p{L}\d])/u', $text, $enMatches);
        $commonProseSet = array_flip(array_map('strtolower', self::COMMON_ENGLISH_PROSE_WORDS));

        foreach ($enMatches[0] ?? [] as $rawToken) {
            $token = trim($rawToken, '.-_');
            if ($token === '' || strlen($token) < 2) {
                continue;
            }
            $lowerTok = strtolower($token);
            if (in_array($lowerTok, ['ats', 'cv'], true)) {
                continue;
            }

            $isCamelCase = (bool) preg_match('/[A-Z][a-z]+[A-Z]|[a-z]+[A-Z]/', $token);
            $isAcronym = (bool) preg_match('/^[A-Z]{2,}[0-9]*s?$/', $token);
            $isPunctuatedOrAlphaNumTech = (bool) preg_match('/[+#]|\.[a-z]{2,}|\d/', $token);
            $isCapitalized = (bool) preg_match('/^[A-Z][a-zA-Z0-9+#.\-]*$/', $token);

            if ($isCamelCase || $isAcronym || $isPunctuatedOrAlphaNumTech) {
                if (! self::isTermInEvidence($token, $evidence)) {
                    return true;
                }

                continue;
            }

            if ($isCapitalized && ! isset($commonProseSet[$lowerTok])) {
                if (! self::isTermInEvidence($token, $evidence)) {
                    return true;
                }
            }
        }

        // Check lowercase known skill bank + AtsScoringService::jobKeywords()
        foreach (self::allKnownSkills() as $skill) {
            if (self::isTermInText($skill, $text) && ! self::isTermInEvidence($skill, $evidence)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private static function allKnownSkills(): array
    {
        static $catalog = null;
        if ($catalog !== null) {
            return $catalog;
        }

        $skills = self::KNOWN_SKILLS;
        foreach (AtsScoringService::jobKeywords() as $category => $keywords) {
            if ($category === 'general') {
                continue;
            }
            foreach ($keywords as $kw) {
                $skills[] = $kw;
            }
        }

        $catalog = array_values(array_unique(array_map(
            static fn (string $s): string => AtsScoringService::normalizeArabic(mb_strtolower(trim($s), 'UTF-8')),
            $skills,
        )));

        return $catalog;
    }

    /**
     * @param  array<string, mixed>  $evidence
     */
    private static function isTermInEvidence(string $term, array $evidence): bool
    {
        if ($evidence['isEmpty']) {
            return false;
        }

        $normTerm = self::trimPunctuation(AtsScoringService::normalizeArabic(mb_strtolower(self::normalizeDigits($term), 'UTF-8')));
        if ($normTerm === '') {
            return true;
        }

        foreach (self::expandTokenVariants($normTerm) as $variant) {
            if (isset($evidence['tokens'][$variant])) {
                return true;
            }
            if (in_array($variant, $evidence['phrases'], true)) {
                return true;
            }
            if (self::isTermInNormalizedString($variant, $evidence['normalized'])) {
                return true;
            }
        }

        $subTokens = preg_split('/[^\p{L}\p{N}+#]+/u', $normTerm) ?: [];
        $subTokens = array_values(array_filter($subTokens, static fn (string $t): bool => $t !== '' && mb_strlen($t) >= 2));

        if (count($subTokens) > 1) {
            foreach ($subTokens as $sub) {
                $subFound = false;
                foreach (self::expandTokenVariants($sub) as $variant) {
                    if (isset($evidence['tokens'][$variant]) || self::isTermInNormalizedString($variant, $evidence['normalized'])) {
                        $subFound = true;
                        break;
                    }
                }
                if (! $subFound) {
                    return false;
                }
            }

            return true;
        }

        return false;
    }

    private static function isTermInText(string $term, string $text): bool
    {
        $normTerm = trim(AtsScoringService::normalizeArabic(mb_strtolower(self::normalizeDigits($term), 'UTF-8')));
        $normText = AtsScoringService::normalizeArabic(mb_strtolower(self::normalizeDigits($text), 'UTF-8'));

        if ($normTerm === '' || $normText === '') {
            return false;
        }

        return self::isTermInNormalizedString($normTerm, $normText);
    }

    private static function isTermInNormalizedString(string $normTerm, string $normHaystack): bool
    {
        $pattern = '/(?<![\p{L}\p{N}])'.preg_quote($normTerm, '/').'(?![\p{L}\p{N}])/u';

        return (bool) preg_match($pattern, $normHaystack);
    }

    /**
     * Check whether $text shares at least one substantive token or phrase with $evidence.
     *
     * @param  array<string, mixed>  $evidence
     */
    private static function hasEvidenceOverlap(string $text, array $evidence): bool
    {
        if ($evidence['isEmpty']) {
            return false;
        }

        $normText = AtsScoringService::normalizeArabic(mb_strtolower(self::normalizeDigits($text), 'UTF-8'));
        foreach ($evidence['phrases'] as $phrase) {
            if (mb_strlen($phrase) >= 2 && self::isTermInNormalizedString($phrase, $normText)) {
                return true;
            }
        }

        $tokens = preg_split('/[^\p{L}\p{N}+#]+/u', $normText) ?: [];
        foreach ($tokens as $tok) {
            $tok = trim($tok);
            if (mb_strlen($tok) < 2) {
                continue;
            }
            foreach (self::expandTokenVariants($tok) as $variant) {
                if (isset($evidence['tokens'][$variant])) {
                    return true;
                }
            }
        }

        return false;
    }

    private static function isInjectionOrRefusal(string $text): bool
    {
        $trimmed = trim($text);
        if ($trimmed === '') {
            return false;
        }

        $patterns = [
            '/\bignore\s+(?:all\s+)?(?:previous|prior|above|earlier|system)\s+(?:instructions?|prompts?|rules?|directives?)\b/iu',
            '/\bdisregard\s+(?:all\s+)?(?:previous|prior|above|earlier|system)\s+(?:instructions?|prompts?|rules?)\b/iu',
            '/\bforget\s+(?:all\s+)?(?:previous|prior|above|earlier)\s+(?:instructions?|prompts?)\b/iu',
            '/\bsystem\s*(?:prompt|override|instruction)\s*:/iu',
            '/\byou\s+are\s+now\s+(?:a|an|in)\b/iu',
            '/\bdo\s+anything\s+now\b|\bdan\s+mode\b|\bjailbreak\b|\bpwned\b|\bhacked\b/iu',
            '/\b(?:i\s+cannot|i\s+can\'t|i\s+am\s+unable\s+to|i\'m\s+sorry,?\s+i\s+cannot)\s+(?:assist|fulfill|comply|help|complete|process)\b/iu',
            '/\bas\s+an\s+ai\s+(?:language\s+)?model\b/iu',
            '/تجاهل\s+(?:كل\s+|جميع\s+|كافة\s+)?(?:التعليمات|الأوامر|القواعد|الإرشادات)\s*(?:السابقة|أعلاه)?/u',
            '/تجاوز\s+(?:كل\s+|جميع\s+)?(?:التعليمات|الأوامر|القيود)/u',
            '/تم\s+الاختراق|بصفتي\s+نموذج\s+ذكاء\s+اصطناعي|أعتذر،?\s+لا\s+يمكنني\s+المساعدة/u',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $trimmed)) {
                return true;
            }
        }

        return false;
    }

    private static function stripInjectedSegments(string $text): string
    {
        $cleaned = self::stripTagsAndControlChars($text);
        $lines = preg_split('/\R+|(?<=[.!?۔؟])\s+/u', $cleaned) ?: [$cleaned];
        $kept = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || self::isInjectionOrRefusal($line)) {
                continue;
            }
            $kept[] = $line;
        }

        return implode("\n", $kept);
    }

    private static function stripTagsAndControlChars(string $text): string
    {
        $scriptPattern = '/<\s*script\b[^>]*>[\s\S]*?<\s*\/\s*script\s*>/iu';
        $tagPattern = '/<\s*\/?\s*(?:cv_snapshot|job_description|system|instructions?|prompt|user|assistant|developer|role|rules?|context|override|script|iframe|object|embed)\b[^>]*>?/iu';

        do {
            $previous = $text;
            $text = preg_replace($scriptPattern, ' ', $text) ?? $text;
            $text = preg_replace($tagPattern, ' ', $text) ?? $text;
        } while ($text !== $previous);

        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text) ?? $text;
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    private static function normalizeDigits(string $text): string
    {
        $text = ArabicIndicDigits::normalize($text);

        return strtr($text, [
            '۰' => '0',
            '۱' => '1',
            '۲' => '2',
            '۳' => '3',
            '۴' => '4',
            '۵' => '5',
            '۶' => '6',
            '۷' => '7',
            '۸' => '8',
            '۹' => '9',
            '٪' => '%',
            '％' => '%',
        ]);
    }

    private static function trimPunctuation(string $value): string
    {
        return (string) preg_replace('/^[\s.,;:،؛\-_]+|[\s.,;:،؛\-_]+$/u', '', $value);
    }
}
