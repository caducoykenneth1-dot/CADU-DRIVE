<?php

namespace App\Entity;

use App\Repository\CarRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CarRepository::class)]
class Car
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 128)]
    private ?string $make = null;

    #[ORM\Column(length: 128)]
    private ?string $model = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column]
    private ?int $year = null;

    #[ORM\Column]
    private ?int $price = null;

    #[ORM\ManyToOne(targetEntity: CarStatus::class, inversedBy: 'cars')]
    #[ORM\JoinColumn(nullable: false)]
    private ?CarStatus $status = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image = null;

    #[ORM\OneToMany(mappedBy: 'car', targetEntity: RentalRequest::class)]
    private Collection $rentalRequests;
    
    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    public function __construct()
    {
        $this->rentalRequests = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMake(): ?string
    {
        return $this->make;
    }

    public function setMake(string $make): static
    {
        $this->make = $make;
        return $this;
    }

    public function getModel(): ?string
    {
        return $this->model;
    }

    public function setModel(string $model): static
    {
        $this->model = $model;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getYear(): ?int
    {
        return $this->year;
    }

    public function setYear(int $year): static
    {
        $this->year = $year;
        return $this;
    }

    public function getPrice(): ?int
    {
        return $this->price;
    }

    public function setPrice(int $price): static
    {
        $this->price = $price;
        return $this;
    }

    public function getStatus(): ?CarStatus
    {
        return $this->status;
    }

    public function getDailyRate(): ?int
    {
        // Return the price as daily rate
        return $this->price;
    }

    public function setStatus(?CarStatus $status): static
    {
        if ($this->status === $status) {
            return $this;
        }

        if ($this->status !== null) {
            $this->status->removeCar($this);
        }

        $this->status = $status;

        if ($status !== null) {
            $status->addCar($this);
        }

        return $this;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): static
    {
        $this->image = $image;
        return $this;
    }

    /**
     * @return Collection<int, RentalRequest>
     */
    public function getRentalRequests(): Collection
    {
        return $this->rentalRequests;
    }
    
    /**
     * Alias for template compatibility - returns the same as getRentalRequests()
     * @return Collection<int, RentalRequest>
     */
    public function getRentals(): Collection
    {
        return $this->rentalRequests;
    }

    public function addRentalRequest(RentalRequest $rentalRequest): static
    {
        if (!$this->rentalRequests->contains($rentalRequest)) {
            $this->rentalRequests->add($rentalRequest);
            $rentalRequest->setCar($this);
        }

        return $this;
    }

    public function removeRentalRequest(RentalRequest $rentalRequest): static
    {
        if ($this->rentalRequests->removeElement($rentalRequest)) {
            if ($rentalRequest->getCar() === $this) {
                $rentalRequest->setCar(null);
            }
        }

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeInterface $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    // ============================================================
    // HELPER METHODS FOR BUSINESS LOGIC
    // ============================================================

    /**
     * Check if the car is currently rented
     */
    public function isCurrentlyRented(): bool
    {
        $status = $this->getStatus();
        return $status && $status->getCode() === 'rented';
    }

    /**
     * Check if the car has any rental history (approved requests)
     */
    public function hasRentalHistory(): bool
    {
        foreach ($this->rentalRequests as $request) {
            if ($request->getStatus() === 'approved') {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if the car can be deleted
     * A car can only be deleted if:
     * - It has no rental history
     * - It's not currently rented
     * - It's not disabled
     */
    public function canBeDeleted(): bool
    {
        $statusCode = $this->getStatusCode();
        return !$this->hasRentalHistory() && 
               !$this->isCurrentlyRented() && 
               $statusCode !== 'disabled';
    }

    /**
     * Check if the car can be disabled
     * A car can be disabled if:
     * - It's not already disabled
     * - It's not currently rented
     */
    public function canBeDisabled(): bool
    {
        $statusCode = $this->getStatusCode();
        return $statusCode !== 'disabled' && 
               !$this->isCurrentlyRented();
    }

    /**
     * Check if the car can be enabled
     * A car can be enabled if it's currently disabled
     */
    public function canBeEnabled(): bool
    {
        return $this->getStatusCode() === 'disabled';
    }

    /**
     * Get the status code (e.g., 'available', 'rented', 'disabled')
     */
    public function getStatusCode(): string
    {
        $status = $this->getStatus();
        return $status ? $status->getCode() : 'unknown';
    }

    /**
     * Get the status label for display (e.g., 'Available', 'Rented', 'Disabled')
     */
    public function getStatusLabel(): string
    {
        $status = $this->getStatus();
        return $status ? $status->getLabel() : 'Unknown';
    }

    /**
     * Get count of rental requests
     */
    public function getRentalCount(): int
    {
        return $this->rentalRequests->count();
    }

    /**
     * Get count of approved rental requests only
     */
    public function getApprovedRentalCount(): int
    {
        $count = 0;
        foreach ($this->rentalRequests as $request) {
            if ($request->getStatus() === 'approved') {
                $count++;
            }
        }
        return $count;
    }
}