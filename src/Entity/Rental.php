<?php

namespace App\Entity;

use App\Repository\RentalRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Records a booking for a specific car between two dates.
 */
#[ORM\Entity(repositoryClass: RentalRepository::class)]
class Rental
{
    /**
     * Surrogate primary key for the rental record.
     */
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * The car being reserved for the rental period.
     */
    #[ORM\ManyToOne(targetEntity: Car::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Car $car = null;

    /**
     * The customer profile responsible for the rental.
     */
    #[ORM\ManyToOne(targetEntity: Customer::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Customer $customer = null;

    /**
     * Start timestamp for the rental.
     */
    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $startDate = null;

    /**
     * Expected return timestamp for the rental.
     */
    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $endDate = null;

    /**
     * Unique identifier accessor.
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Retrieve the car that was booked.
     */
    public function getCar(): ?Car
    {
        return $this->car;
    }

    /**
     * Associate a car with this rental.
     */
    public function setCar(?Car $car): static
    {
        if ($this->car === $car) {
            return $this;
        }

        if ($this->car !== null) {
            $this->car->removeRental($this);
        }

        $this->car = $car;

        if ($car !== null && !$car->getRentals()->contains($this)) {
            $car->addRental($this);
        }

        return $this;
    }

    /**
     * Return the linked customer profile.
     */
    public function getCustomer(): ?Customer
    {
        return $this->customer;
    }

    /**
     * Associate a customer profile with this rental.
     */
    public function setCustomer(?Customer $customer): static
    {
        if ($this->customer === $customer) {
            return $this;
        }

        if ($this->customer !== null) {
            $this->customer->removeRental($this);
        }

        $this->customer = $customer;

        if ($customer !== null && !$customer->getRentals()->contains($this)) {
            $customer->addRental($this);
        }

        return $this;
    }

    /**
     * Retrieve the rental start time.
     */
    public function getStartDate(): ?\DateTimeInterface
    {
        return $this->startDate;
    }

    /**
     * Store the start date/time.
     */
    public function setStartDate(\DateTimeInterface $startDate): static
    {
        $this->startDate = $startDate;

        return $this;
    }

    /**
     * Retrieve the expected end time.
     */
    public function getEndDate(): ?\DateTimeInterface
    {
        return $this->endDate;
    }

    /**
     * Store the expected return date/time.
     */
    public function setEndDate(\DateTimeInterface $endDate): static
    {
        $this->endDate = $endDate;

        return $this;
    }
}
