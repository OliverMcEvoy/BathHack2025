<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

class ExternalApiController extends Controller
{
    public function logApiCall(Request $request)
    {
        $requestBody = $request->json()->all(); // Retrieve the JSON body
        $valence = $requestBody['valence'] ?? 0.4; // Extract valence, default to 0.5 if not provided

        Session::put('valence', $valence); // Store valence in the session
        Log::info('Valence value received', ['valence' => $valence]); // Log the valence

        Log::debug('External API call received', [
            'headers' => $request->headers->all(),
            'body' => $requestBody,
        ]);

        return response()->json(['message' => 'API call logged successfully']);
    }
}
