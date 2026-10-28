<?php

declare(strict_types=1);

namespace App\Validation;

use App\Exceptions\ApiException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;
use Throwable;

final class ReservationQueryValidator
{
    private const ALLOWED_QUERY = ['desde', 'hasta', 'estado', 'page', 'per_page'];

    private const ESTADOS = ['pendiente', 'confirmada', 'cancelada', 'expirada', 'atendida'];

    private const DATE_PATTERN = '/^\d{4}-\d{2}-\d{2}(?:[T ]\d{2}:\d{2}(?::\d{2}(?:\.\d{1,6})?)?(?:Z|[+-]\d{2}:\d{2})?)?$/';

    public static function query(array $query): array
    {
        $unknown = array_diff(array_keys($query), self::ALLOWED_QUERY);

        if ($unknown !== []) {
            throw ApiException::invalidQuery(array_fill_keys($unknown, ['Parámetro no permitido.']));
        }

        $validator = Validator::make($query, [
            'desde' => ['sometimes', 'string', 'max:40'],
            'hasta' => ['sometimes', 'string', 'max:40'],
            'estado' => ['sometimes', 'string', 'in:'.implode(',', self::ESTADOS)],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ], [
            'desde.string' => 'La fecha inicial debe ser texto ISO 8601.',
            'desde.max' => 'La fecha inicial no debe superar 40 caracteres.',
            'hasta.string' => 'La fecha final debe ser texto ISO 8601.',
            'hasta.max' => 'La fecha final no debe superar 40 caracteres.',
            'estado.in' => 'El estado no es válido.',
            'page.integer' => 'La página debe ser un entero.',
            'page.min' => 'La página debe ser al menos 1.',
            'per_page.integer' => 'El tamaño de página debe ser un entero.',
            'per_page.min' => 'El tamaño de página debe ser al menos 1.',
            'per_page.max' => 'El tamaño de página no debe superar 100.',
        ]);

        if ($validator->fails()) {
            throw ApiException::invalidQuery($validator->errors()->toArray());
        }

        $data = $validator->validated();
        $desde = self::date($data['desde'] ?? null, 'desde');
        $hasta = self::date($data['hasta'] ?? null, 'hasta');

        if ($desde !== null && $hasta !== null && $desde->greaterThanOrEqualTo($hasta)) {
            throw ApiException::invalidQuery([
                'hasta' => ['Debe ser posterior a la fecha inicial.'],
            ]);
        }

        return [
            'desde' => $desde?->format('Y-m-d H:i:sP'),
            'hasta' => $hasta?->format('Y-m-d H:i:sP'),
            'estado' => $data['estado'] ?? null,
            'page' => (int) ($data['page'] ?? 1),
            'per_page' => (int) ($data['per_page'] ?? 20),
        ];
    }

    private static function date(mixed $value, string $field): ?CarbonImmutable
    {
        if ($value === null) {
            return null;
        }

        if (! is_string($value) || preg_match(self::DATE_PATTERN, trim($value)) !== 1) {
            throw ApiException::invalidQuery([
                $field => ['Debe ser una fecha ISO 8601, por ejemplo 2026-10-20T14:00:00Z.'],
            ]);
        }

        try {
            return CarbonImmutable::parse(trim($value), 'UTC')->utc();
        } catch (Throwable) {
            throw ApiException::invalidQuery([
                $field => ['Debe ser una fecha ISO 8601 válida.'],
            ]);
        }
    }
}
