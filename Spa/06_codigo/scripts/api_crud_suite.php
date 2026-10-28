<?php

declare(strict_types=1);

$base = rtrim((string) ($argv[1] ?? getenv('API_BASE_URL') ?: 'http://127.0.0.1:8000'), '/');
$api = $base.'/api/v1';
$checks = 0;
$failures = [];

function summarize(mixed $value): string
{
    if (is_string($value)) {
        return $value;
    }

    $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    return $encoded === false ? gettype($value) : mb_substr($encoded, 0, 600);
}

function check(string $label, bool $ok, mixed $detail = null): void
{
    global $checks, $failures;

    $checks++;

    if (! $ok) {
        $failures[] = $label.($detail === null ? '' : ' | '.summarize($detail));
    }
}

function parse_headers(string $raw): array
{
    $headers = [];

    foreach (preg_split('/\r\n|\n|\r/', trim($raw)) as $line) {
        if (str_contains($line, ':')) {
            [$name, $value] = explode(':', $line, 2);
            $headers[strtolower(trim($name))] = trim($value);
        }
    }

    return $headers;
}

function api(string $method, string $url, array|string|null $body = null, ?string $contentType = 'application/json'): array
{
    $headers = ['Accept: application/json'];

    if ($body !== null) {
        if (is_array($body)) {
            $encoded = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            if ($encoded === false) {
                return ['status' => 0, 'headers' => [], 'json' => null, 'raw' => '', 'error' => 'No se pudo codificar el cuerpo.'];
            }

            $body = $encoded;
        }

        if ($contentType !== null) {
            $headers[] = 'Content-Type: '.$contentType;
        }
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 30,
    ]);

    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }

    $raw = curl_exec($ch);

    if ($raw === false) {
        $error = curl_error($ch);
        curl_close($ch);

        return ['status' => 0, 'headers' => [], 'json' => null, 'raw' => '', 'error' => $error];
    }

    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headerText = substr($raw, 0, $headerSize);
    $bodyText = substr($raw, $headerSize);
    curl_close($ch);

    return [
        'status' => $status,
        'headers' => parse_headers($headerText),
        'json' => json_decode($bodyText, true),
        'raw' => $bodyText,
        'error' => null,
    ];
}

function path_value(mixed $value, string $path): mixed
{
    foreach (explode('.', $path) as $key) {
        if (! is_array($value) || ! array_key_exists($key, $value)) {
            return null;
        }

        $value = $value[$key];
    }

    return $value;
}

function expect(string $label, array $response, int $status, ?string $code = null): void
{
    check(
        $label.' HTTP '.$status,
        $response['status'] === $status,
        ['status' => $response['status'], 'body' => $response['json'] ?? $response['raw'], 'error' => $response['error']],
    );

    if ($code !== null) {
        check(
            $label.' código '.$code,
            path_value($response['json'], 'error.code') === $code,
            $response['json'] ?? $response['raw'],
        );
    }
}

function uuid_ok(mixed $value): bool
{
    return is_string($value)
        && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value) === 1;
}

function iso_z_ok(mixed $value): bool
{
    return is_string($value)
        && preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}Z$/', $value) === 1;
}

function collect_refs(mixed $node, array &$refs): void
{
    if (! is_array($node)) {
        return;
    }

    foreach ($node as $key => $value) {
        if ($key === '$ref' && is_string($value)) {
            $refs[] = $value;
        } elseif (is_array($value)) {
            collect_refs($value, $refs);
        }
    }
}

function resolve_ref(array $spec, string $ref): bool
{
    if (! str_starts_with($ref, '#/')) {
        return false;
    }

    $node = $spec;

    foreach (explode('/', substr($ref, 2)) as $part) {
        $part = str_replace(['~1', '~0'], ['/', '~'], $part);

        if (! is_array($node) || ! array_key_exists($part, $node)) {
            return false;
        }

        $node = $node[$part];
    }

    return true;
}

function finish(): never
{
    global $checks, $failures;

    echo 'Comprobaciones: '.$checks.PHP_EOL;

    if ($failures === []) {
        echo 'OK: suite CRUD completa'.PHP_EOL;
        exit(0);
    }

    echo 'FALLOS: '.count($failures).PHP_EOL;

    foreach ($failures as $failure) {
        echo ' - '.$failure.PHP_EOL;
    }

    exit(1);
}

$specPath = __DIR__.'/../openapi/spa-api-v1.json';
$specRaw = @file_get_contents($specPath);
$spec = is_string($specRaw) ? json_decode($specRaw, true) : null;

