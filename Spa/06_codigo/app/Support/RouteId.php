<?php

declare(strict_types=1);

namespace App\Support;

use App\Exceptions\ApiException;

final class RouteId
{
    private const UUID_V4 = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';

    public static function uuidV4(string $id): string
    {
        if (preg_match(self::UUID_V4, $id) !== 1) {
            throw ApiException::invalidId();
        }

        return $id;
    }
}
