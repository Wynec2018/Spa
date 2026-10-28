@php
    $hasErrors = $errors->any();
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#1f3a33">
    <title>Ingresar · Spa Cielo</title>
    <meta name="description" content="Acceso privado al panel de gestión de Spa Cielo.">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <script src="{{ asset('js/login.js') }}" defer></script>
</head>
<body class="auth-page">
    <a class="skip-link" href="#login-form">Saltar al formulario</a>

    <main class="auth-shell">
        <section class="auth-brand" aria-labelledby="brand-heading">
            <div class="brand-lockup">
                <span class="brand-mark" aria-hidden="true">
                    <svg viewBox="0 0 48 48" fill="none">
                        <path d="M10 30c5-12 11-18 20-18 2.8 0 5.2.5 7.4 1.6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                        <path d="M14 36c5-8 11-12 19-12 2.2 0 4.2.3 6.2 1" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                        <circle cx="31" cy="18" r="4.2" stroke="currentColor" stroke-width="1.8"/>
                    </svg>
                </span>
                <span class="brand-wordmark">Spa Cielo</span>
            </div>

            <div class="brand-copy">
                <p class="eyebrow eyebrow-light">Recepción y agenda</p>
                <h1 id="brand-heading">Tu jornada,<br><em>en calma.</em></h1>
                <p class="brand-lead">Servicios, reservas y equipo en un mismo lugar.</p>
            </div>

            <p class="brand-foot">
                <span class="brand-dot" aria-hidden="true"></span>
                Acceso privado del equipo
            </p>
        </section>

        <section class="auth-panel" aria-labelledby="login-heading">
            <div class="auth-card">
                <header class="auth-heading">
                    <p class="eyebrow">Spa Cielo · Acceso</p>
                    <h2 id="login-heading">Bienvenida de nuevo</h2>
                    <p>Ingresa con tu cuenta para abrir la agenda de la sede.</p>
                </header>

                <div
                    id="login-status"
                    class="form-status"
                    data-status
                    data-has-errors="{{ $hasErrors ? 'true' : 'false' }}"
                    role="alert"
                    aria-atomic="true"
                    tabindex="-1"
                    @if (! $hasErrors) hidden @endif
                >
                    @if ($hasErrors)
                        <p class="form-status-title">No pudimos iniciar sesión</p>
                        <p>{{ $errors->first() }}</p>
                    @endif
                </div>

                <form id="login-form" class="auth-form" method="POST" action="{{ route('login.store') }}" aria-labelledby="login-heading" tabindex="-1" data-login-form>
                    @csrf

                    <div class="field">
                        <label for="branch_id">Sucursal</label>
                        <div class="select-wrap">
                            <select
                                id="branch_id"
                                name="branch_id"
                                required
                                @error('branch_id') aria-invalid="true" aria-describedby="branch_id-error" @enderror
                            >
                                <option value="{{ $branchId }}" @selected(old('branch_id', $branchId) === $branchId)>{{ $branchName }}</option>
                            </select>
                            <span class="select-chevron" aria-hidden="true">
                                <svg viewBox="0 0 16 16" fill="none">
                                    <path d="m4 6 4 4 4-4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </span>
                        </div>
                        @error('branch_id')
                            <p class="field-error" id="branch_id-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="field">
                        <label for="contacto">Contacto</label>
                        <input
                            id="contacto"
                            name="contacto"
                            type="text"
                            value="{{ old('contacto') }}"
                            autocomplete="username"
                            autocapitalize="none"
                            spellcheck="false"
                            placeholder="correo o teléfono"
                            aria-describedby="contacto-hint @error('contacto') contacto-error @enderror"
                            @error('contacto') aria-invalid="true" @enderror
                            @if (! $hasErrors) autofocus @endif
                            required
                        >
                        <p class="field-hint" id="contacto-hint">El correo o teléfono con el que te registraron.</p>
                        @error('contacto')
                            <p class="field-error" id="contacto-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="field">
                        <div class="label-row">
                            <label for="password">Contraseña</label>
                            <button
                                class="field-action"
                                type="button"
                                data-password-toggle
                                aria-controls="password"
                                aria-pressed="false"
                                aria-label="Mostrar contraseña"
                                hidden
                            >Mostrar</button>
                        </div>
                        <input
                            id="password"
                            name="password"
                            type="password"
                            autocomplete="current-password"
                            @error('password') aria-invalid="true" aria-describedby="password-error" @enderror
                            required
                        >
                        @error('password')
                            <p class="field-error" id="password-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <button class="button button-primary" type="submit" data-submit>
                        <span data-submit-label>Ingresar</span>
                        <span class="button-spinner" data-spinner aria-hidden="true" hidden></span>
                    </button>
                </form>

                <p class="auth-help">¿Problemas para ingresar? Contacta a la administración de tu sede.</p>
            </div>

            <p class="auth-legal">Sesión protegida · Spa Cielo {{ date('Y') }}</p>
        </section>
    </main>
</body>
</html>
