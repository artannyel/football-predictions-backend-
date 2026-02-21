<?php

namespace App\Jobs;

use App\Services\OneSignalService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class SendChatMessageNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected string $leagueId,
        protected string $leagueName,
        protected string $senderId,
        protected string $senderName,
        protected string $messageText
    ) {}

    public function handle(OneSignalService $oneSignal): void
    {
        $recipients = DB::table('league_user')
            ->join('users', 'league_user.user_id', '=', 'users.id')
            ->where('league_user.league_id', $this->leagueId)
            ->where('users.id', '!=', $this->senderId)
            ->where('users.notify_chat', true)
            ->pluck('users.id')
            ->toArray();

        if (empty($recipients)) return;

        $title = "Nova mensagem em {$this->leagueName}";
        $body = "{$this->senderName}: {$this->messageText}";

        $frontendUrl = env('FRONTEND_URL');
        $url = $frontendUrl ? "{$frontendUrl}/ligas/{$this->leagueId}/chat" : null;

        foreach (array_chunk($recipients, 500) as $chunk) {
            $oneSignal->sendToUsers($chunk, $title, $body, [
                'type' => 'chat_message',
                'league_id' => $this->leagueId,
                'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                'android_group' => 'league_' . $this->leagueId,
                'thread_id' => 'league_' . $this->leagueId,
                'android_group_message' => [
                    'en' => 'You have $[notif_count] new messages in this league.',
                    'pt' => 'Você tem $[notif_count] novas mensagens nesta liga.',
                ],
            ], $url);
        }
    }
}
