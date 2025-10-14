<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251014052918 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // seed required statuses if they do not yet exist
        $this->addSql("INSERT INTO car_status (code, label, description, is_active) VALUES
            ('available', 'Available', 'Vehicle can be booked.', 1),
            ('rented', 'Rented', 'Vehicle is currently rented.', 1),
            ('maintenance', 'Maintenance', 'Vehicle is unavailable while in maintenance.', 1)
        ON DUPLICATE KEY UPDATE label = VALUES(label), description = VALUES(description), is_active = VALUES(is_active)");

        // add the new relation column as nullable while we migrate existing values
        $this->addSql('ALTER TABLE car ADD status_id INT DEFAULT NULL');

        // map legacy status strings to the newly inserted records
        $this->addSql("UPDATE car c SET status_id = (
            SELECT cs.id FROM car_status cs WHERE cs.code = c.status
        ) WHERE c.status IS NOT NULL");

        // ensure every car ends up with a status, defaulting to available
        $this->addSql("UPDATE car SET status_id = (
            SELECT id FROM car_status WHERE code = 'available'
        ) WHERE status_id IS NULL");

        // enforce the NOT NULL constraint and set up the foreign key
        $this->addSql('ALTER TABLE car MODIFY status_id INT NOT NULL');
        $this->addSql('ALTER TABLE car ADD CONSTRAINT FK_773DE69D6BF700BD FOREIGN KEY (status_id) REFERENCES car_status (id)');
        $this->addSql('CREATE INDEX IDX_773DE69D6BF700BD ON car (status_id)');

        // legacy column no longer needed
        $this->addSql('ALTER TABLE car DROP status');
    }

    public function down(Schema $schema): void
    {
        // restore the legacy column
        $this->addSql('ALTER TABLE car ADD status VARCHAR(255) DEFAULT \'available\' NOT NULL');

        // repopulate string statuses based on the relation
        $this->addSql("UPDATE car c SET status = (
            SELECT cs.code FROM car_status cs WHERE cs.id = c.status_id
        ) WHERE c.status_id IS NOT NULL");

        // drop the relation
        $this->addSql('ALTER TABLE car DROP FOREIGN KEY FK_773DE69D6BF700BD');
        $this->addSql('DROP INDEX IDX_773DE69D6BF700BD ON car');
        $this->addSql('ALTER TABLE car DROP status_id');
    }
}
