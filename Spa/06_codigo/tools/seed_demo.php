<?php

declare(strict_types=1);

use App\Support\DatabaseContext;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';

$app->make(Kernel::class)->bootstrap();

if (app()->environment('production') && ! in_array('--force', $argv, true)) {
    fwrite(STDERR, 'Seed demo bloqueado en produccion. Usa --force solo si es intencional.'.PHP_EOL);
    exit(1);
}

const DEMO_BRANCH_ID = '11111111-1111-4111-8111-111111111111';
const DEMO_BRANCH_NAME = 'Sede Central';
const DEMO_BRANCH_ADDRESS = 'Av. Principal 100, Santa Cruz';

$users = [
    [
        'id' => '22222222-2222-4222-8222-222222222201',
        'rol' => 'superusuario',
        'nombre' => 'Superusuario Demo',
        'contacto' => 'super@spa.local',
        'env' => 'DEMO_SUPER_PASSWORD',
    ],
    [
        'id' => '22222222-2222-4222-8222-222222222202',
        'rol' => 'secretaria',
        'nombre' => 'Secretaria Demo',
        'contacto' => 'secretaria@spa.local',
        'env' => 'DEMO_SECRETARIA_PASSWORD',
    ],
    [
        'id' => '22222222-2222-4222-8222-222222222203',
        'rol' => 'trabajadora',
        'nombre' => 'Trabajadora Demo',
        'contacto' => 'trabajadora@spa.local',
        'env' => 'DEMO_TRABAJADORA_PASSWORD',
    ],
];

function demoPassword(string $key): ?string
{
    $value = env($key);

    if (! is_string($value) || trim($value) === '') {
        return null;
    }

    $password = (string) $value;

    if (strlen($password) < 8) {
        throw new RuntimeException("{$key} debe tener al menos 8 caracteres.");
    }

    return $password;
}

function generatedPassword(): string
{
    return bin2hex(random_bytes(12));
}

function seedUser(array $user, string $branchId, array &$generated): array
{
    $password = demoPassword($user['env']);
    $existing = DB::table('auth.app_user')
        ->where('branch_id', $branchId)
        ->where('contacto', $user['contacto'])
        ->first();

    $now = now();

    if ($existing !== null) {
        $values = [
            'rol' => $user['rol'],
            'nombre' => $user['nombre'],
            'contacto' => $user['contacto'],
            'estado' => 'activo',
            'updated_at' => $now,
        ];

        $status = 'existente';

        if ($password !== null) {
            $values['hash_password'] = password_hash($password, PASSWORD_ARGON2ID);
            $status = 'hash actualizado';
        }

        DB::table('auth.app_user')->where('id', $existing->id)->update($values);

        return [
            'contacto' => $user['contacto'],
            'rol' => $user['rol'],
            'estado' => $status,
        ];
    }

    if ($password === null) {
        $password = generatedPassword();
        $generated[$user['contacto']] = $password;
    }

    DB::table('auth.app_user')->insert([
        'id' => $user['id'],
        'branch_id' => $branchId,
        'rol' => $user['rol'],
        'nombre' => $user['nombre'],
        'hash_password' => password_hash($password, PASSWORD_ARGON2ID),
        'contacto' => $user['contacto'],
        'estado' => 'activo',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    return [
        'contacto' => $user['contacto'],
        'rol' => $user['rol'],
        'estado' => 'creado',
    ];
}

$branchId = trim((string) config('spa.branch_id'));

if ($branchId === '') {
    $branchId = DEMO_BRANCH_ID;
}

if (! Str::isUuid($branchId)) {
    fwrite(STDERR, 'APP_BRANCH_ID no es un UUID valido.'.PHP_EOL);
    exit(1);
}

$generated = [];

try {
    $result = DatabaseContext::run(function () use ($branchId, $users, &$generated): array {
        $branch = DB::table('core.branch')->where('id', $branchId)->first();

        if ($branch === null) {
            DB::table('core.branch')->insert([
                'id' => $branchId,
                'nombre' => DEMO_BRANCH_NAME,
                'direccion' => DEMO_BRANCH_ADDRESS,
                'estado' => 'activa',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $branchStatus = 'creada';
        } else {
            $branchStatus = 'existente';
        }

        $seededUsers = [];

        foreach ($users as $user) {
            $seededUsers[] = seedUser($user, $branchId, $generated);
        }

        return [
            'branch' => [
                'id' => $branchId,
                'nombre' => (string) ($branch->nombre ?? DEMO_BRANCH_NAME),
                'estado' => $branchStatus,
            ],
            'users' => $seededUsers,
        ];
    }, $branchId, null, (string) Str::uuid());
} catch (Throwable $exception) {
    fwrite(STDERR, 'Error al sembrar datos demo: '.$exception->getMessage().PHP_EOL);
    exit(1);
}

echo 'Seed demo de Spa Cielo'.PHP_EOL;
echo "Sucursal: {$result['branch']['nombre']} ({$result['branch']['id']}) [{$result['branch']['estado']}]".PHP_EOL;

foreach ($result['users'] as $user) {
    echo "Usuario {$user['rol']}: {$user['contacto']} [{$user['estado']}]".PHP_EOL;
}

if ($generated === []) {
    echo 'No se generaron contrasenas nuevas.'.PHP_EOL;
} else {
    echo 'Contrasenas generadas (se muestran una sola vez; no se guardan en el repositorio):'.PHP_EOL;

    foreach ($generated as $contacto => $password) {
        echo "  {$contacto}: {$password}".PHP_EOL;
    }
}
