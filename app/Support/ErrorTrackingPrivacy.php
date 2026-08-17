<?php

namespace App\Support;

use Sentry\Event;

class ErrorTrackingPrivacy
{
    private const FILTERED = '[Filtered]';

    public static function scrubEvent(Event $event): Event
    {
        $fields = array_fill_keys(
            array_map('strtolower', config('error_tracking.scrub_fields', [])),
            true,
        );

        $protectedAiOrCvEvent = self::isProtectedAiOrCvEvent($event);
        $event->setRequest(
            $protectedAiOrCvEvent
                ? []
                : self::scrub($event->getRequest(), $fields),
        );
        $event->setExtra(self::scrub($event->getExtra(), $fields));

        foreach ($event->getContexts() as $name => $context) {
            $event->setContext($name, self::scrub($context, $fields));
        }

        if ($protectedAiOrCvEvent) {
            foreach ($event->getExceptions() as $exception) {
                $exception->setValue('AI/CV operation failed; payload omitted.');
            }

            if ($event->getMessage() !== null) {
                $event->setMessage('AI/CV operation failed; payload omitted.');
            }

            $event->setBreadcrumb([]);
        }

        return $event;
    }

    private static function isProtectedAiOrCvEvent(Event $event): bool
    {
        $url = (string) ($event->getRequest()['url'] ?? '');

        foreach (config('error_tracking.protected_path_fragments', []) as $path) {
            if ($path !== '' && str_contains($url, $path)) {
                return true;
            }
        }

        $protectedFiles = [
            'OpenAiCvService.php',
            'ClaudeCvService.php',
            'GenerateCvAdviceJob.php',
            'GenerateCvContentJob.php',
        ];

        foreach ($event->getExceptions() as $exception) {
            foreach ($exception->getStacktrace()?->getFrames() ?? [] as $frame) {
                foreach ($protectedFiles as $file) {
                    if (str_ends_with($frame->getFile(), $file)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * @param  array<string|int, mixed>  $value
     * @param  array<string, bool>  $fields
     * @return array<string|int, mixed>
     */
    private static function scrub(array $value, array $fields): array
    {
        foreach ($value as $key => $item) {
            if (is_string($key) && isset($fields[strtolower($key)])) {
                $value[$key] = self::FILTERED;

                continue;
            }

            if (is_array($item)) {
                $value[$key] = self::scrub($item, $fields);
            }
        }

        return $value;
    }
}
