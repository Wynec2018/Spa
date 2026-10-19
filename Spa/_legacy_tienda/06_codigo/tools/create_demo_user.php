<?php
declare(strict_types=1);

// carga .env + envRequired() (CP-BACK-02: leer DEMO_* desde .env)
require dirname(__DIR__) . '/app/Core/bootstrap.php';

$password = getenv('DB_PASSWORD');
$dsn = sprintf(
    'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
    envRequired('DB_HOST'),
    envRequired('DB_PORT'),
    envRequired('DB_NAME')
);

$pdo = new PDO($dsn, envRequired('DB_USER'), $password === false ? '' : $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);

$name = envRequired('DEMO_USER_NAME');
$email = envRequired('DEMO_USER_EMAIL');
$password = envRequired('DEMO_USER_PASSWORD');

$hash = password_hash($password, PASSWORD_ARGON2ID);
if ($hash === false) {
    throw new RuntimeException('No se pudo generar hash Argon2id.');
}

$now = (new DateTimeImmutable())->format('Y-m-d H:i:s');

$pdo->beginTransaction();
try {
    $q = $pdo->prepare('SELECT id FROM auth_usuario WHERE email = :email LIMIT 1');
    $q->execute(['email' => $email]);
    $id = $q->fetchColumn();

    if ($id) {
        $q = $pdo->prepare(
            'UPDATE auth_usuario
             SET nombre=:nombre, hash_password=:hash, estado="activo",
                 user_update=1, user_update_at=:now
             WHERE id=:id'
        );
        $q->execute(['nombre'=>$name,'hash'=>$hash,'now'=>$now,'id'=>$id]);
    } else {
        $q = $pdo->prepare(
            'INSERT INTO auth_usuario
             (nombre,email,hash_password,estado,user_create,user_update,user_created_at,user_update_at)
             VALUES (:nombre,:email,:hash,"activo",1,1,:created_at,:updated_at)'
        );
        $q->execute(['nombre'=>$name,'email'=>$email,'hash'=>$hash,'created_at'=>$now,'updated_at'=>$now]);
    }

    $pdo->commit();
    echo "Usuario demo preparado: {$email}\n";
    echo "La contraseña no se muestra.\n";
} catch (Throwable $e) {
    $pdo->rollBack();
    error_log($e->getMessage());
    fwrite(STDERR, "No se pudo preparar el usuario demo. Ver php_errors.log.\n");
    exit(1);
}
