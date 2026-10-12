<?php
declare(strict_types=1);

namespace App\Validators;

use App\Models\Usuario;
use RuntimeException;

final class AuthValidator
{
    /**
     * @param array|null $body cuerpo JSON ya decodificado (null = inválido)
     * @return array{email: string, password: string}
     */
    public function validateLoginBody(?array $body): array
    {
        if ($body === null) {
            throw new RuntimeException('Cuerpo JSON inválido', 400);
        }
        $email = $body['email'] ?? null;
        if (!is_string($email) || filter_var($email, FILTER_VALIDATE_EMAIL) === false || mb_strlen($email, 'UTF-8') > Usuario::MAX_EMAIL) {
            throw new RuntimeException('email: obligatorio, string, válido, máximo ' . Usuario::MAX_EMAIL . ' caracteres', 400);
        }
        $password = $body['password'] ?? null;
        if (!is_string($password) || $password === '') {
            throw new RuntimeException('password: obligatorio, string no vacío', 400);
        }
        return ['email' => $email, 'password' => $password];
    }
}
