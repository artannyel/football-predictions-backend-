<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\PendingRequest;

class FootballDataService
{
    protected string $baseUrl;
    protected string $token;

    public function __construct()
    {
        $this->baseUrl = config('services.football_data.url');
        $this->token = config('services.football_data.token');
    }

    protected function client(): PendingRequest
    {
        return Http::withHeaders([
            'X-Auth-Token' => $this->token,
        ])->baseUrl($this->baseUrl);
    }

    /**
     * Lista todas as competições disponíveis (Tier 1 por padrão no plano gratuito)
     */
    public function getCompetitions(array $filters = []): array
    {
        return $this->client()->get('/competitions', $filters)->json();
    }

    /**
     * Detalhes de uma competição específica
     */
    public function getCompetition(int $competitionId): array
    {
        return $this->client()->get("/competitions/{$competitionId}")->json();
    }

    /**
     * Lista times de uma competição
     */
    public function getCompetitionTeams(int $competitionId, int $season = null): array
    {
        $params = [];
        if ($season) {
            $params['season'] = $season;
        }
        return $this->client()->get("/competitions/{$competitionId}/teams", $params)->json();
    }

    /**
     * Lista partidas de uma competição
     */
    public function getCompetitionMatches(int $competitionId, array $filters = []): array
    {
        // Filters: dateFrom, dateTo, matchday, status, season
        return $this->client()->get("/competitions/{$competitionId}/matches", $filters)->json();
    }

    /**
     * Detalhes de uma partida específica
     */
    public function getMatch(int $matchId): array
    {
        return $this->client()->get("/matches/{$matchId}")->json();
    }
}
