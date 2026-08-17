<?php

namespace Tests\Unit;

use App\Support\ErrorTrackingPrivacy;
use RuntimeException;
use Sentry\Breadcrumb;
use Sentry\Event;
use Sentry\ExceptionDataBag;
use Tests\TestCase;

class ErrorTrackingConfigTest extends TestCase
{
    public function test_candidate_fields_are_excluded_from_error_tracking(): void
    {
        $expected = [
            'resume_text',
            'experience_input',
            'education_input',
            'summary_input',
            'skills_input',
            'certifications_input',
            'draft',
            'full_name',
            'email',
            'phone',
            'linkedin',
            'location',
        ];

        $this->assertFalse(config('sentry.send_default_pii'));
        $this->assertSame('none', config('sentry.max_request_body_size'));

        foreach ($expected as $field) {
            $this->assertContains($field, config('error_tracking.scrub_fields'));
        }
    }

    public function test_protected_cv_events_drop_request_and_exception_content(): void
    {
        $event = Event::createEvent()
            ->setRequest([
                'url' => 'https://siratie.com/api/generated-cvs',
                'data' => ['resume_text' => 'Candidate CV text'],
            ])
            ->setExceptions([
                new ExceptionDataBag(new RuntimeException('AI response body')),
            ])
            ->setBreadcrumb([
                new Breadcrumb(
                    Breadcrumb::LEVEL_INFO,
                    Breadcrumb::TYPE_DEFAULT,
                    'test',
                    metadata: ['full_name' => 'Candidate Name'],
                ),
            ]);

        $sanitized = ErrorTrackingPrivacy::scrubEvent($event);

        $this->assertSame([], $sanitized->getRequest());
        $this->assertSame([], $sanitized->getBreadcrumbs());
        $this->assertSame(
            'AI/CV operation failed; payload omitted.',
            $sanitized->getExceptions()[0]->getValue(),
        );
    }
}
