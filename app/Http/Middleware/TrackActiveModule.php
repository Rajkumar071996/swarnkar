<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Remembers which product the jeweller is working in.
 *
 * Customers and staff belong to both modules, so without this someone working
 * through Girvi who opens a customer would be dropped back into the GoldScore
 * menu. Only the screens that clearly belong to one product change the module;
 * the shared ones leave it where it was.
 */
class TrackActiveModule
{
    private const GIRVI = 'girvi';

    private const GOLDSCORE = 'goldscore';

    /** @var array<int, string> */
    private const GOLDSCORE_ROUTES = ['dashboard', 'lookup.*', 'khata.*', 'udhaars.*', 'flags.*'];

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->routeIs('girvi.*')) {
            $request->session()->put('active_module', self::GIRVI);
        } elseif ($request->routeIs(self::GOLDSCORE_ROUTES)) {
            $request->session()->put('active_module', self::GOLDSCORE);
        }

        View::share('activeModule', $request->session()->get('active_module', self::GOLDSCORE));

        return $next($request);
    }
}
