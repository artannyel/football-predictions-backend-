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

    public function signalCompetitionUpdate(int $competitionId, array $metadata = []): void
    {
        if (!$this->projectId || !$this->credentials) {
            Log::error("Firestore configuration missing (Project ID or Credentials).");
            return;
        }

        try {
            // 1. Obter Access Token
            $sa = new ServiceAccountCredentials(
                'https://www.googleapis.com/auth/datastore',
                $this->credentials
            );

            $token = $sa->fetchAuthToken();
            $accessToken = $token['access_token'];

            // 2. Montar URL da API REST
            // https://firestore.googleapis.com/v1/projects/{projectId}/databases/(default)/documents/{collection}/{id}
            $url = "https://firestore.googleapis.com/v1/projects/{$this->projectId}/databases/(default)/documents/competition_updates/" . $competitionId;

            // 3. Montar Payload (Formato específico do Firestore REST)
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

            // 4. Fazer Request (PATCH para update/create)
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
}
