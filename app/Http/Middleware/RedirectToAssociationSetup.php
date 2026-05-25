<?php

namespace App\Http\Middleware;

use App\Models\Association;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectToAssociationSetup
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()) {
            return $next($request);
        }

        if (Association::query()->exists()) {
            return $next($request);
        }

        $routeName = (string) ($request->route()?->getName() ?? '');

        if (str_starts_with($routeName, 'filament.admin.auth.')) {
            return $next($request);
        }

        if ($routeName === 'filament.admin.resources.associations.setup' || $request->is('admin/associations/setup*')) {
            return $next($request);
        }

        /** @var RedirectResponse $response */
        $response = redirect()->route('filament.admin.resources.associations.setup');

        return $response;
    }
}
