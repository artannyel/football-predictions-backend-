<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// Desativa ligas finalizadas diariamente
Schedule::job(new \App\Jobs\DeactivateFinishedLeagues)->daily();

// Atualiza jogos ao vivo a cada minuto
Schedule::command('update:live-matches')->everyMinute();

// Atualiza todos os jogos do dia a cada hora (garantia de consistência)
Schedule::command('update:daily-matches')->hourly();

// Atualiza competições e temporadas (currentMatchday, datas) diariamente às 02:55
Schedule::command('import:competitions')->dailyAt('02:55');

// Sincronização completa de jogos (novos jogos, rodadas futuras) diariamente às 03:00
Schedule::command('import:matches')->dailyAt('03:00');

// Lembretes de palpites (Horários BRT: 09:00 e 17:00)
// UTC = BRT + 3
Schedule::command('send:prediction-reminders')->dailyAt('12:00'); // 09:00 BRT
Schedule::command('send:prediction-reminders')->dailyAt('20:00'); // 17:00 BRT
