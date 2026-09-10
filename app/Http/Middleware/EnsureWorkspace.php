<?php

namespace App\Http\Middleware;

use App\Models\Workspace;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/** Users created before workspaces existed (or via tinker) get one on first request. */
class EnsureWorkspace
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->workspace_id) {
            $workspace = Workspace::create([
                'name' => $user->name."'s Workspace",
                'slug' => Str::slug($user->name).'-'.Str::lower(Str::random(6)),
                'owner_id' => $user->id,
            ]);
            $user->forceFill(['workspace_id' => $workspace->id])->save();
        }

        return $next($request);
    }
}
