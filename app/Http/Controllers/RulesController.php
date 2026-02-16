<?php

namespace App\Http\Controllers;

use App\Http\Resources\BadgeResource;
use App\Models\Badge;
use Illuminate\Http\JsonResponse;

class RulesController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'scoring' => [
                [
                    'points' => 7,
                    'title' => '🎯 Placar Exato',
                    'description' => 'Acertou o placar exato da partida.',
                    'example' => 'Palpite 2x1, Resultado 2x1'
                ],
                [
                    'points' => 5,
                    'title' => '✅ Vencedor + Saldo',
                    'description' => 'Acertou o vencedor e a diferença de gols.',
                    'example' => 'Palpite 3x1 (Saldo +2), Resultado 2x0 (Saldo +2)'
                ],
                [
                    'points' => 3,
                    'title' => '⚽ Vencedor + Gols de um Time',
                    'description' => 'Acertou o vencedor e o número de gols de uma das equipes.',
                    'example' => 'Palpite 3x0, Resultado 3x1 (Acertou 3 gols do mandante)'
                ],
                [
                    'points' => 1,
                    'title' => '🏅 Apenas Vencedor',
                    'description' => 'Acertou apenas quem venceu a partida.',
                    'example' => 'Palpite 3x1, Resultado 1x0'
                ],
                [
                    'points' => 0,
                    'title' => '❌ Errou',
                    'description' => 'Não acertou o vencedor nem o empate.',
                    'example' => 'Palpite 1x0, Resultado 0x1'
                ]
            ],
            'tie_breakers' => [
                [
                    'order' => 1,
                    'title' => 'Pontuação Total',
                    'description' => 'Quem tiver mais pontos fica na frente.'
                ],
                [
                    'order' => 2,
                    'title' => 'Quantidade de Placares Exatos',
                    'description' => 'Quem acertou mais placares exatos (7 pts).'
                ],
                [
                    'order' => 3,
                    'title' => 'Quantidade de Vencedor + Saldo',
                    'description' => 'Quem acertou mais vencedores com saldo correto (5 pts).'
                ],
                [
                    'order' => 4,
                    'title' => 'Quantidade de Vencedor + Gols',
                    'description' => 'Quem acertou mais vencedores com gols de um time (3 pts).'
                ],
                [
                    'order' => 5,
                    'title' => 'Quantidade de Apenas Vencedor',
                    'description' => 'Quem acertou mais vencedores simples (1 pt).'
                ],
                [
                    'order' => 6,
                    'title' => 'Menor Número de Erros',
                    'description' => 'Quem errou menos palpites vence. Premia a eficiência.'
                ]
            ],
            'badges' => BadgeResource::collection(Badge::all())
        ]);
    }
}
