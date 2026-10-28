@extends('layouts.app')

@section('title', 'Nueva reserva — Spa Cielo')
@section('description', 'Alta de una reserva para un cliente existente de la sede.')

@php
    $hasErrors = $errors->any();
    $selectedService = old('service_id');
@endphp

@section('content')
    <section class="service-create reservation-create" aria-labelledby="reservation-create-heading">
        <header class="service-create-hero">
            <a class="back-link" href="{{ route('reservations.index') }}">
                <span aria-hidden="true">←</span>
                Volver a la agenda
            </a>
            <p class="eyebrow">Agenda / Nueva reserva</p>
            <h1 id="reservation-create-heading">Una reserva nueva.</h1>
            <p class="service-create-lead">Elige un cliente existente, el servicio y los recursos de {{ $branchName }}. El fin se calcula con la duración del servicio y se muestra antes de guardar.</p>
        </header>

        <div class="service-create-layout">
            <div class="service-form-card">
                <div
                    id="reservation-form-status"
                    class="form-status"
                    data-reservation-status
                    role="alert"
                    aria-atomic="true"
                    tabindex="-1"
                    @if (! $hasErrors) hidden @endif
                >
                    @if ($hasErrors)
                        <p class="form-status-title">No pudimos crear la reserva</p>
                        <p>Revisa los campos marcados e inténtalo de nuevo.</p>
                        @if ($errors->has('reserva'))
                            <p class="form-status-detail">{{ $errors->first('reserva') }}</p>
                        @endif
                    @endif
                </div>

                <form class="service-form reservation-form" method="POST" action="{{ route('reservations.store') }}" data-reservation-form novalidate>
                    @csrf

                    <div class="field">
                        <label for="client_id">Cliente</label>
                        <div class="select-wrap">
                            <select
                                id="client_id"
                                name="client_id"
                                aria-describedby="client-hint @error('client_id') client-error @enderror"
                                @error('client_id') aria-invalid="true" @enderror
                                @if (! $hasErrors) autofocus @endif
                                required
                            >
                                <option value="">Selecciona un cliente</option>
                                @foreach ($options['clients'] as $client)
                                    <option value="{{ $client->id }}" @selected(old('client_id') === $client->id)>
                                        {{ $client->nombre }}@if ($client->contacto) — {{ $client->contacto }}@endif
                                    </option>
                                @endforeach
                            </select>
                            <span class="select-chevron" aria-hidden="true">
                                <svg viewBox="0 0 16 16" fill="none">
                                    <path d="m4 6 4 4 4-4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </span>
                        </div>
                        <p class="field-hint" id="client-hint">Solo clientes ya registrados en {{ $branchName }}. El alta de clientes está fuera de esta fase.</p>
                        @error('client_id')
                            <p class="field-error" id="client-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="field">
                        <label for="service_id">Servicio</label>
                        <div class="select-wrap">
                            <select
                                id="service_id"
                                name="service_id"
                                data-reservation-service
                                aria-describedby="service-hint @error('service_id') service-error @enderror"
                                @error('service_id') aria-invalid="true" @enderror
                                required
                            >
                                <option value="">Selecciona un servicio</option>
                                @foreach ($options['services'] as $service)
                                    <option
                                        value="{{ $service->id }}"
                                        data-duration="{{ $service->duracion_min }}"
                                        data-name="{{ $service->nombre }}"
                                        @selected($selectedService === $service->id)
                                    >
                                        {{ $service->nombre }} — {{ $service->duracion_min }} min
                                    </option>
                                @endforeach
                            </select>
                            <span class="select-chevron" aria-hidden="true">
                                <svg viewBox="0 0 16 16" fill="none">
                                    <path d="m4 6 4 4 4-4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </span>
                        </div>
                        <p class="field-hint" id="service-hint">Solo aparecen los servicios activos. La duración determina el fin de la reserva.</p>
                        @error('service_id')
                            <p class="field-error" id="service-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="service-form-row">
                        <div class="field">
                            <label for="cama_id">Cama</label>
                            <div class="select-wrap">
                                <select
                                    id="cama_id"
                                    name="cama_id"
                                    aria-describedby="cama-hint @error('cama_id') cama-error @enderror"
                                    @error('cama_id') aria-invalid="true" @enderror
                                    required
                                >
                                    <option value="">Selecciona una cama</option>
                                    @foreach ($options['camas'] as $cama)
                                        <option value="{{ $cama->id }}" @selected(old('cama_id') === $cama->id) @disabled($cama->estado === 'no_disponible')>
                                            {{ $cama->nombre }}@if ($cama->estado === 'no_disponible') — No disponible @endif
                                        </option>
                                    @endforeach
                                </select>
                                <span class="select-chevron" aria-hidden="true">
                                    <svg viewBox="0 0 16 16" fill="none">
                                        <path d="m4 6 4 4 4-4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </span>
                            </div>
                            <p class="field-hint" id="cama-hint">Las camas no disponibles no se pueden seleccionar.</p>
                            @error('cama_id')
                                <p class="field-error" id="cama-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="field">
                            <label for="trabajadora_id">Trabajadora</label>
                            <div class="select-wrap">
                                <select
                                    id="trabajadora_id"
                                    name="trabajadora_id"
                                    aria-describedby="trabajadora-hint @error('trabajadora_id') trabajadora-error @enderror"
                                    @error('trabajadora_id') aria-invalid="true" @enderror
                                    required
                                >
                                    <option value="">Selecciona una trabajadora</option>
                                    @foreach ($options['trabajadoras'] as $trabajadora)
                                        <option value="{{ $trabajadora->id }}" @selected(old('trabajadora_id') === $trabajadora->id) @disabled($trabajadora->estado !== 'libre')>
                                            {{ $trabajadora->nombre }}@if ($trabajadora->estado === 'ocupada') — Ocupada @elseif ($trabajadora->estado === 'no_disponible') — No disponible @endif
                                        </option>
                                    @endforeach
                                </select>
                                <span class="select-chevron" aria-hidden="true">
                                    <svg viewBox="0 0 16 16" fill="none">
                                        <path d="m4 6 4 4 4-4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </span>
                            </div>
                            <p class="field-hint" id="trabajadora-hint">Solo se pueden elegir trabajadoras libres.</p>
                            @error('trabajadora_id')
                                <p class="field-error" id="trabajadora-error">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="service-form-row reservation-time-row">
                        <div class="field">
                            <label for="inicio">Inicio (UTC)</label>
                            <input
                                id="inicio"
                                name="inicio"
                                type="datetime-local"
                                value="{{ old('inicio') }}"
                                step="60"
                                data-reservation-start
                                aria-describedby="inicio-hint @error('inicio') inicio-error @enderror"
                                @error('inicio') aria-invalid="true" @enderror
                                required
                            >
                            <p class="field-hint" id="inicio-hint">Se interpreta como UTC. Ejemplo: 01/03/2030, 10:00.</p>
                            @error('inicio')
                                <p class="field-error" id="inicio-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="field">
                            <span class="field-label">Fin calculado</span>
                            <output
                                id="fin-preview"
                                class="reservation-fin-preview"
                                for="service_id inicio"
                                data-reservation-end
                                aria-live="polite"
                            >Selecciona un servicio y un inicio.</output>
                            <p class="field-hint" id="fin-hint" data-reservation-duration>El fin se calcula con la duración del servicio.</p>
                        </div>
                    </div>

                    <div class="service-form-actions">
                        <button class="button button-primary" type="submit" data-reservation-submit>
                            <span data-reservation-submit-label>Crear reserva</span>
                            <span class="button-spinner" data-reservation-spinner aria-hidden="true" hidden></span>
                        </button>
                        <a class="button button-quiet" href="{{ route('reservations.index') }}">Cancelar</a>
                    </div>
                </form>
            </div>

            <aside class="service-aside reservation-aside" aria-labelledby="reservation-aside-heading">
                <p class="eyebrow">Antes de guardar</p>
                <h2 id="reservation-aside-heading">La agenda queda reservada.</h2>
                <ul>
                    <li>La reserva se crea en estado <strong>pendiente</strong>.</li>
                    <li>El <strong>fin</strong> se calcula con la duración del servicio; no se edita a mano.</li>
                    <li>La cama y la trabajadora no pueden solaparse con otra reserva activa.</li>
                    <li>El alta de clientes y los pagos están fuera de esta fase.</li>
                </ul>
            </aside>
        </div>
    </section>
@endsection

@push('scripts')
    <script src="{{ asset('js/reservations.js') }}" defer></script>
@endpush
