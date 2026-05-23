<?php

namespace App\Http\Controllers\Production;

use App\Http\Controllers\Controller;
use App\Http\Requests\Production\Auth\LoginRequest;
use App\Http\Responses\Response;
use App\Services\Admin\AuthService;
use Illuminate\Http\JsonResponse;
use Throwable;

class AuthController extends Controller
{
    public function __construct(private readonly AuthService $authService) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $data = [];

        try {
            $data = $this->authService->login($request->validated());

            if ($data == 400) {
                return Response::Error([], __('auth.not_authorized').' production', 399);
            }

            return Response::Success($data, 'تم تسجيل الدخول (الإنتاج)');

        } catch (Throwable $th) {
            activity('Error: Production Login')->log($th);
            return Response::Error($data, $th->getMessage());
        }
    }

    public function logout(): JsonResponse
    {
        $data = [];

        try {
            $data = $this->authService->logout();

            return Response::Success($data, 'تم تسجيل الخروج (الإنتاج)');

        } catch (Throwable $th) {
            activity('Error: Production Logout')->log($th);
            return Response::Error($data, $th->getMessage());
        }
    }
}
