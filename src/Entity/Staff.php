<?php

namespace App\Entity;

use App\Repository\StaffRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: StaffRepository::class)]
class Staff
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // 🔥 CRITICAL: Connect to User entity (not implement UserInterface)
    #[ORM\OneToOne(targetEntity: User::class, inversedBy: 'staff')]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', unique: true, nullable: false)]
    private ?User $user = null;

    #[ORM\Column(length: 100)]
    private ?string $firstName = null;

    #[ORM\Column(length: 100)]
    private ?string $lastName = null;

    // 🔥 CRITICAL: Connect to Admin (not self)
    #[ORM\ManyToOne(targetEntity: Admin::class, inversedBy: 'staffCreated')]
    #[ORM\JoinColumn(name: 'created_by_admin_id', referencedColumnName: 'id', nullable: true)]
    private ?Admin $createdByAdmin = null;
   
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $phoneNumber = null;
    #[ORM\Column(type: 'string', length: 20, options: ['default' => 'active'])]
    private string $status = 'active';
    
    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $archivedAt = null;
    
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $archivedBy = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $hireDate = null;

    #[ORM\Column(nullable: true)]
    private ?string $department = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    

    public function getPhoneNumber(): ?string
{
    return $this->phoneNumber;
}

public function setPhoneNumber(?string $phoneNumber): static
{
    $this->phoneNumber = $phoneNumber;
    return $this;
}

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->isActive = true;
        $this->status = 'active';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    // User relation
    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): static
    {
        $this->firstName = $firstName;
        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): static
    {
        $this->lastName = $lastName;
        return $this;
    }

    // Admin creator relation
    public function getCreatedByAdmin(): ?Admin
    {
        return $this->createdByAdmin;
    }

    public function setCreatedByAdmin(?Admin $createdByAdmin): static
    {
        $this->createdByAdmin = $createdByAdmin;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getHireDate(): ?\DateTimeImmutable
    {
        return $this->hireDate;
    }

    public function setHireDate(?\DateTimeImmutable $hireDate): static
    {
        $this->hireDate = $hireDate;
        return $this;
    }

    public function getDepartment(): ?string
    {
        return $this->department;
    }

    public function setDepartment(?string $department): static
    {
        $this->department = $department;
        return $this;
    }

    public function isAccountActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
        
        // Auto-update status based on isActive
        if (!$isActive && $this->status === 'active') {
            $this->status = 'disabled';
        } elseif ($isActive && $this->status === 'disabled') {
            $this->status = 'active';
        }
        
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }
    
    public function setStatus(string $status): static
    {
        $this->status = $status;
        
        // Auto-update isActive based on status
        if ($status === 'active') {
            $this->isActive = true;
        } elseif ($status === 'disabled' || $status === 'archived') {
            $this->isActive = false;
        }
        
        return $this;
    }
    
    public function getArchivedAt(): ?\DateTimeInterface
    {
        return $this->archivedAt;
    }
    
    public function setArchivedAt(?\DateTimeInterface $archivedAt): static
    {
        $this->archivedAt = $archivedAt;
        return $this;
    }
    
    public function getArchivedBy(): ?string
    {
        return $this->archivedBy;
    }
    
    public function setArchivedBy(?string $archivedBy): static
    {
        $this->archivedBy = $archivedBy;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    // Helper methods
    public function getFullName(): string
    {
        return $this->firstName . ' ' . $this->lastName;
    }

    // Status helper methods
    public function isActiveStatus(): bool
    {
        return $this->status === 'active';
    }
    
    public function isDisabledStatus(): bool
    {
        return $this->status === 'disabled';
    }
    
    public function isArchivedStatus(): bool
    {
        return $this->status === 'archived';
    }

    // Email getter (through User)
    public function getEmail(): ?string
    {
        return $this->user?->getEmail();
    }

    // ===== ADDED METHODS FOR TEMPLATE COMPATIBILITY =====
    
    /**
     * Get roles from associated User entity
     * For template compatibility: {{ staff.getRoles()|join(', ') }}
     */
    public function getRoles(): array
    {
        if ($this->user) {
            return $this->user->getRoles();
        }
        
        return [];
    }

    /**
     * Alias for isAccountActive() for template compatibility
     * For template: {{ staff.isActive ? 'Active' : 'Inactive' }}
     */
    public function isActive(): bool
    {
        return $this->isActive; // Returns the isActive property
    }

    /**
     * Getter for isActive property
     * For template: {{ staff.isActive }}
     */
    public function getIsActive(): bool
    {
        return $this->isActive;
    }

    public function __toString(): string
    {
        return $this->getFullName() . ' (' . $this->status . ')';
    }
}