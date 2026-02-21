<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class NormalizeUserAvatars extends Command
{
    protected $signature = 'users:normalize-avatars';
    protected $description = 'Rename user avatars to {user_id}.webp format to prevent broken links.';

    public function handle()
    {
        $disk = config('filesystems.default');
        $users = User::whereNotNull('photo_url')->get();
        $count = 0;

        $this->info("Found {$users->count()} users with photos. Starting normalization...");

        foreach ($users as $user) {
            $currentPath = $user->photo_url;
            $expectedPath = 'users/' . $user->id . '.webp';

            // Se já está no padrão, pula
            if ($currentPath === $expectedPath) {
                continue;
            }

            // Verifica se o arquivo atual existe
            if (!Storage::disk($disk)->exists($currentPath)) {
                $this->warn("File not found for User {$user->id}: {$currentPath}");
                continue;
            }

            // Move (Renomeia)
            try {
                // Se já existir um arquivo no destino (lixo antigo), apaga antes
                if (Storage::disk($disk)->exists($expectedPath)) {
                    Storage::disk($disk)->delete($expectedPath);
                }

                Storage::disk($disk)->move($currentPath, $expectedPath);

                // Atualiza banco
                $user->update(['photo_url' => $expectedPath]);

                $this->info("Normalized User {$user->id}: {$currentPath} -> {$expectedPath}");
                $count++;
            } catch (\Exception $e) {
                $this->error("Failed to move file for User {$user->id}: " . $e->getMessage());
            }
        }

        $this->info("Normalization complete. {$count} avatars updated.");
    }
}
