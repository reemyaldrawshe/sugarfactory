<?php

use App\Http\Responses\Response;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )

    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);
    })

    ->withExceptions(function (Exceptions $exceptions): void {

        $exceptions->render(function (Throwable $e, Request $request): JsonResponse|null {
            if ($e instanceof AuthorizationException || $e instanceof AccessDeniedHttpException) {
                $locale = auth()->user()['lang'] ?? $request->header('Accept-Language', 'ar');
                app()->setLocale($locale);
                return Response::error('', __('auth.no_permission'), 403);
            }
            // Let Laravel handle other exceptions
            return null;
        });

        $exceptions->render(function (Throwable $e, Request $request): JsonResponse|null {
            if ($e instanceof AuthenticationException ) {
                $locale = auth()->user()['lang'] ?? $request->header('Accept-Language', 'ar');
                app()->setLocale($locale);
                return Response::error('', __('auth.no_token'), 401);
            }
            // Let Laravel handle other exceptions
            return null;
        });

    })

    ->create();
