<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\LoginRequest;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends ApiController
{
    public function __construct(private readonly AuthService $service) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->service->login(
            $request->validated(),
            $request->ip() ?? 'unknown'
        );

        return $this->respond($result, ['status' => 'authenticated']);
    }

    public function me(Request $request): JsonResponse
    {
        return $this->respond($request->user(), ['total' => 1]);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->service->logout($request->user());

        return $this->respond(null, ['status' => 'logged_out']);
    }
}