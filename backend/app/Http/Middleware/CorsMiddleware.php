<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Log;

class CorsMiddleware
{
    public function handle($request, Closure $next)
    {
        $response = $next($request);

        // Define allowed origins
        $allowedOrigins = [
            'http://localhost:5173',
            'http://localhost:8000',
        ];

        // Get the origin of the current request
        $origin = $request->headers->get('Origin');

        // Check if the origin is in the allowed list
        if (in_array($origin, $allowedOrigins)) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
            $response->headers->set('Access-Control-Allow-Credentials', 'true'); // Allow credentials
        }

        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, X-CSRF-TOKEN');

        // Bypass CSRF token verification for /external-api
        if ($request->is('external-api')) {
            $csrfToken = csrf_token();

            $request->headers->set('X-CSRF-TOKEN', $csrfToken);
        }

        return $response;
    }
}
