<?php

namespace Tests\Feature;

use App\Console\Commands\RepairJobListingsCommand;
use App\Models\JobNews;
use App\Services\Jobs\JobPostClassifier;
use App\Services\Jobs\JobTaxonomyMatcher;
use App\Services\Jobs\SaudiCities;
use App\Services\Jobs\SaudiJobAggregatorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * What an aggregated post becomes: published or not, its title, language and
 * city. Inputs are generated (seeded) so the rules hold beyond audited posts.
 */
class JobPostQualityTest extends TestCase
{
    use RefreshDatabase;

    private const ROLES = [
        'محاسب', 'أخصائي موارد بشرية', 'مهندس كهرباء', 'موظفة استقبال', 'فني صيانة تكييف', 'مشرف مبيعات',
        'Technical Support Specialist', '.NET Developer', 'C++ Engineer', 'HSE Officer', 'مشغل إنتاج – Production Operator',
        'مطور تطبيقات (Flutter)', 'أخصائي تسويق رقمي', 'Accountant',
    ];

    private const DECORATIONS = [
        '🔴 ', '(🔴) ', '🔴) ', '➡️ ', '⬅️ ', '📢 ', '💼 ', '⚫ ', '▪️ ', '⭐️ ', '🔥🔥 ', "\u{200F}", "\u{202B}", '* ', '- ', '• ',
        'عاجل | ', 'رسميًا | ', 'قريبًا | ', '(🔴) عاجل | ', 'عاجل ورسميًا ',
    ];

    private const TRAILERS = ['', ':', ' 📍', ' 🏢', ' |', ' -', '  ', ' ✅'];

    private const COURSES = [
        'دورة تدريبية عبر (Zoom) بشهادة حضور', 'دورات (عن بُعد) لدى الهيئة الحضور مجاني', 'جامعة الملك سعود تعلن فتح القبول ببرامج الدراسات العليا',
        'كلية الأمير تعلن فتح باب القبول لبرنامج بكالوريوس التمريض', 'المعهد العالي يعلن فتح باب التسجيل في برامج الدبلوم',
        'إطلاق برنامج تدريبي في الأمن السيبراني', 'ورشة عمل مجانية في التسويق', 'Free online course in data analysis',
        'جامعة الحدود الشمالية تعلن القبول في برامج الدبلوم العالي', '١٥ دورة مجانية بالكامل',
    ];

    private JobPostClassifier $classifier;

    private JobTaxonomyMatcher $matcher;

    protected function setUp(): void
    {
        parent::setUp();
        mt_srand(20261029);
        $this->classifier = app(JobPostClassifier::class);
        $this->matcher = app(JobTaxonomyMatcher::class);
    }

    private function pick(array $items): mixed
    {
        return $items[array_rand($items)];
    }

    private function noApply(): array
    {
        return ['url' => null, 'email' => null];
    }

    public function test_titles_lose_decoration_but_keep_the_role_and_are_stable(): void
    {
        for ($i = 0; $i < 300; $i++) {
            $role = $this->pick(self::ROLES);
            $line = $this->pick(self::DECORATIONS).$this->pick(self::DECORATIONS).$role.$this->pick(self::TRAILERS);
            $text = $line."\n\nالمتطلبات:\n• خبرة سنتين\nللتقديم: jobs@corp.example.sa";

            $title = $this->classifier->titleFrom($text);

            $this->assertSame($role, $title, json_encode($line, JSON_UNESCAPED_UNICODE));
            $this->assertSame($title, $this->classifier->titleFrom($title), 'cleaning twice changes nothing');
            $this->assertFalse($this->classifier->isMessyTitle($title));
            $this->assertDoesNotMatchRegularExpression('/[\p{So}\x{200E}\x{200F}\x{202A}-\x{202E}]/u', $title);
        }
    }

    public function test_hashtag_lines_and_generic_headers_give_way_to_the_role(): void
    {
        $cities = SaudiCities::allNames();
        for ($i = 0; $i < 200; $i++) {
            $role = $this->pick(self::ROLES);
            $city = $this->pick($cities);
            $header = $this->pick([
                '#وظائف_'.str_replace(' ', '_', $city).' #'.str_replace(' ', '_', $city),
                '📢 فرصة وظيفية | '.$city.' 📍',
                'وظائف في '.$city,
                '📢 إعلان وظيفي',
                'فرص وظيفية انضم إلى فريقنا',
            ]);
            $roleLine = $this->pick(['💼 '.$role, '💼 المسمى الوظيفي: '.$role, 'المسمى: '.$role, '⬅️ '.$role]);
            $text = $header."\n\n".$roleLine."\n📍 ".$city."\nللتقديم: cv@corp.example.sa";

            $this->assertSame($role, $this->classifier->titleFrom($text), json_encode($text, JSON_UNESCAPED_UNICODE));
        }
    }

