# Paso 03 — Diagrama Entidad-Relación (conceptual)

Sistema: **Spa Cielo**
Skill: `database-schema-designer`
Formato: **Mermaid** (versionable en Git).

---

## 1. Diagrama

```mermaid
erDiagram
    BRANCH ||--o{ APP_USER : "pertenece"
    BRANCH ||--o{ CLIENT : "atiende"
    BRANCH ||--o{ SERVICE : "ofrece"
    BRANCH ||--o{ RESOURCE : "posee"
    BRANCH ||--o{ BOOKING : "ubica"
    BRANCH ||--o{ PROMOTION : "aplica"

    APP_USER |o--o| RESOURCE : "es trabajadora"
    APP_USER ||--o{ AUDIT_LOG : "actor"

    CLIENT ||--o{ BOOKING : "reserva"
    CLIENT ||--o{ NOTIFICATION : "recibe"

    SERVICE ||--o{ BOOKING : "contrata"
    PROMOTION }o--o{ SERVICE : "aplica a"

    RESOURCE ||--o{ BOOKING : "cama asignada"
    RESOURCE ||--o{ BOOKING : "trabajadora asignada"

    BOOKING ||--o| PAYMENT : "se paga con"
    BOOKING ||--o{ OUTBOX : "genera eventos"
    PAYMENT ||--o{ REFUND : "devuelve"

    BRANCH {
        uuid id PK
        string nombre
        string direccion
        string estado
        timestamp created_at
        timestamp updated_at
    }
    APP_USER {
        uuid id PK
        uuid branch_id FK
        string rol "superusuario|secretaria|trabajadora"
        string nombre
        string hash_password
        string contacto
        string estado
        timestamp created_at
        timestamp updated_at
    }
    CLIENT {
        uuid id PK
        uuid branch_id FK
        string nombre
        bytes documento_cifrado
        string contacto
        timestamp created_at
        timestamp updated_at
    }
    SERVICE {
        uuid id PK
        uuid branch_id FK
        string nombre
        int duracion_min
        decimal precio
        string estado
    }
    RESOURCE {
        uuid id PK
        uuid branch_id FK
        enum tipo "cama|trabajadora"
        uuid user_id FK "nullable (si trabajadora)"
        string nombre
        enum estado "libre|ocupada|no_disponible"
        int version
        timestamp updated_at
    }
    BOOKING {
        uuid id PK
        uuid branch_id FK
        uuid client_id FK
        uuid service_id FK
        uuid cama_id FK
        uuid trabajadora_id FK
        timestamp inicio
        timestamp fin
        enum estado "pendiente|confirmada|cancelada|expirada|atendida"
        uuid payment_id FK "nullable"
        timestamp created_at
    }
    PAYMENT {
        uuid id PK
        uuid booking_id FK
        string proveedor
        text qr_payload
        decimal monto
        char moneda
        enum estado "pendiente|verificado|rechazado|reembolsado"
        uuid verificado_por FK "nullable"
        timestamp verificado_en
    }
    PROMOTION {
        uuid id PK
        uuid branch_id FK
        string nombre
        json reglas
        timestamp valid_from
        timestamp valid_to
        boolean activo
    }
    REFUND {
        uuid id PK
        uuid payment_id FK
        decimal monto
        string motivo
        enum estado "solicitada|aprobada|rechazada"
        uuid autorizado_por FK
        timestamp created_at
    }
    NOTIFICATION {
        uuid id PK
        uuid client_id FK
        string canal "whatsapp|email"
        string plantilla
        enum estado "pendiente|enviada|fallida"
        timestamp enviado_en
    }
    IDEMPOTENCY_KEY {
        string clave PK "scope:key"
        string request_hash
        json response
        timestamp expires_at
    }
    OUTBOX {
        bigserial id PK
        string tipo_evento
        json payload
        boolean publicado
        int intentos
        timestamp created_at
    }
    AUDIT_LOG {
        bigserial id PK
        uuid actor_user_id FK
        string accion
        string entidad
        uuid entidad_id
        json datos_previos
        json datos_nuevos
        string correlation_id
        string prev_hash
        timestamp creado_en
    }
    APP_CONFIG {
        string clave PK
        json valor
        uuid updated_by FK
        timestamp updated_at
    }
```

> Nota: `PROMOTION }o--o{ SERVICE` es conceptual; en el modelo lógico se resuelve con la
> tabla asociativa `promotion_service`.

---

## 2. Convenciones

- PK = clave primaria; FK = clave foránea; UK = clave única.
- `uuid` para entidades de dominio (evita enumeración, D-07); `bigserial` para
  `outbox` y `audit_log` (alto volumen, append-only).
- Tipos mostrados aquí son **indicativos conceptuales**; se fijan en el diseño físico.

**Estado del paso:** completado.
