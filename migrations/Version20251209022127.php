<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251209022127 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

   public function up(Schema $schema): void
{
    // Skip admin table creation if exists
    $this->skipIf($schema->hasTable('admin'), 'Admin table already exists');
    
    // Only run the staff modifications
    $this->addSql('ALTER TABLE staff DROP FOREIGN KEY FK_426EF392B03A8386');
    $this->addSql('DROP INDEX IDX_426EF392B03A8386 ON staff');
    $this->addSql('DROP INDEX UNIQ_426EF392E7927C74 ON staff');
    $this->addSql('ALTER TABLE staff ADD user_id INT NOT NULL, ADD hire_date DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', ADD department VARCHAR(255) DEFAULT NULL, ADD is_active TINYINT(1) DEFAULT 1 NOT NULL, DROP email, DROP password, DROP roles, CHANGE first_name first_name VARCHAR(100) NOT NULL, CHANGE last_name last_name VARCHAR(100) NOT NULL, CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE created_by_id created_by_admin_id INT DEFAULT NULL');
    $this->addSql('ALTER TABLE staff ADD CONSTRAINT FK_426EF392A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
    $this->addSql('ALTER TABLE staff ADD CONSTRAINT FK_426EF39264F1F4EE FOREIGN KEY (created_by_admin_id) REFERENCES `admin` (id)');
    $this->addSql('CREATE UNIQUE INDEX UNIQ_426EF392A76ED395 ON staff (user_id)');
    $this->addSql('CREATE INDEX IDX_426EF39264F1F4EE ON staff (created_by_admin_id)');
}
   public function down(Schema $schema): void
{
    // Skip reversing if admin table exists (we didn't create it)
    $this->skipIf($schema->hasTable('admin'), 'Admin table exists, cannot reverse migration');
    
    // Only reverse staff modifications
    $this->addSql('ALTER TABLE staff DROP FOREIGN KEY FK_426EF392A76ED395');
    $this->addSql('DROP INDEX UNIQ_426EF392A76ED395 ON staff');
    $this->addSql('DROP INDEX IDX_426EF39264F1F4EE ON staff');
    $this->addSql('ALTER TABLE staff ADD email VARCHAR(255) NOT NULL, ADD password VARCHAR(255) NOT NULL, ADD roles LONGTEXT NOT NULL COMMENT \'(DC2Type:simple_array)\', DROP user_id, DROP hire_date, DROP department, DROP is_active, CHANGE first_name first_name VARCHAR(255) NOT NULL, CHANGE last_name last_name VARCHAR(255) NOT NULL, CHANGE created_at created_at DATETIME NOT NULL, CHANGE created_by_admin_id created_by_id INT DEFAULT NULL');
    $this->addSql('ALTER TABLE staff ADD CONSTRAINT FK_426EF392B03A8386 FOREIGN KEY (created_by_id) REFERENCES staff (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
    $this->addSql('CREATE INDEX IDX_426EF392B03A8386 ON staff (created_by_id)');
    $this->addSql('CREATE UNIQUE INDEX UNIQ_426EF392E7927C74 ON staff (email)');
}
}