    public function test_brackets_in_titles_are_balanced(): void
    {
        for ($i = 0; $i < 200; $i++) {
            $inner = $this->pick(self::ROLES);
            $line = $this->pick(['(🔴) ', '🔴 ', '']).$this->pick([
                "فتح التوظيف في ({$inner}",
                "({$inner}) لحملة الثانوية",
                "{$inner}):",
                "عاجل ({$inner}) بالرياض",
            ]);
            $title = $this->classifier->titleFrom($line."\nللتقديم: a@b.sa");

            $this->assertSame(substr_count($title, '('), substr_count($title, ')'), $title);
            $this->assertStringContainsString($inner, $title);
        }
    }

    public function test_image_headers_and_near_empty_posts_are_not_published_but_actionable_ones_are(): void
    {
        foreach (SaudiCities::allNames() as $city) {
            $header = $this->pick(['وظائف في ', 'وظائف ', '📢 وظائف في ', 'فرص وظيفية في ']).$city;

            $this->assertSame(JobPostClassifier::REJECT_IMAGE_ONLY, $this->classifier->rejectionReason($header, $this->noApply(), true));
            $this->assertSame(JobPostClassifier::REJECT_EMPTY, $this->classifier->rejectionReason($header, $this->noApply(), false));

            // Any way to apply keeps it: a link, an email or a phone in any digit script.
            $this->assertNull($this->classifier->rejectionReason($header, ['url' => 'https://careers.x.sa/jobs/1', 'email' => null], true));
            $this->assertNull($this->classifier->rejectionReason($header, ['url' => null, 'email' => 'hr@x.sa'], true));
            $phone = $this->pick(['0551234567', '055 123 4567', '+966551234567', '٠٥٥١٢٣٤٥٦٧']);
            $this->assertNull($this->classifier->rejectionReason($header."\nللتواصل: ".$phone, $this->noApply(), true));
        }

        // A short text post that names a role is a job even without contact.
        foreach (self::ROLES as $role) {
            $this->assertNull($this->classifier->rejectionReason("مطلوب {$role} للعمل في الرياض\nخبرة سنتين", $this->noApply(), false), $role);
        }
        $this->assertNotNull($this->classifier->rejectionReason("سلام عليكم ورحمه الله وبركاته\nممكن تنشر الإعلان", $this->noApply(), false));
    }

    public function test_courses_and_admissions_are_not_jobs_unless_they_hire(): void
    {
        $apply = ['url' => 'https://example.sa/register', 'email' => null];
        foreach (self::COURSES as $course) {
            foreach (['', '(🔴) ', 'عاجل | '] as $prefix) {
                // A body line with "المطلوب" (the required qualification) is not a hiring cue.
                $text = $prefix.$course.":\n▪️ المؤهل المطلوب: الثانوية العامة\n▪️ التفاصيل في الرابط";
                $this->assertSame(JobPostClassifier::REJECT_NOT_A_JOB, $this->classifier->rejectionReason($text, $apply, false), $text);

                $hiring = $prefix.$course.' منتهي بالتوظيف:'."\n▪️ مكافأة شهرية";
                $this->assertNull($this->classifier->rejectionReason($hiring, $apply, false), $hiring);
            }
        }

        // Job posts that mention training or admission further down stay.
        foreach (self::ROLES as $role) {
            $text = "مطلوب {$role}\nالرياض\nعضوية الهيئة في حال القبول\nدورات تدريبية احترافية";
            $this->assertNull($this->classifier->rejectionReason($text, $apply, false), $role);
        }
    }

    public function test_language_follows_the_script_of_the_post(): void
    {
        $english = ['is seeking', 'Requirements: Bachelor degree', 'Apply now at careers page', 'Location: Riyadh, Saudi Arabia'];
        $arabic = ['مطلوب للعمل فورا', 'المتطلبات: بكالوريوس', 'للتقديم عبر البريد', 'مكان العمل الرياض'];
        for ($i = 0; $i < 200; $i++) {
            $role = $this->pick(self::ROLES);
            $en = 'Company '.$this->pick($english)."\n".$this->pick($english)."\n".$this->pick($english);
            $ar = $this->pick($arabic)."\n".$role.' (Excel, SAP, Oracle)'."\n".$this->pick($arabic);

            $this->assertSame('en', $this->classifier->languageOf($en));
            $this->assertSame('ar', $this->classifier->languageOf($ar));
        }
    }

