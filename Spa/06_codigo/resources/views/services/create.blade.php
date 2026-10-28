@extends('layouts.app')

@section('title', 'Nuevo servicio · Spa Cielo')
@section('description', 'Alta de un servicio del catálogo de la sede.')

@php
    $hasErrors = $errors->any();
    $currencyLabel = $currency === 'BOB' ? 'Bs' : $currency;
@endphp

@section('content')
    <section class="service-create" aria-labelledby="create-heading">
        <header class="service-create-hero">
            <a class="back-link" href="{{ route('services.index') }}">
                <span aria-hidden="true">←</span>
                Volver al catálogo
            </a>
            <p class="eyebrow">Catálogo / Nuevo servicio</p>
            <h1 id="create-heading">Un servicio nuevo.</h1>
            <p class="service-create-lead">Completa los datos básicos de {{ $branchName }}. El servicio quedará disponible para nuevas reservas.</p>
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
                        <p class="form-status-title">No pudimos crear el servicio</p>
                        <p>Revisa los campos marcados e inténtalo de nuevo.</p>
                    @endif
                </div>

                <form class="service-form" method="POST" action="{{ route('services.store') }}" data-service-form novalidate>
                    @csrf

                    <div class="field">
                        <label for="nombre">Nombre del servicio</label>
                        <input
                            id="nombre"
                            name="nombre"
                            type="text"
                            value="{{ old('nombre') }}"
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
                                    value="{{ old('duracion_min') }}"
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
                                    value="{{ old('precio') }}"
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
                            <span data-service-submit-label>Crear servicio</span>
                            <span class="button-spinner" data-service-spinner aria-hidden="true" hidden></span>
                        </button>
                        <a class="button button-quiet" href="{{ route('services.index') }}">Cancelar</a>
                    </div>
                </form>
            </div>

            <aside class="service-aside" aria-labelledby="service-aside-heading">
                <p class="eyebrow">Antes de guardar</p>
                <h2 id="service-aside-heading">Quedará disponible.</h2>
                <ul>
                    <li>El servicio se crea como <strong>activo</strong> y puede usarse en reservas.</li>
                    <li>El nombre debe ser único dentro de {{ $branchName }}.</li>
                    <li>La duración y el precio se guardan con dos decimales de precisión.</li>
                </ul>
            </aside>
        </div>
    </section>
@endsection

@push('scripts')
    <script src="{{ asset('js/services.js') }}" defer></script>
@endpush
