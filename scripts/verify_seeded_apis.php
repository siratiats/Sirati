<?php

/**
 * CLI Verification Tool for Seeded Server APIs
 *
 * Usage:
 *   php scripts/verify_seeded_apis.php [API_BASE_URL]
 *
 * Examples:
 *   php scripts/verify_seeded_apis.php http://127.0.0.1:8000/api
 *   php scripts/verify_seeded_apis.php https://sirati-main-shokc5.laravel.cloud/api
 */

$baseUrl = rtrim($argv[1] ?? 'https://sirati-main-shokc5.laravel.cloud/api', '/');

echo "\n=========================================================\n";
echo "  Sirati API Seeded Data Verification Tool\n";
echo "  Target Server: {$baseUrl}\n";
echo "=========================================================\n\n";

$tests = [
    [
        'name' => 'Job Titles Taxonomy (GET /mobile/job-titles)',
        'url' => "{$baseUrl}/mobile/job-titles",
        'method' => 'GET',
        'validate' => function (array $json, int $status) {
            if ($status !== 200) return "Expected HTTP 200, got {$status}";
            $data = $json['data'] ?? null;
            if (!is_array($data)) return "Missing 'data' array in response";
            $count = count($data);
            if ($count < 70) return "Expected >= 70 job titles, found {$count}";
            $slugs = array_column($data, 'slug');
            if (!in_array('software-engineer', $slugs)) return "Missing 'software-engineer' slug";
            if (!in_array('other', $slugs)) return "Missing 'other' slug";
            return true;
        },
        'detail' => function (array $json) {
            $count = count($json['data'] ?? []);
            return "Found {$count} active job titles seeded";
        },
    ],
    [
        'name' => 'CV Templates Catalog (GET /mobile/cv-templates)',
        'url' => "{$baseUrl}/mobile/cv-templates?lang=en",
        'method' => 'GET',
        'validate' => function (array $json, int $status) {
            if ($status !== 200) return "Expected HTTP 200, got {$status}";
            $items = $json['data']['items'] ?? null;
            if (!is_array($items)) return "Missing 'data.items' array in response";
            $count = count($items);
            if ($count < 6) return "Expected >= 6 CV templates, found {$count}";
            $slugs = array_column($items, 'slug');
            if (!in_array('ats-classic-professional', $slugs)) return "Missing 'ats-classic-professional' template";
            if (!in_array('bilingual-global-professional', $slugs)) return "Missing 'bilingual-global-professional' template";
            return true;
        },
        'detail' => function (array $json) {
            $items = $json['data']['items'] ?? [];
            $names = array_map(fn($t) => $t['name_ar'] ?? $t['name'] ?? $t['slug'], $items);
            return count($items) . " templates: " . implode(', ', $names);
        },
    ],
    [
        'name' => 'Education Hub (GET /mobile/education?type=study)',
        'url' => "{$baseUrl}/mobile/education?type=study&lang=ar",
        'method' => 'GET',
        'validate' => function (array $json, int $status) {
            if ($status !== 200) return "Expected HTTP 200, got {$status}";
            if (!isset($json['data'])) return "Missing 'data' key in response";
            return true;
        },
        'detail' => function (array $json) {
            return "Valid education articles response structure";
        },
    ],
    [
        'name' => 'Job News Board (GET /mobile/job-news)',
        'url' => "{$baseUrl}/mobile/job-news?lang=ar",
        'method' => 'GET',
        'validate' => function (array $json, int $status) {
            if ($status !== 200) return "Expected HTTP 200, got {$status}";
            if (!isset($json['data'])) return "Missing 'data' key in response";
            return true;
        },
        'detail' => function (array $json) {
            return "Valid job opportunities response structure";
        },
    ],
];

$passed = 0;
$failed = 0;
$total = count($tests);

foreach ($tests as $i => $test) {
    $num = $i + 1;
    echo "[$num/{$total}] {$test['name']} ... ";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $test['url']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json', 'User-Agent: Sirati-Seeded-Verifier/1.0']);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        echo "\033[31m[FAILED]\033[0m cURL error: {$curlError}\n";
        $failed++;
        continue;
    }

    $json = json_decode((string) $body, true) ?? [];
    $result = $test['validate']($json, $status);

    if ($result === true) {
        $detail = $test['detail']($json);
        echo "\033[32m[PASS]\033[0m ({$status}) -> {$detail}\n";
        $passed++;
    } else {
        echo "\033[31m[FAIL]\033[0m ({$status}) -> {$result}\n";
        $failed++;
    }
}

echo "\n---------------------------------------------------------\n";
echo "  Results: {$passed} PASSED, {$failed} FAILED\n";
echo "---------------------------------------------------------\n\n";

exit($failed > 0 ? 1 : 0);
