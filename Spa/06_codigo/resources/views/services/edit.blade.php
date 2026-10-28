@extends('layouts.app')

@section('title', 'Editar servicio · Spa Cielo')
@section('description', 'Edición e inactivación de un servicio del catálogo de la sede.')

@php
    $hasErrors = $errors->any();
    $currencyLabel = $currency === 'BOB' ? 'Bs' : $currency;
    $isActive = $service->estado === 'activo';
    $priceValue = old('precio', number_format((float) $service->precio, 2, '.', ''));
@endphp

@section('content')
    <section class="service-create service-edit" aria-labelledby="edit-heading">
        <header class="service-create-hero">
            <a class="back-link" href="{{ route('services.index') }}">
                <span aria-hidden="true">←</span>
                Volver al catálogo
            </a>
            <p class="eyebrow">Catálogo / Editar servicio</p>
            <h1 id="edit-heading">Ajustar un servicio.</h1>
            <p class="service-create-lead">Actualiza los datos de {{ $service->nombre }} en {{ $branchName }}. Los cambios se aplican al catálogo de la sede.</p>
        </header>

        <div class="service-create-layout">
            <div class="service-form-card">
                <div
                    id="service-form-status"
                    class="form-status"
                    data-service-status
                    role="alert"
                    aria-atomic="true"
                    tabindex="-1"
                    @if (! $hasErrors) hidden @endif
                >
                    @if ($hasErrors)
                        <p class="form-status-title">No pudimos guardar los cambios</p>
                        <p>Revisa los campos marcados e inténtalo de nuevo.</p>
                    @endif
                </div>

                <form
                    class="service-form"
                    method="POST"
                    action="{{ route('services.update', $service->id) }}"
                    data-service-form
                    data-service-loading-label="Guardando…"
                    novalidate
                >
                    @csrf
                    @method('PUT')

                    <div class="field">
                        <label for="nombre">Nombre del servicio</label>
                        <input
                            id="nombre"
                            name="nombre"
                            type="text"
                            value="{{ old('nombre', $service->nombre) }}"
                            maxlength="120"
                            autocomplete="off"
                            placeholder="Ej. Masaje relajante"
                            aria-describedby="nombre-hint @error('nombre') nombre-error @enderror"
                            @error('nombre') aria-invalid="true" @enderror
                            @if (! $hasErrors) autofocus @endif
                            required
                        >
                        <p class="field-hint" id="nombre-hint">Un nombre claro y único en la sede, de hasta 120 caracteres.</p>
                        @error('nombre')
                            <p class="field-error" id="nombre-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="service-form-row">
                        <div class="field">
                            <label for="duracion_min">Duración</label>
                            <div class="input-suffix">
                                <input
                                    id="duracion_min"
                                    name="duracion_min"
                                    type="number"
                                    value="{{ old('duracion_min', $service->duracion_min) }}"
                                    min="1"
                                    max="1440"
                                    step="1"
                                    inputmode="numeric"
                                    placeholder="60"
                                    aria-describedby="duracion-hint @error('duracion_min') duracion-error @enderror"
                                    @error('duracion_min') aria-invalid="true" @enderror
                                    required
                                >
                                <span aria-hidden="true">min</span>
                            </div>
                            <p class="field-hint" id="duracion-hint">Entre 1 y 1440 minutos.</p>
                            @error('duracion_min')
                                <p class="field-error" id="duracion-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="field">
                            <label for="precio">Precio</label>
                            <div class="input-prefix">
                                <span aria-hidden="true">{{ $currencyLabel }}</span>
                                <input
                                    id="precio"
                                    name="precio"
                                    type="text"
                                    value="{{ $priceValue }}"
                                    inputmode="decimal"
                                    autocomplete="off"
                                    placeholder="250.00"
                                    aria-describedby="precio-hint @error('precio') precio-error @enderror"
                                    @error('precio') aria-invalid="true" @enderror
                                    required
                                >
                            </div>
                            <p class="field-hint" id="precio-hint">Máximo dos decimales. Puedes usar coma o punto.</p>
                            @error('precio')
                                <p class="field-error" id="precio-error">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="service-form-actions">
                        <button class="button button-primary" type="submit" data-service-submit>
                            <span data-service-submit-label>Guardar cambios</span>
                            <span class="button-spinner" data-service-spinner aria-hidden="true" hidden></span>
                        </button>
                        <a class="button button-quiet" href="{{ route('services.index') }}">Cancelar</a>
                    </div>
                </form>
            </div>

            <div class="service-edit-aside">
                <aside id="service-status" @class(['service-aside', 'service-status-aside', 'is-inactive' => ! $isActive]) aria-labelledby="service-status-heading">
                    <p class="eyebrow">Estado actual</p>
                    <h2 id="service-status-heading">{{ $isActive ? 'Disponible en el catálogo.' : 'Fuera del catálogo.' }}</h2>
                    <p class="service-status-badge-line">
                        <span @class(['status-badge', 'status-active' => $isActive, 'status-inactive' => ! $isActive])>
                            {{ $isActive ? 'Activo' : 'Inactivo' }}
                        </span>
                    </p>
                    <p class="service-status-copy">
                        @if ($isActive)
                            Puede usarse en nuevas reservas hasta que lo inactives.
                        @else
                            No se ofrece para nuevas reservas. Editarlo no cambia su estado.
                        @endif
                    </p>
                    <dl class="service-status-list">
                        <div>
                            <dt>Sede</dt>
                            <dd>{{ $branchName }}</dd>
                        </div>
                        <div>
                            <dt>Duración</dt>
                            <dd>{{ $service->duracion_min }} min</dd>
                        </div>
                        <div>
                            <dt>Precio</dt>
                            <dd>{{ $currencyLabel }}&nbsp;{{ number_format((float) $service->precio, 2, ',', '.') }}</dd>
                        </div>
                    </dl>
                </aside>

                @if ($isActive)
                    <section class="service-danger-zone" aria-labelledby="inactivate-heading">
                        <p class="eyebrow">Zona sensible</p>
                        <h2 id="inactivate-heading">Inactivar servicio.</h2>
                        <p>Dejará de estar disponible para nuevas reservas. El registro y su historial se conservan.</p>

                        <details class="inactivate-disclosure">
                            <summary class="button button-danger-quiet" aria-label="Inactivar el servicio {{ $service->nombre }}">Inactivar servicio</summary>
                            <div class="inactivate-panel">
                                <p><strong>¿Confirmas la inactivación?</strong> No se borrará el servicio ni sus datos.</p>
                                <form method="POST" action="{{ route('services.deactivate', $service->id) }}" data-inactivate-form>
                                    @csrf
                                    <div class="inactivate-actions">
                                        <button class="button button-danger" type="submit" aria-label="Confirmar inactivación de {{ $service->nombre }}" data-inactivate-submit>
                                            <span data-inactivate-label>Confirmar inactivación</span>
                                            <span class="button-spinner" data-inactivate-spinner aria-hidden="true" hidden></span>
                                        </button>
                                        <a class="button button-quiet" href="{{ route('services.edit', $service->id) }}#service-status">Cancelar</a>
                                    </div>
                                </form>
                            </div>
                        </details>
                    </section>
                @else
                    <section class="service-inactive-panel" aria-labelledby="inactive-heading">
                        <p class="eyebrow">Servicio inactivo</p>
                        <h2 id="inactive-heading">Sin reactivación en esta fase.</h2>
                        <p>Puedes corregir sus datos, pero seguirá fuera del catálogo hasta que exista un flujo de reactivación.</p>
                    </section>
                @endif
            </div>
        </div>
    </section>
@endsection

@push('scripts')
    <script src="{{ asset('js/services.js') }}" defer></script>
@endpush
