<?php

namespace Database\Seeders;

use App\Models\Badge;
use Illuminate\Database\Seeder;

class BadgeSeeder extends Seeder
{
    public function run(): void
    {
        $badges = [
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
        ];

        foreach ($badges as $badge) {
            // Usa firstOrCreate para não sobrescrever edições manuais (ex: ícones/imagens)
            Badge::firstOrCreate(['slug' => $badge['slug']], $badge);
        }
    }
}
