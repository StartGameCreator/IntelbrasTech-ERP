<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;

class PermissaoMiddleware
{
    public function handle(Request $request, Closure $next, string $permissao)
    {
        $user = $request->user();
        [$modulo, $acao] = explode('.', $permissao, 2);
        if (! $user?->temPermissao($modulo, $acao)) {
            return response()->json(['message' => 'Acesso negado.'], 403);
        }
        return $next($request);
    }
}
