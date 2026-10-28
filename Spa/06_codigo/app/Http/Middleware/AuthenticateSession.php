<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use App\Services\AuthService;
use App\Support\RequestId;
use App\Support\SessionAuth;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AuthenticateSession
{
    public const USER_ATTRIBUTE = 'auth_user';

    public const BRANCH_ATTRIBUTE = 'auth_branch_id';

    public const REQUEST_ID_ATTRIBUTE = 'auth_request_id';

    public function __construct(private readonly AuthService $auth) {}

    public function handle(Request $request, Closure $next): Response
    {
        $requestId = RequestId::from($request);
        [$userId, $branchId] = SessionAuth::credentials($request);
        $user = $this->auth->current($userId, $branchId, $requestId);

        if ($user === null) {
            $request->session()->invalidate();
            throw ApiException::unauthenticated();
        }

        $request->attributes->set(self::USER_ATTRIBUTE, $user);
        $request->attributes->set(self::BRANCH_ATTRIBUTE, $branchId);
        $request->attributes->set(self::REQUEST_ID_ATTRIBUTE, $requestId);

        return $next($request);
    }
}
