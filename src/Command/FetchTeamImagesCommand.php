<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Team;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Doctrine\ORM\EntityManagerInterface;

#[AsCommand(
    name: 'app:fetch-team-images',
    description: 'Fetch and save team logos using HttpClient',
)]
class FetchTeamImagesCommand extends Command
{
    private const IMAGE_PATH = 'public/cdn/teams/';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        ini_set('memory_limit', '2048M');
        $io = new SymfonyStyle($input, $output);
        $io->title('Fetching and updating team logos');

        $teams = $this->entityManager->getRepository(Team::class)->createQueryBuilder('t')
            ->where('t.image IS NOT NULL')
            ->andWhere('t.image LIKE :urlPattern')
            ->setParameter('urlPattern', 'https://%')
            ->getQuery()
            ->getResult();

        if (empty($teams)) {
            $io->warning('No teams found with a logo URL.');
            return Command::SUCCESS;
        }

        $io->info(sprintf('Found %d teams with logos.', count($teams)));

        foreach ($teams as $team) {
            $io->info('Fetching '.$team->getId().' teams image from link '. $team->getImage());
            $teamId = $team->getPandascoreId();
            $imageUrl = $team->getImage();
            $extension = $this->getImageExtension($imageUrl);
            $filePath = self::IMAGE_PATH . "{$teamId}.{$extension}";

            if (file_exists($filePath)) {
                $team->setImage(str_replace('public/', '', $filePath));
                $this->entityManager->persist($team);
                $io->info('Continue');
                continue;
            }

            if ($this->downloadImage($imageUrl, $filePath, $io)) {
                $team->setImage(str_replace('public/', '', $filePath));
                $this->entityManager->persist($team);
                $io->info('downloading');
            }
        }

        $this->entityManager->flush();
        $io->success('All team logos fetched and updated.');
        return Command::SUCCESS;
    }

    private function downloadImage(string $imageUrl, string $filePath, $io): bool
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
        } catch (\Exception $exception) {
            $io->info('Exception' . $exception->getMessage());
            return false;
        }
    }

    private function getImageExtension(string $imageUrl): string
    {
        $extension = pathinfo(parse_url($imageUrl, PHP_URL_PATH), PATHINFO_EXTENSION);
        return in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif']) ? $extension : 'jpg';
    }
}
