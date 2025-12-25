<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251118234310 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE category (id SERIAL NOT NULL, company_id INT NOT NULL, name VARCHAR(255) NOT NULL, type VARCHAR(10) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_64C19C1979B1AD6 ON category (company_id)');
        $this->addSql('CREATE TABLE company (id SERIAL NOT NULL, name VARCHAR(255) NOT NULL, tax_id VARCHAR(20) DEFAULT NULL, address TEXT DEFAULT NULL, active BOOLEAN NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE contractor (id SERIAL NOT NULL, company_id INT NOT NULL, name VARCHAR(255) NOT NULL, tax_id VARCHAR(20) DEFAULT NULL, address TEXT DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_437BD2EF979B1AD6 ON contractor (company_id)');
        $this->addSql('CREATE TABLE document (id SERIAL NOT NULL, company_id INT NOT NULL, category_id INT DEFAULT NULL, contractor_id INT DEFAULT NULL, created_by_id INT DEFAULT NULL, type VARCHAR(15) NOT NULL, issue_date DATE NOT NULL, event_date DATE NOT NULL, description VARCHAR(255) DEFAULT NULL, net_amount NUMERIC(10, 2) NOT NULL, vat_amount NUMERIC(10, 2) NOT NULL, gross_amount NUMERIC(10, 2) NOT NULL, status VARCHAR(10) NOT NULL, ledger_number INT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_D8698A76979B1AD6 ON document (company_id)');
        $this->addSql('CREATE INDEX IDX_D8698A7612469DE2 ON document (category_id)');
        $this->addSql('CREATE INDEX IDX_D8698A76B0265DC7 ON document (contractor_id)');
        $this->addSql('CREATE INDEX IDX_D8698A76B03A8386 ON document (created_by_id)');
        $this->addSql('COMMENT ON COLUMN document.issue_date IS \'(DC2Type:date_immutable)\'');
        $this->addSql('COMMENT ON COLUMN document.event_date IS \'(DC2Type:date_immutable)\'');
        $this->addSql('COMMENT ON COLUMN document.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN document.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE "user" (id SERIAL NOT NULL, company_id INT DEFAULT NULL, email VARCHAR(180) NOT NULL, password VARCHAR(255) NOT NULL, first_name VARCHAR(100) DEFAULT NULL, last_name VARCHAR(100) DEFAULT NULL, role VARCHAR(20) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_8D93D649979B1AD6 ON "user" (company_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL ON "user" (email)');
        $this->addSql('ALTER TABLE category ADD CONSTRAINT FK_64C19C1979B1AD6 FOREIGN KEY (company_id) REFERENCES company (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE contractor ADD CONSTRAINT FK_437BD2EF979B1AD6 FOREIGN KEY (company_id) REFERENCES company (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE document ADD CONSTRAINT FK_D8698A76979B1AD6 FOREIGN KEY (company_id) REFERENCES company (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE document ADD CONSTRAINT FK_D8698A7612469DE2 FOREIGN KEY (category_id) REFERENCES category (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE document ADD CONSTRAINT FK_D8698A76B0265DC7 FOREIGN KEY (contractor_id) REFERENCES contractor (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE document ADD CONSTRAINT FK_D8698A76B03A8386 FOREIGN KEY (created_by_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE "user" ADD CONSTRAINT FK_8D93D649979B1AD6 FOREIGN KEY (company_id) REFERENCES company (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE category DROP CONSTRAINT FK_64C19C1979B1AD6');
        $this->addSql('ALTER TABLE contractor DROP CONSTRAINT FK_437BD2EF979B1AD6');
        $this->addSql('ALTER TABLE document DROP CONSTRAINT FK_D8698A76979B1AD6');
        $this->addSql('ALTER TABLE document DROP CONSTRAINT FK_D8698A7612469DE2');
        $this->addSql('ALTER TABLE document DROP CONSTRAINT FK_D8698A76B0265DC7');
        $this->addSql('ALTER TABLE document DROP CONSTRAINT FK_D8698A76B03A8386');
        $this->addSql('ALTER TABLE "user" DROP CONSTRAINT FK_8D93D649979B1AD6');
        $this->addSql('DROP TABLE category');
        $this->addSql('DROP TABLE company');
        $this->addSql('DROP TABLE contractor');
        $this->addSql('DROP TABLE document');
        $this->addSql('DROP TABLE "user"');
    }
}
