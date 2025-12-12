<?php

namespace App\Entity;

use App\Repository\CustomerRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

/**
 * Stores renter profile information so bookings can be linked to known customers.
 */
#[ORM\Entity(repositoryClass: CustomerRepository::class)]
#[ORM\Table(name: 'customer')]
#[UniqueEntity(fields: ['email'], message: 'A customer already exists with this email.')]
class Customer
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $firstName = null;

    #[ORM\Column(length: 100)]
    private ?string $lastName = null;

    #[ORM\Column(length: 180, unique: true)]
    private ?string $email = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(length: 60, nullable: true)]
    private ?string $licenseNumber = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\OneToOne(inversedBy: 'customer', targetEntity: User::class, cascade: ['persist'])]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $user = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $updatedAt = null;

    // REMOVED: Old Rental relationship
    // #[ORM\OneToMany(mappedBy: 'customer', targetEntity: Rental::class, cascade: ['remove'])]
    // private Collection $rentals;

    // ADDED: New RentalRequest relationship
    #[ORM\OneToMany(mappedBy: 'customer', targetEntity: RentalRequest::class)]
    private Collection $rentalRequests;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
        // $this->rentals = new ArrayCollection(); // REMOVED
        $this->rentalRequests = new ArrayCollection(); // ADDED
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;
        return $this;
    }

    public function getLicenseNumber(): ?string
    {
        return $this->licenseNumber;
    }

    public function setLicenseNumber(?string $licenseNumber): static
    {
        $this->licenseNumber = $licenseNumber;
        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;
        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        if ($user === null && $this->user !== null && $this->user->getCustomer() === $this) {
            $this->user->setCustomer(null);
        }

        $this->user = $user;

        if ($user !== null && $user->getCustomer() !== $this) {
            $user->setCustomer($this);
        }

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

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    /**
     * Helper used in select boxes / templates.
     */
    public function getDisplayName(): string
    {
        return trim(sprintf('%s %s', $this->firstName ?? '', $this->lastName ?? '')) ?: ($this->email ?? 'Customer');
    }

    // REMOVED: Old Rental methods
    // public function getRentals(): Collection
    // public function addRental(Rental $rental): static
    // public function removeRental(Rental $rental): static

    // ADDED: New RentalRequest methods

    /**
     * @return Collection<int, RentalRequest>
     */
    public function getRentalRequests(): Collection
    {
        return $this->rentalRequests;
    }

    public function addRentalRequest(RentalRequest $rentalRequest): static
    {
        if (!$this->rentalRequests->contains($rentalRequest)) {
            $this->rentalRequests->add($rentalRequest);
            if ($rentalRequest->getCustomer() !== $this) {
                $rentalRequest->setCustomer($this);
            }
        }

        return $this;
    }

    public function removeRentalRequest(RentalRequest $rentalRequest): static
    {
        if ($this->rentalRequests->removeElement($rentalRequest) && $rentalRequest->getCustomer() === $this) {
            $rentalRequest->setCustomer(null);
        }

        return $this;
    }
}