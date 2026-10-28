<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

final class ServiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'duracion_min' => (int) $this->duracion_min,
            'precio' => number_format((float) $this->precio, 2, '.', ''),
            'estado' => $this->estado,
            'created_at' => Carbon::parse($this->created_at)->utc()->toIso8601ZuluString(),
            'updated_at' => Carbon::parse($this->updated_at)->utc()->toIso8601ZuluString(),
        ];
    }
}
