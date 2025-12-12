<?php

namespace App\Entity;

use App\Repository\RentalRequestRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RentalRequestRepository::class)]
class RentalRequest
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?\DateTime $createdAt = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTime $startDate = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTime $endDate = null;

    #[ORM\Column(length: 20)]
    private ?string $status = 'pending';

    // FIXED: ManyToOne instead of OneToMany
    // One RentalRequest belongs to ONE Customer
    #[ORM\ManyToOne(targetEntity: Customer::class, inversedBy: 'rentalRequests')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Customer $customer = null;

    // FIXED: Added targetEntity and nullable false
    #[ORM\ManyToOne(targetEntity: Car::class, inversedBy: 'rentalRequests')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Car $car = null;
    

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $approvedAt = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $rejectedAt = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $totalPrice = null;

    // REMOVED: No __construct needed for customer since it's not a Collection anymore

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTime $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getStartDate(): ?\DateTime
    {
        return $this->startDate;
    }

    public function setStartDate(\DateTime $startDate): static
    {
        $this->startDate = $startDate;

        return $this;
    }

    public function getEndDate(): ?\DateTime
    {
        return $this->endDate;
    }

    public function setEndDate(\DateTime $endDate): static
    {
        $this->endDate = $endDate;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    // FIXED: Returns ONE User, not Collection
    public function getCustomer(): ?Customer
{
    return $this->customer;
}

public function setCustomer(?Customer $customer): static
{
    $this->customer = $customer;
    return $this;
}

    public function getCar(): ?Car
    {
        return $this->car;
    }

    public function setCar(?Car $car): static
    {
        $this->car = $car;

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
 // src/Entity/RentalRequest.php


// Add getter and setter
public function getUpdatedAt(): ?\DateTimeInterface
{
    return $this->updatedAt;
}

public function setUpdatedAt(?\DateTimeInterface $updatedAt): static
{
    $this->updatedAt = $updatedAt;
    return $this;
}
public function approve(): void
{
    $this->status = 'approved';
    $this->approvedAt = new \DateTime();
    
    // Update car status
    if ($this->car) {
        $this->car->setStatus('rented');
        $this->car->setUpdatedAt(new \DateTime());
    }
    
    $this->updatedAt = new \DateTime();
}

public function getApprovedAt(): ?\DateTimeInterface
{
    return $this->approvedAt;
}

public function setApprovedAt(?\DateTimeInterface $approvedAt): static
{
    $this->approvedAt = $approvedAt;
    return $this;
}

public function getRejectedAt(): ?\DateTimeInterface
{
    return $this->rejectedAt;
}

public function setRejectedAt(?\DateTimeInterface $rejectedAt): static
{
    $this->rejectedAt = $rejectedAt;
    return $this;
}
public function getTotalPrice(): ?string
    {
        return $this->totalPrice;
    }

    public function setTotalPrice(?string $totalPrice): static
    {
        $this->totalPrice = $totalPrice;

        return $this;
    }
}