    public function test_remote_jobs_must_admit_candidates_in_saudi_arabia(): void
    {
        foreach (['Worldwide', 'worldwide', 'Anywhere in the World', 'EMEA', 'Europe, Middle East', 'MENA', 'Saudi Arabia', 'KSA, UAE', 'GCC', 'Global'] as $open) {
            $this->assertTrue($this->classifier->remoteOpenToSaudi($open), $open);
        }
        foreach (['', '  ', 'USA', 'USA, Canada, USA timezones', 'Europe, USA, Canada, APAC', 'UK', 'Germany', 'Latin America', 'Americas'] as $closed) {
            $this->assertFalse($this->classifier->remoteOpenToSaudi($closed), $closed);
        }
    }

    public function test_every_city_name_is_found_as_a_word_and_never_inside_another_word(): void
    {
        foreach (SaudiCities::CITIES as $code => [, , $names]) {
            foreach ($names as $name) {
                foreach (['', 'في ', 'ب', 'و', 'بمدينة ', '📍 ', '('] as $prefix) {
                    $text = "مطلوب محاسب {$prefix}{$name} خبرة";
                    $this->assertSame($code, $this->matcher->detectCity($text)['city'], $text);
                }
                // A single word followed by more letters is another word ("الرياضة", "الخبرة").
                if (! str_contains($name, ' ') && preg_match('/\p{Arabic}$/u', $name)) {
                    foreach (['ة', 'ات', 'ية', 'ين'] as $suffix) {
                        $text = "خبرة في {$name}{$suffix} مطلوبة";
                        $this->assertNotSame($code, $this->matcher->detectCity($text)['city'], $text);
                    }
                }
            }
        }

        // "الخبرة" (experience) is not Khobar.
        $this->assertNull($this->matcher->detectCity('مطلوب محاسب بخبرة سنتين، الخبرة شرط')['city']);
    }

    public function test_the_first_city_named_wins_and_a_province_counts_only_without_a_city(): void
    {
        $codes = array_keys(SaudiCities::CITIES);
        for ($i = 0; $i < 200; $i++) {
            [$a, $b] = array_rand(array_flip($codes), 2);
            if (mt_rand(0, 1)) {
                [$a, $b] = [$b, $a];
            }
            $text = 'العمل في '.SaudiCities::CITIES[$a][2][0].' ثم '.SaudiCities::CITIES[$b][2][0].' والمنطقة الشرقية';
            $this->assertSame($a, $this->matcher->detectCity($text)['city'], $text);
        }
        foreach (SaudiCities::PROVINCES as $code => [, , $names]) {
            $this->assertSame($code, $this->matcher->detectCity('وظائف في '.$names[0])['city']);
        }
    }

    public function test_each_code_is_in_exactly_one_region_and_the_region_filter_uses_them_all(): void
    {
        $all = array_keys(SaudiCities::CITIES + SaudiCities::PROVINCES);
        $byRegion = [];
        foreach (SaudiCities::REGIONS as $region) {
            $byRegion[$region] = SaudiCities::codesInRegion($region);
        }
        $flat = array_merge(...array_values($byRegion));
        sort($flat);
        $sorted = $all;
        sort($sorted);
        $this->assertSame($sorted, $flat, 'every code is in one region, none twice');

        foreach ($all as $code) {
            JobNews::create([
                'language' => 'ar', 'title' => "وظيفة {$code}", 'body' => 'x', 'city' => $code,
                'is_published' => true, 'published_at' => now(), 'external_source' => 'telegram_t', 'external_id' => $code,
            ]);
        }
        foreach ($byRegion as $region => $codes) {
            $got = collect($this->getJson("/api/mobile/job-news?region={$region}")->assertOk()->json('data.items'))->pluck('city')->sort()->values()->all();
            $want = $codes;
            sort($want);
            $this->assertSame(array_slice($want, 0, 30), array_slice($got, 0, 30), $region);
        }
    }

    public function test_aggregated_posts_show_in_both_app_languages_and_curated_ones_in_their_own(): void
    {
        foreach (['ar', 'en'] as $lang) {
            JobNews::create(['language' => $lang, 'title' => "curated {$lang}", 'body' => 'x', 'is_published' => true, 'published_at' => now()]);
            JobNews::create([
                'language' => $lang, 'title' => "aggregated {$lang}", 'body' => 'x', 'is_published' => true, 'published_at' => now(),
                'external_source' => 'telegram_t', 'external_id' => $lang,
            ]);
        }

        foreach (['ar' => 'en', 'en' => 'ar'] as $lang => $other) {
            $titles = collect($this->getJson("/api/mobile/job-news?lang={$lang}", ['Accept-Language' => $lang])->assertOk()->json('data.items'))->pluck('title');
            $this->assertContains("aggregated {$lang}", $titles);
            $this->assertContains("aggregated {$other}", $titles);
            $this->assertContains("curated {$lang}", $titles);
            $this->assertNotContains("curated {$other}", $titles);
        }
    }

