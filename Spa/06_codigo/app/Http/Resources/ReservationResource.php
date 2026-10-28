<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

final class ReservationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'inicio' => Carbon::parse($this->inicio)->utc()->toIso8601ZuluString(),
            'fin' => Carbon::parse($this->fin)->utc()->toIso8601ZuluString(),
            'estado' => $this->estado,
            'cliente' => [
                'id' => $this->client_id,
                'nombre' => $this->client_nombre,
                'contacto' => $this->client_contacto,
            ],
            'servicio' => [
                'id' => $this->service_id,
                'nombre' => $this->service_nombre,
                'duracion_min' => (int) $this->service_duracion_min,
                'precio' => number_format((float) $this->service_precio, 2, '.', ''),
            ],
            'cama' => [
                'id' => $this->cama_id,
                'nombre' => $this->cama_nombre,
            ],
            'trabajadora' => [
                'id' => $this->trabajadora_id,
                'nombre' => $this->trabajadora_nombre,
            ],
            'created_at' => Carbon::parse($this->created_at)->utc()->toIso8601ZuluString(),
            'updated_at' => Carbon::parse($this->updated_at)->utc()->toIso8601ZuluString(),
        ];
    }
}
