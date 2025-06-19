<?php

declare(strict_types=1);

namespace Tourze\TusUploadServerBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tourze\TusUploadServerBundle\Service\TusUploadService;

#[AsCommand(
    name: self::NAME,
    description: 'Clean up expired TUS uploads'
)]
class TusCleanupCommand extends Command
{
    public const NAME = 'tus:cleanup';
public function __construct(
        private readonly TusUploadService $tusUploadService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->info('Starting TUS upload cleanup...');

        $deletedCount = $this->tusUploadService->cleanupExpiredUploads();

        if ($deletedCount === 0) {
            $io->success('No expired uploads found.');
        } else {
            $io->success(sprintf('Cleaned up %d expired uploads.', $deletedCount));
        }

        return Command::SUCCESS;
    }
}
