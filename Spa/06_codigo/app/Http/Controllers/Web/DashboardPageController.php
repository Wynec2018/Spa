<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Exceptions\ApiException;
use App\Repositories\BranchRepository;
use App\Repositories\DashboardRepository;
use App\Services\AuthService;
use App\Support\RequestId;
use App\Support\SessionAuth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class DashboardPageController
{
    public function index(
        Request $request,
        AuthService $auth,
        BranchRepository $branches,
        DashboardRepository $dashboard,
    ): View|RedirectResponse {
        try {
            [$userId, $branchId] = SessionAuth::credentials($request);
        } catch (ApiException) {
            return redirect()->route('login');
        }

        $requestId = RequestId::from($request);
        $user = $auth->current($userId, $branchId, $requestId);

        if ($user === null) {
            $request->session()->invalidate();

            return redirect()->route('login');
        }

        $branch = $branches->find($branchId, $requestId);

        return view('dashboard', [
            'user' => $user,
            'branchName' => $branch?->nombre ?? 'Sede principal',
            'summary' => $dashboard->summary($branchId, $requestId, $userId),
        ]);
    }
}
