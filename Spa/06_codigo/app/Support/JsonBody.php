<?php

declare(strict_types=1);

namespace App\Support;

use App\Exceptions\ApiException;
use Illuminate\Http\Request;

final class JsonBody
{
    public static function parse(Request $request): array
    {
        $contentType = strtolower(trim(explode(';', (string) $request->header('Content-Type'))[0]));

        if ($contentType !== 'application/json') {
            throw ApiException::unsupportedMedia();
        }

        $decoded = json_decode($request->getContent());

        if (json_last_error() !== JSON_ERROR_NONE || ! is_object($decoded)) {
            throw ApiException::invalidBody([], 'El cuerpo de la petición debe ser un objeto JSON válido.');
        }

        return (array) $decoded;
    }
}
