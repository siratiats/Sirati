<?php

namespace App\Http\Middleware;

use App\Services\I18n\ServerText;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Server text follows the app language (Abobker85/Sirati#12). The app sends it
 * as X-App-Language on every call; older callers only send ?lang=, which
 * otherwise picks content rows. Accept-Language is ignored: HTTP stacks fill it
 * from the device, not the language chosen in the app. A language that is not
 * enabled falls back to the default one.
 */
class SetRequestLanguage
{
    public const HEADER = 'X-App-Language';

    public function __construct(private readonly ServerText $text)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $chosen = null;
        foreach ([ServerText::normalize($request->header(self::HEADER)), ServerText::normalize($request->query('lang'))] as $code) {
            if ($code !== null && $this->text->chain($code)[0] === $code) {
                $chosen = $code;
                break;
            }
        }

        $this->text->setLanguage($chosen);
        // Validation messages and Carbon read the app locale.
        app()->setLocale($this->text->language());

        return $next($request);
    }
}
