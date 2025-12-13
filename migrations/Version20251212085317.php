<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251212085317 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE rental_request ADD approved_by_id INT DEFAULT NULL, ADD rejected_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE rental_request ADD CONSTRAINT FK_E87155702D234F6A FOREIGN KEY (approved_by_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE rental_request ADD CONSTRAINT FK_E8715570CBF05FC9 FOREIGN KEY (rejected_by_id) REFERENCES user (id)');
        $this->addSql('CREATE INDEX IDX_E87155702D234F6A ON rental_request (approved_by_id)');
        $this->addSql('CREATE INDEX IDX_E8715570CBF05FC9 ON rental_request (rejected_by_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE rental_request DROP FOREIGN KEY FK_E87155702D234F6A');
        $this->addSql('ALTER TABLE rental_request DROP FOREIGN KEY FK_E8715570CBF05FC9');
        $this->addSql('DROP INDEX IDX_E87155702D234F6A ON rental_request');
        $this->addSql('DROP INDEX IDX_E8715570CBF05FC9 ON rental_request');
        $this->addSql('ALTER TABLE rental_request DROP approved_by_id, DROP rejected_by_id');
    }
}
