<?php

declare(strict_types=1);

namespace App\Validation;

use App\Exceptions\ApiException;
use Illuminate\Support\Facades\Validator;

final class ReservationStateValidator
{
    private const ALLOWED_FIELDS = ['estado'];

    public static function payload(array $payload): string
    {
        $unknown = array_diff(array_keys($payload), self::ALLOWED_FIELDS);

        if ($unknown !== []) {
            throw ApiException::invalidBody(array_fill_keys($unknown, ['Campo no permitido.']));
        }

        if (isset($payload['estado']) && is_string($payload['estado'])) {
            $payload['estado'] = trim($payload['estado']);
        }

        $validator = Validator::make($payload, [
            'estado' => ['required', 'string', 'in:cancelada'],
        ], [
            'estado.required' => 'El estado es obligatorio.',
            'estado.string' => 'El estado debe ser texto.',
            'estado.in' => 'Solo se permite el estado cancelada.',
        ]);

        if ($validator->fails()) {
            throw ApiException::validation($validator->errors()->toArray());
        }

        return 'cancelada';
    }
}
