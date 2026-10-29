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

    public function test_an_attachment_never_drops_a_post_that_names_a_role(): void
    {
        foreach (self::ROLES as $role) {
            foreach (['مطلوب %s للعمل في الرياض خبرة سنتين', 'نبحث عن %s', 'وظيفة %s بالدمام'] as $shape) {
                $text = sprintf($shape, $role);
                $this->assertNull($this->classifier->rejectionReason($text, $this->noApply(), false), $text);
                $this->assertNull($this->classifier->rejectionReason($text, $this->noApply(), true), "with media: {$text}");
            }
        }
    }

    public function test_an_attachment_post_that_points_at_the_file_or_only_has_tags_is_image_only(): void
    {
        foreach (self::ROLES as $role) {
            foreach (["(🔴) وظائف {$role} 📘 شركة الصحة (PDF)\nاضغط على الملف", "وظيفة {$role}\nالتفاصيل في الصورة المرفقة", "{$role} jobs, see the attached PDF"] as $text) {
                $this->assertSame(JobPostClassifier::REJECT_IMAGE_ONLY, $this->classifier->rejectionReason($text, $this->noApply(), true), $text);
            }
        }
        $tagsOnly = "#وظائف_الشرقية #الخبر\nالشرقية\n5:36 PM · Oct 28, 2026\n#المنطقة_الشرقية, #الدمام";
        $this->assertSame(JobPostClassifier::REJECT_IMAGE_ONLY, $this->classifier->rejectionReason($tagsOnly, $this->noApply(), true));
        $this->assertSame(JobPostClassifier::REJECT_EMPTY, $this->classifier->rejectionReason($tagsOnly, $this->noApply(), false));
    }

    public function test_a_course_cue_in_the_opening_lines_or_behind_a_hashtag_still_rejects(): void
    {
        $apply = ['url' => 'https://forms.gle/AbC123', 'email' => null];
        $openers = ['فرص جديدة للسعوديين', 'سجّل الآن وطوّر مهاراتك', 'لا تفوت الفرصة #وظائف', '#وظائف_الرياض #تدريب'];
        foreach (self::COURSES as $course) {
            for ($i = 0; $i < 3; $i++) {
                $lines = [$this->pick($openers), $this->pick($openers)];
                array_splice($lines, mt_rand(0, 2), 0, [$course.' #وظائف']);
                $text = implode("\n", $lines)."\nللتسجيل عبر الرابط";
                $this->assertSame(JobPostClassifier::REJECT_NOT_A_JOB, $this->classifier->rejectionReason($text, $apply, false), $text);
            }
        }
    }

    public function test_a_short_post_whose_title_is_a_known_role_is_kept(): void
    {
        $this->seed(\Database\Seeders\JobTitleSeeder::class);
        $names = \App\Models\JobTitle::query()->active()->where('slug', '!=', 'other')->get()
            ->flatMap(fn ($t) => [$t->name_en, $t->name_ar])->filter()->values()->all();
        $this->assertNotEmpty($names);
        $classifier = app(JobPostClassifier::class);

        foreach ($names as $name) {
            $city = $this->pick(SaudiCities::CITIES)[2][0];
            foreach (["{$name} — {$city}\n5 years", "{$name} - {$city}"] as $text) {
                $this->assertNull($classifier->rejectionReason($text, $this->noApply(), false), $text);
                $this->assertNull($classifier->rejectionReason($text, $this->noApply(), true), "with media: {$text}");
            }
        }
    }

    public function test_courses_about_employment_are_still_courses(): void
    {
        $apply = ['url' => 'https://example.sa/register', 'email' => null];
        $topics = ['مهارات التوظيف', 'كتابة السيرة الذاتية للتوظيف', 'المقابلات الوظيفية', 'الاستعداد للتوظيف', 'Job interview skills'];
        foreach ($topics as $topic) {
            foreach (['دورة تدريبية في %s', 'ورشة عمل: %s', 'دورات مجانية عن %s', 'Free course: %s'] as $shape) {
                $text = sprintf($shape, $topic)."\nالتسجيل عبر الرابط";
                $this->assertSame(JobPostClassifier::REJECT_NOT_A_JOB, $this->classifier->rejectionReason($text, $apply, false), $text);
            }
        }
    }

    public function test_links_and_emails_do_not_decide_the_language(): void
    {
        for ($i = 0; $i < 100; $i++) {
            $noise = 'https://careers.example.com/jobs/'.bin2hex(random_bytes(mt_rand(20, 120))).'?utm_source=telegram&utm_campaign=x';
            $email = 'Recruitment.Department.'.mt_rand(1, 999).'@very-long-company-domain.example.com';
            $ar = 'مطلوب '.$this->pick(self::ROLES)." في الرياض\n{$noise}\n{$email}";
            $this->assertSame('ar', $this->classifier->languageOf($ar), $ar);

            $en = 'We are hiring a '.$this->pick(['Accountant', 'HSE Officer', 'Engineer'])." in Riyadh\nللتقديم {$noise}";
            $this->assertSame('en', $this->classifier->languageOf($en), $en);
        }
    }

    public function test_the_workplace_beats_a_head_office_or_branch_mentioned_first(): void
    {
        $codes = array_keys(SaudiCities::CITIES);
        $cues = ['مقر العمل: %s', 'مكان العمل %s', 'للعمل في %s', 'العمل بمدينة %s', '📍 %s', 'Work location: %s', 'Location: %s'];
        for ($i = 0; $i < 200; $i++) {
            [$hq, $work] = array_rand(array_flip($codes), 2);
            $text = 'شركة رائدة مقرها '.SaudiCities::CITIES[$hq][2][0].' تعلن عن وظيفة محاسب'."\n"
                .sprintf($this->pick($cues), SaudiCities::CITIES[$work][2][0]);
            $this->assertSame($work, $this->matcher->detectCity($text)['city'], $text);
        }
    }

    public function test_work_in_a_setting_is_not_a_place_and_a_later_city_is_not_the_workplace(): void
    {
        $settings = ['بيئة مهنية', 'فريق متميز', 'شركة رائدة', 'دوام مرن', 'قطاع التجزئة'];
        foreach (SaudiCities::CITIES as $code => [, , $names]) {
            $text = 'مطلوب محاسب للعمل في '.$this->pick($settings).'، مقر الشركة الأم في '.$names[0].' والمقابلات عن بعد';
            $this->assertSame($code, $this->matcher->detectCity($text)['city'], 'only mention, so it is the fallback: '.$text);

            [$other] = array_values(array_diff(array_keys(SaudiCities::CITIES), [$code]));
            $withWork = $text."\n📍 مقر العمل: ".SaudiCities::CITIES[$other][2][0];
            $this->assertSame($other, $this->matcher->detectCity($withWork)['city'], $withWork);
        }
    }

    public function test_repair_clears_a_role_the_rules_no_longer_find(): void
    {
        $this->seed(\Database\Seeders\JobTitleSeeder::class);
        $ids = \App\Models\JobTitle::query()->pluck('id')->all();
        $bodies = ["إعلان عن لقاء مفتوح\nللتسجيل: jobs@corp.example.sa", "مطلوب محاسب في الرياض\nللتقديم: cv@corp.example.sa", "فرصة عمل مميزة\nالتفاصيل: jobs@x.sa"];
        foreach ($bodies as $i => $body) {
            $title = strtok($body, "\n");
            $found = $this->matcher->match($title, $body, $this->matcher->locationLabel($body))['job_title_id'];
            $stale = $this->pick(array_values(array_diff($ids, [$found])));
            $job = JobNews::create([
                'language' => 'ar', 'title' => $title, 'body' => $body, 'external_source' => 'telegram_jobmag',
                'external_id' => "r{$i}", 'job_title_id' => $stale, 'is_published' => true, 'published_at' => now(),
            ]);

            $this->artisan('jobs:repair-listings')->assertSuccessful();

            $this->assertSame($found, $job->fresh()->job_title_id, 'the stored role is what the rules find now: '.$body);
        }
    }

    public function test_a_refetch_that_now_fails_the_rules_unpublishes_the_stored_post(): void
    {
        $post = $where = '';
        $page = fn (string $text): string => '<html><body><div class="tgme_widget_message_wrap"><div class="tgme_widget_message" data-post="chan/5">'
            .'<div class="tgme_widget_message_text js-message_text">'.$text.'</div><time datetime="2026-10-28T12:00:00+03:00"></time></div></div></body></html>';
        $remotive = fn (string $where): array => ['jobs' => [['id' => 7, 'title' => 'Writer', 'company_name' => 'A', 'url' => 'https://remotive.com/job/7', 'description' => 'x', 'candidate_required_location' => $where]]];
        $svc = app(SaudiJobAggregatorService::class);
        $telegram = ['source' => 'telegram_chan', 'name' => '@chan', 'channel' => 'chan', 'type' => 'telegram'];
        $remote = ['source' => 'remotive_remote', 'name' => 'Remotive', 'url' => 'https://remotive.com/api/remote-jobs', 'type' => 'remotive_api'];

        // One closure fake: Http::fake stubs stack and the first one would keep answering.
        $post = 'مطلوب محاسب في جدة<br/>للتقديم: cv@corp.example.sa';
        $where = 'Worldwide';
        Http::fake(function ($request) use (&$post, &$where, $page, $remotive) {
            return str_contains($request->url(), 't.me')
                ? Http::response($page($post))
                : Http::response($remotive($where));
        });
        $svc->aggregateFeed($telegram);
        $svc->aggregateFeed($remote);
        $this->assertSame(2, JobNews::query()->where('is_published', true)->count());

        $post = 'دورة تدريبية عبر (Zoom) بشهادة حضور<br/>التسجيل: cv@corp.example.sa';
        $where = 'Worldwide except Saudi Arabia';
        $result = $svc->aggregateFeed($telegram);
        $svc->aggregateFeed($remote);

        $this->assertSame(0, JobNews::query()->where('is_published', true)->count());
        $this->assertSame([JobPostClassifier::REJECT_NOT_A_JOB => 1], $result['rejected']);
    }

    public function test_feed_health_tracks_yield_apart_from_fetch_failures(): void
    {
        $svc = app(SaudiJobAggregatorService::class);
        $health = fn (): array => collect($svc->getFeedsHealth())->keyBy('source')['telegram_jobmag'];

        // A streak of all-rejected runs is unhealthy only once it lasts about a day.
        for ($run = 1; $run <= SaudiJobAggregatorService::ZERO_ACCEPT_RUNS; $run++) {
            $svc->recordFeedRun('telegram_jobmag', 20, [JobPostClassifier::REJECT_NOT_A_JOB => 20], 0);
            $this->assertSame($run < SaudiJobAggregatorService::ZERO_ACCEPT_RUNS, $health()['healthy'], "run {$run}");
            $this->assertSame(0, $health()['last_accepted_count']);
            $this->assertSame(0, $health()['consecutive_zero_fetches'], 'a successful fetch is not a zero fetch');
            $this->assertStringContainsString('accepted none', (string) $health()['last_issue']);
        }

        // One run that accepts posts with apply destinations ends the streak.
        $svc->recordFeedRun('telegram_jobmag', 20, [JobPostClassifier::REJECT_EMPTY => 5], 9);
        $this->assertTrue($health()['healthy']);
        $this->assertSame(15, $health()['last_accepted_count']);
        $this->assertSame(9, $health()['last_with_apply_count']);

        // Accepted posts with no apply link or email at all: extraction broke.
        $accepted = SaudiJobAggregatorService::APPLY_CHECK_MIN_ACCEPTED + mt_rand(0, 15);
        $svc->recordFeedRun('telegram_jobmag', $accepted, [], 0);
        $this->assertFalse($health()['healthy']);
        $this->assertStringContainsString('apply link', (string) $health()['last_issue']);

        // Too few accepted posts to judge is not an alarm.
        $svc->recordFeedRun('telegram_jobmag', SaudiJobAggregatorService::APPLY_CHECK_MIN_ACCEPTED - 1, [], 0);
        $this->assertTrue($health()['healthy']);

        $this->travel(SaudiJobAggregatorService::STALE_AFTER_HOURS + 1)->hours();
        $health = collect($svc->getFeedsHealth())->keyBy('source')['telegram_jobmag'];
        $this->assertFalse($health['healthy'], 'no success for too long');
        $this->assertTrue($health['stale']);
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
            // The same place with qualifiers around it is still open.
            foreach (['%s only', 'Remote – %s', 'Remote - %s', '%s (remote)', 'USA, %s', 'Middle East (%s)', '%s residents', 'Fully remote / %s'] as $shape) {
                $qualified = sprintf($shape, $open);
                $this->assertTrue($this->classifier->remoteOpenToSaudi($qualified), $qualified);
            }
        }
        foreach (['', '  ', 'USA', 'USA, Canada, USA timezones', 'Europe, USA, Canada, APAC', 'UK', 'Germany', 'Latin America', 'Americas',
            'Worldwide except Saudi Arabia', 'Anywhere in Europe', 'Anywhere in the US', 'Global (excluding MENA)', 'EMEA, not KSA',
            'Worldwide without Middle East', 'Gulf of Mexico states', 'Outside the GCC', 'Remote – USA only', 'Europe (EU residents only)'] as $closed) {
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
