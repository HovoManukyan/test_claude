<?php

namespace App\Service;

class ParseImageService
{
    public function downloadAndSaveImagesFromPandascore(string $url, int $id, string $type = 'general'): string
    {
        $uploadDir = __DIR__ . "/../../public/cdn/{$type}";
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $imagePath = "{$uploadDir}/{$id}.jpg";

        $imageContent = file_get_contents($url);
        if ($imageContent === false) {
            throw new \Exception("Unable to download image from URL: $url");
        }

        file_put_contents($imagePath, $imageContent);

        return "/cdn/{$type}/{$id}.jpg";
    }

}