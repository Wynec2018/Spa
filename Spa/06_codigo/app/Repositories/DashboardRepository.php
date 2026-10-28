<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\DatabaseContext;
use Illuminate\Support\Facades\DB;

final class DashboardRepository
{
    public function summary(string $branchId, string $requestId, string $userId): array
    {
        return DatabaseContext::run(function () use ($branchId): array {
            $services = DB::table('catalog.service')
                ->where('branch_id', $branchId)
                ->select('estado')
                ->selectRaw('count(*) as total')
                ->groupBy('estado')
                ->pluck('total', 'estado');

            $bookings = DB::table('booking.booking')
                ->where('branch_id', $branchId)
                ->select('estado')
                ->selectRaw('count(*) as total')
                ->groupBy('estado')
                ->pluck('total', 'estado');

            return [
                'services_active' => (int) ($services['activo'] ?? 0),
                'services_inactive' => (int) ($services['inactivo'] ?? 0),
                'services_total' => (int) $services->sum(),
                'bookings_pending' => (int) ($bookings['pendiente'] ?? 0),
                'bookings_cancelled' => (int) ($bookings['cancelada'] ?? 0),
                'bookings_total' => (int) $bookings->sum(),
            ];
        }, branchId: $branchId, userId: $userId, correlationId: $requestId);
    }
}
