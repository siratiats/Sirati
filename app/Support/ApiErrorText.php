<?php

namespace App\Support;

use App\Services\I18n\ServerText;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Framework error messages ("Unauthenticated.", "No query results for model …",
 * "Too Many Attempts.", "Server Error") reach the app as catalog labels in the
 * caller's language (Abobker85/Sirati#12). Messages the API wrote itself
 * already come from the catalog and pass through unchanged.
 */
final class ApiErrorText
{
    private const FRAMEWORK_MESSAGES = [
        '',
        'Unauthenticated.',
        'Unauthorized.',
        'Forbidden',
        'This action is unauthorized.',
        'Not Found',
        'Too Many Attempts.',
        'Too Many Requests',
        'Method Not Allowed',
        'Server Error',
        'Internal Server Error',
        'Service Unavailable',
    ];

    public static function localize(Response $response, \Throwable $exception, Request $request): Response
    {
        if (! $request->is('api/*') || ! $response instanceof JsonResponse) {
            return $response;
        }

        $data = $response->getData(true);
        if (! is_array($data)) {
            return $response;
        }

        $text = app(ServerText::class);

        if ($exception instanceof ValidationException) {
            $messages = collect($exception->errors())->flatten()->values();
            $first = (string) ($messages->first() ?? '');
            $data['message'] = $messages->count() > 1
                ? $text->get('serverValidationMoreErrors', ['message' => $first, 'count' => $messages->count() - 1])
                : $first;
            $response->setData($data);

            return $response;
        }

        $message = (string) ($data['message'] ?? '');
        if (! self::isFrameworkMessage($message)) {
            return $response;
        }

        $key = match (true) {
            $response->getStatusCode() === 401 => 'serverErrorUnauthenticated',
            $response->getStatusCode() === 403 => 'serverErrorForbidden',
            $response->getStatusCode() === 404 => 'serverErrorNotFound',
            $response->getStatusCode() === 405 => 'serverErrorMethodNotAllowed',
            $response->getStatusCode() === 429 => 'serverErrorTooManyRequests',
            $response->getStatusCode() === 503 => 'serverErrorUnavailable',
            $response->getStatusCode() >= 500 => 'serverErrorServer',
            default => 'serverErrorRequestFailed',
        };

        // Debug output (exception, trace) stays for developers; only the text changes.
        $data['message'] = $text->get($key);
        $response->setData($data);

        return $response;
    }

    private static function isFrameworkMessage(string $message): bool
    {
        return in_array($message, self::FRAMEWORK_MESSAGES, true)
            || str_starts_with($message, 'No query results for model')
            || (str_starts_with($message, 'The route ') && str_ends_with($message, 'could not be found.'));
    }
}
