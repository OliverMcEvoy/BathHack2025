<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Cache;

class SpotifyController extends Controller
{
    private $scopes = [
        'user-read-playback-state',
        'user-modify-playback-state',
        'user-read-currently-playing',
        'streaming',
        'app-remote-control',
        'user-read-email' // Valid scope
        // Removed 'web-playback' as it is not a valid Spotify scope
    ];

    public function authorize()
    {
        $state = bin2hex(random_bytes(16));
        Session::put('spotify_state', $state);

        $params = http_build_query([
            'client_id' => env('SPOTIFY_CLIENT_ID'),
            'response_type' => 'code',
            'redirect_uri' => env('SPOTIFY_REDIRECT_URI'),
            'state' => $state,
            'scope' => implode(' ', $this->scopes),
            'show_dialog' => true
        ]);

        return response()->json([
            'authUrl' => 'https://accounts.spotify.com/authorize?' . $params
        ]);
    }

    public function callback(Request $request)
    {
        if ($request->error) {
            return redirect('/')->with('error', 'Authorization failed');
        }

        if ($request->state !== Session::get('spotify_state')) {
            return redirect('/')->with('error', 'State mismatch');
        }

        try {
            $response = Http::asForm()->post('https://accounts.spotify.com/api/token', [
                'grant_type' => 'authorization_code',
                'code' => $request->code,
                'redirect_uri' => env('SPOTIFY_REDIRECT_URI'),
                'client_id' => env('SPOTIFY_CLIENT_ID'),
                'client_secret' => env('SPOTIFY_CLIENT_SECRET'),
            ]);

            if (!$response->successful()) {
                throw new \Exception('Token request failed');
            }

            $data = $response->json();
            Session::put('spotify_token', $data['access_token']);
            Session::put('spotify_refresh_token', $data['refresh_token']);

            return redirect('/')->with('success', 'Successfully connected to Spotify');
        } catch (\Exception $e) {
            Log::error('Spotify callback error: ' . $e->getMessage());
            return redirect('/')->with('error', 'Authentication failed');
        }
    }

    public function checkAuth()
    {
        return response()->json([
            'authenticated' => Session::has('spotify_token')
        ]);
    }

    public function getAccessToken()
    {
        if (!Session::has('spotify_token')) {
            throw new \Exception('Not authenticated');
        }

        try {
            // Check if we need to refresh the token
            if (Session::has('spotify_refresh_token')) {
                $response = Http::asForm()->post('https://accounts.spotify.com/api/token', [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => Session::get('spotify_refresh_token'),
                    'client_id' => env('SPOTIFY_CLIENT_ID'),
                    'client_secret' => env('SPOTIFY_CLIENT_SECRET'),
                ]);

                if ($response->successful()) {
                    $data = $response->json();
                    Session::put('spotify_token', $data['access_token']);
                    return ['access_token' => $data['access_token']];
                }
            }

            return ['access_token' => Session::get('spotify_token')];
        } catch (\Exception $e) {
            Log::error('Token refresh error: ' . $e->getMessage());
            throw $e;
        }
    }

    public function getTrack(Request $request)
    {
        try {
            // Get new token
            $tokenData = $this->getAccessToken();

            if (!isset($tokenData['access_token'])) {
                throw new \Exception('Invalid access token');
            }

            $accessToken = $tokenData['access_token'];
            $trackId = $request->query('track_id', '3n3Ppam7vgaVa1iaRUc9Lp');

            // First get track info
            $trackResponse = Http::withToken($accessToken)
                ->get("https://api.spotify.com/v1/tracks/{$trackId}");

            if (!$trackResponse->successful()) {
                Log::error('Track fetch error: ' . $trackResponse->body());
                throw new \Exception('Failed to fetch track');
            }

            // Then get audio features
            $audioResponse = Http::withToken($accessToken)
                ->get("https://api.spotify.com/v1/audio-features/{$trackId}");

            if (!$audioResponse->successful()) {
                Log::error('Audio features error: ' . $audioResponse->body());
                // Don't throw error for audio features, just return track data
                return $trackResponse->json();
            }


            return array_merge(
                // $trackResponse->json(),
                // ['audio_features' => $audioResponse->json()],
            );
        } catch (\Exception $e) {
            Log::error('Track error: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function getAudio(Request $request)
    {
        try {
            $tokenData = $this->getAccessToken();
            $accessToken = $tokenData['access_token'];
            $trackId = $request->query('track_id');

            if (!$trackId) {
                return response()->json(['error' => 'No track ID provided'], 400);
            }

            // First, get available devices
            $devicesResponse = Http::withToken($accessToken)
                ->get('https://api.spotify.com/v1/me/player/devices');

            if (!$devicesResponse->successful()) {
                return response()->json(['error' => 'Failed to get devices'], 500);
            }

            $devices = $devicesResponse->json()['devices'];

            $valence = Cache::get('valence', 0.5); // Retrieve valence from cache, default to 0.5
            Log::info('Valence retrieved from cache in getAudio', ['valence' => $valence]); // Log the valence

            // Start playback on the first available device
            if (!empty($devices)) {
                $deviceId = $devices[0]['id'];

                $response = Http::withToken($accessToken)
                    ->put("https://api.spotify.com/v1/me/player/play?device_id={$deviceId}", [
                        'uris' => ["spotify:track:{$trackId}"]
                    ]);

                if ($response->successful()) {
                    return response()->json([
                        'success' => true,
                        'token' => $accessToken,
                        'deviceId' => $deviceId,
                        'valence' => $valence // Include valence in the response
                    ]);
                }
            }

            return response()->json(['error' => 'No available playback devices', 'valence' => $valence], 404);
        } catch (\Exception $e) {
            Log::error("Audio playback error: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage(), 'valence' => Cache::get('valence', 0.5)], 500);
        }
    }
}
