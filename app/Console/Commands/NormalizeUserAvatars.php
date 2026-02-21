<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
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
        $errors = 0;

        $this->info("Found {$users->count()} users with photos. Starting normalization on disk: {$disk}...");

        foreach ($users as $user) {
            $currentPath = $user->photo_url;
            $expectedPath = 'users/' . $user->id . '.webp';

            if ($currentPath === $expectedPath) {
                continue;
            }

            if (!Storage::disk($disk)->exists($currentPath)) {
                $this->warn("File not found for User {$user->id}: {$currentPath}");
                continue;
            }

            try {
                $copied = Storage::disk($disk)->copy($currentPath, $expectedPath);

                if ($copied) {
                    $user->update(['photo_url' => $expectedPath]);

                    Storage::disk($disk)->delete($currentPath);

                    $this->info("Normalized User {$user->id}: {$currentPath} -> {$expectedPath}");
                    $count++;
                } else {
                    $this->error("Failed to copy file for User {$user->id}");
                    $errors++;
                }
            } catch (\Exception $e) {
                $this->error("Exception for User {$user->id}: " . $e->getMessage());
                Log::error("NormalizeAvatars Error: " . $e->getMessage());
                $errors++;
            }
        }

        $this->info("Normalization complete. {$count} avatars updated. {$errors} errors.");
    }
}
