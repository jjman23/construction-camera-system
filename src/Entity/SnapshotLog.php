<?php
// src/Entity/SnapshotLog.php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'snapshot_logs')]
class SnapshotLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Camera::class, inversedBy: 'snapshotLogs')]
    #[ORM\JoinColumn(nullable: false)]
    private Camera $camera;

    #[ORM\Column(type: 'datetime')]
    private \DateTime $attemptedAt;

    #[ORM\Column(type: 'string', length: 20)]
    private string $status;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $errorMessage = null;

    #[ORM\Column(type: 'string', length: 500, nullable: true)]
    private ?string $filePath = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $fileSize = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $executionTimeMs = null;

    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';
    public const STATUS_SKIPPED = 'skipped';

    public function __construct()
    {
        $this->attemptedAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCamera(): Camera
    {
        return $this->camera;
    }

    public function setCamera(Camera $camera): self
    {
        $this->camera = $camera;
        return $this;
    }

    public function getAttemptedAt(): \DateTime
    {
        return $this->attemptedAt;
    }

    public function setAttemptedAt(\DateTime $attemptedAt): self
    {
        $this->attemptedAt = $attemptedAt;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function setErrorMessage(?string $errorMessage): self
    {
        $this->errorMessage = $errorMessage;
        return $this;
    }

    public function getFilePath(): ?string
    {
        return $this->filePath;
    }

    public function setFilePath(?string $filePath): self
    {
        $this->filePath = $filePath;
        return $this;
    }

    public function getFileSize(): ?int
    {
        return $this->fileSize;
    }

    public function setFileSize(?int $fileSize): self
    {
        $this->fileSize = $fileSize;
        return $this;
    }

    public function getExecutionTimeMs(): ?int
    {
        return $this->executionTimeMs;
    }

    public function setExecutionTimeMs(?int $executionTimeMs): self
    {
        $this->executionTimeMs = $executionTimeMs;
        return $this;
    }

    public function isSuccess(): bool
    {
        return $this->status === self::STATUS_SUCCESS;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function isSkipped(): bool
    {
        return $this->status === self::STATUS_SKIPPED;
    }

    public function getFormattedFileSize(): string
    {
        if ($this->fileSize === null) {
            return 'N/A';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $size = $this->fileSize;
        $unitIndex = 0;

        while ($size >= 1024 && $unitIndex < count($units) - 1) {
            $size /= 1024;
            $unitIndex++;
        }

        return round($size, 2) . ' ' . $units[$unitIndex];
    }

    public function getFormattedExecutionTime(): string
    {
        if ($this->executionTimeMs === null) {
            return 'N/A';
        }

        if ($this->executionTimeMs < 1000) {
            return $this->executionTimeMs . 'ms';
        }

        return round($this->executionTimeMs / 1000, 2) . 's';
    }
}
