<?php

use App\Exceptions\ProblemDetails;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Semua ProblemDetails yang dilempar controller dirender seragam:
        // bodi RFC 7807 dengan Content-Type application/problem+json.
        $exceptions->render(function (ProblemDetails $e, Request $request) {
            return response()->json(
                $e->toArray($request->getRequestUri()),
                $e->getStatusCode(),
                ['Content-Type' => 'application/problem+json'],
            );
        });

        // Validasi input bawaan Laravel (422) juga dipakaikan ke format yang sama,
        // supaya tidak ada endpoint yang menjawab dengan bentuk berbeda.
        // Callback ini hanya mengambil alih panggilan API; panggilan web dibiarkan bawaan.
        $exceptions->render(function (ValidationException $e, Request $request) {
            if (! ($request->is('api/*') || $request->expectsJson())) {
                return null;
            }

            return response()->json([
                'type' => ProblemDetails::ISIAN_TIDAK_VALID,
                'title' => 'Isian Permintaan Tidak Valid',
                'status' => 422,
                'detail' => 'Beberapa isian tidak memenuhi aturan. Periksa medan `errors`.',
                'instance' => $request->getRequestUri(),
                'errors' => $e->errors(),
            ], 422, ['Content-Type' => 'application/problem+json']);
        });
    })->create();
