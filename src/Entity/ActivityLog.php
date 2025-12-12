<?php

namespace App\Entity;

use App\Repository\ActivityLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ActivityLogRepository::class)]
class ActivityLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'activityLogs')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column(length: 255)]
    private ?string $action = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $targetData = null;  // Metadata about what was affected

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $username = null;  // Store username/email separately

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $userRoles = null;  // Comma-separated roles

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $ipAddress = null;  // IP address

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $userAgent = null;  // Browser/device info

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $userType = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    // Getters and setters...
    public function getId(): ?int { return $this->id; }
    
    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }
    
    public function getAction(): ?string { return $this->action; }
    public function setAction(string $action): static
    {
        $this->action = $action;
        return $this;
    }
    
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }
    
    public function getTargetData(): ?string { return $this->targetData; }
    public function setTargetData(?string $targetData): static
    {
        $this->targetData = $targetData;
        return $this;
    }
    
    public function getUsername(): ?string { return $this->username; }
    public function setUsername(?string $username): static
    {
        $this->username = $username;
        return $this;
    }
    
    public function getUserRoles(): ?string { return $this->userRoles; }
    public function setUserRoles(?string $userRoles): static
    {
        $this->userRoles = $userRoles;
        return $this;
    }
    
    public function getIpAddress(): ?string { return $this->ipAddress; }
    public function setIpAddress(?string $ipAddress): static
    {
        $this->ipAddress = $ipAddress;
        return $this;
    }
    
    public function getUserAgent(): ?string { return $this->userAgent; }
    public function setUserAgent(?string $userAgent): static
    {
        $this->userAgent = $userAgent;
        return $this;
    }
    
    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUserType(): ?string
    {
        return $this->userType;
    }

    public function setUserType(?string $userType): static
    {
        $this->userType = $userType;

        return $this;
    }
}