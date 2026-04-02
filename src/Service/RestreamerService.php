<?php
// src/Service/RestreamerService.php
namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class RestreamerService
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $restreamerUrl,
        private string $publicBaseUrl,
        private string $dbPath,
        private LoggerInterface $logger,
    ) {}

    public function isAvailable(): bool
    {
        return file_exists($this->dbPath) && is_readable($this->dbPath);
    }

    /**
     * Returns all streams from Restreamer db.json.
     * Each entry: ['id' => uuid, 'name' => string, 'url' => string, 'running' => bool]
     */
    public function getRunningStreams(): array
    {
        $streams = $this->getAllStreams();
        return array_values(array_filter($streams, fn($s) => $s['running']));
    }

    /**
     * Look up a single stream by UUID.
     * Returns null if not found.
     */
    public function getStream(string $id): ?array
    {
        foreach ($this->getAllStreams() as $stream) {
            if ($stream['id'] === $id) {
                return $stream;
            }
        }
        return null;
    }

    private function getAllStreams(): array
    {
        if (!$this->isAvailable()) {
            return [];
        }

        try {
            $db = json_decode(file_get_contents($this->dbPath), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Exception $e) {
            $this->logger->error('Failed to read Restreamer db.json: ' . $e->getMessage());
            return [];
        }

        $processes  = $db['process'] ?? [];
        $metadata   = $db['metadata']['process'] ?? [];
        $streams    = [];

        foreach ($processes as $pid => $proc) {
            // Skip snapshot processes
            if (str_ends_with($pid, '_snapshot')) {
                continue;
            }

            // Process IDs are: restreamer-ui:ingest:{uuid}
            $parts = explode(':', $pid);
            $uuid = end($parts);

            $name = $metadata[$pid]['restreamer-ui']['meta']['name'] ?? $uuid;
            $url  = rtrim($this->publicBaseUrl, '/') . '/' . $uuid . '.m3u8';

            $streams[] = [
                'id'      => $uuid,
                'name'    => $name,
                'url'     => $url,
                'running' => $this->checkHlsAvailable($uuid),
            ];
        }

        return $streams;
    }

    private function checkHlsAvailable(string $uuid): bool
    {
        try {
            $response = $this->httpClient->request(
                'HEAD',
                $this->restreamerUrl . '/memfs/' . $uuid . '.m3u8',
                ['timeout' => 3]
            );
            return $response->getStatusCode() === 200;
        } catch (\Exception) {
            return false;
        }
    }
}
