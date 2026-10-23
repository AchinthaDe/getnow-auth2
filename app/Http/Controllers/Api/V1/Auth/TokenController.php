<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domain\Accounts\Contracts\TokenIssuer;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RefreshTokenRequest;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

final class TokenController extends Controller
{
    public function __construct(
        private readonly TokenIssuer $tokenIssuer,
    ) {}

    public function refresh(RefreshTokenRequest $request): JsonResponse
    {
        try {
            $tokenPair = $this->tokenIssuer->refresh((string) $request->validated('refresh_token'));
        } catch (AuthenticationException) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        return response()->json([
            'data' => [
                'tokens' => $tokenPair->toArray(),
            ],
        ], 200);
    }

    public function logout(): Response
    {
        $user = Auth::guard('api')->user();

        if (! $user instanceof User) {
            throw new AuthenticationException('Unauthenticated.');
        }

        $this->tokenIssuer->revoke($user);

        return response()->noContent();
    }
}
