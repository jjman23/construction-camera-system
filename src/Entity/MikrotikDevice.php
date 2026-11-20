<?php
// src/Entity/MikrotikDevice.php
namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'mikrotik_devices')]
#[ORM\HasLifecycleCallbacks]
class MikrotikDevice
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 100)]
    private string $name = '';

    #[ORM\ManyToOne(targetEntity: Building::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Building $building = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $host = '';

    #[ORM\Column(type: 'integer', options: ['default' => 8728])]
    private int $port = 8728;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $useSsl = false;

    #[ORM\Column(type: 'string', length: 100)]
    private string $username = '';

    #[ORM\Column(type: 'string', length: 255)]
    private string $password = '';

    /** Optional: only report stations on this wireless interface (e.g. wlan1). */
    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $wirelessInterface = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $active = true;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTime $lastPolledAt = null;

    /** pending | ok | no_link | error */
    #[ORM\Column(type: 'string', length: 20, options: ['default' => 'pending'])]
    private string $lastStatus = 'pending';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $lastError = null;

    #[ORM\Column(type: 'datetime')]
    private \DateTime $createdAt;

    #[ORM\Column(type: 'datetime')]
    private \DateTime $updatedAt;

    #[ORM\OneToMany(mappedBy: 'device', targetEntity: MikrotikStat::class, cascade: ['remove'])]
    #[ORM\OrderBy(['polledAt' => 'DESC'])]
    private Collection $stats;

    public function __construct()
    {
        $this->stats = new ArrayCollection();
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    #[ORM\PreUpdate]
    public function touch(): void
    {
        $this->updatedAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }

    public function getName(): string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; return $this; }

    public function getBuilding(): ?Building { return $this->building; }
    public function setBuilding(?Building $building): self { $this->building = $building; return $this; }

    public function getHost(): string { return $this->host; }
    public function setHost(string $host): self { $this->host = $host; return $this; }

    public function getPort(): int { return $this->port; }
    public function setPort(int $port): self { $this->port = $port; return $this; }

    public function isUseSsl(): bool { return $this->useSsl; }
    public function setUseSsl(bool $useSsl): self { $this->useSsl = $useSsl; return $this; }

    public function getUsername(): string { return $this->username; }
    public function setUsername(string $username): self { $this->username = $username; return $this; }

    public function getPassword(): string { return $this->password; }
    public function setPassword(string $password): self { $this->password = $password; return $this; }

    public function getWirelessInterface(): ?string { return $this->wirelessInterface; }
    public function setWirelessInterface(?string $i): self { $this->wirelessInterface = $i ?: null; return $this; }

    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $notes): self { $this->notes = $notes ?: null; return $this; }

    public function isActive(): bool { return $this->active; }
    public function setActive(bool $active): self { $this->active = $active; return $this; }

    public function getLastPolledAt(): ?\DateTime { return $this->lastPolledAt; }
    public function setLastPolledAt(?\DateTime $d): self { $this->lastPolledAt = $d; return $this; }

    public function getLastStatus(): string { return $this->lastStatus; }
    public function setLastStatus(string $s): self { $this->lastStatus = $s; return $this; }

    public function getLastError(): ?string { return $this->lastError; }
    public function setLastError(?string $e): self { $this->lastError = $e; return $this; }

    public function getCreatedAt(): \DateTime { return $this->createdAt; }
    public function getUpdatedAt(): \DateTime { return $this->updatedAt; }

    /** @return Collection<int, MikrotikStat> */
    public function getStats(): Collection { return $this->stats; }
}
