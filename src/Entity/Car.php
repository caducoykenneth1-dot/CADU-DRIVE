<?php

namespace App\Entity;

use App\Repository\CarRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Represents a vehicle offered in the rental fleet.
 *
 * Stores basic descriptive information together with operational status
 * and an optional image filename for display in the UI.
 */
#[ORM\Entity(repositoryClass: CarRepository::class)]
class Car
{
    /**
     * Surrogate primary key.
     */
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * Manufacturer or brand (e.g. Toyota, BMW).
     */
    #[ORM\Column(length: 128)]
    private ?string $make = null;

    /**
     * Specific model name supplied by the manufacturer.
     */
    #[ORM\Column(length: 128)]
    private ?string $model = null;

    /**
     * Optional marketing copy or vehicle highlights.
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    /**
     * Production year of the vehicle.
     */
    #[ORM\Column]
    private ?int $year = null;

    /**
     * Daily rental price expressed in the smallest currency unit.
     */
    #[ORM\Column]
    private ?int $price = null;

    /**
     * Current availability marker (available, rented, maintenance, etc.).
     */
    #[ORM\Column(length: 255, options: ["default" => "available"])]
    private ?string $status = 'available';

    /**
     * Filename of the uploaded image stored under `public/images`.
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image = null; // �o. KEEP JUST THIS ONE

    /**
     * Unique identifier accessor.
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Return the car manufacturer.
     */
    public function getMake(): ?string
    {
        return $this->make;
    }

    /**
     * Define the car manufacturer label.
     */
    public function setMake(string $make): static
    {
        $this->make = $make;
        return $this;
    }

    /**
     * Return the car model name.
     */
    public function getModel(): ?string
    {
        return $this->model;
    }

    /**
     * Persist the model name.
     */
    public function setModel(string $model): static
    {
        $this->model = $model;
        return $this;
    }

    /**
     * Fetch the optional description text.
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * Update the descriptive text shown in listings.
     */
    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    /**
     * Retrieve the production year.
     */
    public function getYear(): ?int
    {
        return $this->year;
    }

    /**
     * Store the production year.
     */
    public function setYear(int $year): static
    {
        $this->year = $year;
        return $this;
    }

    /**
     * Retrieve the daily rental price.
     */
    public function getPrice(): ?int
    {
        return $this->price;
    }

    /**
     * Persist the daily rental price.
     */
    public function setPrice(int $price): static
    {
        $this->price = $price;
        return $this;
    }

    /**
     * Return the current availability status.
     */
    public function getStatus(): ?string
    {
        return $this->status;
    }

    /**
     * Change the availability status string.
     */
    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    /**
     * Retrieve the stored image filename.
     */
    public function getImage(): ?string
    {
        return $this->image;
    }

    /**
     * Store the filename for the uploaded image.
     */
    public function setImage(?string $image): static
    {
        $this->image = $image;
        return $this;
    }
}
