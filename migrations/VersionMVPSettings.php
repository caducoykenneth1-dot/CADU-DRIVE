<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class VersionMVPSettings extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create Setting entity table for MVP settings (system, financial, booking, email)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE `setting` (
            id INT AUTO_INCREMENT NOT NULL,
            setting_key VARCHAR(255) NOT NULL,
            setting_value LONGTEXT NOT NULL,
            setting_type VARCHAR(50) NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            UNIQUE INDEX UNIQ_setting_key (setting_key),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE `setting`');
    }
}
