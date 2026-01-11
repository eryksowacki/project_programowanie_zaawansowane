<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251230003726 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add invoice_number to document (MySQL compatible)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE document ADD invoice_number VARCHAR(128) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE document DROP COLUMN invoice_number');
    }
}