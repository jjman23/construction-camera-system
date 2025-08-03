<?php
// src/Service/SnapshotService.php
namespace App\Service;

use App\Entity\Camera;
use App\Entity\SnapshotLog;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process;

class SnapshotService
{
    private string $imagesBasePath;
    private string $ffmpegPath;
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;

    public function __construct(
        string $imagesBasePath,
        string $ffmpegPath,
        EntityManagerInterface $entityManager,
        LoggerInterface $logger
    ) {
        $this->imagesBasePath = rtrim($imagesBasePath, '/');
        $this->ffmpegPath = $ffmpegPath;
        $this->entityManager = $entityManager;
        $this->logger = $logger;
    }

    /**
     * Process all enabled cameras for snapshot capture
     */
    public function processAllCameras(): array
    {
        $results = [];
        
        // Get all cameras with snapshots enabled
        $cameras = $this->entityManager->getRepository(Camera::class)
            ->findBy([
                'active' => true,
                'snapshotEnabled' => true
            ]);

        $this->logger->info("Processing snapshots for " . count($cameras) . " cameras");

        foreach ($cameras as $camera) {
            try {
                // Check if enough time has passed since last snapshot
                if (!$this->shouldTakeSnapshot($camera)) {
                    $results[$camera->getId()] = [
                        'success' => false,
                        'error' => 'Interval not reached',
                        'skipped' => true
                    ];
                    continue;
                }

                $result = $this->processCameraSnapshot($camera);
                $results[$camera->getId()] = $result;
            } catch (\Exception $e) {
                $this->logger->error("Failed to process camera {$camera->getId()}: " . $e->getMessage());
                $results[$camera->getId()] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                    'skipped' => false
                ];
            }
        }

