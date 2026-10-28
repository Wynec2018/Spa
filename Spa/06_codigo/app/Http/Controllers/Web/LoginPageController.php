<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Exceptions\ApiException;
use App\Http\Requests\Web\LoginRequest;
use App\Repositories\BranchRepository;
use App\Services\AuthService;
use App\Support\BranchContext;
use App\Support\RequestId;
use App\Support\SessionAuth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class LoginPageController
{
    public function show(
        Request $request,
        AuthService $auth,
        BranchRepository $branches,
    ): View|RedirectResponse {
        if ($this->currentUser($request, $auth) !== null) {
            return redirect()->route('dashboard');
        }

        $branchId = BranchContext::id();
        $branch = $branches->find($branchId, RequestId::from($request));

        return view('auth.login', [
            'branchId' => $branchId,
            'branchName' => $branch?->nombre ?? 'Sucursal principal',
        ]);
    }

    public function store(LoginRequest $request, AuthService $auth): RedirectResponse
    {
        $branchId = BranchContext::id();

        if ((string) $request->validated('branch_id') !== $branchId) {
            return back()
                ->withErrors(['branch_id' => 'La sucursal seleccionada no es válida.'])
                ->withInput($request->only('contacto'));
        }

        try {
            $user = $auth->attempt(
                (string) $request->validated('contacto'),
                (string) $request->validated('password'),
                $branchId,
                RequestId::from($request),
            );
        } catch (ApiException $e) {
            if ($e->status === 401) {
                return back()
                    ->withErrors(['contacto' => 'El contacto o la contraseña no son correctos.'])
                    ->withInput($request->only('contacto', 'branch_id'));
            }

            if ($e->status === 403) {
                return back()
                    ->withErrors(['contacto' => 'Tu usuario está inactivo. Contacta a la administración.'])
                    ->withInput($request->only('contacto', 'branch_id'));
            }

            throw $e;
        }

        $request->session()->regenerate();
        $request->session()->put('auth_user_id', $user->id);
        $request->session()->put('auth_branch_id', $user->branch_id);
        $request->session()->put('auth_rol', $user->rol);

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function currentUser(Request $request, AuthService $auth): ?object
    {
        try {
            [$userId, $branchId] = SessionAuth::credentials($request);
        } catch (ApiException) {
            return null;
        }

        return $auth->current($userId, $branchId, RequestId::from($request));
    }
}
