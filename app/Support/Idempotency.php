<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

class Idempotency
{
    public static function key(Request $request): ?string
    {
        $key = trim((string) $request->header('Idempotency-Key', ''));
        if ($key === '' || strlen($key) > 128) {
            return null;
        }

        return $key;
    }

    public static function find(Request $request, Builder $query): ?Model
    {
        $key = self::key($request);
        $userId = $request->user()?->id;
        if ($key === null || $userId === null) {
            return null;
        }

        return $query->where('user_id', $userId)
            ->where('idempotency_key', $key)
            ->first();
    }

    /**
     * @template T of Model
     *
     * @param  callable(): T  $create
     * @return T
     */
    public static function firstOrCreate(Request $request, Builder $query, callable $create): Model
    {
        $existing = self::find($request, $query);
        if ($existing !== null) {
            return $existing;
        }

        try {
            return $create();
        } catch (QueryException $exception) {
            $replay = self::find($request, $query);
            if ($replay !== null) {
                return $replay;
            }

            throw $exception;
        }
    }
}
