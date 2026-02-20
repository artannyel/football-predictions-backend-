<?php

namespace App\Services;

use Google\Auth\Credentials\ServiceAccountCredentials;
use Google\Auth\Middleware\AuthTokenMiddleware;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FirestoreService
{
    protected $projectId;
    protected $credentials;

    public function __construct()
    {
        $this->projectId = $this->getProjectId();
        $this->credentials = $this->getCredentials();
    }

    private function getCredentials()
    {
        $credentials = env('FIREBASE_CREDENTIALS');
        if (!$credentials) {
            return null;
        }

        if (str_starts_with(trim($credentials), '{')) {
            return json_decode($credentials, true);
        }

        $path = str_starts_with($credentials, '/') ? $credentials : base_path($credentials);
        if (!file_exists($path) && file_exists(storage_path('app/' . $credentials))) {
            $path = storage_path('app/' . $credentials);
        }

        if (file_exists($path)) {
            return json_decode(file_get_contents($path), true);
        }

        return null;
    }

    private function getProjectId()
    {
        $creds = $this->getCredentials();
        return $creds['project_id'] ?? env('FIREBASE_PROJECT_ID');
    }

    private function getAccessToken()
    {
        if (!$this->credentials) return null;

        $sa = new ServiceAccountCredentials(
            'https://www.googleapis.com/auth/datastore',
            $this->credentials
        );

        $token = $sa->fetchAuthToken();
        return $token['access_token'];
    }

    public function signalCompetitionUpdate(int $competitionId, array $metadata = []): void
    {
        if (!$this->projectId) {
            Log::error("Firestore configuration missing (Project ID).");
            return;
        }

        try {
            $accessToken = $this->getAccessToken();
            if (!$accessToken) return;

            $url = "https://firestore.googleapis.com/v1/projects/{$this->projectId}/databases/(default)/documents/competition_updates/" . $competitionId;

            $fields = [
                'updated_at' => ['integerValue' => (string) time()],
                'last_update_iso' => ['stringValue' => now()->toIso8601String()],
            ];

            foreach ($metadata as $key => $value) {
                if (is_int($value)) {
                    $fields[$key] = ['integerValue' => (string) $value];
                } elseif (is_bool($value)) {
                    $fields[$key] = ['booleanValue' => $value];
                } else {
                    $fields[$key] = ['stringValue' => (string) $value];
                }
            }

            $response = Http::withToken($accessToken)
                ->patch($url, [
                    'fields' => $fields
                ]);

            if ($response->failed()) {
                Log::error("Firestore REST API failed: " . $response->body());
            } else {
                Log::info("Firestore signal sent for competition {$competitionId} (REST API)", $metadata);
            }

        } catch (\Exception $e) {
            Log::error("Failed to signal Firestore update: " . $e->getMessage());
        }
    }

    public function addChatMessage(string $leagueId, array $messageData): bool
    {
        if (!$this->projectId) {
            Log::error("Firestore configuration missing (Project ID).");
            return false;
        }

        try {
            $accessToken = $this->getAccessToken();
            if (!$accessToken) return false;

            // URL para criar documento na subcoleção 'messages'
            // POST https://firestore.googleapis.com/v1/projects/{projectId}/databases/(default)/documents/leagues/{leagueId}/messages
            $url = "https://firestore.googleapis.com/v1/projects/{$this->projectId}/databases/(default)/documents/leagues/{$leagueId}/messages";

            $fields = [
                'text' => ['stringValue' => $messageData['text']],
                'userId' => ['stringValue' => $messageData['userId']],
                'userName' => ['stringValue' => $messageData['userName']],
                'createdAt' => ['timestampValue' => now()->toIso8601String()], // Formato RFC 3339
            ];

            if (!empty($messageData['userPhoto'])) {
                $fields['userPhoto'] = ['stringValue' => $messageData['userPhoto']];
            }

            $response = Http::withToken($accessToken)
                ->post($url, [
                    'fields' => $fields
                ]);

            if ($response->failed()) {
                Log::error("Firestore Chat API failed: " . $response->body());
                return false;
            }

            return true;

        } catch (\Exception $e) {
            Log::error("Failed to add chat message: " . $e->getMessage());
            return false;
        }
    }
}
