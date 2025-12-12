<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251210193639 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE activity_log ADD action VARCHAR(255) NOT NULL, ADD target_data LONGTEXT DEFAULT NULL, ADD username VARCHAR(100) DEFAULT NULL, ADD user_roles VARCHAR(255) DEFAULT NULL, ADD ip_address VARCHAR(45) DEFAULT NULL, ADD user_agent VARCHAR(255) DEFAULT NULL, CHANGE user_id user_id INT NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE activity_log DROP action, DROP target_data, DROP username, DROP user_roles, DROP ip_address, DROP user_agent, CHANGE user_id user_id INT DEFAULT NULL');
    }
}