    public function test_ingest_skips_photo_headers_and_courses_and_remote_jobs_closed_to_saudi(): void
    {
        $posts = [
            ['وظائف في القطيف', true],
            ['(🔴) دورة تدريبية عبر (Zoom) بشهادة حضور:<br/>للتسجيل <a href="https://forms.gle/AbC123">هنا</a>', false],
            ['(🔴) عاجل | مطلوب محاسب في القطيف:<br/>للتقديم: cv@corp.example.sa', false],
        ];
        $html = '<html><body>';
        foreach ($posts as $i => [$text, $photo]) {
            $media = $photo ? '<a class="tgme_widget_message_photo_wrap" style="background-image:url(x)"></a>' : '';
            $html .= '<div class="tgme_widget_message_wrap"><div class="tgme_widget_message" data-post="chan/'.($i + 1).'">'
                .$media.'<div class="tgme_widget_message_text js-message_text">'.$text.'</div>'
                .'<time datetime="2026-10-28T12:00:00+03:00"></time></div></div>';
        }
        Http::fake([
            'https://t.me/s/chan*' => Http::response($html.'</body></html>', 200),
            'https://remotive.com/*' => Http::response(['jobs' => [
                ['id' => 1, 'title' => 'Open Role', 'company_name' => 'A', 'url' => 'https://remotive.com/job/1', 'description' => 'x', 'candidate_required_location' => 'Worldwide'],
                ['id' => 2, 'title' => 'US Role', 'company_name' => 'B', 'url' => 'https://remotive.com/job/2', 'description' => 'x', 'candidate_required_location' => 'USA'],
                ['id' => 3, 'title' => 'Unknown Role', 'company_name' => 'C', 'url' => 'https://remotive.com/job/3', 'description' => 'x'],
            ]], 200),
        ]);
        $svc = app(SaudiJobAggregatorService::class);
        $svc->aggregateFeed(['source' => 'telegram_chan', 'name' => '@chan', 'channel' => 'chan', 'type' => 'telegram', 'language' => 'ar']);
        $svc->aggregateFeed(['source' => 'remotive_remote', 'name' => 'Remotive', 'url' => 'https://remotive.com/api/remote-jobs', 'type' => 'remotive_api']);

        $stored = JobNews::query()->orderBy('id')->get();
        $this->assertSame(['مطلوب محاسب في القطيف', 'Open Role'], $stored->pluck('title')->all());
        $this->assertSame('qatif', $stored[0]->city);
        $this->assertSame('القطيف', $stored[0]->location);
        $this->assertSame('عن بُعد (Worldwide)', $stored[1]->location);
        $this->assertSame('en', $stored[1]->language);
    }

    public function test_repair_reclassifies_stored_posts_and_is_idempotent(): void
    {
        $base = ['language' => 'ar', 'is_published' => true, 'published_at' => now()];
        $messy = JobNews::create($base + [
            'title' => '🔴) عاجل | مطلوب محاسب بالقطيف', 'body' => "🔴) عاجل | مطلوب محاسب بالقطيف\nالخبرة سنتين\nللتقديم: cv@corp.example.sa",
            'external_source' => 'telegram_jobmag', 'external_id' => '1', 'city' => 'khobar',
        ]);
        $course = JobNews::create($base + [
            'title' => 'دورة تدريبية عبر Zoom', 'body' => "(🔴) دورة تدريبية عبر (Zoom) بشهادة حضور:\nالتسجيل مجاني\nhttps://forms.gle/x",
            'external_source' => 'telegram_ewdifh', 'external_id' => '2', 'apply_url' => 'https://forms.gle/AbC123',
        ]);
        $stub = JobNews::create($base + ['title' => 'وظائف في القطيف', 'body' => 'وظائف في القطيف', 'external_source' => 'telegram_jobs2ksa', 'external_id' => '3']);
        $remote = JobNews::create($base + [
            'language' => 'en', 'title' => 'Writer', 'body' => 'x', 'location' => RepairJobListingsCommand::UNCHECKED_REMOTE_LOCATION,
            'external_source' => 'remotive_remote', 'external_id' => '4', 'is_remote' => true,
        ]);
        $curated = JobNews::create($base + ['title' => 'وظائف في القطيف', 'body' => 'وظائف في القطيف']);

        $this->artisan('jobs:repair-listings')->assertSuccessful();

        $this->assertSame('مطلوب محاسب بالقطيف', $messy->fresh()->title);
        $this->assertSame('qatif', $messy->fresh()->city, 'الخبرة is not Khobar');
        $this->assertTrue($messy->fresh()->is_published);
        $this->assertFalse($course->fresh()->is_published);
        $this->assertFalse($stub->fresh()->is_published);
        $this->assertFalse($remote->fresh()->is_published);
        $this->assertTrue($curated->fresh()->is_published, 'curated listings are not reclassified');

        $this->artisan('jobs:repair-listings')->expectsOutput('Repaired 0 job listing(s).')->assertSuccessful();
    }
}
