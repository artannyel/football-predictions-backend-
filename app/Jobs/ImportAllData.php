<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class ImportAllData implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 1200; // 20 minutos

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Starting full data import...');

        try {
            Log::info('Importing competitions...');
            Artisan::call('import:competitions');

            Log::info('Importing teams...');
            Artisan::call('import:teams');

            Log::info('Importing matches...');
            Artisan::call('import:matches');

            Log::info('Full data import completed successfully!');
        } catch (\Exception $e) {
            Log::error('Data import failed: ' . $e->getMessage());
        }
    }
}
