<?php

namespace App\Actions;

use App\Models\Badge;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;

class UpdateBadgesAction
{
    public function execute(array $data): void
    {
        foreach ($data as $item) {
            if (!isset($item['slug'])) continue;

            $badge = Badge::where('slug', $item['slug'])->first();
            if (!$badge) continue;

            $updateData = [];

            if (isset($item['name'])) {
                $updateData['name'] = $item['name'];
            }

            if (isset($item['description'])) {
                $updateData['description'] = $item['description'];
            }

            if (isset($item['icon_file']) && $item['icon_file'] instanceof UploadedFile) {
                $disk = config('filesystems.default');

                // Remove antigo se existir
                if ($badge->icon && Storage::disk($disk)->exists($badge->icon)) {
                    Storage::disk($disk)->delete($badge->icon);
                }

                // Muda extensão para .webp
                $filename = $item['slug'] . '_' . time() . '.webp';
                $path = 'badges/' . $filename;

                // Redimensiona e converte para WebP (qualidade 80)
                $image = Image::read($item['icon_file'])
                    ->resize(500, 500, function ($constraint) {
                        $constraint->aspectRatio();
                        $constraint->upsize();
                    })
                    ->toWebp(80);

                Storage::disk($disk)->put($path, (string) $image, 'public');

                $updateData['icon'] = $path;
            }

            if (!empty($updateData)) {
                $badge->update($updateData);
            }
        }
    }
}
