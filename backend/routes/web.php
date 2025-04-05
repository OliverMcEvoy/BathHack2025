<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Http\Controllers\SpotifyController;
use App\Http\Middleware\CorsMiddleware;

Route::prefix('spotify')->group(function () {
    Route::get('/authorize', [SpotifyController::class, 'authorize']);
    Route::match(['GET', 'POST'], '/callback', [SpotifyController::class, 'callback']); // Allow both GET and POST
    Route::get('/check-auth', [SpotifyController::class, 'checkAuth']);
    Route::get('/track', [SpotifyController::class, 'getTrack']);
    Route::get('/audio', [SpotifyController::class, 'getAudio']);

    // Proxy route for Spotify API
    Route::get('/proxy', function (Request $request) {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . env('SPOTIFY_ACCESS_TOKEN'),
        ])->get('https://apresolve.spotify.com', $request->query());

        return response($response->body(), $response->status());
    });

    // Route to fetch Spotify token
    Route::get('/token', function () {
        return response()->json(['token' => env('SPOTIFY_ACCESS_TOKEN')]);
    });
});

Route::get('/{any}', function () {
    return file_get_contents(public_path('index.html')); // Serve the Vue app's entry point
})->where('any', '.*');

// Other routes