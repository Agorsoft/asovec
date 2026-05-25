<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckInstallation
{
    public function handle(Request $request, Closure $next)
    {
        $installed = (bool) config('app.installed');

        // Allow installer routes and assets to be accessed even when not installed
        if (! $installed) {
            $path = ltrim($request->path(), '/');

            if ($request->is('install') || $request->is('install/*') || $request->is('vendor/*') || $request->is('_ignition/*') || $request->is('sanctum/*')) {
                return $next($request);
            }

            return redirect()->route('install.show');
        }

        return $next($request);
    }
}
