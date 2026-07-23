<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->redirectGuestsTo(null);

        // Aliases usados nas rotas da API (routes/api.php)
        $middleware->alias([
            'empresa'   => \App\Http\Middleware\EmpresaMiddleware::class,
            'permissao' => \App\Http\Middleware\PermissaoMiddleware::class,
        ]);

        // Sanctum: necessario para autenticacao SPA via cookie (mobile/token nao precisa)
        //$middleware->statefulApi();

        $middleware->api(prepend: [
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (
            \Illuminate\Auth\AuthenticationException $e,
            \Illuminate\Http\Request $request
        ) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => 'Nao autenticado.',
                ], 401);
            }

            return null;
        });

        // Sempre responde em JSON quando a requisicao e para a API,
        // em vez de tentar renderizar a pagina de erro em HTML do Laravel.
        $exceptions->shouldRenderJsonWhen(function ($request, Throwable $e) {
            return $request->is('api/*') || $request->expectsJson();
        });
    })->create();
