<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class FileService
{
    public function __construct(
        private readonly Filesystem $filesystem,
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        #[Autowire('%upload_dir%')]
        private readonly string $uploadDir,
        #[Autowire('%team_images_dir%')]
        private readonly string $teamImagesDir,
        #[Autowire('%player_images_dir%')]
        private readonly string $playerImagesDir,
    ) {
    }

    /**
     * Download image from URL and save to the filesystem
     */
    public function downloadImage(string $imageUrl, string $directory, string $filename): ?string
    {
        try {
            $response = $this->httpClient->request('GET', $imageUrl);

            if ($response->getStatusCode() !== 200) {
                $this->logger->warning('Failed to download image', [
                    'url' => $imageUrl,
                    'status_code' => $response->getStatusCode(),
                ]);
                return null;
            }

            $content = $response->getContent(false);
            $extension = $this->getImageExtension($imageUrl);
            $fullFilename = $filename . '.' . $extension;
            $filePath = $directory . '/' . $fullFilename;

            // Ensure the directory exists
            $this->filesystem->mkdir($directory, 0755);

            // Write the file
            $this->filesystem->dumpFile($filePath, $content);

            // Return the relative path from public directory
            return str_replace('public/', '', $filePath);
        } catch (\Exception $e) {
            $this->logger->error('Error downloading image', [
                'url' => $imageUrl,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }

    /**
     * Upload a file from a form request
     */
    public function uploadFile(UploadedFile $file, string $directory, ?string $filename = null): ?string
    {
        try {
            $originalFilename = $file->getClientOriginalName();
            $safeFilename = $filename ?? $this->getSafeFilename($originalFilename);
            $extension = $file->guessExtension() ?? 'bin';
            $newFilename = $safeFilename . '.' . $extension;

            // Ensure the directory exists
            $this->filesystem->mkdir($directory, 0755);

            // Move the file
            $file->move($directory, $newFilename);

            // Return the relative path from public directory
            return str_replace('public/', '', $directory . '/' . $newFilename);
        } catch (\Exception $e) {
            $this->logger->error('Error uploading file', [
                'original_filename' => $file->getClientOriginalName(),
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }

    /**
     * Delete a file
     */
    public function deleteFile(string $path): bool
    {
        try {
            $fullPath = 'public/' . $path;

            if ($this->filesystem->exists($fullPath)) {
                $this->filesystem->remove($fullPath);
                return true;
            }

            return false;
        } catch (\Exception $e) {
            $this->logger->error('Error deleting file', [
                'path' => $path,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }

    /**
     * Download team logo
     */
    public function downloadTeamLogo(string $imageUrl, string $teamId): ?string
    {
        return $this->downloadImage($imageUrl, $this->teamImagesDir, $teamId);
    }

    /**
     * Download player image
     */
    public function downloadPlayerImage(string $imageUrl, string $playerId): ?string
    {
        return $this->downloadImage($imageUrl, $this->playerImagesDir, $playerId);
    }

    /**
     * Get image extension from URL
     */
    private function getImageExtension(string $imageUrl): string
    {
        $extension = pathinfo(parse_url($imageUrl, PHP_URL_PATH), PATHINFO_EXTENSION);
        return in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif']) ? $extension : 'jpg';
    }

    /**
     * Get a safe filename
     */
    private function getSafeFilename(string $filename): string
    {
        // Replace non-alphanumeric characters with underscores
        $safeFilename = preg_replace('/[^A-Za-z0-9-]/', '_', $filename);
        // Ensure filename is unique by adding a timestamp
        return $safeFilename . '_' . time();
    }
}