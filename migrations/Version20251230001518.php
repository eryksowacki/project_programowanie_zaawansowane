<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251230001518 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add vat_active to company (MySQL compatible)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE company ADD vat_active TINYINT(1) NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE company DROP COLUMN vat_active');
    }
}