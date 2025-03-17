<?php

declare(strict_types=1);

namespace App\Service;

use App\Service\Http\HttpClientService;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Service for file system operations
 */
class FileService
{
    /**
     * @param Filesystem $filesystem Symfony filesystem component
     * @param HttpClientService $httpClient HTTP client service
     * @param LoggerInterface $logger Logger
     * @param SluggerInterface $slugger String slugger
     * @param string $uploadDir Base upload directory
     * @param string $teamImagesDir Team images directory
     * @param string $playerImagesDir Player images directory
     * @param string $bannerImagesDir Banner images directory
     */
    public function __construct(
        private readonly Filesystem $filesystem,
        private readonly HttpClientService $httpClient,
        private readonly LoggerInterface $logger,
        private readonly SluggerInterface $slugger,
        #[Autowire('%upload_dir%')]
        private readonly string $uploadDir,
        #[Autowire('%team_images_dir%')]
        private readonly string $teamImagesDir,
        #[Autowire('%player_images_dir%')]
        private readonly string $playerImagesDir,
        #[Autowire('%banner_images_dir%')]
        private readonly string $bannerImagesDir,
    ) {
    }

    /**
     * Download image from URL and save to filesystem
     *
     * @param string $imageUrl The URL of the image to download
     * @param string $directory Target directory (relative to project root)
     * @param string $filename Filename without extension
     * @return string|null Path relative to public directory or null on failure
     */
    public function downloadImage(string $imageUrl, string $directory, string $filename): ?string
    {
        try {
            // Ensure the directory exists
            $this->createDirectory($directory);

            $content = $this->httpClient->downloadFile($imageUrl);
            $extension = $this->getImageExtension($imageUrl);
            $fullFilename = $filename . '.' . $extension;
            $filePath = $directory . '/' . $fullFilename;

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
     *
     * @param UploadedFile $file The uploaded file
     * @param string $directory Target directory (relative to project root)
     * @param string|null $filename Optional custom filename without extension
     * @return string|null Path relative to public directory or null on failure
     */
    public function uploadFile(UploadedFile $file, string $directory, ?string $filename = null): ?string
    {
        try {
            // Ensure the directory exists
            $this->createDirectory($directory);

            $originalFilename = $file->getClientOriginalName();
            $safeFilename = $filename ?? $this->getSafeFilename($originalFilename);
            $extension = $file->guessExtension() ?? 'bin';
            $newFilename = $safeFilename . '.' . $extension;

            // Move the file
            $file->move($directory, $newFilename);

            // Return the relative path from public directory
            return str_replace('public/', '', $directory . '/' . $newFilename);
        } catch (FileException $e) {
            $this->logger->error('Error uploading file', [
                'original_filename' => $file->getClientOriginalName(),
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Delete a file
     *
     * @param string $path File path relative to public directory
     * @return bool True on success, false on failure
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
        } catch (IOExceptionInterface $e) {
            $this->logger->error('Error deleting file', [
                'path' => $path,
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Download team logo
     *
     * @param string $imageUrl The URL of the logo to download
     * @param string $teamId Team ID used as filename
     * @return string|null Path relative to public directory or null on failure
     */
    public function downloadTeamLogo(string $imageUrl, string $teamId): ?string
    {
        return $this->downloadImage($imageUrl, $this->teamImagesDir, $teamId);
    }

    /**
     * Download player image
     *
     * @param string $imageUrl The URL of the image to download
     * @param string $playerId Player ID used as filename
     * @return string|null Path relative to public directory or null on failure
     */
    public function downloadPlayerImage(string $imageUrl, string $playerId): ?string
    {
        return $this->downloadImage($imageUrl, $this->playerImagesDir, $playerId);
    }

    /**
     * Upload banner image
     *
     * @param UploadedFile $image The uploaded image
     * @param int $bannerId Banner ID used as directory name
     * @return string|null Path relative to public directory or null on failure
     */
    public function uploadBannerImage(UploadedFile $image, int $bannerId): ?string
    {
        $directory = $this->bannerImagesDir . '/' . $bannerId;
        return $this->uploadFile($image, $directory, 'banner');
    }

    /**
     * Create directory if it doesn't exist
     *
     * @param string $directory Directory path
     * @return void
     * @throws IOExceptionInterface
     */
    private function createDirectory(string $directory): void
    {
        if (!$this->filesystem->exists($directory)) {
            $this->filesystem->mkdir($directory, 0755);
        }
    }

    /**
     * Get image extension from URL
     *
     * @param string $imageUrl Image URL
     * @return string Extension (jpg by default)
     */
    private function getImageExtension(string $imageUrl): string
    {
        $extension = pathinfo(parse_url($imageUrl, PHP_URL_PATH), PATHINFO_EXTENSION);
        return in_array(strtolower($extension), ['jpg', 'jpeg', 'png', 'webp', 'gif']) ? $extension : 'jpg';
    }

    /**
     * Get a safe filename
     *
     * @param string $filename Original filename
     * @return string Safe filename
     */
    private function getSafeFilename(string $filename): string
    {
        // Get filename without extension
        $baseFilename = pathinfo($filename, PATHINFO_FILENAME);

        // Slugify filename to make it safe
        $safeFilename = $this->slugger->slug($baseFilename)->lower();

        // Add unique identifier
        return $safeFilename . '_' . uniqid();
    }
}