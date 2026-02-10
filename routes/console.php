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

// Sincronização completa diária (novos jogos, rodadas futuras)
Schedule::command('import:matches')->dailyAt('03:00');
