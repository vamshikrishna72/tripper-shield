<?php

use App\Exceptions\InvalidCalculationException;
use App\Exceptions\InvalidStateTransitionException;
use App\Exceptions\MockInsurerTimeoutException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (InvalidStateTransitionException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'error_type' => 'INVALID_STATE_TRANSITION',
                    'message' => $e->getMessage(),
                    'from_state' => $e->fromState->value,
                    'to_state' => $e->toState->value,
                ], 422);
            }
        });

        $exceptions->render(function (InvalidCalculationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'error_type' => 'INVALID_CALCULATION_INPUT',
                    'message' => $e->getMessage(),
                ], 422);
            }
        });

        $exceptions->render(function (MockInsurerTimeoutException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'error_type' => 'INSURER_GATEWAY_TIMEOUT',
                    'message' => $e->getMessage(),
                ], 504);
            }
        });
    })->create();
