# Corrección arquitectónica de la API

La implementación actual está concentrando la lógica de la API en:

`Spa/06_codigo/public/index.php`

Esto no cumple la arquitectura MVC definida para el spa.

No avances al siguiente checkpoint.

## Regla

`public/index.php` debe ser únicamente el Front Controller.

No debe contener:

- SQL;
- consultas PDO;
- CRUD;
- validaciones de negocio;
- lógica específica de categorías;
- autenticación.

## Estructura obligatoria

```text
Spa/06_codigo/
├── public/
│   └── index.php
├── app/
│   ├── Core/
│   │   ├── Router.php
│   │   ├── Request.php
│   │   ├── Response.php
│   │   └── Database.php
│   ├── Controllers/
│   │   └── Api/
│   │       └── CategoriaController.php
│   ├── Models/
│   │   └── Categoria.php
│   ├── Repositories/
│   │   └── CategoriaRepository.php
│   ├── Services/
│   │   └── CategoriaService.php
│   └── Validators/
│       └── CategoriaValidator.php
├── routes/
│   └── api.php
├── config/
│   ├── app.php
│   └── database.php
└── tests/
```

## Responsabilidades

### public/index.php
- bootstrap;
- cargar configuración;
- cargar rutas;
- ejecutar Router.

### Router.php
- resolver método HTTP;
- resolver URI;
- llamar controlador.

### CategoriaController.php
- recibir request;
- llamar validator/service;
- devolver response.

### CategoriaValidator.php
- validar entrada.

### CategoriaService.php
- reglas y casos de uso.

### CategoriaRepository.php
- consultas SQL de `cat_categoria`;
- uso de PDO.

### Database.php
- crear y proporcionar conexión PDO.

### routes/api.php
- registrar endpoints.

## Acción requerida

1. Refactoriza únicamente el código ya implementado.
2. No agregues nuevos endpoints.
3. No avances al siguiente checkpoint.
4. Verifica sintaxis PHP.
5. Prueba los endpoints existentes.
6. Confirma que `public/index.php` no contiene SQL ni lógica CRUD.
7. Actualiza `agents/state/api-pilot-workflow.json`.
8. Marca `HUMAN_STATUS: PENDING`.
9. Detente.

Si la refactorización falla:

```text
TECHNICAL_STATUS: FAILED
CAN_CONTINUE: false
```