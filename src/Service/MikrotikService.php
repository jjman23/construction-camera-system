<?php
// src/Service/MikrotikService.php
namespace App\Service;

use App\Entity\MikrotikDevice;
use App\Entity\MikrotikStat;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class MikrotikService
{
    public function __construct(
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
    ) {}

    /**
     * Poll one device, persist the result rows and update the device's last status.
     * Does not flush.
     *
     * @return MikrotikStat[]
     */
    public function poll(MikrotikDevice $device): array
    {
        $now = new \DateTime();
        $device->setLastPolledAt($now);
        $stats = [];

        try {
            $entries = $this->fetchRegistrationTable($device);

            if (!$entries) {
                $stat = new MikrotikStat($device, $now, 'no_link');
                $stat->setErrorMessage('Registration table is empty (not associated)');
                $stats[] = $stat;
                $device->setLastStatus('no_link');
                $device->setLastError('Not associated with an access point');
            } else {
                foreach ($entries as $entry) {
                    $stats[] = $this->buildStat($device, $now, $entry);
                }
                $device->setLastStatus('ok');
                $device->setLastError(null);
            }
        } catch (\Throwable $e) {
            $stat = new MikrotikStat($device, $now, 'error');
            $stat->setErrorMessage($e->getMessage());
            $stats[] = $stat;
            $device->setLastStatus('error');
            $device->setLastError($e->getMessage());
            $this->logger->warning('MikroTik poll failed', ['device' => $device->getName(), 'error' => $e->getMessage()]);
        }

        foreach ($stats as $stat) {
            $this->em->persist($stat);
        }

        return $stats;
    }

    /** @return array<int, array<string, string>> */
    public function fetchRegistrationTable(MikrotikDevice $device): array
    {
        $client = new RouterOsClient();
        try {
            $client->connect($device->getHost(), $device->getPort(), $device->getUsername(), $device->getPassword(), $device->isUseSsl());

            $queries = [];
            if ($device->getWirelessInterface()) {
                $queries[] = '?interface=' . $device->getWirelessInterface();
            }
            // "stats" adds signal-to-noise, tx/rx-ccq, etc. (equivalent of `print stats` in the CLI)
            return $client->command('/interface/wireless/registration-table/print', ['stats' => null], $queries);
        } finally {
            $client->close();
        }
    }

    /** Delete stats older than $days. Returns rows deleted. */
    public function prune(int $days): int
    {
        return $this->em->createQuery('DELETE FROM App\Entity\MikrotikStat s WHERE s.polledAt < :cutoff')
            ->setParameter('cutoff', new \DateTime("-{$days} days"))
            ->execute();
    }

    /**
     * Stats for charting, oldest first.
     *
     * @return MikrotikStat[]
     */
    public function history(MikrotikDevice $device, \DateTime $since): array
    {
        return $this->em->createQueryBuilder()
            ->select('s')
            ->from(MikrotikStat::class, 's')
            ->where('s.device = :d')
            ->andWhere('s.polledAt >= :since')
            ->setParameter('d', $device)
            ->setParameter('since', $since)
            ->orderBy('s.polledAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function latest(MikrotikDevice $device): ?MikrotikStat
    {
        return $this->em->getRepository(MikrotikStat::class)
            ->findOneBy(['device' => $device], ['polledAt' => 'DESC', 'id' => 'DESC']);
    }

    private function buildStat(MikrotikDevice $device, \DateTime $now, array $e): MikrotikStat
    {
        $stat = new MikrotikStat($device, $now, 'ok');
        $stat->setInterface($e['interface'] ?? null)
            ->setRemoteMac($e['mac-address'] ?? null)
            ->setRemoteName(isset($e['radio-name']) && $e['radio-name'] !== '' ? $e['radio-name'] : null)
            ->setSignalStrength($this->leadingInt($e['signal-strength'] ?? null))
            ->setTxSignalStrength($this->leadingInt($e['tx-signal-strength'] ?? null))
            ->setSignalToNoise($this->leadingInt($e['signal-to-noise'] ?? null))
            ->setTxCcq($this->leadingInt($e['tx-ccq'] ?? null))
            ->setRxCcq($this->leadingInt($e['rx-ccq'] ?? null))
            ->setTxRate($e['tx-rate'] ?? null)
            ->setRxRate($e['rx-rate'] ?? null)
            ->setTxRateMbps($this->rateMbps($e['tx-rate'] ?? null))
            ->setRxRateMbps($this->rateMbps($e['rx-rate'] ?? null))
            ->setUptimeSeconds($this->durationSeconds($e['uptime'] ?? null))
            ->setRaw($e);

        return $stat;
    }

    /** "-62dBm@6Mbps" -> -62, "95" -> 95 */
    private function leadingInt(?string $v): ?int
    {
        if ($v === null || !preg_match('/^\s*(-?\d+)/', $v, $m)) {
            return null;
        }
        return (int) $m[1];
    }

    /** "130Mbps-20MHz/2S/SGI" -> 130.0, "54Mbps" -> 54.0, "5.5Mbps" -> 5.5 */
    private function rateMbps(?string $v): ?float
    {
        if ($v === null || !preg_match('/([\d.]+)\s*([GMk])bps/i', $v, $m)) {
            return null;
        }
        $n = (float) $m[1];
        return match (strtolower($m[2])) {
            'g' => $n * 1000,
            'k' => $n / 1000,
            default => $n,
        };
    }

    /** RouterOS duration "1w2d03:04:05", "3h4m5s", "5s" -> seconds */
    private function durationSeconds(?string $v): ?int
    {
        if ($v === null || $v === '') {
            return null;
        }
        $total = 0;
        if (preg_match('/(\d+):(\d+):(\d+)$/', $v, $m)) {
            $total += $m[1] * 3600 + $m[2] * 60 + $m[3];
            $v = substr($v, 0, -strlen($m[0]));
        }
        $units = ['w' => 604800, 'd' => 86400, 'h' => 3600, 'm' => 60, 's' => 1];
        preg_match_all('/(\d+)(ms|w|d|h|m|s)/', $v, $parts, PREG_SET_ORDER);
        foreach ($parts as $p) {
            if ($p[2] !== 'ms') {
                $total += (int) $p[1] * $units[$p[2]];
            }
        }
        return $total;
    }
}
