<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251013143333 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $schemaManager = method_exists($this->connection, 'createSchemaManager')
            ? $this->connection->createSchemaManager()
            : $this->connection->getSchemaManager();

        $columns = [];
        try {
            $columns = $schemaManager->listTableColumns('rental');
        } catch (\Throwable $e) {
            // ignore - let subsequent SQL throw if table truly missing
        }

        $hasCustomerId = false;
        $hasLegacyName = false;
        foreach ($columns as $column) {
            $name = strtolower($column->getName());
            if ($name === 'customer_id') {
                $hasCustomerId = true;
            }
            if ($name === 'customer_name') {
                $hasLegacyName = true;
            }
        }

        if (!$hasCustomerId) {
            $this->addSql('ALTER TABLE rental ADD customer_id INT DEFAULT NULL');
        }

        $rentals = $hasLegacyName
            ? $this->connection->fetchAllAssociative('SELECT id, customer_name FROM rental')
            : [];
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $customerCache = [];

        $toLower = static function (string $value): string {
            return function_exists('mb_strtolower') ? mb_strtolower($value) : strtolower($value);
        };

        foreach ($rentals as $rental) {
            $rawName = trim((string) ($rental['customer_name'] ?? ''));
            if ($rawName === '') {
                $firstName = 'Guest';
                $lastName = 'Customer';
            } else {
                $parts = preg_split('/\s+/', $rawName, 2);
                $firstName = $parts[0] ?: 'Guest';
                $lastName = $parts[1] ?? 'Customer';
            }

            $key = $toLower($firstName . '|' . $lastName);

            if (!isset($customerCache[$key])) {
                $emailBase = preg_replace('/[^a-z0-9]+/', '.', $toLower($firstName . '.' . $lastName)) ?: 'guest.customer';
                $email = trim($emailBase, '.') . '.' . bin2hex(random_bytes(3)) . '@legacy.example.com';

                $this->connection->insert('customer', [
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => $email,
                    'phone' => null,
                    'license_number' => null,
                    'notes' => 'Imported from historical rentals',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $customerCache[$key] = (int) $this->connection->lastInsertId();
            }

            $this->connection->update('rental', ['customer_id' => $customerCache[$key]], ['id' => $rental['id']]);
        }

        $customerCount = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM customer');
        if ($customerCount === 0) {
            $this->connection->insert('customer', [
                'first_name' => 'Legacy',
                'last_name' => 'Customer',
                'email' => 'legacy.placeholder@import.local',
                'phone' => null,
                'license_number' => null,
                'notes' => 'Auto-created placeholder for legacy rentals',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $fallbackCustomerId = (int) $this->connection->fetchOne('SELECT id FROM customer ORDER BY id LIMIT 1');
        $this->connection->executeStatement('UPDATE rental SET customer_id = :fallback WHERE customer_id IS NULL OR customer_id = 0', [
            'fallback' => $fallbackCustomerId,
        ]);
        $this->addSql('ALTER TABLE rental MODIFY customer_id INT NOT NULL');

        $foreignKeys = [];
        try {
            $foreignKeys = $schemaManager->listTableForeignKeys('rental');
        } catch (\Throwable $e) {
            // ignore
        }

        $hasCustomerFk = false;
        foreach ($foreignKeys as $foreignKey) {
            $local = array_map('strtolower', $foreignKey->getLocalColumns());
            if ($local === ['customer_id']) {
                $hasCustomerFk = true;
                break;
            }
        }

        if (!$hasCustomerFk) {
            $this->addSql('ALTER TABLE rental ADD CONSTRAINT FK_1619C27D9395C3F3 FOREIGN KEY (customer_id) REFERENCES customer (id)');
        }

        $indexes = [];
        try {
            $indexes = $schemaManager->listTableIndexes('rental');
        } catch (\Throwable $e) {
            // ignore
        }

        $hasCustomerIndex = false;
        foreach ($indexes as $index) {
            $columnsIdx = array_map('strtolower', $index->getColumns());
            if ($columnsIdx === ['customer_id']) {
                $hasCustomerIndex = true;
                break;
            }
        }

        if (!$hasCustomerIndex) {
            $this->addSql('CREATE INDEX IDX_1619C27D9395C3F3 ON rental (customer_id)');
        }

        if ($hasLegacyName) {
            $this->addSql('ALTER TABLE rental DROP customer_name');
        }
    }

    public function down(Schema $schema): void
    {
        $schemaManager = method_exists($this->connection, 'createSchemaManager')
            ? $this->connection->createSchemaManager()
            : $this->connection->getSchemaManager();

        $columns = [];
        try {
            $columns = $schemaManager->listTableColumns('rental');
        } catch (\Throwable $e) {
            // ignore
        }

        $hasCustomerName = false;
        $hasCustomerId = false;
        foreach ($columns as $column) {
            $name = strtolower($column->getName());
            if ($name === 'customer_name') {
                $hasCustomerName = true;
            }
            if ($name === 'customer_id') {
                $hasCustomerId = true;
            }
        }

        if (!$hasCustomerName) {
            $this->addSql('ALTER TABLE rental ADD customer_name VARCHAR(255) DEFAULT NULL');
        }

        if ($hasCustomerId) {
            $rentals = $this->connection->fetchAllAssociative('SELECT r.id, c.first_name, c.last_name FROM rental r JOIN customer c ON r.customer_id = c.id');
            foreach ($rentals as $rental) {
                $fullName = trim(sprintf('%s %s', $rental['first_name'], $rental['last_name']));
                $this->connection->update('rental', ['customer_name' => $fullName], ['id' => $rental['id']]);
            }
        }

        $foreignKeys = [];
        try {
            $foreignKeys = $schemaManager->listTableForeignKeys('rental');
        } catch (\Throwable $e) {
            // ignore
        }

        foreach ($foreignKeys as $foreignKey) {
            $local = array_map('strtolower', $foreignKey->getLocalColumns());
            if ($local === ['customer_id']) {
                $this->addSql('ALTER TABLE rental DROP FOREIGN KEY ' . $foreignKey->getName());
            }
        }

        $indexes = [];
        try {
            $indexes = $schemaManager->listTableIndexes('rental');
        } catch (\Throwable $e) {
            // ignore
        }

        foreach ($indexes as $index) {
            $columnsIdx = array_map('strtolower', $index->getColumns());
            if ($columnsIdx === ['customer_id']) {
                $this->addSql('DROP INDEX ' . $index->getName() . ' ON rental');
            }
        }

        if (!$hasCustomerName) {
            $this->addSql('ALTER TABLE rental MODIFY customer_name VARCHAR(255) NOT NULL');
        }

        if ($hasCustomerId) {
            $this->addSql('ALTER TABLE rental DROP customer_id');
        }
    }
}
