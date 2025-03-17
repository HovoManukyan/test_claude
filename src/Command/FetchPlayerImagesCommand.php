<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Player;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Doctrine\ORM\EntityManagerInterface;

#[AsCommand(
    name: 'app:fetch-player-images',
    description: 'Fetch and save player images using HttpClient',
)]
class FetchPlayerImagesCommand extends Command
{
    private const IMAGE_PATH = 'public/cdn/players/';

    public function __construct(
        private readonly HttpClientInterface    $httpClient,
        private readonly EntityManagerInterface $entityManager
    )
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Fetching and updating player images');

        $players = $this->entityManager->getRepository(Player::class)
            ->createQueryBuilder('p')
            ->where('p.image IS NOT NULL')
            ->andWhere('p.image LIKE :urlPattern')
            ->setParameter('urlPattern', 'https%')
            ->getQuery()
            ->getResult();


        if (empty($players)) {
            $io->warning('No players found with an image URL.');
            return Command::SUCCESS;
        }

        $io->info(sprintf('Found %d players with images.', count($players)));

        foreach ($players as $player) {
            $playerId = $player->getPandascoreId();
            $imageUrl = $player->getImage();
            $extension = $this->getImageExtension($imageUrl);
            $filePath = self::IMAGE_PATH . "{$playerId}.{$extension}";

            if (file_exists($filePath)) {
                continue;
            }

            if ($this->downloadImage($imageUrl, $filePath)) {
                $player->setImage(str_replace('public/', '', $filePath));
                $this->entityManager->persist($player);
            }
        }

        $this->entityManager->flush();
        $io->success('All player images fetched and updated.');
        return Command::SUCCESS;
    }

    private function downloadImage(string $imageUrl, string $filePath): bool
    {
        try {
            $response = $this->httpClient->request('GET', $imageUrl);
            $content = $response->getContent(false);

            if ($response->getStatusCode() !== 200) {
                return false;
            }

            if (!is_dir(dirname($filePath))) {
                mkdir(dirname($filePath), 0777, true);
            }

            return file_put_contents($filePath, $content) !== false;
        } catch (\Exception) {
            return false;
        }
    }

    private function getImageExtension(string $imageUrl): string
    {
        $extension = pathinfo(parse_url($imageUrl, PHP_URL_PATH), PATHINFO_EXTENSION);
        return in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif']) ? $extension : 'jpg';
    }
}
