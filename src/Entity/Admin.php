<?php

namespace App\Entity;

use App\Repository\AdminRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AdminRepository::class)]
#[ORM\Table(name: '`admin`')]
class Admin
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // 🔥 CRITICAL: Add User relation
    #[ORM\OneToOne(targetEntity: User::class, inversedBy: 'admin')]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', unique: true, nullable: false)]
    private ?User $user = null;

    #[ORM\Column(length: 255)]
    private ?string $firstName = null;

    #[ORM\Column(length: 255)]
    private ?string $lastName = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastLoginAt = null;

    // Add Staff collection
    #[ORM\OneToMany(mappedBy: 'createdByAdmin', targetEntity: Staff::class)]
    private Collection $staffCreated;

    public function __construct()
    {
        $this->staffCreated = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable(); // Auto-set
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    // 🔥 CRITICAL: Add User getter/setter
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

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getLastLoginAt(): ?\DateTimeImmutable
    {
        return $this->lastLoginAt;
    }

    public function setLastLoginAt(?\DateTimeImmutable $lastLoginAt): static
    {
        $this->lastLoginAt = $lastLoginAt;
        return $this;
    }

    // 🔥 CRITICAL: Add Staff collection methods
    /**
     * @return Collection<int, Staff>
     */
    public function getStaffCreated(): Collection
    {
        return $this->staffCreated;
    }

    public function addStaffCreated(Staff $staffCreated): static
    {
        if (!$this->staffCreated->contains($staffCreated)) {
            $this->staffCreated->add($staffCreated);
            $staffCreated->setCreatedByAdmin($this);
        }

        return $this;
    }

    public function removeStaffCreated(Staff $staffCreated): static
    {
        if ($this->staffCreated->removeElement($staffCreated)) {
            // set the owning side to null (unless already changed)
            if ($staffCreated->getCreatedByAdmin() === $this) {
                $staffCreated->setCreatedByAdmin(null);
            }
        }

        return $this;
    }

    // 🔥 HELPER: Add toString() for displays
    public function __toString(): string
    {
        return $this->firstName . ' ' . $this->lastName;
    }
}