check('OpenAPI archivo legible', is_string($specRaw) && $specRaw !== '');
check('OpenAPI JSON válido', is_array($spec));

if (is_array($spec)) {
    check('OpenAPI versión 3.1.0', ($spec['openapi'] ?? null) === '3.1.0');
    check('OpenAPI título', ($spec['info']['title'] ?? null) === 'Spa Cielo API');
    check('OpenAPI versión de API', is_string($spec['info']['version'] ?? null));

    $requiredPaths = ['/api/v1/health', '/api/v1/servicios', '/api/v1/servicios/{id}'];
    $missingPaths = array_values(array_filter($requiredPaths, fn (string $path): bool => ! isset($spec['paths'][$path])));
    check('OpenAPI paths', $missingPaths === [], $missingPaths);

    $requiredOperations = [
        ['/api/v1/health', 'get'],
        ['/api/v1/servicios', 'get'],
        ['/api/v1/servicios', 'post'],
        ['/api/v1/servicios/{id}', 'get'],
        ['/api/v1/servicios/{id}', 'put'],
        ['/api/v1/servicios/{id}', 'delete'],
    ];
    $missingOperations = [];

    foreach ($requiredOperations as [$path, $method]) {
        if (! isset($spec['paths'][$path][$method])) {
            $missingOperations[] = strtoupper($method).' '.$path;
        }
    }

    check('OpenAPI operaciones', $missingOperations === [], $missingOperations);

    $expectedCodes = [
        '/api/v1/health' => ['get' => ['200', '503']],
        '/api/v1/servicios' => ['get' => ['200', '400'], 'post' => ['201', '400', '409', '415', '422']],
        '/api/v1/servicios/{id}' => [
            'get' => ['200', '400', '404'],
            'put' => ['200', '400', '404', '409', '415', '422'],
            'delete' => ['200', '400', '404'],
        ],
    ];
    $missingCodes = [];

    foreach ($expectedCodes as $path => $methods) {
        foreach ($methods as $method => $codes) {
            foreach ($codes as $code) {
                if (! isset($spec['paths'][$path][$method]['responses'][$code])) {
                    $missingCodes[] = strtoupper($method).' '.$path.' -> '.$code;
                }
            }
        }
    }

    check('OpenAPI respuestas', $missingCodes === [], $missingCodes);

    $requiredSchemas = ['Service', 'ServiceWrite', 'ServiceResponse', 'ServiceListResponse', 'ErrorResponse'];
    $missingSchemas = array_values(array_filter($requiredSchemas, fn (string $name): bool => ! isset($spec['components']['schemas'][$name])));
    check('OpenAPI schemas', $missingSchemas === [], $missingSchemas);

    $refs = [];
    collect_refs($spec, $refs);
    $missingRefs = [];

    foreach (array_unique($refs) as $ref) {
        if (! resolve_ref($spec, $ref)) {
            $missingRefs[] = $ref;
        }
    }

    check('OpenAPI $ref resueltas', $missingRefs === [], $missingRefs);
}

$health = api('GET', $api.'/health');
expect('health', $health, 200);
check('health success', path_value($health['json'], 'success') === true, $health['json']);
check('health status ok', path_value($health['json'], 'data.status') === 'ok', $health['json']);
check('health db ok', path_value($health['json'], 'data.db') === 'ok', $health['json']);
check('health sin meta', is_array($health['json']) && ! array_key_exists('meta', $health['json']), $health['json']);

$initial = api('GET', $api.'/servicios?per_page=1');
expect('listado inicial', $initial, 200);
check('listado data array', is_array(path_value($initial['json'], 'data')), $initial['json']);
$initialMeta = path_value($initial['json'], 'meta');
check('listado meta page', path_value($initialMeta, 'page') === 1, $initialMeta);
check('listado meta per_page', path_value($initialMeta, 'per_page') === 1, $initialMeta);
check('listado meta total', is_int(path_value($initialMeta, 'total')) && path_value($initialMeta, 'total') >= 0, $initialMeta);
check('listado meta last_page', is_int(path_value($initialMeta, 'last_page')) && path_value($initialMeta, 'last_page') >= 1, $initialMeta);

$invalidQueries = [
    'estado=otro',
    'foo=bar',
    'page=0',
    'page=abc',
    'per_page=0',
    'per_page=101',
    'sort=id',
    'order=up',
    'q=',
];

foreach ($invalidQueries as $query) {
    $response = api('GET', $api.'/servicios?'.$query);
    expect('query inválida '.$query, $response, 400, 'INVALID_REQUEST');
}

