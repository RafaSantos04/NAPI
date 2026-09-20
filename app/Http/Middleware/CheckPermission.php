<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    public function handle(Request $request, Closure $next, string $routeName, string $action = 'view'): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // Verifica se o usuário tem permissão para essa ação no menu.
        // wherePivot() só existe na própria relação BelongsToMany; dentro do
        // closure de whereHas() o builder é do model relacionado (Menu), então
        // wherePivot() cai no parser mágico "where{Coluna}" do Eloquent e vira
        // um `where "pivot" = ...` errado. É preciso referenciar a tabela pivot
        // (menu_profiles) explicitamente.
        $hasPermission = $user->profiles()
            ->whereHas('menus', function ($query) use ($routeName, $action) {
                $query->where('route_name', $routeName)
                    ->where("menu_profiles.can_{$action}", true);
            })
            ->exists();

        if (! $hasPermission) {
            // Retorna 404 para não revelar que a rota existe
            return response()->json(['message' => 'Not found.'], 404);
        }

        return $next($request);
    }
}
