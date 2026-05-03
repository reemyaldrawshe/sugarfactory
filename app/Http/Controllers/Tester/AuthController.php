<?php

namespace App\Http\Controllers\Tester;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Auth\LoginRequest;
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
            if($data == 400){
                return Response::Error([], __('auth.not_authorized').__('auth.'.'admin'), 399);
            }
            return Response::Success($data, __('auth.login_success'));
        } catch (Throwable $th) {
            activity('Error: Admin Login')->log($th);
            return Response::Error($data, $th->getMessage());
        }
    }

    public function logout(): JsonResponse
    {
        $data = [];
        try {
            $data = $this->authService->logout();
            $message = __('auth.logout_success');
            return Response::Success($data, $message);

        } catch (Throwable $th) {
            // Log any exceptions or errors
            activity('Error: Admin Logout')->log($th);
            $message = $th->getMessage();
            return Response::Error($data, $message);
        }
    }
}

