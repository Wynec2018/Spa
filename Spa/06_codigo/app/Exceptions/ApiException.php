<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class ApiException extends RuntimeException
{
    public function __construct(
        public readonly int $status,
        public readonly string $errorCode,
        string $message,
        public readonly ?array $details = null,
        public readonly ?array $data = null,
    ) {
        parent::__construct($message);
    }

    public static function invalidQuery(array $details): self
    {
        return new self(400, 'INVALID_REQUEST', 'Los parámetros de consulta no son válidos.', $details);
    }

    public static function invalidBody(array $details = [], string $message = 'El cuerpo de la petición no es válido.'): self
    {
        return new self(400, 'INVALID_REQUEST', $message, $details);
    }

    public static function validation(array $details): self
    {
        return new self(422, 'VALIDATION_ERROR', 'Los datos enviados no son válidos.', $details);
    }

    public static function invalidId(): self
    {
        return new self(400, 'INVALID_ID', 'El identificador no es un UUID válido.');
    }

    public static function serviceNotFound(): self
    {
        return new self(404, 'SERVICE_NOT_FOUND', 'El servicio no existe en la sucursal.');
    }

    public static function reservationNotFound(): self
    {
        return new self(404, 'RESERVATION_NOT_FOUND', 'La reserva no existe en la sucursal.');
    }

    public static function clientNotFound(): self
    {
        return new self(404, 'CLIENT_NOT_FOUND', 'El cliente no existe en la sucursal.');
    }

    public static function resourceNotFound(): self
    {
        return new self(404, 'RESOURCE_NOT_FOUND', 'La cama o la trabajadora no existe en la sucursal.');
    }

    public static function serviceInactive(): self
    {
        return new self(409, 'SERVICE_INACTIVE', 'El servicio no está activo.');
    }

    public static function resourceUnavailable(): self
    {
        return new self(409, 'RESOURCE_UNAVAILABLE', 'La cama o la trabajadora no está disponible.');
    }

    public static function reservationConflict(): self
    {
        return new self(409, 'RESERVATION_CONFLICT', 'La cama o la trabajadora ya tiene una reserva en esa franja.');
    }

    public static function invalidStateTransition(): self
    {
        return new self(409, 'INVALID_STATE_TRANSITION', 'La reserva no puede pasar a ese estado.');
    }

    public static function duplicateService(): self
    {
        return new self(409, 'DUPLICATE_SERVICE', 'Ya existe un servicio con ese nombre en la sucursal.');
    }

    public static function unsupportedMedia(): self
    {
        return new self(415, 'UNSUPPORTED_MEDIA_TYPE', 'El Content-Type debe ser application/json.');
    }

    public static function invalidCredentials(): self
    {
        return new self(401, 'INVALID_CREDENTIALS', 'Contacto, contraseña o sucursal incorrectos.');
    }

    public static function unauthenticated(): self
    {
        return new self(401, 'UNAUTHENTICATED', 'La sesión no está autenticada.');
    }

    public static function userInactive(): self
    {
        return new self(403, 'USER_INACTIVE', 'El usuario está inactivo.');
    }

    public static function internal(): self
    {
        return new self(500, 'INTERNAL_ERROR', 'Ocurrió un error interno.');
    }

    public static function serviceUnavailable(array $data): self
    {
        return new self(503, 'SERVICE_UNAVAILABLE', 'No se pudo verificar la base de datos.', null, $data);
    }
}
