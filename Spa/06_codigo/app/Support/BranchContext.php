<?php

declare(strict_types=1);

namespace App\Support;

use App\Exceptions\ApiException;

final class BranchContext
{
    public static function id(): string
    {
        $branchId = (string) config('spa.branch_id');

        if ($branchId === '') {
            throw ApiException::internal();
        }

        return $branchId;
    }
}
