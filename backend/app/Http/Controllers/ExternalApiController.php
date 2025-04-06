<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class ExternalApiController extends Controller
{
    public function logApiCall(Request $request)
    {
        // Ensure the request has a JSON payload
        if ($request->isJson()) {
            $requestBody = $request->json()->all(); // Retrieve the JSON body
        } else {
            $requestBody = []; // Default to an empty array if not JSON
        }

        $valence = $requestBody['valence'] ?? 0.5; // Extract valence, default to 0.5 if not provided
        $tempo = $requestBody['tempo'] ?? 120; // Extract tempo, default to 120 if not provided

        Cache::put('valence', $valence, now()->addMinutes(60)); // Store valence in cache for 60 minutes
        Cache::put('tempo', $tempo, now()->addMinutes(60)); // Store tempo in cache for 60 minutes

        return response()->json([
            'message' => 'API call logged successfully',
            'valence' => $valence, // Include valence in the response
            'tempo' => $tempo // Include tempo in the response
        ]);
    }
}
