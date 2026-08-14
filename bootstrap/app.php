<?php

use App\Http\Middleware\SetApiCacheHeaders;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Prepended so it wraps OUTSIDE HandleCors (registered further down
        // the default global stack). A CORS preflight OPTIONS request is
        // short-circuited by HandleCors before routing/route-group
        // middleware ever runs, so a route-scoped middleware would never
        // see it — this is the only way api/v1/* preflight responses get
        // X-MFG-API-Version and Access-Control-Expose-Headers stamped too.
        // The middleware itself no-ops for every non-api/v1 path.
        $middleware->prepend(SetApiCacheHeaders::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Return JSON for any request hitting api/* — regardless of Accept header.
        // This ensures browser requests to unmatched api/v1/* paths get the JSON
        // envelope rather than a Blade error page.
        $exceptions->shouldRenderJsonWhen(
            fn ($request, $throwable) => $request->is('api/*') || $request->expectsJson()
        );

        // Map unhandled throwables on api/v1/* to the house error envelope:
        // {"error":{"code":"...","message":"..."}}
        // Unmatched-route exceptions (NotFound, MethodNotAllowed) are handled
        // here because route-group middleware does not run for unmatched routes.
        // The X-MFG-API-Version header is set here for that same reason.
        $exceptions->render(function (\Throwable $throwable, $request): ?\Illuminate\Http\JsonResponse {
            if (!$request->is('api/v1') && !$request->is('api/v1/*')) {
                return null; // Let other paths use their own handling.
            }

            [$status, $code] = match (true) {
                $throwable instanceof NotFoundHttpException,
                $throwable instanceof ModelNotFoundException   => [404, 'NOT_FOUND'],
                $throwable instanceof MethodNotAllowedHttpException => [405, 'METHOD_NOT_ALLOWED'],
                $throwable instanceof ValidationException       => [$throwable->status, 'VALIDATION_ERROR'],
                $throwable instanceof HttpException             => [$throwable->getStatusCode(), 'HTTP_ERROR'],
                default                                        => [500, 'SERVER_ERROR'],
            };

            $message = $throwable->getMessage() ?: 'An error occurred.';

            return response()->json(
                ['error' => ['code' => $code, 'message' => $message]],
                $status
            )->header('X-MFG-API-Version', '1');
        });
    })->create();
