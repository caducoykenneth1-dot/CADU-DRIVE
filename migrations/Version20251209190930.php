<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251209190930 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE rental DROP FOREIGN KEY FK_1619C27D9395C3F3');
        $this->addSql('ALTER TABLE rental DROP FOREIGN KEY FK_1619C27DC3C6F69F');
        $this->addSql('DROP TABLE rental');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE rental (id INT AUTO_INCREMENT NOT NULL, car_id INT NOT NULL, customer_id INT NOT NULL, start_date DATETIME NOT NULL, end_date DATETIME NOT NULL, confirmed_at DATETIME DEFAULT NULL, INDEX IDX_1619C27D9395C3F3 (customer_id), INDEX IDX_1619C27DC3C6F69F (car_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE rental ADD CONSTRAINT FK_1619C27D9395C3F3 FOREIGN KEY (customer_id) REFERENCES customer (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE rental ADD CONSTRAINT FK_1619C27DC3C6F69F FOREIGN KEY (car_id) REFERENCES car (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
    }
}
