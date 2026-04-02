<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260402205919 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add restreamer_id to cameras table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cameras ADD COLUMN restreamer_id VARCHAR(255) NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cameras DROP COLUMN restreamer_id');
    }
}
