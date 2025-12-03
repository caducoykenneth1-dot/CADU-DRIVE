<?php

namespace App\Repository;

use App\Entity\Setting;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Setting>
 */
final class SettingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Setting::class);
    }

    public function findByKey(string $key): ?Setting
    {
        return $this->findOneBy(['settingKey' => $key]);
    }

    /**
     * Get all settings as key-value pairs
     * @return array<string, mixed>
     */
    public function getAllAsArray(): array
    {
        $settings = $this->findAll();
        $result = [];

        foreach ($settings as $setting) {
            $result[$setting->getSettingKey()] = $setting->getParsedValue();
        }

        return $result;
    }

    /**
     * Create or update a setting
     */
    public function upsert(string $key, mixed $value, string $type = 'string'): Setting
    {
        $setting = $this->findByKey($key);

        if (!$setting) {
            $setting = new Setting($key, (string)$value, $type);
            $this->getEntityManager()->persist($setting);
        } else {
            $setting->setSettingValue((string)$value);
            $setting->setSettingType($type);
        }

        $this->getEntityManager()->flush();
        return $setting;
    }
}
