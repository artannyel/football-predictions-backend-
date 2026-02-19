<?php

namespace Database\Seeders;

use App\Models\Badge;
use Illuminate\Database\Seeder;

class BadgeSeeder extends Seeder
{
    public function run(): void
    {
        $badges = [
            // Liga (Eventuais)
            [
                'slug' => 'sniper',
                'type' => 'league',
                'name' => 'Sniper',
                'description' => 'Acertou o placar exato de um jogo.',
                'default_icon' => 'target',
            ],
            [
                'slug' => 'zebra',
                'type' => 'league',
                'name' => 'Caçador de Zebras',
                'description' => 'Acertou um resultado que menos de 15% dos usuários apostaram.',
                'default_icon' => 'zebra',
            ],
            [
                'slug' => 'ousado',
                'type' => 'league',
                'name' => 'Ousado',
                'description' => 'Apostou no empate e acertou.',
                'default_icon' => 'balance',
            ],
            // Power-Up Badges
            [
                'slug' => 'magician',
                'type' => 'league',
                'name' => 'Mágico',
                'description' => 'Acertou um Placar Exato usando o Coringa (x2).',
                'default_icon' => 'magic_wand',
            ],
            [
                'slug' => 'waster',
                'type' => 'league',
                'name' => 'Mão Furada',
                'description' => 'Usou o Coringa e fez 0 pontos.',
                'default_icon' => 'broken_coin',
            ],
            // Liga (Marcos de Pontos)
            [
                'slug' => 'points_50',
                'type' => 'league',
                'name' => 'Iniciante',
                'description' => 'Alcançou 50 pontos em uma liga.',
                'default_icon' => 'medal_bronze',
            ],
            [
                'slug' => 'points_200',
                'type' => 'league',
                'name' => 'Profissional',
                'description' => 'Alcançou 200 pontos em uma liga.',
                'default_icon' => 'medal_silver',
            ],
            [
                'slug' => 'points_500',
                'type' => 'league',
                'name' => 'Mestre',
                'description' => 'Alcançou 500 pontos em uma liga.',
                'default_icon' => 'medal_gold',
            ],
            [
                'slug' => 'points_1000',
                'type' => 'league',
                'name' => 'Lenda',
                'description' => 'Alcançou 1000 pontos em uma liga.',
                'default_icon' => 'medal_diamond',
            ],
            // Ranking Mensal
            [
                'slug' => 'monthly_top_1',
                'type' => 'monthly',
                'name' => 'Rei do Mês',
                'description' => 'Terminou em 1º lugar no Ranking Global Mensal.',
                'default_icon' => 'crown_monthly',
            ],
            [
                'slug' => 'monthly_top_3',
                'type' => 'monthly',
                'name' => 'Pódio Mensal',
                'description' => 'Terminou no Top 3 do Ranking Global Mensal.',
                'default_icon' => 'podium_monthly',
            ],
            [
                'slug' => 'monthly_top_10',
                'type' => 'monthly',
                'name' => 'Elite Mensal',
                'description' => 'Terminou no Top 10 do Ranking Global Mensal.',
                'default_icon' => 'star_monthly',
            ],
            // Ranking Anual
            [
                'slug' => 'season_top_1',
                'type' => 'season',
                'name' => 'Lenda da Temporada',
                'description' => 'Terminou em 1º lugar no Ranking Global Anual.',
                'default_icon' => 'crown_season',
            ],
            [
                'slug' => 'season_top_3',
                'type' => 'season',
                'name' => 'Pódio da Temporada',
                'description' => 'Terminou no Top 3 do Ranking Global Anual.',
                'default_icon' => 'podium_season',
            ],
            [
                'slug' => 'season_top_10',
                'type' => 'season',
                'name' => 'Elite da Temporada',
                'description' => 'Terminou no Top 10 do Ranking Global Anual.',
                'default_icon' => 'star_season',
            ],
        ];

        foreach ($badges as $data) {
            $badge = Badge::where('slug', $data['slug'])->first();

            if ($badge) {
                // Atualiza apenas metadados, preserva ícone se já existir
                $badge->update([
                    'type' => $data['type'],
                    'name' => $data['name'],
                    'description' => $data['description'],
                    // Só atualiza o ícone se ele estiver nulo no banco
                    'icon' => $badge->icon ?? $data['default_icon'],
                ]);
            } else {
                // Cria novo
                Badge::create([
                    'slug' => $data['slug'],
                    'type' => $data['type'],
                    'name' => $data['name'],
                    'description' => $data['description'],
                    'icon' => $data['default_icon'],
                ]);
            }
        }
    }
}
