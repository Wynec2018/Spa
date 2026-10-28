<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ApiException;
use App\Repositories\AuthRepository;

final class AuthService
{
    private const DUMMY_HASH = '$argon2id$v=19$m=65536,t=4,p=1$MzEyZEp2WFdkNUo2MFZZVA$S72KpQ9O5KayapO18qJLd2kpfudiU+YIeXo6YDv2hBM';

    public function __construct(private readonly AuthRepository $users) {}

    public function attempt(
        string $contacto,
        string $password,
        string $branchId,
        string $requestId,
    ): object {
        $user = $this->users->findActiveBranchUser($contacto, $branchId, $requestId);
        $hash = $user === null ? self::DUMMY_HASH : (string) $user->hash_password;
        $valid = password_verify($password, $hash);

        if ($user === null || ! $valid) {
            throw ApiException::invalidCredentials();
        }

        if ($user->estado !== 'activo') {
            throw ApiException::userInactive();
        }

        if (password_needs_rehash($hash, PASSWORD_ARGON2ID)) {
            $newHash = password_hash($password, PASSWORD_ARGON2ID);

            $this->users->updatePasswordHash($user->id, $newHash, $branchId, $requestId);
            $user->hash_password = $newHash;
        }

        unset($user->hash_password);

        return $user;
    }

    public function current(
        string $userId,
        string $branchId,
        string $requestId,
    ): ?object {
        return $this->users->findActiveBranchUserById(
            $userId,
            $branchId,
            $requestId,
            $userId,
        );
    }
}
