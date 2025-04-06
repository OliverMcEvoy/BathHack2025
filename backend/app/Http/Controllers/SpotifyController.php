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
        'user-read-email',
        'user-top-read', // Valid scope
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

        $url = 'https://accounts.spotify.com/authorize?' . $params;

        // Log the authorization URL for debugging
        Log::info('Spotify authorization URL', ['url' => $url]);


        return response()->json([
            'authUrl' => $url,
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
            Log::info('Spotify token received', ['access_token' => $data['access_token']]); // Log the token
            Session::put('spotify_toekn_2', $data['access_token']);
            Session::put('spotify_refresh_token', $data['refresh_token']);

            return redirect('/')->with('success', 'Successfully connected to Spotify');
        } catch (\Exception $e) {
            Log::error('Spotify callback error: ' . $e->getMessage());
            return redirect('/')->with('error', 'Authentication failed');
        }
    }

    public function checkAuth()
    {
        log::Debug(session('spotify_toekn_2'));
        return response()->json([
            'authenticated' => Session::has('spotify_toekn_2')
        ]);
    }

    public function getAccessToken()
    {
        if (!Session::has('spotify_toekn_2')) {
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
                    Session::put('spotify_toekn_2', $data['access_token']);
                    return ['access_token' => $data['access_token']];
                }
            }

            return ['access_token' => Session::get('spotify_toekn_2')];
        } catch (\Exception $e) {
            Log::error('Token refresh error: ' . $e->getMessage());
            throw $e;
        }
    }

    public function getTop5Artists()
    {
        try {
            // Get new token
            $tokenData = $this->getAccessToken();

            if (!isset($tokenData['access_token'])) {
                throw new \Exception('Invalid access token');
            }

            $accessToken = $tokenData['access_token'];

            // First get artist info
            $artistResponse = Http::withToken($accessToken)
                ->get("https://api.spotify.com/v1/me/top/artists?limit=5");

            if (!$artistResponse->successful()) {
                Log::error('Artist top 5 fetch error: ' . $artistResponse->body());
                throw new \Exception('Failed to fetch top 5 artists');
            }

            return array_merge(
                $artistResponse->json(),
            );
        } catch (\Exception $e) {
            Log::error('Artist top 5 error: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function getTop5Tracks()
    {
        try {
            // Get new token
            $tokenData = $this->getAccessToken();

            if (!isset($tokenData['access_token'])) {
                throw new \Exception('Invalid access token');
            }

            $accessToken = $tokenData['access_token'];

            // First get track info
            $trackResponse = Http::withToken($accessToken)
                ->get("https://api.spotify.com/v1/me/top/tracks?limit=5");

            if (!$trackResponse->successful()) {
                Log::error('Track top 5 fetch error: ' . $trackResponse->body());
                throw new \Exception('Failed to fetch top 5 tracks');
            }

            return array_merge(
                $trackResponse->json(),
            );
        } catch (\Exception $e) {
            Log::error('Track top 5 error: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function getTopTracksTitles()
    {
        try {
            $tokenData = $this->getAccessToken();
            if (!isset($tokenData['access_token'])) {
                throw new \Exception('Invalid access token');
            }
    
            $accessToken = $tokenData['access_token'];
            $trackResponse = Http::withToken($accessToken)
                ->get("https://api.spotify.com/v1/me/top/tracks?limit=3");
    
            if (!$trackResponse->successful()) {
                Log::error('Track top name fetch error: ' . $trackResponse->body());
                throw new \Exception('Failed to fetch top tracks');
            }
    
            $data = $trackResponse->json();
            $songsWithAnalysis = [];
    
            $gpt = new GPTController(); 
    
            $csvPath = storage_path('app/song_analysis.csv');
            $fileExists = file_exists($csvPath);
            $handle = fopen($csvPath, 'a');
    
            if (!$fileExists) {
                fputcsv($handle, ['ID', 'Song', 'Tempo', 'Valence']);
            }
    
            foreach ($data['items'] as $track) {
                $trackName = $track['name'];
                $artistName = $track['artists'][0]['name'];
                $trackId = $track['id'];
                $formattedSong = $trackName . ' by ' . $artistName;
    
                // Call GPT analyze
                $analysis = $gpt->analyzeSongData($trackId, $formattedSong);
    
                if ($analysis) {
                    // Save to CSV
                    fputcsv($handle, [$trackId, $formattedSong, $analysis['tempo'], $analysis['valence']]);
    
                    $songsWithAnalysis[] = [
                        'id' => $trackId,
                        'song' => $formattedSong,
                        'tempo' => $analysis['tempo'],
                        'valence' => $analysis['valence'],
                    ];
                }
            }
    
            fclose($handle);
    
            return response()->json($songsWithAnalysis);
    
        } catch (\Exception $e) {
            Log::error('Track top error: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    
    

    // public function getReccomendationId()
    // {
    //     try {
    //         $tokenData = $this->getAccessToken();
    //         $accessToken = $tokenData['access_token'];

    //         // Get user's top 5 artists
    //         $topArtists = $this->getTop5Artists()['items'] ?? [];
    //         $artistIds = collect($topArtists)->pluck('id')->take(5)->toArray();

    //         // Get top 5 tracks from each artist
    //         $allTracks = collect($artistIds)
    //             ->flatMap(function ($artistId) use ($accessToken) {
    //                 return $this->getArtistTopTracks($artistId, $accessToken);
    //             })
    //             ->filter()
    //             ->shuffle()
    //             ->toArray();

    //         if (empty($allTracks)) {
    //             throw new \Exception('No tracks found from top artists');
    //         }

    //         // Select random track from the pool
    //         $randomTrack = $allTracks[array_rand($allTracks)];

    //         return response()->json([
    //             'recommendation' => $randomTrack['id'],
    //             'track_name' => $randomTrack['name'],
    //             'artist' => $randomTrack['artists'][0]['name']
    //         ]);
    //     } catch (\Exception $e) {
    //         Log::error('Recommendation error: ' . $e->getMessage());
    //         return response()->json(['error' => $e->getMessage()], 500);
    //     }
    // }

    private function getArtistTopTracks($artistId, $accessToken)
    {
        $response = Http::withToken($accessToken)
            ->get("https://api.spotify.com/v1/artists/{$artistId}/top-tracks", [
                'market' => 'from_token'
            ]);

        if (!$response->successful()) {
            return [];
        }

        $tracks = $response->json()['tracks'] ?? [];

        // Return only first 5 tracks with essential info
        return array_map(function ($track) {
            return [
                'id' => $track['id'],
                'name' => $track['name'],
                'artists' => $track['artists']
            ];
        }, array_slice($tracks, 0, 5));
    }

    // Helper methods using active endpoints
    private function getRelatedArtists($artistId, $accessToken)
    {
        $response = Http::withToken($accessToken)
            ->get("https://api.spotify.com/v1/artists/{$artistId}/related-artists");

        return $response->successful()
            ? collect($response->json()['artists'])->pluck('id')->toArray()
            : [];
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

            $valence = Cache::get('valence', 0.5); // Retrieve valence from session, default to 0.5
            Log::info('Valence retrieved from session in getTrack', ['valence' => $valence]); // Log the valence

            return array_merge(
                $trackResponse->json(),
                ['audio_features' => $audioResponse->json()],
                ['valence' => $valence] // Include valence in the response
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

            $valence = Cache::get('valence', 0.7); // Retrieve valence from session, default to 0.5
            Log::info('Valence retrieved from session in getAudio', ['valence' => $valence]); // Log the valence

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
            return response()->json(['error' => $e->getMessage(), 'valence' => session('valence', 0.5)], 500);
        }
    }

    public function getValence()
    {
        try {
            $valence = Cache::get('valence', 0.5); // Default to 0.5 if not found
            return response()->json(['valence' => $valence]);
        } catch (\Exception $e) {
            Log::error('Error fetching valence:', ['message' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to fetch valence'], 500);
        }
    }

    public function logout()
    {
        Session::flush(); // Clear all session data
        return redirect('/login'); // Redirect to the login page
    }
}