        return $results;
    }

    /**
     * Check if camera should take a snapshot based on its interval
     */
    private function shouldTakeSnapshot(Camera $camera): bool
    {
        $lastSnapshot = $camera->getLastSnapshotAt();
        
        // If never taken a snapshot, take one now
        if (!$lastSnapshot) {
            return true;
        }

        // Check if enough time has passed based on camera's interval
        $intervalSeconds = $camera->getSnapshotInterval();
        $nextSnapshotTime = clone $lastSnapshot;
        $nextSnapshotTime->add(new \DateInterval("PT{$intervalSeconds}S"));
        
        return new \DateTime() >= $nextSnapshotTime;
    }

    /**
     * Process snapshot for a single camera
     */
    public function processCameraSnapshot(Camera $camera): array
    {
        $startTime = microtime(true);
        
        // Check if within construction hours
        if (!$camera->isWithinConstructionHours()) {
            $this->logSnapshot($camera, SnapshotLog::STATUS_SKIPPED, 'Outside construction hours');
            return [
                'success' => false,
                'error' => 'Outside construction hours',
                'skipped' => true
            ];
        }

        try {
            // Create directory structure
            $dateDir = date('Ymd');
            $cameraDir = $this->imagesBasePath . "/cam{$camera->getId()}";
            $dayDir = $cameraDir . "/{$dateDir}";
            $thumbnailDir = $dayDir . "/thumbnail";

            $this->ensureDirectoryExists($dayDir);
            $this->ensureDirectoryExists($thumbnailDir);

            // Generate filenames
            $timestamp = date('His');
            $filename = "cam{$camera->getId()}_{$timestamp}.jpg";
            $fullImagePath = $dayDir . "/{$filename}";
            $thumbnailPath = $thumbnailDir . "/thumbnail-{$filename}";

            // Capture full-size snapshot
            $this->captureSnapshot($camera->getRtspUrl(), $fullImagePath);
            
            // Capture thumbnail
            $this->captureSnapshot($camera->getRtspUrl(), $thumbnailPath, 192, 108);

            // Verify files were created
            if (!file_exists($fullImagePath)) {
                throw new \Exception("Full-size snapshot file was not created");
            }

            if (!file_exists($thumbnailPath)) {
                $this->logger->warning("Thumbnail was not created for camera {$camera->getId()}");
            }

            $fileSize = filesize($fullImagePath);
            $executionTime = (int)((microtime(true) - $startTime) * 1000);

            // Update camera status
            $camera->setLastSnapshotAt(new \DateTime());
            $camera->setLastSnapshotStatus('success');
            $this->entityManager->flush();

            // Log success
            $this->logSnapshot(
                $camera, 
                SnapshotLog::STATUS_SUCCESS, 
                null, 
                "images/cam{$camera->getId()}/{$dateDir}/{$filename}",
                $fileSize,
                $executionTime
            );

            $this->logger->info("Snapshot captured successfully for camera {$camera->getId()}: {$filename}");

            return [
                'success' => true,
                'filename' => $filename,
                'fileSize' => $fileSize,
                'executionTime' => $executionTime,
                'skipped' => false
            ];

        } catch (\Exception $e) {
            $executionTime = (int)((microtime(true) - $startTime) * 1000);
            
            // Update camera status
            $camera->setLastSnapshotAt(new \DateTime());
            $camera->setLastSnapshotStatus('failed');
            $this->entityManager->flush();

            // Log failure
            $this->logSnapshot($camera, SnapshotLog::STATUS_FAILED, $e->getMessage(), null, null, $executionTime);

            $this->logger->error("Snapshot failed for camera {$camera->getId()}: " . $e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'executionTime' => $executionTime,
                'skipped' => false
            ];
        }
    }

    /**
     * Capture snapshot using FFmpeg
     */
    private function captureSnapshot(string $streamUrl, string $outputPath, int $width = 0, int $height = 0): void
    {
        $command = [
            $this->ffmpegPath,
            '-y', // Overwrite output file
        ];

        // Add protocol-specific options
        if (str_starts_with($streamUrl, 'rtsp://')) {
            $command[] = '-rtsp_transport';
            $command[] = 'tcp'; // Force TCP for RTSP
        }

        $command[] = '-i';
        $command[] = $streamUrl;
        $command[] = '-vframes';
        $command[] = '1'; // Capture one frame
        $command[] = '-f';
        $command[] = 'image2'; // Output format

        // Add scaling if dimensions specified
        if ($width > 0 && $height > 0) {
            $command[] = '-vf';
            $command[] = "scale={$width}:{$height}";
        }

        // Add quality settings
        $command[] = '-q:v';
        $command[] = '2'; // High quality

        $command[] = $outputPath;

        // Create process with timeout
        $process = new Process($command);
        $process->setTimeout(60); // Increase to 60 seconds for testing

        $this->logger->debug("Running FFmpeg command: " . $process->getCommandLine());

        $process->run();

        if (!$process->isSuccessful()) {
            throw new \Exception("FFmpeg failed: " . $process->getErrorOutput());
        }
    }

    /**
     * Ensure directory exists with proper permissions
     */
    private function ensureDirectoryExists(string $path): void
    {
        if (!is_dir($path)) {
            if (!mkdir($path, 0755, true)) {
                throw new \Exception("Failed to create directory: {$path}");
            }
        }

        if (!is_writable($path)) {
            throw new \Exception("Directory is not writable: {$path}");
        }
    }

    /**
     * Log snapshot attempt to database
     */
    private function logSnapshot(
        Camera $camera, 
        string $status, 
        ?string $errorMessage = null, 
        ?string $filePath = null,
        ?int $fileSize = null,
        ?int $executionTimeMs = null
    ): void {
        try {
            $log = new SnapshotLog();
            $log->setCamera($camera);
            $log->setStatus($status);
            $log->setErrorMessage($errorMessage);
            $log->setFilePath($filePath);
            $log->setFileSize($fileSize);
            $log->setExecutionTimeMs($executionTimeMs);

            $this->entityManager->persist($log);
            $this->entityManager->flush();
        } catch (\Exception $e) {
            $this->logger->error("Failed to log snapshot attempt: " . $e->getMessage());
        }
    }

    /**
     * Clean up old snapshots and logs
     */
    public function cleanupOldFiles(int $daysToKeep = 30): void
    {
        $cutoffDate = new \DateTime("-{$daysToKeep} days");
        
        // Clean up old log entries
        $this->entityManager->createQuery(
            'DELETE FROM App\Entity\SnapshotLog sl WHERE sl.attemptedAt < :cutoff'
        )->setParameter('cutoff', $cutoffDate)->execute();

        // Clean up old snapshot files
        $cameras = $this->entityManager->getRepository(Camera::class)->findAll();
        
        foreach ($cameras as $camera) {
            $cameraDir = $this->imagesBasePath . "/cam{$camera->getId()}";
            
            if (!is_dir($cameraDir)) {
                continue;
            }

            $directories = glob($cameraDir . '/????????'); // YYYYMMDD pattern
            
            foreach ($directories as $dir) {
                $dirDate = \DateTime::createFromFormat('Ymd', basename($dir));
                
                if ($dirDate && $dirDate < $cutoffDate) {
                    $this->removeDirectory($dir);
                    $this->logger->info("Removed old snapshot directory: " . basename($dir));
                }
            }
        }
    }

    /**
     * Recursively remove directory
     */
    private function removeDirectory(string $dir): void
    {
        if (is_dir($dir)) {
            $files = array_diff(scandir($dir), ['.', '..']);
            
            foreach ($files as $file) {
                $path = $dir . '/' . $file;
                is_dir($path) ? $this->removeDirectory($path) : unlink($path);
            }
            
            rmdir($dir);
        }
    }

    /**
     * Get snapshot statistics
     */
    public function getStatistics(): array
    {
        // Total snapshots today
        $today = new \DateTime('today');
        $qb = $this->entityManager->createQueryBuilder();
        
        $totalToday = $qb->select('COUNT(sl.id)')
            ->from(SnapshotLog::class, 'sl')
            ->where('sl.attemptedAt >= :today')
            ->setParameter('today', $today)
            ->getQuery()
            ->getSingleScalarResult();

        // Success rate today
        $qb = $this->entityManager->createQueryBuilder();
        $successToday = $qb->select('COUNT(sl.id)')
            ->from(SnapshotLog::class, 'sl')
            ->where('sl.attemptedAt >= :today')
            ->andWhere('sl.status = :status')
            ->setParameter('today', $today)
            ->setParameter('status', SnapshotLog::STATUS_SUCCESS)
            ->getQuery()
            ->getSingleScalarResult();

        $successRate = $totalToday > 0 ? round(($successToday / $totalToday) * 100, 1) : 0;

        return [
            'totalToday' => $totalToday,
            'successToday' => $successToday,
            'successRate' => $successRate,
            'failedToday' => $totalToday - $successToday
        ];
    }
}