$suffix = gmdate('YmdHis').'-'.bin2hex(random_bytes(3));
$nameA = 'Suite API A '.$suffix;
$nameB = 'Suite API B '.$suffix;

$createA = api('POST', $api.'/servicios', [
    'nombre' => '  '.$nameA.'  ',
    'duracion_min' => 30,
    'precio' => ' 123.4 ',
]);
expect('crear A', $createA, 201);
$idA = path_value($createA['json'], 'data.id');
check('crear A id UUID v4', uuid_ok($idA), $idA);
check('crear A nombre trim', path_value($createA['json'], 'data.nombre') === $nameA, $createA['json']);
check('crear A precio normalizado', path_value($createA['json'], 'data.precio') === '123.40', $createA['json']);
check('crear A estado activo', path_value($createA['json'], 'data.estado') === 'activo', $createA['json']);
check('crear A Location', ($createA['headers']['location'] ?? null) === '/api/v1/servicios/'.$idA, $createA['headers'] ?? null);
check('crear A created_at', iso_z_ok(path_value($createA['json'], 'data.created_at')), $createA['json']);
check('crear A updated_at', iso_z_ok(path_value($createA['json'], 'data.updated_at')), $createA['json']);

if (! is_string($idA)) {
    $failures[] = 'Sin id del servicio A; se detienen las pruebas dependientes.';
    finish();
}

$duplicate = api('POST', $api.'/servicios', [
    'nombre' => $nameA,
    'duracion_min' => 30,
    'precio' => '1.00',
]);
expect('crear duplicado', $duplicate, 409, 'DUPLICATE_SERVICE');

$extra = api('POST', $api.'/servicios', [
    'nombre' => 'Extra '.$suffix,
    'duracion_min' => 30,
    'precio' => '1.00',
    'estado' => 'activo',
]);
expect('crear campo extra', $extra, 400, 'INVALID_REQUEST');
check('crear campo extra details', is_array(path_value($extra['json'], 'error.details.estado')), $extra['json']);

$missing = api('POST', $api.'/servicios', ['nombre' => 'Solo nombre']);
expect('crear faltantes', $missing, 422, 'VALIDATION_ERROR');
check('crear faltantes duracion', is_array(path_value($missing['json'], 'error.details.duracion_min')), $missing['json']);
check('crear faltantes precio', is_array(path_value($missing['json'], 'error.details.precio')), $missing['json']);

$empty = api('POST', $api.'/servicios', '{}', 'application/json');
expect('crear cuerpo vacío', $empty, 422, 'VALIDATION_ERROR');

$priceThreeDecimals = api('POST', $api.'/servicios', [
    'nombre' => 'Precio tres decimales '.$suffix,
    'duracion_min' => 30,
    'precio' => '10.999',
]);
expect('crear precio 3 decimales', $priceThreeDecimals, 422, 'VALIDATION_ERROR');
check('crear precio 3 decimales detalle', is_array(path_value($priceThreeDecimals['json'], 'error.details.precio')), $priceThreeDecimals['json']);

$negativePrice = api('POST', $api.'/servicios', [
    'nombre' => 'Precio negativo '.$suffix,
    'duracion_min' => 30,
    'precio' => '-1.00',
]);
expect('crear precio negativo', $negativePrice, 422, 'VALIDATION_ERROR');

$zeroDuration = api('POST', $api.'/servicios', [
    'nombre' => 'Duración cero '.$suffix,
    'duracion_min' => 0,
    'precio' => '1.00',
]);
expect('crear duración cero', $zeroDuration, 422, 'VALIDATION_ERROR');

$longDuration = api('POST', $api.'/servicios', [
    'nombre' => 'Duración larga '.$suffix,
    'duracion_min' => 1441,
    'precio' => '1.00',
]);
expect('crear duración 1441', $longDuration, 422, 'VALIDATION_ERROR');

$longName = api('POST', $api.'/servicios', [
    'nombre' => str_repeat('x', 121),
    'duracion_min' => 30,
    'precio' => '1.00',
]);
expect('crear nombre 121', $longName, 422, 'VALIDATION_ERROR');

$wrongType = api('POST', $api.'/servicios', [
    'nombre' => 'Tipo incorrecto '.$suffix,
    'duracion_min' => 30,
    'precio' => '1.00',
], 'text/plain');
expect('crear Content-Type incorrecto', $wrongType, 415, 'UNSUPPORTED_MEDIA_TYPE');

$malformed = api('POST', $api.'/servicios', '{"nombre":', 'application/json');
expect('crear JSON malformado', $malformed, 400, 'INVALID_REQUEST');

