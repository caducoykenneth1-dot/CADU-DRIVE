<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251209175959 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE rental_request (id INT AUTO_INCREMENT NOT NULL, customer_id INT NOT NULL, car_id INT NOT NULL, created_at DATETIME NOT NULL, start_date DATE NOT NULL, end_date DATE NOT NULL, status VARCHAR(20) NOT NULL, INDEX IDX_E87155709395C3F3 (customer_id), INDEX IDX_E8715570C3C6F69F (car_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE rental_request ADD CONSTRAINT FK_E87155709395C3F3 FOREIGN KEY (customer_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE rental_request ADD CONSTRAINT FK_E8715570C3C6F69F FOREIGN KEY (car_id) REFERENCES car (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE rental_request DROP FOREIGN KEY FK_E87155709395C3F3');
        $this->addSql('ALTER TABLE rental_request DROP FOREIGN KEY FK_E8715570C3C6F69F');
        $this->addSql('DROP TABLE rental_request');
    }
}
