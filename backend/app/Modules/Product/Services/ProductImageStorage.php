<?php

namespace App\Modules\Product\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class ProductImageStorage
{
    /** @return array{path: string, url: string} */
    public function store(UploadedFile $image): array
    {
        $path = Storage::disk('public')->putFileAs(
            'products/'.now()->format('Y/m'),
            $image,
            Str::uuid().'.'.$image->extension(),
        );
        if (! $path) {
            throw new RuntimeException('Không thể lưu ảnh sản phẩm.');
        }

        return ['path' => $path, 'url' => Storage::disk('public')->url($path)];
    }

    /** @param string[] $paths */
    public function deletePaths(array $paths): void
    {
        if ($paths !== []) {
            Storage::disk('public')->delete($paths);
        }
    }

    /** @param string[] $urls */
    public function deleteUrls(array $urls): void
    {
        $paths = [];
        foreach ($urls as $url) {
            $path = parse_url($url, PHP_URL_PATH);
            if (! is_string($path)) {
                continue;
            }
            $marker = '/storage/products/';
            $position = strpos($path, $marker);
            if ($position !== false) {
                $paths[] = substr($path, $position + strlen('/storage/'));
            }
        }
        $this->deletePaths($paths);
    }
}
