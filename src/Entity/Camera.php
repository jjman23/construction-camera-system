<?php
// src/Entity/Camera.php
namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'cameras')]
class Camera
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Building::class, inversedBy: 'cameras')]
    #[ORM\JoinColumn(nullable: false)]
    private Building $building;

    #[ORM\Column(type: 'string', length: 100)]
    private string $name;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $rtspUrl;

    #[ORM\Column(type: 'string', length: 500, nullable: true)]
    private ?string $liveStreamUrl = null;

    // Snapshot settings
    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $snapshotEnabled = true;

    #[ORM\Column(type: 'integer', options: ['default' => 300])]
    private int $snapshotInterval = 300;

    #[ORM\Column(type: 'time')]
    private \DateTime $startTime;

    #[ORM\Column(type: 'time')]
    private \DateTime $stopTime;

    // Display settings
    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $galleryEnabled = true;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $liveEnabled = true;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $displayOrder = 0;

    // Status
    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $active = true;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTime $lastSnapshotAt = null;

    #[ORM\Column(type: 'string', length: 50, options: ['default' => 'pending'])]
    private string $lastSnapshotStatus = 'pending';

    // Metadata
    #[ORM\Column(type: 'datetime')]
    private \DateTime $createdAt;

    #[ORM\Column(type: 'datetime')]
    private \DateTime $updatedAt;

    #[ORM\OneToMany(mappedBy: 'camera', targetEntity: SnapshotLog::class, cascade: ['persist', 'remove'])]
    private Collection $snapshotLogs;

    public function __construct()
    {
        $this->snapshotLogs = new ArrayCollection();
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
        $this->startTime = new \DateTime('05:00:00');
        $this->stopTime = new \DateTime('22:00:00');
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBuilding(): Building
    {
        return $this->building;
    }

    public function setBuilding(Building $building): self
    {
        $this->building = $building;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function getRtspUrl(): string
    {
        return $this->rtspUrl;
    }

    public function setRtspUrl(string $rtspUrl): self
    {
        $this->rtspUrl = $rtspUrl;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function getLiveStreamUrl(): ?string
    {
        return $this->liveStreamUrl;
    }

    public function setLiveStreamUrl(?string $liveStreamUrl): self
    {
        $this->liveStreamUrl = $liveStreamUrl;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function isSnapshotEnabled(): bool
    {
        return $this->snapshotEnabled;
    }

    public function setSnapshotEnabled(bool $snapshotEnabled): self
    {
        $this->snapshotEnabled = $snapshotEnabled;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function getSnapshotInterval(): int
    {
        return $this->snapshotInterval;
    }

    public function setSnapshotInterval(int $snapshotInterval): self
    {
        $this->snapshotInterval = $snapshotInterval;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function getStartTime(): \DateTime
    {
        return $this->startTime;
    }

    public function setStartTime(\DateTime $startTime): self
    {
        $this->startTime = $startTime;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function getStopTime(): \DateTime
    {
        return $this->stopTime;
    }

    public function setStopTime(\DateTime $stopTime): self
    {
        $this->stopTime = $stopTime;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function isGalleryEnabled(): bool
    {
        return $this->galleryEnabled;
    }

    public function setGalleryEnabled(bool $galleryEnabled): self
    {
        $this->galleryEnabled = $galleryEnabled;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function isLiveEnabled(): bool
    {
        return $this->liveEnabled;
    }

    public function setLiveEnabled(bool $liveEnabled): self
    {
        $this->liveEnabled = $liveEnabled;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function getDisplayOrder(): int
    {
        return $this->displayOrder;
    }

    public function setDisplayOrder(int $displayOrder): self
    {
        $this->displayOrder = $displayOrder;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): self
    {
        $this->active = $active;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function getLastSnapshotAt(): ?\DateTime
    {
        return $this->lastSnapshotAt;
    }

    public function setLastSnapshotAt(?\DateTime $lastSnapshotAt): self
    {
        $this->lastSnapshotAt = $lastSnapshotAt;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function getLastSnapshotStatus(): string
    {
        return $this->lastSnapshotStatus;
    }

    public function setLastSnapshotStatus(string $lastSnapshotStatus): self
    {
        $this->lastSnapshotStatus = $lastSnapshotStatus;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTime
    {
        return $this->updatedAt;
    }

    public function getSnapshotLogs(): Collection
    {
        return $this->snapshotLogs;
    }

    public function addSnapshotLog(SnapshotLog $snapshotLog): self
    {
        if (!$this->snapshotLogs->contains($snapshotLog)) {
            $this->snapshotLogs[] = $snapshotLog;
            $snapshotLog->setCamera($this);
        }
        return $this;
    }

    public function removeSnapshotLog(SnapshotLog $snapshotLog): self
    {
        if ($this->snapshotLogs->removeElement($snapshotLog)) {
            if ($snapshotLog->getCamera() === $this) {
                $snapshotLog->setCamera(null);
            }
        }
        return $this;
    }

    /**
     * Check if current time is within construction hours
     */
    public function isWithinConstructionHours(\DateTime $time = null): bool
    {
        if ($time === null) {
            $time = new \DateTime();
        }
        
        $currentTime = $time->format('H:i:s');
        $startTime = $this->startTime->format('H:i:s');
        $stopTime = $this->stopTime->format('H:i:s');
        
        return $currentTime >= $startTime && $currentTime <= $stopTime;
    }

    /**
     * Get image directory path for this camera
     */
    public function getImageDirectory(): string
    {
        return "cam{$this->id}";
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
