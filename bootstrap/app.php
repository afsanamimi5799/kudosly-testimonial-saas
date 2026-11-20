<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Stripe posts to the webhook from outside the application, so it
        // carries no session and no CSRF token. The request is authenticated
        // by the signature of the payload instead, which the controller
        // checks before it reads anything.
        $middleware->validateCsrfTokens(except: [
            'stripe/webhook',
        ]);

        // The signature covers the exact bytes Stripe sent, so the payload
        // must arrive unchanged. Trimming and converting empty strings would
        // alter it and the signature would no longer match.
        $middleware->trimStrings(except: [
            'stripe/webhook',
        ]);

        $middleware->convertEmptyStringsToNull(except: [
            fn (Request $request) => $request->is('stripe/webhook'),
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
