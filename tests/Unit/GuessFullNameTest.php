<?php

namespace Tests\Unit;

use App\Http\Controllers\GeneratedCvController;
use ReflectionMethod;
use Tests\TestCase;

class GuessFullNameTest extends TestCase
{
    private function guess(string $text, string $account = ''): string
    {
        $method = new ReflectionMethod(GeneratedCvController::class, 'guessFullName');

        return $method->invoke(app(GeneratedCvController::class), $text, $account);
    }

    public function test_markdown_section_headings_are_never_taken_as_the_name(): void
    {
        foreach (['## Professional Summary', '### الخبرة المهنية', "\n\n## Skills"] as $heading) {
            $this->assertSame('Account Name', $this->guess($heading."\n\nBody text here.", 'Account Name'));
        }
    }

    public function test_an_h1_or_plain_first_line_is_the_name(): void
    {
        $this->assertSame('Sara Test', $this->guess("# Sara Test\n\n## Summary\nText", 'Account Name'));
        $this->assertSame('سارة أحمد', $this->guess("سارة أحمد\nمطورة", 'Account Name'));
        $this->assertSame('Sara Ahmed سارة', $this->guess("\n  Sara Ahmed سارة  \nBody"));
    }

    public function test_it_falls_back_to_a_neutral_placeholder_without_any_account_name(): void
    {
        $this->assertSame('صاحب السيرة', $this->guess("## Summary\nText"));
        $this->assertSame('صاحب السيرة', $this->guess(''));
    }
}
