<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#1f3a33">
    <title>@yield('title', 'Panel · Spa Cielo')</title>
    <meta name="description" content="@yield('description', 'Panel de gestión de Spa Cielo.')">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body class="app-page">
    <a class="skip-link" href="#main-content">Saltar al contenido</a>

    <header class="app-topbar">
        <a class="app-brand" href="{{ route('dashboard') }}" aria-label="Spa Cielo, resumen">
            <span class="brand-mark brand-mark-small" aria-hidden="true">
                <svg viewBox="0 0 48 48" fill="none">
                    <path d="M10 30c5-12 11-18 20-18 2.8 0 5.2.5 7.4 1.6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                    <path d="M14 36c5-8 11-12 19-12 2.2 0 4.2.3 6.2 1" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                    <circle cx="31" cy="18" r="4.2" stroke="currentColor" stroke-width="1.8"/>
                </svg>
            </span>
            <span>Spa Cielo</span>
        </a>

        <nav class="app-nav" aria-label="Navegación principal">
            <a
                @class(['app-nav-link', 'is-active' => request()->routeIs('dashboard')])
                href="{{ route('dashboard') }}"
                @if (request()->routeIs('dashboard')) aria-current="page" @endif
            >Resumen</a>
            <a
                @class(['app-nav-link', 'is-active' => request()->routeIs('services.*')])
                href="{{ route('services.index') }}"
                @if (request()->routeIs('services.*')) aria-current="page" @endif
            >Servicios</a>
            <a
                @class(['app-nav-link', 'is-active' => request()->routeIs('reservations.*')])
                href="{{ route('reservations.index') }}"
                @if (request()->routeIs('reservations.*')) aria-current="page" @endif
            >Reservas</a>
        </nav>

        <div class="app-user">
            <span class="app-avatar" aria-hidden="true">{{ mb_substr($user->nombre, 0, 1) }}</span>
            <span class="app-user-copy">
                <span class="app-user-name" title="{{ $user->nombre }}">{{ $user->nombre }}</span>
                <span class="app-user-role">{{ ucfirst($user->rol) }}</span>
            </span>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="button button-quiet" type="submit">Cerrar sesión</button>
            </form>
        </div>
    </header>

    <main id="main-content" class="app-main" tabindex="-1">
        @yield('content')
    </main>

    <footer class="app-footer">
        <p>Spa Cielo · Gestión de la sede</p>
        <p>Sesión protegida</p>
    </footer>

    @stack('scripts')
</body>
</html>
