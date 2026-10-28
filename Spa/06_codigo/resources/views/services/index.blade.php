@extends('layouts.app')

@section('title', 'Servicios · Spa Cielo')
@section('description', 'Catálogo de servicios de la sede con filtros por nombre y estado.')

@php
    $hasFilters = $filters['q'] !== '' || $filters['estado'] !== null;
    $pageStart = max(1, $page - 2);
    $pageEnd = min($lastPage, $page + 2);
    $pageQuery = array_filter($filters, static fn ($value) => $value !== '' && $value !== null);
    $currencyLabel = $currency === 'BOB' ? 'Bs' : $currency;
@endphp

@section('content')
    <section class="services-page" aria-labelledby="services-heading">
        @if (session('status'))
            <div class="flash-success" role="status">
                <span class="flash-success-mark" aria-hidden="true">✓</span>
                <p>{{ session('status') }}</p>
            </div>
        @endif

        <header class="services-hero">
            <div class="services-hero-copy">
                <p class="eyebrow">Catálogo de la sede</p>
                <h1 id="services-heading">Servicios, en orden.</h1>
                <p class="services-lead">Consulta la duración, el precio y el estado del catálogo de {{ $branchName }}.</p>
            </div>

            <div class="services-hero-aside">
                <div class="services-count">
                    <span class="services-count-value">{{ $total }}</span>
                    <span class="services-count-label">{{ $hasFilters ? 'resultados' : 'servicios' }}</span>
                </div>
                <a class="button button-primary services-create-link" href="{{ route('services.create') }}">Nuevo servicio</a>
            </div>
        </header>

        <form class="service-filters" method="GET" action="{{ route('services.index') }}" role="search" aria-label="Filtrar servicios">
            <div class="field filter-field-search">
                <label for="q">Buscar por nombre</label>
                <input
                    id="q"
                    name="q"
                    type="search"
                    value="{{ $filters['q'] }}"
                    placeholder="Ej. masaje relajante"
                    maxlength="120"
                    autocomplete="off"
                >
            </div>

            <div class="field filter-field-state">
                <label for="estado">Estado</label>
                <div class="select-wrap">
                    <select id="estado" name="estado">
                        <option value="" @selected($filters['estado'] === null)>Todos</option>
                        <option value="activo" @selected($filters['estado'] === 'activo')>Activos</option>
                        <option value="inactivo" @selected($filters['estado'] === 'inactivo')>Inactivos</option>
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
                    <a class="button button-quiet" href="{{ route('services.index') }}">Limpiar</a>
                @endif
            </div>
        </form>

        <div class="services-results">
            <p class="services-result-count" aria-live="polite">
                @if ($total === 0)
                    Sin resultados
                @else
                    Mostrando {{ $from }}–{{ $to }} de {{ $total }} {{ $total === 1 ? 'servicio' : 'servicios' }}
                @endif
            </p>
            @if ($hasFilters)
                <p class="services-filter-note">Filtros aplicados: {{ $filters['q'] !== '' ? '“'.$filters['q'].'”' : 'todos los nombres' }} · {{ $filters['estado'] === null ? 'todos los estados' : ($filters['estado'] === 'activo' ? 'solo activos' : 'solo inactivos') }}</p>
            @endif
        </div>

        @if ($services->isEmpty())
            <div class="empty-state">
                <span class="empty-state-mark" aria-hidden="true">
                    <svg viewBox="0 0 48 48" fill="none">
                        <circle cx="24" cy="24" r="16" stroke="currentColor" stroke-width="1.5"/>
                        <path d="M18 27c3-6 7-9 12-9 1.6 0 3 .3 4.2 1" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                        <path d="M19 32c2.4-3.6 5.5-5.5 9.4-5.5 1.2 0 2.3.2 3.4.6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                    </svg>
                </span>
                <h2>No encontramos servicios.</h2>
                <p>Prueba con otro nombre o cambia el filtro de estado para ver el catálogo completo.</p>
                <a class="button button-quiet" href="{{ route('services.index') }}">Ver todos los servicios</a>
            </div>
        @else
            <div class="service-table-wrap" role="region" aria-label="Tabla de servicios de {{ $branchName }}, página {{ $page }} de {{ $lastPage }}" tabindex="0">
                <table class="service-table">
                    <caption class="sr-only">Servicios de {{ $branchName }}, página {{ $page }} de {{ $lastPage }}</caption>
                    <thead>
                        <tr>
                            <th scope="col">Servicio</th>
                            <th scope="col">Duración</th>
                            <th scope="col">Precio</th>
                            <th scope="col">Estado</th>
                            <th scope="col">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($services as $service)
                            <tr>
                                <th scope="row">
                                    <span class="service-name">{{ $service->nombre }}</span>
                                </th>
                                <td data-label="Duración">{{ $service->duracion_min }} min</td>
                                <td data-label="Precio">
                                    <span class="service-price">{{ $currencyLabel }}&nbsp;{{ number_format((float) $service->precio, 2, ',', '.') }}</span>
                                </td>
                                <td data-label="Estado">
                                    <span @class(['status-badge', 'status-active' => $service->estado === 'activo', 'status-inactive' => $service->estado !== 'activo'])>
                                        {{ $service->estado === 'activo' ? 'Activo' : 'Inactivo' }}
                                    </span>
                                </td>
                                <td data-label="Acciones">
                                    <a
                                        class="service-edit-link"
                                        href="{{ route('services.edit', $service->id) }}"
                                        aria-label="Editar {{ $service->nombre }}"
                                    >Editar <span aria-hidden="true">→</span></a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($lastPage > 1)
                <nav class="pagination" aria-label="Paginación del catálogo">
                    @if ($page > 1)
                        <a class="pagination-step" href="{{ route('services.index', array_merge($pageQuery, ['page' => $page - 1])) }}" rel="prev">Anterior</a>
                    @else
                        <span class="pagination-step is-disabled" aria-disabled="true">Anterior</span>
                    @endif

                    <ol class="pagination-pages">
                        @if ($pageStart > 1)
                            <li>
                                <a class="pagination-page" href="{{ route('services.index', array_merge($pageQuery, ['page' => 1])) }}" aria-label="Página 1">1</a>
                            </li>
                            @if ($pageStart > 2)
                                <li class="pagination-gap" aria-hidden="true">…</li>
                            @endif
                        @endif

                        @for ($number = $pageStart; $number <= $pageEnd; $number++)
                            <li>
                                <a
                                    @class(['pagination-page', 'is-current' => $number === $page])
                                    href="{{ route('services.index', array_merge($pageQuery, ['page' => $number])) }}"
                                    @if ($number === $page) aria-current="page" @endif
                                >{{ $number }}</a>
                            </li>
                        @endfor

                        @if ($pageEnd < $lastPage)
                            @if ($pageEnd < $lastPage - 1)
                                <li class="pagination-gap" aria-hidden="true">…</li>
                            @endif
                            <li>
                                <a class="pagination-page" href="{{ route('services.index', array_merge($pageQuery, ['page' => $lastPage])) }}" aria-label="Página {{ $lastPage }}">{{ $lastPage }}</a>
                            </li>
                        @endif
                    </ol>

                    @if ($page < $lastPage)
                        <a class="pagination-step" href="{{ route('services.index', array_merge($pageQuery, ['page' => $page + 1])) }}" rel="next">Siguiente</a>
                    @else
                        <span class="pagination-step is-disabled" aria-disabled="true">Siguiente</span>
                    @endif

                    <span class="pagination-status">Página {{ $page }} de {{ $lastPage }}</span>
                </nav>
            @endif
        @endif
    </section>
@endsection
