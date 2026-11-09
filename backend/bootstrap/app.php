<?php

use App\Exceptions\BusinessRuleException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias(['role' => \App\Http\Middleware\EnsureRole::class]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $json = fn (Request $r) => $r->is('api/*') || $r->expectsJson();
        $fail = fn (string $message, int $status, array $errors = []) => response()->json(
            array_filter(['success' => false, 'message' => $message, 'errors' => $errors ?: null], fn ($v) => $v !== null), $status);

        $exceptions->render(function (ValidationException $e, Request $r) use ($json, $fail) {
            if ($json($r)) return $fail(collect($e->errors())->flatten()->first() ?? 'Validation failed', 422, $e->errors());
        });
        $exceptions->render(function (BusinessRuleException $e, Request $r) use ($json, $fail) {
            if ($json($r)) return $fail($e->getMessage(), $e->status, $e->errors);
        });
        $exceptions->render(function (AuthenticationException $e, Request $r) use ($json, $fail) {
            if ($json($r)) return $fail('Please log in to continue.', 401);
        });
        $exceptions->render(function (AuthorizationException $e, Request $r) use ($json, $fail) {
            if ($json($r)) return $fail('You are not allowed to perform this action.', 403);
        });
        $exceptions->render(function (NotFoundHttpException $e, Request $r) use ($json, $fail) {
            if ($json($r)) return $fail($e->getPrevious() instanceof ModelNotFoundException ? 'Record not found.' : ($e->getMessage() ?: 'Not found.'), 404);
        });
        $exceptions->render(function (HttpExceptionInterface $e, Request $r) use ($json, $fail) {
            if ($json($r)) return $fail($e->getMessage() ?: 'Request failed.', $e->getStatusCode());
        });
    })->create();
