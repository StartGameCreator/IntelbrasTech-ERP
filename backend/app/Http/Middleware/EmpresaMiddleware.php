<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;

class EmpresaMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if ($user && ! $user->empresa->emTrialOuAtiva()) {
            return response()->json(['message' => 'Plano expirado. Renove sua assinatura.'], 402);
        }
        return $next($request);
    }
}