$createB = api('POST', $api.'/servicios', [
    'nombre' => $nameB,
    'duracion_min' => 15,
    'precio' => 99.5,
], 'application/json; charset=utf-8');
expect('crear B con número', $createB, 201);
$idB = path_value($createB['json'], 'data.id');
check('crear B id UUID v4', uuid_ok($idB), $idB);
check('crear B precio normalizado', path_value($createB['json'], 'data.precio') === '99.50', $createB['json']);

$getA = api('GET', $api.'/servicios/'.$idA);
expect('obtener A', $getA, 200);
check('obtener A nombre', path_value($getA['json'], 'data.nombre') === $nameA, $getA['json']);

$getInvalidId = api('GET', $api.'/servicios/no-es-uuid');
expect('obtener UUID inválido', $getInvalidId, 400, 'INVALID_ID');

$getNonV4 = api('GET', $api.'/servicios/00000000-0000-1000-8000-000000000000');
expect('obtener UUID no v4', $getNonV4, 400, 'INVALID_ID');

$getMissing = api('GET', $api.'/servicios/99999999-9999-4999-8999-999999999999');
expect('obtener inexistente', $getMissing, 404, 'SERVICE_NOT_FOUND');

$updateA = api('PUT', $api.'/servicios/'.$idA, [
    'nombre' => '  '.$nameA.' editada  ',
    'duracion_min' => 45,
    'precio' => 200,
]);
expect('actualizar A', $updateA, 200);
check('actualizar A nombre trim', path_value($updateA['json'], 'data.nombre') === $nameA.' editada', $updateA['json']);
check('actualizar A duración', path_value($updateA['json'], 'data.duracion_min') === 45, $updateA['json']);
check('actualizar A precio', path_value($updateA['json'], 'data.precio') === '200.00', $updateA['json']);
check('actualizar A estado intacto', path_value($updateA['json'], 'data.estado') === 'activo', $updateA['json']);

$updateSameName = api('PUT', $api.'/servicios/'.$idA, [
    'nombre' => $nameA.' editada',
    'duracion_min' => 50,
    'precio' => '210.5',
]);
expect('actualizar mismo nombre', $updateSameName, 200);
check('actualizar mismo nombre precio', path_value($updateSameName['json'], 'data.precio') === '210.50', $updateSameName['json']);

$updateDuplicate = api('PUT', $api.'/servicios/'.$idA, [
    'nombre' => $nameB,
    'duracion_min' => 50,
    'precio' => '210.50',
]);
expect('actualizar duplicado', $updateDuplicate, 409, 'DUPLICATE_SERVICE');

$getB = api('GET', $api.'/servicios/'.$idB);
expect('obtener B tras conflicto', $getB, 200);
check('obtener B sin cambios', path_value($getB['json'], 'data.nombre') === $nameB, $getB['json']);

$updateExtra = api('PUT', $api.'/servicios/'.$idA, [
    'nombre' => 'Extra',
    'duracion_min' => 30,
    'precio' => '1.00',
    'estado' => 'inactivo',
]);
expect('actualizar campo extra', $updateExtra, 400, 'INVALID_REQUEST');

$updateMissing = api('PUT', $api.'/servicios/'.$idA, ['nombre' => 'Solo nombre']);
expect('actualizar faltantes', $updateMissing, 422, 'VALIDATION_ERROR');

$updateInvalidId = api('PUT', $api.'/servicios/no-es-uuid', [
    'nombre' => 'X',
    'duracion_min' => 30,
    'precio' => '1.00',
]);
expect('actualizar UUID inválido', $updateInvalidId, 400, 'INVALID_ID');

$updateMissingId = api('PUT', $api.'/servicios/99999999-9999-4999-8999-999999999999', [
    'nombre' => 'X',
    'duracion_min' => 30,
    'precio' => '1.00',
]);
expect('actualizar inexistente', $updateMissingId, 404, 'SERVICE_NOT_FOUND');

$updateWrongType = api('PUT', $api.'/servicios/'.$idA, [
    'nombre' => 'X',
    'duracion_min' => 30,
    'precio' => '1.00',
], 'text/plain');
expect('actualizar Content-Type incorrecto', $updateWrongType, 415, 'UNSUPPORTED_MEDIA_TYPE');

$updateMalformed = api('PUT', $api.'/servicios/'.$idA, '{"nombre":', 'application/json');
expect('actualizar JSON malformado', $updateMalformed, 400, 'INVALID_REQUEST');

