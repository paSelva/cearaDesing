<?php

use App\Http\Middleware\AddSecurityHeaders;
use App\Http\Middleware\EnsureUserHasRole;
use App\Support\RoleHome;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(AddSecurityHeaders::class);
        // Só o domínio de APP_URL: outro Host recebe 400 (não vale em local nem nos testes).
        $middleware->trustHosts(
            at: fn () => ['^'.preg_quote((string) parse_url((string) config('app.url'), PHP_URL_HOST)).'$'],
            subdomains: false,
        );
        $middleware->redirectGuestsTo(fn (Request $request) => RoleHome::loginFor($request));
        $middleware->redirectUsersTo(fn (Request $request) => RoleHome::for($request->user()?->role));
        $middleware->alias(['role' => EnsureUserHasRole::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Arquivo acima do post_max_size do PHP: o corpo é descartado antes da validação.
        $exceptions->render(function (PostTooLargeException $e, Request $request) {
            if ($request->expectsJson()) {
                return null;
            }

            return back()->withErrors(['upload' => 'O arquivo é maior que o limite permitido. Imagens: até 5 MB; PDF: até 10 MB.']);
        });

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
