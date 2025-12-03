<?php

namespace App\Entity;

use App\Repository\SettingRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SettingRepository::class)]
#[ORM\Table(name: '`setting`')]
#[ORM\UniqueConstraint(name: 'UNIQ_setting_key', columns: ['setting_key'])]
final class Setting
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    private string $settingKey;

    #[ORM\Column(type: 'text')]
    private string $settingValue;

    #[ORM\Column(length: 50)]
    private string $settingType = 'string'; // string, boolean, integer, float

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct(string $settingKey, string $settingValue, string $settingType = 'string')
    {
        $this->settingKey = $settingKey;
        $this->settingValue = $settingValue;
        $this->settingType = $settingType;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSettingKey(): string
    {
        return $this->settingKey;
    }

    public function setSettingKey(string $settingKey): static
    {
        $this->settingKey = $settingKey;
        return $this;
    }

    public function getSettingValue(): string
    {
        return $this->settingValue;
    }

    public function setSettingValue(string $settingValue): static
    {
        $this->settingValue = $settingValue;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getSettingType(): string
    {
        return $this->settingType;
    }

    public function setSettingType(string $settingType): static
    {
        $this->settingType = $settingType;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * Converts raw value to proper type based on settingType
     */
    public function getParsedValue(): bool|int|float|string
    {
        return match ($this->settingType) {
            'boolean' => filter_var($this->settingValue, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int)$this->settingValue,
            'float' => (float)$this->settingValue,
            default => $this->settingValue,
        };
    }
}

