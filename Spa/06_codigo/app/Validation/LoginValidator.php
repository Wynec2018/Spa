<?php

declare(strict_types=1);

namespace App\Validation;

use App\Exceptions\ApiException;
use Illuminate\Support\Facades\Validator;

final class LoginValidator
{
    private const ALLOWED_FIELDS = ['contacto', 'password', 'branch_id'];

    public static function payload(array $payload): array
    {
        $unknown = array_diff(array_keys($payload), self::ALLOWED_FIELDS);

        if ($unknown !== []) {
            throw ApiException::invalidBody(array_fill_keys($unknown, ['Campo no permitido.']));
        }

        if (isset($payload['contacto']) && is_string($payload['contacto'])) {
            $payload['contacto'] = trim($payload['contacto']);
        }

        if (isset($payload['branch_id']) && is_string($payload['branch_id'])) {
            $payload['branch_id'] = trim($payload['branch_id']);
        }

        $validator = Validator::make($payload, [
            'contacto' => ['required', 'string', 'min:1', 'max:160'],
            'password' => ['required', 'string', 'max:200'],
            'branch_id' => ['required', 'uuid'],
        ], [
            'contacto.required' => 'El contacto es obligatorio.',
            'contacto.string' => 'El contacto debe ser texto.',
            'contacto.min' => 'El contacto no puede estar vacío.',
            'contacto.max' => 'El contacto no debe superar 160 caracteres.',
            'password.required' => 'La contraseña es obligatoria.',
            'password.string' => 'La contraseña debe ser texto.',
            'password.max' => 'La contraseña no debe superar 200 caracteres.',
            'branch_id.required' => 'La sucursal es obligatoria.',
            'branch_id.uuid' => 'La sucursal no es un UUID válido.',
        ]);

        if ($validator->fails()) {
            throw ApiException::validation($validator->errors()->toArray());
        }

        return [
            'contacto' => (string) $payload['contacto'],
            'password' => (string) $payload['password'],
            'branch_id' => (string) $payload['branch_id'],
        ];
    }
}
