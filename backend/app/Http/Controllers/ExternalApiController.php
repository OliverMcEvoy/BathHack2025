<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cookie;

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

        Cookie::queue('valence', $valence, 60); // Store valence in a cookie for 60 minutes
        Log::info('Valence value received', ['valence' => $valence]); // Log the valence
        Log::info('Request body logged', ['body' => $requestBody]); // Log the entire request body

        Log::debug('External API call received', [
            'headers' => $request->headers->all(),
            'body' => $requestBody,
        ]);

        return response()->json(['message' => 'API call logged successfully']);
    }
}
