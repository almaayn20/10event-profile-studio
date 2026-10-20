<?php

use App\Exceptions\UserFacingException;
use App\Http\Middleware\EnsureStudioAccess;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // behind Cloudflare: the visitor's address comes from its header, and only when the request really came through it
        $middleware->trustProxies(
            at: (require __DIR__.'/../config/cloudflare.php')['ranges'],
            headers: Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PROTO,
        );
        $middleware->web(append: [HandleInertiaRequests::class]);
        $middleware->alias(['studio' => EnsureStudioAccess::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // expected, actionable errors → back with a translated flash (or JSON for fetch requests)
        $exceptions->render(function (UserFacingException $e, Request $request) {
            $payload = ['code' => $e->errorCode, 'params' => $e->params];
            if ($request->expectsJson() && ! $request->header('X-Inertia')) {
                return response()->json(['error' => $payload + ['message' => __('errors.'.$e->errorCode.'.message', $e->params)]], $e->status);
            }

            return back()->with('error', $payload);
        });

        // everything else: a friendly page in production with a reference code; the full trace stays in the log
        $exceptions->respond(function ($response, Throwable $e, Request $request) {
            $status = $response->getStatusCode();
            if ($e instanceof ValidationException || app()->hasDebugModeEnabled() && $status >= 500) {
                return $response;
            }
            if ($status === 419) {
                return back()->with('error', ['code' => 'session_expired']);
            }
            if (! in_array($status, [403, 404, 413, 429, 500, 503], true)) {
                return $response;
            }
            $code = [403 => 'forbidden', 404 => 'not_found', 413 => 'file_too_large', 429 => 'too_many_requests', 500 => 'generic', 503 => 'maintenance'][$status];
            $ref = strtoupper(Str::random(8));
            if ($status >= 500) {
                logger()->error('ref '.$ref.': '.$e->getMessage(), ['exception' => $e]);
            }

            return Inertia::render('Error', ['status' => $status, 'code' => $code, 'ref' => $ref])->toResponse($request)->setStatusCode($status);
        });
    })->create();
