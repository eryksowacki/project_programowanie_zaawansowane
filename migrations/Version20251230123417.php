<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251230123417 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add roles table and link users.role_id (MySQL compatible)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("
            CREATE TABLE roles (
                id INT AUTO_INCREMENT NOT NULL,
                code VARCHAR(50) NOT NULL,
                name VARCHAR(100) NOT NULL,
                UNIQUE INDEX UNIQ_B63E2EC777153098 (code),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        ");

        $this->addSql('ALTER TABLE users ADD role_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE users DROP COLUMN role');

        $this->addSql('CREATE INDEX IDX_8D93D649D60322AC ON users (role_id)');
        $this->addSql('ALTER TABLE users ADD CONSTRAINT FK_8D93D649D60322AC FOREIGN KEY (role_id) REFERENCES roles (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP FOREIGN KEY FK_8D93D649D60322AC');
        $this->addSql('DROP INDEX IDX_8D93D649D60322AC ON users');

        $this->addSql('ALTER TABLE users ADD role VARCHAR(20) NOT NULL');
        $this->addSql('ALTER TABLE users DROP COLUMN role_id');

        $this->addSql('DROP TABLE roles');
    }
}