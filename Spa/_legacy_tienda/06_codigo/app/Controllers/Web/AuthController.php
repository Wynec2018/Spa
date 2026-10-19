<?php
declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Session;
use App\Core\View;

// CP-FRONT-01: resuelve la vista de login. Sin SQL/PDO ni negocio:
// la autenticación vive en la API (POST /api/v1/auth/login).
final class AuthController
{
    public function showLogin(Request $request): never
    {
        // CP-FRONT-02: con sesión activa, el login redirige al dashboard
        if (isset($_COOKIE[Session::name()])) {
            header('Location: /dashboard');
            exit;
        }
        header('Content-Type: text/html; charset=utf-8'); // index.php fija JSON; aquí es HTML
        View::render('auth/login', ['pageTitle' => 'Iniciar sesión'], 'layouts/auth');
    }
}
