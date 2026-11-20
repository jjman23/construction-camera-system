<?php
// src/Entity/MikrotikStat.php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * One row per poll per registration-table entry (normally one: the AP the station is linked to).
 * A failed poll / no link is stored as a row with status error / no_link and null metrics.
 */
#[ORM\Entity]
#[ORM\Table(name: 'mikrotik_stats')]
#[ORM\Index(name: 'idx_mikrotik_stats_device_time', columns: ['device_id', 'polled_at'])]
class MikrotikStat
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: MikrotikDevice::class, inversedBy: 'stats')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private MikrotikDevice $device;

    #[ORM\Column(type: 'datetime')]
    private \DateTime $polledAt;

    /** ok | no_link | error */
    #[ORM\Column(type: 'string', length: 20)]
    private string $status = 'ok';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $errorMessage = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $interface = null;

    #[ORM\Column(type: 'string', length: 17, nullable: true)]
    private ?string $remoteMac = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $remoteName = null;

    /** dBm */
    #[ORM\Column(type: 'smallint', nullable: true)]
    private ?int $signalStrength = null;

    /** dBm, as heard by the remote end */
    #[ORM\Column(type: 'smallint', nullable: true)]
    private ?int $txSignalStrength = null;

    /** dB */
    #[ORM\Column(type: 'smallint', nullable: true)]
    private ?int $signalToNoise = null;

    /** % */
    #[ORM\Column(type: 'smallint', nullable: true)]
    private ?int $txCcq = null;

    /** % */
    #[ORM\Column(type: 'smallint', nullable: true)]
    private ?int $rxCcq = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $txRate = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $rxRate = null;

    /** Mbps, parsed from tx/rx rate */
    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $txRateMbps = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $rxRateMbps = null;

    /** Link uptime in seconds */
    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $uptimeSeconds = null;

    /** Raw registration-table attributes, for anything not broken out above */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $raw = null;

    public function __construct(MikrotikDevice $device, \DateTime $polledAt, string $status = 'ok')
    {
        $this->device = $device;
        $this->polledAt = $polledAt;
        $this->status = $status;
    }

    public function getId(): ?int { return $this->id; }
    public function getDevice(): MikrotikDevice { return $this->device; }
    public function getPolledAt(): \DateTime { return $this->polledAt; }
    public function getStatus(): string { return $this->status; }

    public function getErrorMessage(): ?string { return $this->errorMessage; }
    public function setErrorMessage(?string $m): self { $this->errorMessage = $m; return $this; }

    public function getInterface(): ?string { return $this->interface; }
    public function setInterface(?string $v): self { $this->interface = $v; return $this; }

    public function getRemoteMac(): ?string { return $this->remoteMac; }
    public function setRemoteMac(?string $v): self { $this->remoteMac = $v; return $this; }

    public function getRemoteName(): ?string { return $this->remoteName; }
    public function setRemoteName(?string $v): self { $this->remoteName = $v; return $this; }

    public function getSignalStrength(): ?int { return $this->signalStrength; }
    public function setSignalStrength(?int $v): self { $this->signalStrength = $v; return $this; }

    public function getTxSignalStrength(): ?int { return $this->txSignalStrength; }
    public function setTxSignalStrength(?int $v): self { $this->txSignalStrength = $v; return $this; }

    public function getSignalToNoise(): ?int { return $this->signalToNoise; }
    public function setSignalToNoise(?int $v): self { $this->signalToNoise = $v; return $this; }

    public function getTxCcq(): ?int { return $this->txCcq; }
    public function setTxCcq(?int $v): self { $this->txCcq = $v; return $this; }

    public function getRxCcq(): ?int { return $this->rxCcq; }
    public function setRxCcq(?int $v): self { $this->rxCcq = $v; return $this; }

    public function getTxRate(): ?string { return $this->txRate; }
    public function setTxRate(?string $v): self { $this->txRate = $v; return $this; }

    public function getRxRate(): ?string { return $this->rxRate; }
    public function setRxRate(?string $v): self { $this->rxRate = $v; return $this; }

    public function getTxRateMbps(): ?float { return $this->txRateMbps; }
    public function setTxRateMbps(?float $v): self { $this->txRateMbps = $v; return $this; }

    public function getRxRateMbps(): ?float { return $this->rxRateMbps; }
    public function setRxRateMbps(?float $v): self { $this->rxRateMbps = $v; return $this; }

    public function getUptimeSeconds(): ?int { return $this->uptimeSeconds; }
    public function setUptimeSeconds(?int $v): self { $this->uptimeSeconds = $v; return $this; }

    public function getRaw(): ?array { return $this->raw; }
    public function setRaw(?array $v): self { $this->raw = $v; return $this; }
}
