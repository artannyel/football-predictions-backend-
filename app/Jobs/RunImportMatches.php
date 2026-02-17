<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class RunImportMatches implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 3600; // 1 hora

    public function __construct(protected ?int $competitionId = null) {}

    public function handle(): void
    {
        Log::channel('recalculation')->info("Starting manual match import via Admin..." . ($this->competitionId ? " Competition: {$this->competitionId}" : " ALL"));

        $params = [];
        if ($this->competitionId) {
            $params['competition_id'] = $this->competitionId;
        }

        Artisan::call('import:matches', $params);

        // Captura a saída do comando para logar
        $output = Artisan::output();
        Log::channel('recalculation')->info("Import Output: " . $output);

        Log::channel('recalculation')->info("Manual match import finished.");
    }
}
