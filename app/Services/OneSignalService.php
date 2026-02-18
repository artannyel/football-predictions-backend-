<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OneSignalService
{
    protected string $appId;
    protected string $apiKey;
    protected string $apiUrl = 'https://onesignal.com/api/v1/notifications';

    public function __construct()
    {
        $this->appId = env('ONESIGNAL_APP_ID');
        $this->apiKey = env('ONESIGNAL_REST_API_KEY');
    }

    /**
     * Envia notificação para usuários específicos.
     *
     * @param array $userIds Lista de UUIDs dos usuários (external_id no OneSignal)
     * @param string $title Título da notificação
     * @param string $message Corpo da mensagem
     * @param array|null $data Dados adicionais (payload)
     * @param string|null $url URL para abrir ao clicar (Web)
     */
    public function sendToUsers(array $userIds, string $title, string $message, ?array $data = null, ?string $url = null): void
    {
        if (empty($this->appId) || empty($this->apiKey)) {
            Log::warning('OneSignal credentials not configured.');
            return;
        }

        if (empty($userIds)) {
            return;
        }

        try {
            $payload = [
                'app_id' => $this->appId,
                'include_aliases' => [
                    'external_id' => array_map('strval', $userIds)
                ],
                'target_channel' => 'push',
                'headings' => ['en' => $title, 'pt' => $title],
                'contents' => ['en' => $message, 'pt' => $message],
                'small_icon' => 'ic_stat_notification_icon',
                'data' => $data,
            ];

            if ($url) {
                $payload['url'] = $url;
            }

            $response = Http::withToken($this->apiKey, 'Basic')
                ->post($this->apiUrl, $payload);

            if ($response->failed()) {
                Log::error('OneSignal API Error: ' . $response->body());
            } else {
                Log::info('OneSignal notification sent to ' . count($userIds) . ' users.');
            }

        } catch (\Exception $e) {
            Log::error('Failed to send OneSignal notification: ' . $e->getMessage());
        }
    }

    /**
     * Envia notificação para todos os usuários (Broadcast).
     */
    public function sendToAll(string $title, string $message, ?array $data = null, ?string $url = null): void
    {
        if (empty($this->appId) || empty($this->apiKey)) {
            return;
        }

        try {
            $payload = [
                'app_id' => $this->appId,
                'included_segments' => ['Total Subscriptions'],
                'headings' => ['en' => $title, 'pt' => $title],
                'contents' => ['en' => $message, 'pt' => $message],
                'small_icon' => 'ic_stat_notification_icon',
                'data' => $data,
            ];

            if ($url) {
                $payload['url'] = $url;
            }

            Http::withToken($this->apiKey, 'Basic')->post($this->apiUrl, $payload);

        } catch (\Exception $e) {
            Log::error('Failed to send OneSignal broadcast: ' . $e->getMessage());
        }
    }
}
