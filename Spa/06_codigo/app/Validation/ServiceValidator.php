<?php

declare(strict_types=1);

namespace App\Validation;

use App\Exceptions\ApiException;
use Illuminate\Support\Facades\Validator;

final class ServiceValidator
{
    private const ALLOWED_QUERY = ['estado', 'q', 'page', 'per_page', 'sort', 'order'];

    private const ALLOWED_FIELDS = ['nombre', 'duracion_min', 'precio'];

    private const SORTS = ['nombre', 'precio', 'duracion_min', 'created_at'];

    private const ORDERS = ['asc', 'desc'];

    public static function query(array $query): array
    {
        $unknown = array_diff(array_keys($query), self::ALLOWED_QUERY);

        if ($unknown !== []) {
            throw ApiException::invalidQuery(array_fill_keys($unknown, ['Parámetro no permitido.']));
        }

        $validator = Validator::make($query, [
            'estado' => ['sometimes', 'string', 'in:activo,inactivo'],
            'q' => ['sometimes', 'string', 'min:1', 'max:120'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'sort' => ['sometimes', 'string', 'in:'.implode(',', self::SORTS)],
            'order' => ['sometimes', 'string', 'in:'.implode(',', self::ORDERS)],
        ], [
            'estado.in' => 'El estado debe ser activo o inactivo.',
            'q.string' => 'La búsqueda debe ser texto.',
            'q.min' => 'La búsqueda debe tener al menos 1 carácter.',
            'q.max' => 'La búsqueda no debe superar 120 caracteres.',
            'page.integer' => 'La página debe ser un entero.',
            'page.min' => 'La página debe ser al menos 1.',
            'per_page.integer' => 'El tamaño de página debe ser un entero.',
            'per_page.min' => 'El tamaño de página debe ser al menos 1.',
            'per_page.max' => 'El tamaño de página no debe superar 100.',
            'sort.in' => 'El ordenamiento no es válido.',
            'order.in' => 'La dirección debe ser asc o desc.',
        ]);

        if ($validator->fails()) {
            throw ApiException::invalidQuery($validator->errors()->toArray());
        }

        $data = $validator->validated();
        $q = array_key_exists('q', $data) ? trim((string) $data['q']) : null;

        if ($q === '') {
            throw ApiException::invalidQuery(['q' => ['No puede estar vacío.']]);
        }

        return [
            'estado' => $data['estado'] ?? null,
            'q' => $q,
            'page' => (int) ($data['page'] ?? 1),
            'per_page' => (int) ($data['per_page'] ?? 20),
            'sort' => $data['sort'] ?? 'nombre',
            'order' => $data['order'] ?? 'asc',
        ];
    }

    public static function payload(array $payload): array
    {
        $unknown = array_diff(array_keys($payload), self::ALLOWED_FIELDS);

        if ($unknown !== []) {
            throw ApiException::invalidBody(array_fill_keys($unknown, ['Campo no permitido.']));
        }

        if (isset($payload['nombre']) && is_string($payload['nombre'])) {
            $payload['nombre'] = trim($payload['nombre']);
        }

        if (isset($payload['precio']) && is_string($payload['precio'])) {
            $payload['precio'] = trim($payload['precio']);
        }

        $validator = Validator::make($payload, [
            'nombre' => ['required', 'string', 'min:1', 'max:120'],
            'duracion_min' => ['required', 'integer', 'min:1', 'max:1440'],
            'precio' => ['required'],
        ], [
            'nombre.required' => 'El nombre es obligatorio.',
            'nombre.string' => 'El nombre debe ser texto.',
            'nombre.min' => 'El nombre no puede estar vacío.',
            'nombre.max' => 'El nombre no debe superar 120 caracteres.',
            'duracion_min.required' => 'La duración es obligatoria.',
            'duracion_min.integer' => 'La duración debe ser un entero.',
            'duracion_min.min' => 'La duración debe ser al menos 1 minuto.',
            'duracion_min.max' => 'La duración no debe superar 1440 minutos.',
            'precio.required' => 'El precio es obligatorio.',
        ]);

        $failed = $validator->fails();
        $precio = null;
        $priceError = null;

        if (! $failed) {
            $precio = self::normalizePrice($payload['precio'] ?? null);

            if ($precio === null) {
                $priceError = 'El precio debe ser un número mayor o igual a 0, con máximo 2 decimales.';
            }
        }

        if ($failed || $priceError !== null) {
            $details = $validator->errors()->toArray();

            if ($priceError !== null) {
                $details['precio'][] = $priceError;
            }

            throw ApiException::validation($details);
        }

        return [
            'nombre' => $payload['nombre'],
            'duracion_min' => (int) $payload['duracion_min'],
            'precio' => $precio,
        ];
    }

    private static function normalizePrice(mixed $value): ?string
    {
        if (is_int($value)) {
            $raw = (string) $value;
        } elseif (is_float($value)) {
            if (! is_finite($value)) {
                return null;
            }

            $raw = (string) $value;
        } elseif (is_string($value)) {
            $raw = trim($value);
        } else {
            return null;
        }

        if (preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', $raw, $matches) !== 1) {
            return null;
        }

        $integer = ltrim($matches[1], '0');
        $integer = $integer === '' ? '0' : $integer;
        $decimals = $matches[2] ?? '';

        if (strlen($integer) > 10) {
            return null;
        }

        $cents = ((int) $integer) * 100 + (int) str_pad($decimals, 2, '0');

        if ($cents > 999999999999) {
            return null;
        }

        return $integer.'.'.str_pad($decimals, 2, '0');
    }
}
