<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ApiKeyMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        // Ambil access key dari header
        $accessKey = $request->header('X-ACCESS-KEY');
        
        // Cek apakah access key valid
        if ($accessKey !== config('app.access_key')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Invalid access key.'
            ], 401);
        }

        return $next($request);
    }
}
