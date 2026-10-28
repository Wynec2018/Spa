@extends('layouts.app')

@section('title', 'Reservas — Spa Cielo')
@section('description', 'Agenda de reservas de la sede por fecha y estado.')

@php
    $hasStatus = $filters['estado'] !== null;
    $hasFilters = $hasStatus || $filters['fecha'] !== $today;
    $pageStart = max(1, $page - 2);
    $pageEnd = min($lastPage, $page + 2);
    $pageQuery = ['fecha' => $filters['fecha']];
    $dateLabel = \Illuminate\Support\Carbon::parse($filters['fecha'])->format('d/m/Y');
    $statusLabels = [
        'pendiente' => 'Pendiente',
        'confirmada' => 'Confirmada',
        'cancelada' => 'Cancelada',
        'expirada' => 'Expirada',
        'atendida' => 'Atendida',
    ];
    $statusClasses = [
        'pendiente' => 'status-pending',
        'confirmada' => 'status-confirmed',
        'cancelada' => 'status-cancelled',
        'expirada' => 'status-expired',
        'atendida' => 'status-attended',
    ];

    if ($hasStatus) {
        $pageQuery['estado'] = $filters['estado'];
    }
@endphp

@section('content')
    <section class="services-page reservations-page" aria-labelledby="reservations-heading">
        @if (session('status'))
            <div class="flash-success" role="status">
                <span class="flash-success-mark" aria-hidden="true">V</span>
                <p>{{ session('status') }}</p>
            </div>
        @endif

        @if (session('error'))
            <div class="flash-error" role="alert">
                <span class="flash-error-mark" aria-hidden="true">!</span>
                <p>{{ session('error') }}</p>
            </div>
        @endif

        <header class="services-hero">
            <div class="services-hero-copy">
                <p class="eyebrow">Agenda de la sede</p>
                <h1 id="reservations-heading">Reservas, a la vista.</h1>
                <p class="services-lead">Consulta la agenda de {{ $branchName }} por fecha y estado. Los horarios se muestran en UTC.</p>
            </div>

            <div class="services-hero-aside">
                <div class="services-count">
                    <span class="services-count-value">{{ $total }}</span>
                    <span class="services-count-label">{{ $hasStatus ? 'resultados' : 'reservas' }}</span>
                </div>
                <a class="button button-primary services-create-link" href="{{ route('reservations.create') }}">Nueva reserva</a>
            </div>
        </header>

        <form class="service-filters reservation-filters" method="GET" action="{{ route('reservations.index') }}" role="search" aria-label="Filtrar reservas">
            <div class="field filter-field-date">
                <label for="fecha">Fecha</label>
                <input id="fecha" name="fecha" type="date" value="{{ $filters['fecha'] }}" required>
            </div>

            <div class="field filter-field-state">
                <label for="estado">Estado</label>
                <div class="select-wrap">
                    <select id="estado" name="estado">
                        <option value="" @selected($filters['estado'] === null)>Todos</option>
                        @foreach ($statusLabels as $value => $label)
                            <option value="{{ $value }}" @selected($filters['estado'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <span class="select-chevron" aria-hidden="true">
                        <svg viewBox="0 0 16 16" fill="none">
                            <path d="m4 6 4 4 4-4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </span>
                </div>
            </div>

            <div class="filter-actions">
                <button class="button button-primary filter-submit" type="submit">Filtrar</button>
                @if ($hasFilters)
                    <a class="button button-quiet" href="{{ route('reservations.index') }}">Limpiar</a>
                @endif
            </div>
        </form>

        <div class="services-results">
            <p class="services-result-count" aria-live="polite">
                @if ($total === 0)
                    Sin resultados
                @else
                    Mostrando {{ $from }}–{{ $to }} de {{ $total }} {{ $total === 1 ? 'reserva' : 'reservas' }}
                @endif
            </p>
            <p class="services-filter-note">
                Fecha {{ $dateLabel }} · {{ $hasStatus ? 'solo '.$statusLabels[$filters['estado']] : 'todos los estados' }} · horas UTC
            </p>
        </div>

        @if ($reservations->isEmpty())
            <div class="empty-state">
                <span class="empty-state-mark" aria-hidden="true">
                    <svg viewBox="0 0 48 48" fill="none">
                        <rect x="9" y="12" width="30" height="28" rx="5" stroke="currentColor" stroke-width="1.5"/>
                        <path d="M9 20h30M17 8v7M31 8v7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                        <path d="M18 26h4M18 32h9" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                    </svg>
                </span>
                <h2>{{ $hasStatus ? 'No hay reservas con ese estado.' : 'No hay reservas para esta fecha.' }}</h2>
                <p>Cambia la fecha o el estado para consultar otra parte de la agenda de {{ $branchName }}.</p>

                <div class="empty-state-actions">
                    @if ($hasStatus)
                        <a class="button button-quiet" href="{{ route('reservations.index', ['fecha' => $filters['fecha']]) }}">Ver todos los estados</a>
                    @endif
                    @if ($filters['fecha'] !== $today)
                        <a class="button button-primary" href="{{ route('reservations.index') }}">Ir a hoy</a>
                    @endif
                </div>
            </div>
        @else
            <div class="service-table-wrap" role="region" aria-label="Tabla de reservas de {{ $branchName }} para el {{ $dateLabel }}, página {{ $page }} de {{ $lastPage }}" tabindex="0">
                <table class="service-table reservation-table">
                    <caption class="sr-only">Reservas de {{ $branchName }} para el {{ $dateLabel }}, página {{ $page }} de {{ $lastPage }}</caption>
                    <thead>
                        <tr>
                            <th scope="col">Hora UTC</th>
                            <th scope="col">Cliente</th>
                            <th scope="col">Servicio</th>
                            <th scope="col">Recursos</th>
                            <th scope="col">Estado</th>
                            <th scope="col">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($reservations as $reservation)
                            @php
                                $inicio = \Illuminate\Support\Carbon::parse($reservation->inicio)->utc();
                                $fin = \Illuminate\Support\Carbon::parse($reservation->fin)->utc();
                                $statusClass = $statusClasses[$reservation->estado] ?? 'status-expired';
                                $statusLabel = $statusLabels[$reservation->estado] ?? ucfirst($reservation->estado);
                            @endphp
                            <tr>
                                <th scope="row" data-label="Hora">
                                    <span class="reservation-time">
                                        <time datetime="{{ $inicio->toIso8601ZuluString() }}">{{ $inicio->format('H:i') }}</time>
                                        <span aria-hidden="true">–</span>
                                        <time datetime="{{ $fin->toIso8601ZuluString() }}">{{ $fin->format('H:i') }}</time>
                                    </span>
                                    <span class="reservation-duration">{{ $reservation->service_duracion_min }} min</span>
                                </th>
                                <td data-label="Cliente">
                                    <span class="reservation-client-name">{{ $reservation->client_nombre }}</span>
                                    <span class="reservation-client-contact">{{ $reservation->client_contacto }}</span>
                                </td>
                                <td data-label="Servicio">
                                    <span class="reservation-service-name">{{ $reservation->service_nombre }}</span>
                                </td>
                                <td data-label="Recursos">
                                    <span class="reservation-resource-line">
                                        <span class="reservation-resource-label">Cama</span>
                                        {{ $reservation->cama_nombre }}
                                    </span>
                                    <span class="reservation-resource-line">
                                        <span class="reservation-resource-label">Trabajadora</span>
                                        {{ $reservation->trabajadora_nombre }}
                                    </span>
                                </td>
                                <td data-label="Estado">
                                    <span @class(['status-badge', $statusClass])>{{ $statusLabel }}</span>
                                </td>
                                <td data-label="Acciones" class="reservation-actions-cell">
                                    @if ($reservation->estado === 'pendiente')
                                        <details class="reservation-cancel-disclosure">
                                            <summary class="button button-danger-quiet" aria-label="Cancelar reserva de {{ $reservation->client_nombre }} a las {{ $inicio->format('H:i') }} UTC">Cancelar reserva</summary>
                                            <div class="reservation-cancel-panel">
                                                <p><strong>¿Confirmas la cancelación?</strong> La trabajadora quedará libre y la reserva no se puede reactivar en esta fase.</p>
                                                <form method="POST" action="{{ route('reservations.cancel', $reservation->id) }}" data-cancel-form>
                                                    @csrf
                                                    <input type="hidden" name="fecha" value="{{ $filters['fecha'] }}">
                                                    @if ($filters['estado'] !== null)
                                                        <input type="hidden" name="estado" value="{{ $filters['estado'] }}">
                                                    @endif
                                                    @if ($page > 1)
                                                        <input type="hidden" name="page" value="{{ $page }}">
                                                    @endif
                                                    <div class="reservation-cancel-actions">
                                                        <button class="button button-danger" type="submit" aria-label="Confirmar cancelación de la reserva de {{ $reservation->client_nombre }} a las {{ $inicio->format('H:i') }} UTC" data-cancel-submit>
                                                            <span data-cancel-label>Confirmar cancelación</span>
                                                            <span class="button-spinner" data-cancel-spinner aria-hidden="true" hidden></span>
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </details>
                                    @else
                                        <span class="reservation-no-action">
                                            <span aria-hidden="true">—</span>
                                            <span class="sr-only">Sin acciones</span>
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($lastPage > 1)
                <nav class="pagination" aria-label="Paginación de reservas">
                    @if ($page > 1)
                        <a class="pagination-step" href="{{ route('reservations.index', array_merge($pageQuery, ['page' => $page - 1])) }}" rel="prev">Anterior</a>
                    @else
                        <span class="pagination-step is-disabled" aria-disabled="true">Anterior</span>
                    @endif

                    <ol class="pagination-pages">
                        @if ($pageStart > 1)
                            <li>
                                <a class="pagination-page" href="{{ route('reservations.index', array_merge($pageQuery, ['page' => 1])) }}" aria-label="Página 1">1</a>
                            </li>
                            @if ($pageStart > 2)
                                <li class="pagination-gap" aria-hidden="true">…</li>
                            @endif
                        @endif

                        @for ($number = $pageStart; $number <= $pageEnd; $number++)
                            <li>
                                <a
                                    @class(['pagination-page', 'is-current' => $number === $page])
                                    href="{{ route('reservations.index', array_merge($pageQuery, ['page' => $number])) }}"
                                    @if ($number === $page) aria-current="page" @endif
                                >{{ $number }}</a>
                            </li>
                        @endfor

                        @if ($pageEnd < $lastPage)
                            @if ($pageEnd < $lastPage - 1)
                                <li class="pagination-gap" aria-hidden="true">…</li>
                            @endif
                            <li>
                                <a class="pagination-page" href="{{ route('reservations.index', array_merge($pageQuery, ['page' => $lastPage])) }}" aria-label="Página {{ $lastPage }}">{{ $lastPage }}</a>
                            </li>
                        @endif
                    </ol>

                    @if ($page < $lastPage)
                        <a class="pagination-step" href="{{ route('reservations.index', array_merge($pageQuery, ['page' => $page + 1])) }}" rel="next">Siguiente</a>
                    @else
                        <span class="pagination-step is-disabled" aria-disabled="true">Siguiente</span>
                    @endif

                    <span class="pagination-status">Página {{ $page }} de {{ $lastPage }}</span>
                </nav>
            @endif
        @endif
    </section>
@endsection

@push('scripts')
    <script src="{{ asset('js/reservations.js') }}" defer></script>
@endpush
