<?php

declare(strict_types=1);

namespace App\Support;

use App\Exceptions\ApiException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class SessionAuth
{
    public static function credentials(Request $request): array
    {
        $userId = self::uuid($request->session()->get('auth_user_id'));
        $branchId = self::uuid($request->session()->get('auth_branch_id'));

        if ($userId === null || $branchId === null) {
            throw ApiException::unauthenticated();
        }

        return [$userId, $branchId];
    }

    public static function forget(Request $request): void
    {
        $request->session()->forget(['auth_user_id', 'auth_branch_id', 'auth_rol']);
    }

    private static function uuid(mixed $value): ?string
    {
        return is_string($value) && Str::isUuid($value) ? $value : null;
    }
}
