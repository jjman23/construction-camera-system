<?php
// src/Command/CleanupCommand.php
namespace App\Command;

use App\Service\SnapshotService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:cleanup',
    description: 'Thin snapshot images older than 90 days to one per hour'
)]
class CleanupCommand extends Command
{
    public function __construct(
        private SnapshotService $snapshotService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Snapshot Cleanup');
        $io->info('Retention policy: keep all images ≤ 90 days, one per hour after that.');

        $start = microtime(true);
        $stats = $this->snapshotService->cleanupOldFiles();
        $duration = round(microtime(true) - $start, 2);

        $io->table(['Metric', 'Count'], [
            ['Directories thinned', $stats['dirs_thinned']],
            ['Files deleted',       $stats['files_deleted']],
            ['Log entries deleted', $stats['logs_deleted']],
        ]);

        $io->success(sprintf('Cleanup completed in %.2f seconds.', $duration));
        return Command::SUCCESS;
    }
}
