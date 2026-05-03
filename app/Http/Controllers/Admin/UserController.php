<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\User\StoreRequest;
use App\Http\Requests\Admin\User\UpdateRequest;
use App\Http\Requests\Admin\User\IndexRequest;
use App\Http\Responses\Response;
use App\Models\User;
use App\Services\Admin\UserService;
use Illuminate\Http\JsonResponse;
use Throwable;

class UserController extends Controller
{
    private UserService $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    public function index(IndexRequest $request): JsonResponse
    {
        try {
            $filters = $request->validated();
            $users = $this->userService->list($request, $filters);

            return Response::Success(
                $users,
                __('user.index')
            );
        } catch (Throwable $e) {
            activity('Error: Admin User Index')->log($e);
            return Response::Error(
                [],
                __('user.index_error')
            );
        }
    }

    public function store(StoreRequest $request): JsonResponse
    {
        try {
            $user = $this->userService->create($request->validated());

            return Response::Success(
                $user,
                __('user.created')
            );
        } catch (Throwable $e) {
            activity('Error: Admin User Store')->log($e);
            return Response::Error(
                [],
                __('user.create_error')
            );
        }
    }

    public function show(User $user): JsonResponse
    {
        try {
            $user = $this->userService->show($user);

            return Response::Success(
                $user,
                __('user.found')
            );
        } catch (Throwable $e) {
            activity('Error: Admin User Show')->log($e);
            return Response::Error(
                [],
                __('user.not_found')
            );
        }
    }

    public function update(UpdateRequest $request, User $user): JsonResponse
    {
        try {
            $user = $this->userService->update($user, $request->validated());

            return Response::Success(
                $user,
                __('user.updated')
            );
        } catch (Throwable $e) {
            activity('Error: Admin User Update')->log($e);
            return Response::Error(
                [],
                __('user.update_error')
            );
        }
    }

    public function destroy(User $user): JsonResponse
    {
        try {
            return Response::Success(
                $this->userService->delete($user),
                __('user.deleted')
            );
        } catch (Throwable $e) {
            activity('Error: Admin User Destroy')->log($e);
            return Response::Error(
                [],
                $e->getMessage()
            );
        }
    }
}

