<?php

declare(strict_types=1);

namespace App\Validation;

use App\Exceptions\ApiException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;
use Throwable;

final class ReservationValidator
{
    private const ALLOWED_FIELDS = ['client_id', 'service_id', 'cama_id', 'trabajadora_id', 'inicio'];

    private const DATE_PATTERN = '/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}(?::\d{2}(?:\.\d{1,6})?)?(?:Z|[+-]\d{2}:\d{2})$/';

    public static function payload(array $payload): array
    {
        $unknown = array_diff(array_keys($payload), self::ALLOWED_FIELDS);

        if ($unknown !== []) {
            throw ApiException::invalidBody(array_fill_keys($unknown, ['Campo no permitido.']));
        }

        foreach (['client_id', 'service_id', 'cama_id', 'trabajadora_id', 'inicio'] as $field) {
            if (isset($payload[$field]) && is_string($payload[$field])) {
                $payload[$field] = trim($payload[$field]);
            }
        }

        $validator = Validator::make($payload, [
            'client_id' => ['required', 'string', 'uuid'],
            'service_id' => ['required', 'string', 'uuid'],
            'cama_id' => ['required', 'string', 'uuid'],
            'trabajadora_id' => ['required', 'string', 'uuid'],
            'inicio' => ['required', 'string', 'max:40'],
        ], [
            'client_id.required' => 'El cliente es obligatorio.',
            'client_id.uuid' => 'El cliente debe ser un UUID válido.',
            'service_id.required' => 'El servicio es obligatorio.',
            'service_id.uuid' => 'El servicio debe ser un UUID válido.',
            'cama_id.required' => 'La cama es obligatoria.',
            'cama_id.uuid' => 'La cama debe ser un UUID válido.',
            'trabajadora_id.required' => 'La trabajadora es obligatoria.',
            'trabajadora_id.uuid' => 'La trabajadora debe ser un UUID válido.',
            'inicio.required' => 'La fecha y hora de inicio son obligatorias.',
            'inicio.string' => 'La fecha y hora de inicio deben ser texto ISO 8601.',
            'inicio.max' => 'La fecha y hora de inicio no deben superar 40 caracteres.',
        ]);

        if ($validator->fails()) {
            throw ApiException::validation($validator->errors()->toArray());
        }

        $data = $validator->validated();
        $inicio = self::date($data['inicio']);

        return [
            'client_id' => $data['client_id'],
            'service_id' => $data['service_id'],
            'cama_id' => $data['cama_id'],
            'trabajadora_id' => $data['trabajadora_id'],
            'inicio' => $inicio->format('Y-m-d H:i:sP'),
        ];
    }

    private static function date(string $value): CarbonImmutable
    {
        if (preg_match(self::DATE_PATTERN, $value) !== 1) {
            throw ApiException::validation([
                'inicio' => ['Debe ser una fecha ISO 8601 con zona horaria, por ejemplo 2026-10-20T14:00:00Z.'],
            ]);
        }

        try {
            return CarbonImmutable::parse($value, 'UTC')->utc();
        } catch (Throwable) {
            throw ApiException::validation([
                'inicio' => ['Debe ser una fecha ISO 8601 válida.'],
            ]);
        }
    }
}