$updatePrice = api('PUT', $api.'/servicios/'.$idA, [
    'nombre' => 'Precio inválido',
    'duracion_min' => 30,
    'precio' => '1.999',
]);
expect('actualizar precio 3 decimales', $updatePrice, 422, 'VALIDATION_ERROR');

$deleteA = api('DELETE', $api.'/servicios/'.$idA);
expect('inactivar A', $deleteA, 200);
check('inactivar A estado', path_value($deleteA['json'], 'data.estado') === 'inactivo', $deleteA['json']);
check('inactivar A id', path_value($deleteA['json'], 'data.id') === $idA, $deleteA['json']);
$updatedAtA = path_value($deleteA['json'], 'data.updated_at');

$deleteAAgain = api('DELETE', $api.'/servicios/'.$idA);
expect('inactivar A de nuevo', $deleteAAgain, 200);
check('inactivar A idempotente', path_value($deleteAAgain['json'], 'data.updated_at') === $updatedAtA, $deleteAAgain['json']);

$getAAfterDelete = api('GET', $api.'/servicios/'.$idA);
expect('obtener A inactivo', $getAAfterDelete, 200);
check('obtener A estado inactivo', path_value($getAAfterDelete['json'], 'data.estado') === 'inactivo', $getAAfterDelete['json']);
check('obtener A fila conservada', path_value($getAAfterDelete['json'], 'data.nombre') === $nameA.' editada', $getAAfterDelete['json']);

$deleteB = api('DELETE', $api.'/servicios/'.$idB);
expect('inactivar B', $deleteB, 200);
check('inactivar B estado', path_value($deleteB['json'], 'data.estado') === 'inactivo', $deleteB['json']);

$filtered = api('GET', $api.'/servicios?'.http_build_query(['q' => $suffix, 'per_page' => 100]));
expect('listar por sufijo', $filtered, 200);
check('listar por sufijo total', path_value($filtered['json'], 'meta.total') === 2, $filtered['json']);
$filteredData = path_value($filtered['json'], 'data');
check('listar por sufijo inactivos', is_array($filteredData) && count($filteredData) === 2 && array_reduce(
    $filteredData,
    fn (bool $carry, array $item): bool => $carry && ($item['estado'] ?? null) === 'inactivo',
    true,
), $filteredData);

$activeFiltered = api('GET', $api.'/servicios?'.http_build_query(['q' => $suffix, 'estado' => 'activo', 'per_page' => 100]));
expect('listar sufijo activos', $activeFiltered, 200);
check('listar sufijo activos total 0', path_value($activeFiltered['json'], 'meta.total') === 0, $activeFiltered['json']);

$inactiveFiltered = api('GET', $api.'/servicios?'.http_build_query(['q' => $suffix, 'estado' => 'inactivo', 'per_page' => 100]));
expect('listar sufijo inactivos', $inactiveFiltered, 200);
check('listar sufijo inactivos total 2', path_value($inactiveFiltered['json'], 'meta.total') === 2, $inactiveFiltered['json']);

$deleteInvalidId = api('DELETE', $api.'/servicios/no-es-uuid');
expect('inactivar UUID inválido', $deleteInvalidId, 400, 'INVALID_ID');

$deleteMissing = api('DELETE', $api.'/servicios/99999999-9999-4999-8999-999999999999');
expect('inactivar inexistente', $deleteMissing, 404, 'SERVICE_NOT_FOUND');

$methodNotAllowed = api('PATCH', $api.'/servicios/'.$idA, []);
expect('método no permitido', $methodNotAllowed, 405, 'METHOD_NOT_ALLOWED');
check('método no permitido sin traza', ! str_contains($methodNotAllowed['raw'], '"trace"') && ! str_contains($methodNotAllowed['raw'], '"exception"'), $methodNotAllowed['raw']);

$unknownRoute = api('GET', $api.'/desconocido');
expect('ruta desconocida', $unknownRoute, 404);
check('ruta desconocida envelope', path_value($unknownRoute['json'], 'success') === false, $unknownRoute['json']);
check('ruta desconocida sin traza', ! str_contains($unknownRoute['raw'], '"trace"') && ! str_contains($unknownRoute['raw'], '"exception"'), $unknownRoute['raw']);

$healthFinal = api('GET', $api.'/health');
expect('health final', $healthFinal, 200);
check('health final db ok', path_value($healthFinal['json'], 'data.db') === 'ok', $healthFinal['json']);

echo 'Servicios de prueba: '.$idA.' y '.$idB.' (inactivos, filas conservadas)'.PHP_EOL;

finish();
