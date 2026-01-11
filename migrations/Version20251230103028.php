<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251230103028 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove company_id from category (MySQL compatible)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE category DROP FOREIGN KEY FK_64C19C1979B1AD6');
        $this->addSql('DROP INDEX IDX_64C19C1979B1AD6 ON category');
        $this->addSql('ALTER TABLE category DROP COLUMN company_id');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE category ADD company_id INT NOT NULL');
        $this->addSql('CREATE INDEX IDX_64C19C1979B1AD6 ON category (company_id)');
        $this->addSql('ALTER TABLE category ADD CONSTRAINT FK_64C19C1979B1AD6 FOREIGN KEY (company_id) REFERENCES company (id)');
    }
}