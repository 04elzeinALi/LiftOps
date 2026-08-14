<?php

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
    ->withMiddleware(function (Middleware $middleware): void {
    $middleware->alias([
        'role' => \App\Http\Middleware\EnsureUserHasRole::class,
    ]);

    // Laravel's own withMiddleware() setup registers
    // redirectGuestsTo(fn () => route('login')) before this callback ever
    // runs — a default aimed at an app with a login page. This one has none
    // (routes/web.php is just the framework welcome page; the frontend is a
    // separate app), so an unauthenticated request without an explicit
    // `Accept: application/json` header (a bare curl, a browser address bar)
    // crashed with "Route [login] not defined" the moment that closure ran,
    // instead of the 401 the request actually deserved. The app's own client
    // (axios) always sends that header, so real traffic never hit this — but
    // an API has no business 500ing over "you're not logged in" regardless of
    // who's asking.
    //
    // Returning null here is what "expects JSON" already gets: Authenticate
    // treats a null redirect target as "no redirect, just throw" (see
    // vendor Authenticate::unauthenticated()), so this makes that the
    // outcome unconditionally rather than only when the caller happened to
    // ask for JSON.
    $middleware->redirectGuestsTo(fn () => null);
})

    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
