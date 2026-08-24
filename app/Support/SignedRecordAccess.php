<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class SignedRecordAccess
{
    public static function authorize(Request $request, Model $record, string $ownerColumn = 'user_id'): void
    {
        if ($request->hasValidSignatureWhileIgnoring(['template']) ||
            $request->hasValidSignatureWhileIgnoring(['template'], false) ||
            $request->hasValidSignature(false) ||
            $request->hasValidSignature()) {
            return;
        }

        $ownerId = $record->{$ownerColumn} ?? null;
        $userId = $request->user()?->id;
        if ($userId !== null && $ownerId !== null && (int) $userId === (int) $ownerId) {
            return;
        }

        abort(403);
    }

    public static function temporaryUrl(string $routeName, array $parameters, int $days = 7): string
    {
        return URL::temporarySignedRoute($routeName, now()->addDays($days), $parameters);
    }
}
