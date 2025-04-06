<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Cache;

class GPTController extends Controller
{
    private $openaiApiKey;

    public function __construct()
    {
        $this->openaiApiKey = env('OPENAI_API_KEY'); // Make sure to set your OpenAI API key in the .env file
    }

    public function checkAuth()
    {
        return response()->json([
            'authenticated' => !empty($this->openaiApiKey),
        ]);
    }

    public function analyzeSongData($id, $song)
    {
        log::debug('analyse song data ');
        log::debug('ID: ' . $id);
        log::debug('Song: ' . $song);

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->openaiApiKey,
            ])->post('https://hack.funandprofit.ai/api/providers/openai/v1/chat/completions', [
                'model' => 'gpt-4',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are a music analysis assistant. Given a song title and artist, estimate its tempo (in BPM) and emotional valence (from 0 to 1, where 0 is sad/negative and 1 is happy/positive). Return the result as JSON with keys: "tempo" and "valence".',
                    ],
                    [
                        'role' => 'user',
                        'content' => "Song: {$song}",
                    ],
                ],
                'temperature' => 0.7,
            ]);

            if (!$response->successful()) {
                throw new \Exception('API request failed: ' . $response->body());
            }

            $content = $response->json()['choices'][0]['message']['content'];
            $data = json_decode($content, true);

            if (!is_array($data) || !isset($data['tempo'], $data['valence'])) {
                throw new \Exception('Unexpected response format: ' . $content);
            }

            return [
                'tempo' => $data['tempo'],
                'valence' => $data['valence'],
            ];
        } catch (\Exception $e) {
            Log::error('OpenAI music analysis error: ' . $e->getMessage());
            return null;
        }
    }

    public function findClosestSongMatch()
    {
        $spotifyController = new SpotifyController();
        log::debug(' start of function cloest song math');
        $spotifyController->getTopTracksTitles();

        $goalTempo = Cache::get('tempo', 120); // Replace with your method to get the goal tempo
        $goalValence = Cache::get('valence', 0.5); // Replace with your method to get the goal valence

        $csvFile = 'song_analysis_' . Session::getId() . '.csv';

        if (!\Illuminate\Support\Facades\Storage::disk('local')->exists($csvFile)) {
            return ['error' => 'CSV file not found.'];
        }

        $contents = \Illuminate\Support\Facades\Storage::disk('local')->get($csvFile);
        $lines = array_filter(explode("\n", $contents)); // Remove empty lines
        $csvData = array_map('str_getcsv', $lines);
        $header = array_shift($csvData); // Get header

        $bestMatch = null;
        $smallestDistance = PHP_FLOAT_MAX;
        $bestIndex = null;

        foreach ($csvData as $index => $row) {
            if (count($row) < 4) continue;
            list($id, $song, $tempo, $valence) = $row;
            $tempo = floatval($tempo);
            $valence = floatval($valence);
            $distance = sqrt(pow($goalTempo - $tempo, 2) + pow($goalValence - $valence, 2));

            if ($distance < $smallestDistance) {
                $smallestDistance = $distance;
                $bestMatch = ['id' => $id];
                $bestIndex = $index;
            }
        }

        if (!is_null($bestMatch)) {
            $playedFile = 'played_songs_' . Session::getId() . '.txt';
            $alreadyPlayed = false;
            if (\Illuminate\Support\Facades\Storage::disk('local')->exists($playedFile)) {
                $playedContent = \Illuminate\Support\Facades\Storage::disk('local')->get($playedFile);
                $playedIds = array_filter(explode("\n", $playedContent));
                if (in_array($bestMatch['id'], $playedIds)) {
                    $alreadyPlayed = true;
                }
            }

            if ($alreadyPlayed) {
                // If already played, get recommendation instead.
                $recResponse = $spotifyController->getReccomendationId();
                $recData = $recResponse->getData(true);
                if (isset($recData['recommendation'])) {
                    $bestMatch = ['id' => $recData['recommendation']];
                }
            } else {
                // Mark this song as played.
                if (!\Illuminate\Support\Facades\Storage::disk('local')->exists($playedFile)) {
                    \Illuminate\Support\Facades\Storage::disk('local')->put($playedFile, $bestMatch['id'] . "\n");
                } else {
                    \Illuminate\Support\Facades\Storage::disk('local')->append($playedFile, $bestMatch['id'] . "\n");
                }
            }
        } else {
            // Always get recommendation from SpotifyController and extract the ID
            $recResponse = $spotifyController->getReccomendationId();
            $recData = $recResponse->getData(true);
            if (isset($recData['recommendation'])) {
                $bestMatch = ['id' => $recData['recommendation']];
            }
        }

        return $bestMatch ?? ['error' => 'No songs found.'];
    }
}
