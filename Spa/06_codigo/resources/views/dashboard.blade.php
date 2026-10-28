@extends('layouts.app')

@section('title', 'Resumen · Spa Cielo')
@section('description', 'Resumen de servicios y reservas de la sede.')

@section('content')
    <section class="dashboard" aria-labelledby="dashboard-heading">
        <header class="dashboard-hero">
            <div class="dashboard-hero-copy">
                <p class="eyebrow">Resumen de la sede</p>
                <h1 id="dashboard-heading">Tu jornada, en orden.</h1>
                <p class="dashboard-lead">Una vista rápida de {{ $branchName }} para empezar con lo importante a la vista.</p>
            </div>

            <dl class="dashboard-context" aria-label="Contexto del panel">
                <div>
                    <dt>Sede</dt>
                    <dd>{{ $branchName }}</dd>
                </div>
                <div>
                    <dt>Fecha</dt>
                    <dd><time datetime="{{ now()->toDateString() }}">{{ now()->format('d/m/Y') }}</time></dd>
                </div>
            </dl>
        </header>

        <div class="stat-grid">
            <article class="stat-card">
                <div class="stat-top">
                    <span class="stat-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none">
                            <path d="M12 3v18M3 12h18M5.6 5.6l12.8 12.8M18.4 5.6 5.6 18.4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                        </svg>
                    </span>
                    <span class="stat-kicker">Catálogo</span>
                </div>

                <h2 class="stat-label">Servicios activos</h2>
                <p class="stat-value">{{ $summary['services_active'] }}</p>
                <p class="stat-detail">{{ $summary['services_inactive'] }} inactivos · {{ $summary['services_total'] }} en total</p>
                <p class="stat-note">Disponibles para nuevas reservas.</p>
            </article>

            <article class="stat-card stat-card-accent">
                <div class="stat-top">
                    <span class="stat-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none">
                            <rect x="3.5" y="5" width="17" height="15.5" rx="3" stroke="currentColor" stroke-width="1.5"/>
                            <path d="M7.5 3.5v3M16.5 3.5v3M3.5 9.5h17" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                            <path d="M8 13.5h3M8 16.5h6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                        </svg>
                    </span>
                    <span class="stat-kicker">Agenda</span>
                </div>

                <h2 class="stat-label">Reservas pendientes</h2>
                <p class="stat-value">{{ $summary['bookings_pending'] }}</p>
                <p class="stat-detail">{{ $summary['bookings_cancelled'] }} canceladas · {{ $summary['bookings_total'] }} en total</p>
                <p class="stat-note">Aún no se han cancelado.</p>
            </article>
        </div>

        <section class="dashboard-lower" aria-labelledby="session-heading">
            <div class="dashboard-note">
                <p class="eyebrow">Tu sesión</p>
                <h2 id="session-heading">Todo en orden.</h2>
                <p>Estás trabajando como <strong>{{ $user->nombre }}</strong> en {{ $branchName }}. Al terminar, cierra la sesión para proteger la agenda.</p>
            </div>

            <dl class="session-list">
                <div>
                    <dt>Contacto</dt>
                    <dd>{{ $user->contacto }}</dd>
                </div>
                <div>
                    <dt>Rol</dt>
                    <dd>{{ ucfirst($user->rol) }}</dd>
                </div>
                <div>
                    <dt>Acceso</dt>
                    <dd>Activo</dd>
                </div>
            </dl>
        </section>
    </section>
@endsection
