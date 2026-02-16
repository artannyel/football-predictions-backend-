<?php

namespace Database\Seeders;

use App\Models\Badge;
use Illuminate\Database\Seeder;

class BadgeSeeder extends Seeder
{
    public function run(): void
    {
        $badges = [
            // Eventuais
            [
                'slug' => 'sniper',
                'name' => 'Sniper',
                'description' => 'Acertou o placar exato de um jogo.',
                'icon' => 'target',
            ],
            [
                'slug' => 'zebra',
                'name' => 'Caçador de Zebras',
                'description' => 'Acertou um resultado que menos de 15% dos usuários apostaram.',
                'icon' => 'zebra',
            ],
            [
                'slug' => 'ousado',
                'name' => 'Ousado',
                'description' => 'Apostou no empate e acertou.',
                'icon' => 'balance',
            ],
            // Marcos de Pontos
            [
                'slug' => 'points_50',
                'name' => 'Iniciante',
                'description' => 'Alcançou 50 pontos em uma liga.',
                'icon' => 'medal_bronze',
            ],
            [
                'slug' => 'points_200',
                'name' => 'Profissional',
                'description' => 'Alcançou 200 pontos em uma liga.',
                'icon' => 'medal_silver',
            ],
            [
                'slug' => 'points_500',
                'name' => 'Mestre',
                'description' => 'Alcançou 500 pontos em uma liga.',
                'icon' => 'medal_gold',
            ],
            [
                'slug' => 'points_1000',
                'name' => 'Lenda',
                'description' => 'Alcançou 1000 pontos em uma liga.',
                'icon' => 'medal_diamond',
            ],
        ];

        foreach ($badges as $badge) {
            Badge::firstOrCreate(['slug' => $badge['slug']], $badge);
        }
    }
}
