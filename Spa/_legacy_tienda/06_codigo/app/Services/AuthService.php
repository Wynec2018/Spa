<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Session;
use App\Repositories\UsuarioRepository;
use RuntimeException;

// decisiones_backend.md: buscar email → activo → password_verify →
// regenerar ID de sesión → datos mínimos. Sin JWT ni RBAC.
final class AuthService
{
    public function __construct(private readonly UsuarioRepository $repository = new UsuarioRepository())
    {
    }

    public function login(string $email, string $password): array
    {
        $user = $this->repository->findByEmail($email);
        // 401 único para inexistente/inactivo/password mal → sin enumeración de usuarios
        if ($user === null || $user->estado !== 'activo' || !password_verify($password, $user->hashPassword)) {
            throw new RuntimeException('Credenciales inválidas', 401);
        }
        Session::start();
        session_regenerate_id(true); // anti-fijación de sesión
        $_SESSION['user_id'] = $user->id;
        $_SESSION['nombre'] = $user->nombre;
        $_SESSION['email'] = $user->email;
        return $user->toArray();
    }

    // CP-INT-02: guard de sesión para endpoints protegidos (cookie presente + sesión válida)
    public static function requireSession(): void
    {
        if (!isset($_COOKIE[Session::name()])) {
            throw new RuntimeException('No autenticado', 401);
        }
        Session::start();
        if (!isset($_SESSION['user_id'])) {
            throw new RuntimeException('No autenticado', 401);
        }
    }

    // CP-BACK-04: usuario de la sesión actual
    public function me(): array
    {
        self::requireSession();
        return [
            'id' => $_SESSION['user_id'],
            'nombre' => $_SESSION['nombre'],
            'email' => $_SESSION['email'],
        ];
    }

    // CP-BACK-04: destruye la sesión; sin sesión responde igual (idempotente)
    public function logout(): array
    {
        Session::destroy();
        return ['mensaje' => 'Sesión cerrada'];
    }
}
