<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

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
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->openaiApiKey,
            ])->post('https://api.openai.com/v1/chat/completions', [
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
        $goalTempo = 120;
        $goalValence = 0.8;
    
        $csvPath = storage_path('app/song_analysis.csv');
    
        if (!file_exists($csvPath)) {
            return [
                'error' => 'CSV file not found.'
            ];
        }
    
        $handle = fopen($csvPath, 'r');
        $header = fgetcsv($handle); // Skip header
    
        $bestMatch = null;
        $smallestDistance = PHP_FLOAT_MAX;
    
        while (($row = fgetcsv($handle)) !== false) {
            [$id, $song, $tempo, $valence] = $row;
    
            $tempo = floatval($tempo);
            $valence = floatval($valence);
    
            $distance = sqrt(pow($goalTempo - $tempo, 2) + pow($goalValence - $valence, 2));
    
            if ($distance < $smallestDistance) {
                $smallestDistance = $distance;
                $bestMatch = [
                    'id' => $id
                ];
            }
        }
    
        fclose($handle);
    
        return $bestMatch ?? ['error' => 'No songs found.'];
    }    

}

