<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251118234310 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Initial schema (MySQL/MariaDB compatible)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("
            CREATE TABLE company (
                id INT AUTO_INCREMENT NOT NULL,
                name VARCHAR(255) NOT NULL,
                tax_id VARCHAR(20) DEFAULT NULL,
                address LONGTEXT DEFAULT NULL,
                active TINYINT(1) NOT NULL,
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        ");

        $this->addSql("
            CREATE TABLE category (
                id INT AUTO_INCREMENT NOT NULL,
                company_id INT NOT NULL,
                name VARCHAR(255) NOT NULL,
                type VARCHAR(10) NOT NULL,
                INDEX IDX_64C19C1979B1AD6 (company_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        ");

        $this->addSql("
            CREATE TABLE contractor (
                id INT AUTO_INCREMENT NOT NULL,
                company_id INT NOT NULL,
                name VARCHAR(255) NOT NULL,
                tax_id VARCHAR(20) DEFAULT NULL,
                address LONGTEXT DEFAULT NULL,
                INDEX IDX_437BD2EF979B1AD6 (company_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        ");

        $this->addSql("
            CREATE TABLE users (
                id INT AUTO_INCREMENT NOT NULL,
                company_id INT DEFAULT NULL,
                email VARCHAR(180) NOT NULL,
                password VARCHAR(255) NOT NULL,
                first_name VARCHAR(100) DEFAULT NULL,
                last_name VARCHAR(100) DEFAULT NULL,
                role VARCHAR(20) NOT NULL,
                UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL (email),
                INDEX IDX_8D93D649979B1AD6 (company_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        ");

        $this->addSql("
            CREATE TABLE document (
                id INT AUTO_INCREMENT NOT NULL,
                company_id INT NOT NULL,
                category_id INT DEFAULT NULL,
                contractor_id INT DEFAULT NULL,
                created_by_id INT DEFAULT NULL,
                type VARCHAR(15) NOT NULL,
                issue_date DATE NOT NULL,
                event_date DATE NOT NULL,
                description VARCHAR(255) DEFAULT NULL,
                net_amount DECIMAL(10, 2) NOT NULL,
                vat_amount DECIMAL(10, 2) NOT NULL,
                gross_amount DECIMAL(10, 2) NOT NULL,
                status VARCHAR(10) NOT NULL,
                ledger_number INT DEFAULT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                INDEX IDX_D8698A76979B1AD6 (company_id),
                INDEX IDX_D8698A7612469DE2 (category_id),
                INDEX IDX_D8698A76B0265DC7 (contractor_id),
                INDEX IDX_D8698A76B03A8386 (created_by_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        ");

        $this->addSql("ALTER TABLE category
            ADD CONSTRAINT FK_64C19C1979B1AD6 FOREIGN KEY (company_id) REFERENCES company (id)
        ");

        $this->addSql("ALTER TABLE contractor
            ADD CONSTRAINT FK_437BD2EF979B1AD6 FOREIGN KEY (company_id) REFERENCES company (id)
        ");

        $this->addSql("ALTER TABLE document
            ADD CONSTRAINT FK_D8698A76979B1AD6 FOREIGN KEY (company_id) REFERENCES company (id)
        ");

        $this->addSql("ALTER TABLE document
            ADD CONSTRAINT FK_D8698A7612469DE2 FOREIGN KEY (category_id) REFERENCES category (id)
        ");

        $this->addSql("ALTER TABLE document
            ADD CONSTRAINT FK_D8698A76B0265DC7 FOREIGN KEY (contractor_id) REFERENCES contractor (id)
        ");

        $this->addSql("ALTER TABLE document
            ADD CONSTRAINT FK_D8698A76B03A8386 FOREIGN KEY (created_by_id) REFERENCES users (id)
        ");

        $this->addSql("ALTER TABLE users
            ADD CONSTRAINT FK_8D93D649979B1AD6 FOREIGN KEY (company_id) REFERENCES company (id)
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE document DROP FOREIGN KEY FK_D8698A76B03A8386');
        $this->addSql('ALTER TABLE document DROP FOREIGN KEY FK_D8698A76B0265DC7');
        $this->addSql('ALTER TABLE document DROP FOREIGN KEY FK_D8698A7612469DE2');
        $this->addSql('ALTER TABLE document DROP FOREIGN KEY FK_D8698A76979B1AD6');
        $this->addSql('ALTER TABLE contractor DROP FOREIGN KEY FK_437BD2EF979B1AD6');
        $this->addSql('ALTER TABLE category DROP FOREIGN KEY FK_64C19C1979B1AD6');
        $this->addSql('ALTER TABLE users DROP FOREIGN KEY FK_8D93D649979B1AD6');

        $this->addSql('DROP TABLE document');
        $this->addSql('DROP TABLE users');
        $this->addSql('DROP TABLE contractor');
        $this->addSql('DROP TABLE category');
        $this->addSql('DROP TABLE company');
    }
}