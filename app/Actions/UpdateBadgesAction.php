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

                $filename = $item['slug'] . '_' . time() . '.png'; // Usa slug para nomear
                $path = 'badges/' . $filename;

                // Redimensiona e converte para PNG (transparência)
                $image = Image::read($item['icon_file'])
                    ->resize(200, 200, function ($constraint) {
                        $constraint->aspectRatio();
                        $constraint->upsize();
                    })
                    ->toPng();

                Storage::disk($disk)->put($path, (string) $image, 'public');

                $updateData['icon'] = $path;
            }

            if (!empty($updateData)) {
                $badge->update($updateData);
            }
        }
    }
}
