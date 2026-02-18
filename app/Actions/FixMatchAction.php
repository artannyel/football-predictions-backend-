<?php

namespace App\Actions;

use App\Jobs\ProcessMatchResults;
use App\Models\FootballMatch;
use Illuminate\Support\Facades\Log;

class FixMatchAction
{
    public function execute(FootballMatch $match, array $data): FootballMatch
    {
        $updateData = [
            'is_manual_update' => true,
        ];

        $fields = [
            'status',
            'score_fulltime_home', 'score_fulltime_away',
            'score_halftime_home', 'score_halftime_away',
            'score_extratime_home', 'score_extratime_away',
            'score_penalties_home', 'score_penalties_away',
            'score_winner', 'score_duration'
        ];

        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) { // Usa array_key_exists para permitir null
                $updateData[$field] = $data[$field];
            }
        }

        // Se quiser destravar (voltar para automático)
        if (isset($data['unlock']) && $data['unlock']) {
            $updateData['is_manual_update'] = false;
        }

        $match->update($updateData);

        // Se finalizou ou mudou placar, reprocessa
        if ($match->status === 'FINISHED') {
            Log::channel('recalculation')->info("Manual fix applied to Match {$match->external_id}. Dispatching processing job.");
            ProcessMatchResults::dispatch($match->external_id);
        }

        return $match;
    }
}
