<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ExternalApiController extends Controller
{
    public function logApiCall(Request $request)
    {
        Log::debug("Test log entry to confirm logging works."); // Test log entry
        Log::debug('External API call received', [
            'headers' => $request->headers->all(),
            'body' => $request->all(),
        ]);
        Log::info('External API call received', [
            'headers' => $request->headers->all(),
            'body' => $request->all(),
        ]);

        return response()->json([
            'message' => 'API call logged successfully',
            'status' => 'Request received, all good!'
        ], 200); // Explicitly return 200 OK
    }
}
