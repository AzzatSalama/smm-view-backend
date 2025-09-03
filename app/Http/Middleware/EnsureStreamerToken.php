<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStreamerToken
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Check if the token has streamer ability
        if (!$request->user()->currentAccessToken()->can('streamer')) {
            return response()->json(['message' => 'Access denied. Streamer token required.'], 403);
        }

        // Check if user is actually a streamer
        if (!$user->isStreamer()) {
            return response()->json(['message' => 'Access denied. Streamer account required.'], 403);
        }

        return $next($request);
    }
}
