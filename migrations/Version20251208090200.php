<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251208090200 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE staff ADD created_by_id INT DEFAULT NULL, ADD roles LONGTEXT NOT NULL COMMENT \'(DC2Type:simple_array)\', DROP role');
        $this->addSql('ALTER TABLE staff ADD CONSTRAINT FK_426EF392B03A8386 FOREIGN KEY (created_by_id) REFERENCES staff (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_426EF392E7927C74 ON staff (email)');
        $this->addSql('CREATE INDEX IDX_426EF392B03A8386 ON staff (created_by_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE staff DROP FOREIGN KEY FK_426EF392B03A8386');
        $this->addSql('DROP INDEX UNIQ_426EF392E7927C74 ON staff');
        $this->addSql('DROP INDEX IDX_426EF392B03A8386 ON staff');
        $this->addSql('ALTER TABLE staff ADD role VARCHAR(255) NOT NULL, DROP created_by_id, DROP roles');
    }
}
