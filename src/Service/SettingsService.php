<?php

namespace App\Service;

use App\Repository\SettingRepository;

/**
 * Service for managing MVP Settings with in-memory caching
 * Handles system status, financial, booking, and email settings
 */
final class SettingsService
{
    /** @var array<string, mixed> */
    private array $cache = [];

    private bool $cacheLoaded = false;

    /**
     * MVP Default Settings - Essential for booking workflow
     */
    private const MVP_DEFAULTS = [
        // System/Status Settings
        'SYSTEM_MAINTENANCE_MODE' => ['value' => false, 'type' => 'boolean'],
        'SYSTEM_DEFAULT_TIMEZONE' => ['value' => 'UTC', 'type' => 'string'],

        // Financial Settings
        'FINANCE_CURRENCY_SYMBOL' => ['value' => '$', 'type' => 'string'],
        'FINANCE_TAX_RATE_PERCENT' => ['value' => 10.0, 'type' => 'float'],
        'LATE_RETURN_FEE_HOURLY' => ['value' => 15.00, 'type' => 'float'],

        // Booking Logic Settings
        'BOOKING_MIN_PERIOD_HOURS' => ['value' => 4, 'type' => 'integer'],
        'RENTAL_BUFFER_HOURS' => ['value' => 2, 'type' => 'integer'],
        'RENTAL_ADVANCE_NOTICE_HOURS' => ['value' => 1, 'type' => 'integer'],

        // Email Settings
        'EMAIL_SENDER_ADDRESS' => ['value' => 'noreply@domain.com', 'type' => 'string'],
        'EMAIL_ADMIN_ALERT_ADDRESS' => ['value' => 'admin@domain.com', 'type' => 'string'],
    ];

    public function __construct(private SettingRepository $settingRepository)
    {
    }

    /**
     * Get all settings as key-value pairs
     * @return array<string, mixed>
     */
    public function all(): array
    {
        if (!$this->cacheLoaded) {
            $this->loadCache();
        }

        return $this->cache;
    }

    /**
     * Get a single setting by key with optional default
     */
    public function get(string $key, mixed $default = null): mixed
    {
        if (!$this->cacheLoaded) {
            $this->loadCache();
        }

        return $this->cache[$key] ?? $default;
    }

    /**
     * Set a setting value
     */
    public function set(string $key, mixed $value, string $type = 'string'): void
    {
        $this->settingRepository->upsert($key, $value, $type);
        $this->cache[$key] = $value;
    }

    /**
     * Check if system is in maintenance mode
     */
    public function isMaintenanceMode(): bool
    {
        return (bool)$this->get('SYSTEM_MAINTENANCE_MODE', false);
    }

    /**
     * Get default timezone
     */
    public function getDefaultTimezone(): string
    {
        return (string)$this->get('SYSTEM_DEFAULT_TIMEZONE', 'UTC');
    }

    /**
     * Get currency symbol
     */
    public function getCurrencySymbol(): string
    {
        return (string)$this->get('FINANCE_CURRENCY_SYMBOL', '$');
    }

    /**
     * Get tax rate as percentage
     */
    public function getTaxRatePercent(): float
    {
        return (float)$this->get('FINANCE_TAX_RATE_PERCENT', 10.0);
    }

    /**
     * Get late return fee per hour
     */
    public function getLateReturnFeeHourly(): float
    {
        return (float)$this->get('LATE_RETURN_FEE_HOURLY', 15.00);
    }

    /**
     * Get minimum booking period in hours
     */
    public function getMinBookingPeriodHours(): int
    {
        return (int)$this->get('BOOKING_MIN_PERIOD_HOURS', 4);
    }

    /**
     * Get rental preparation buffer in hours
     */
    public function getRentalBufferHours(): int
    {
        return (int)$this->get('RENTAL_BUFFER_HOURS', 2);
    }

    /**
     * Get minimum advance notice for booking in hours
     */
    public function getRentalAdvanceNoticeHours(): int
    {
        return (int)$this->get('RENTAL_ADVANCE_NOTICE_HOURS', 1);
    }

    /**
     * Get system sender email address
     */
    public function getEmailSenderAddress(): string
    {
        return (string)$this->get('EMAIL_SENDER_ADDRESS', 'noreply@domain.com');
    }

    /**
     * Get admin alert email address
     */
    public function getEmailAdminAlertAddress(): string
    {
        return (string)$this->get('EMAIL_ADMIN_ALERT_ADDRESS', 'admin@domain.com');
    }

    /**
     * Initialize database with MVP defaults (call once during installation)
     */
    public function initializeDefaults(): void
    {
        foreach (self::MVP_DEFAULTS as $key => $config) {
            $existing = $this->settingRepository->findByKey($key);
            if (!$existing) {
                $this->set($key, $config['value'], $config['type']);
            }
        }
    }

    /**
     * Load all settings from database into cache
     */
    private function loadCache(): void
    {
        $this->cache = $this->settingRepository->getAllAsArray();

        // Merge in any MVP defaults that don't exist in database
        foreach (self::MVP_DEFAULTS as $key => $config) {
            if (!isset($this->cache[$key])) {
                $this->cache[$key] = $config['value'];
            }
        }

        $this->cacheLoaded = true;
    }

    /**
     * Clear cache (call after direct database modifications)
     */
    public function clearCache(): void
    {
        $this->cache = [];
        $this->cacheLoaded = false;
    }
}
