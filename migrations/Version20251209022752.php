<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251209022752 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE staff CHANGE user_id user_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE staff ADD CONSTRAINT FK_426EF392A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE staff ADD CONSTRAINT FK_426EF39264F1F4EE FOREIGN KEY (created_by_admin_id) REFERENCES `admin` (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_426EF392A76ED395 ON staff (user_id)');
        $this->addSql('CREATE INDEX IDX_426EF39264F1F4EE ON staff (created_by_admin_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE staff DROP FOREIGN KEY FK_426EF392A76ED395');
        $this->addSql('ALTER TABLE staff DROP FOREIGN KEY FK_426EF39264F1F4EE');
        $this->addSql('DROP INDEX UNIQ_426EF392A76ED395 ON staff');
        $this->addSql('DROP INDEX IDX_426EF39264F1F4EE ON staff');
        $this->addSql('ALTER TABLE staff CHANGE user_id user_id INT DEFAULT NULL');
    }
}
