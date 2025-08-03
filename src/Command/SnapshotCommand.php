<?php
// src/Command/SnapshotCommand.php
namespace App\Command;

use App\Service\SnapshotService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:snapshot',
    description: 'Capture snapshots from all enabled cameras'
)]
class SnapshotCommand extends Command
{
    public function __construct(
        private SnapshotService $snapshotService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('camera-id', 'c', InputOption::VALUE_OPTIONAL, 'Process only specific camera ID')
            ->addOption('cleanup', null, InputOption::VALUE_NONE, 'Run cleanup of old files after snapshots')
            ->addOption('stats', null, InputOption::VALUE_NONE, 'Show statistics only')
            ->addOption('daemon', 'd', InputOption::VALUE_NONE, 'Run as daemon (continuous loop)')
            ->addOption('interval', 'i', InputOption::VALUE_OPTIONAL, 'Daemon check interval in seconds', 60)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Show statistics only
        if ($input->getOption('stats')) {
            $this->showStatistics($io);
            return Command::SUCCESS;
        }

        // Run as daemon
        if ($input->getOption('daemon')) {
            return $this->runDaemon($input, $output, $io);
        }

        // Run once
        return $this->runOnce($input, $output, $io);
    }

    private function runOnce(InputInterface $input, OutputInterface $output, SymfonyStyle $io): int
    {
        $cameraId = $input->getOption('camera-id');
        
        if ($cameraId) {
            $io->title("Processing Camera ID: {$cameraId}");
            // TODO: Implement single camera processing
            $io->warning('Single camera processing not yet implemented');
            return Command::FAILURE;
        } else {
            $io->title('Processing All Enabled Cameras');
            
            $startTime = microtime(true);
            $results = $this->snapshotService->processAllCameras();
            $duration = microtime(true) - $startTime;

            $this->displayResults($io, $results, $duration);
        }

        // Run cleanup if requested
        if ($input->getOption('cleanup')) {
            $io->section('Running Cleanup');
            $this->snapshotService->cleanupOldFiles();
            $io->success('Cleanup completed');
        }

        return Command::SUCCESS;
    }

    private function runDaemon(InputInterface $input, OutputInterface $output, SymfonyStyle $io): int
    {
        $interval = (int)$input->getOption('interval');
        
        $io->title('Starting Snapshot Daemon');
        $io->info("Check interval: {$interval} seconds");
        $io->info('Press Ctrl+C to stop');
        
        $iteration = 0;
        
        while (true) {
            $iteration++;
            $io->section("Iteration #{$iteration} - " . date('Y-m-d H:i:s'));
            
            try {
                $startTime = microtime(true);
                $results = $this->snapshotService->processAllCameras();
                $duration = microtime(true) - $startTime;

                $this->displayResults($io, $results, $duration, true);

                // Run cleanup every 100 iterations (or roughly daily if interval is 60s)
                if ($iteration % 100 === 0) {
                    $io->info('Running periodic cleanup...');
                    $this->snapshotService->cleanupOldFiles();
                }

            } catch (\Exception $e) {
                $io->error("Error in daemon iteration: " . $e->getMessage());
            }

            $io->info("Sleeping for {$interval} seconds...");
            sleep($interval);
        }

        return Command::SUCCESS;
    }

    private function displayResults(SymfonyStyle $io, array $results, float $duration, bool $compact = false): void
    {
        $total = count($results);
        $successful = count(array_filter($results, fn($r) => $r['success'] === true));
        $failed = count(array_filter($results, fn($r) => $r['success'] === false && !$r['skipped']));
        $skipped = count(array_filter($results, fn($r) => $r['skipped'] === true));

        if ($compact) {
            $io->info(sprintf(
                'Processed %d cameras in %.2fs - Success: %d, Failed: %d, Skipped: %d',
                $total, $duration, $successful, $failed, $skipped
            ));
        } else {
            $io->success(sprintf('Processed %d cameras in %.2f seconds', $total, $duration));

            // Summary table
            $io->table(['Status', 'Count'], [
                ['Successful', $successful],
                ['Failed', $failed],
                ['Skipped', $skipped],
                ['Total', $total],
            ]);

            // Detailed results
            if ($total > 0) {
                $tableRows = [];
                foreach ($results as $cameraId => $result) {
                    $status = $result['success'] ? 'SUCCESS' : ($result['skipped'] ? 'SKIPPED' : 'FAILED');
                    $details = '';
                    
                    if ($result['success']) {
                        $details = sprintf('%s (%.0fms)', $result['filename'] ?? '', $result['executionTime'] ?? 0);
                    } else {
                        $details = $result['error'] ?? 'Unknown error';
                    }
                    
                    $tableRows[] = [$cameraId, $status, $details];
                }

                $io->table(['Camera ID', 'Status', 'Details'], $tableRows);
            }
        }
    }

    private function showStatistics(SymfonyStyle $io): void
    {
        $io->title('Snapshot Statistics');
        
        $stats = $this->snapshotService->getStatistics();
        
        $io->table(['Metric', 'Value'], [
            ['Snapshots Today', $stats['totalToday']],
            ['Successful Today', $stats['successToday']],
            ['Failed Today', $stats['failedToday']],
            ['Success Rate', $stats['successRate'] . '%'],
        ]);
    }
}
