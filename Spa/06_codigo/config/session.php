<?php
declare(strict_types=1);

// CP-BACK-03: configuración de sesión PHP (sin framework)
return [
    'name' => 'sistema',
    'lifetime' => 0,        // cookie de sesión: se cierra con el navegador
    'httponly' => true,     // JS no puede leer la cookie
    'samesite' => 'Lax',
    'strict' => true,       // use_strict_mode: rechaza IDs de sesión ajenos
];
