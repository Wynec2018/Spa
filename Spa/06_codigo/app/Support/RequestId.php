<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class RequestId
{
    public static function from(Request $request): string
    {
        $header = (string) config('spa.request_id_header', 'X-Request-Id');
        $value = $request->header($header);

        if (is_array($value)) {
            $value = $value[0] ?? null;
        }

        return is_string($value) && trim($value) !== '' ? trim($value) : (string) Str::uuid();
    }
}
