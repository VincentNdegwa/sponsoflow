<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class WorkspaceMiddlware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();
        if (! $user) {
            return $next($request);
        }

        $workspace = $user->workspaces()->first();
        if (! $workspace) {
            return $next($request);
        }

        $request->attributes->set('current_workspace', $workspace);
        session(['current_workspace_id' => $workspace->id]);
        View::share('currentWorkspace', $workspace);
        app()->instance('current.workspace', $workspace);

        return $next($request);
    }
}
