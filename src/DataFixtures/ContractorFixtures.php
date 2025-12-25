<?php

namespace App\DataFixtures;

use App\Entity\Company;
use App\Entity\Contractor;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;

class ContractorFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        /** @var Company $company */
        /** @var Company $company */
        $company = $this->getReference(CompanyFixtures::COMPANY_DEMO, Company::class);

        $c1 = new Contractor();
        $c1
            ->setName('ABC Handel Sp. z o.o.')
            ->setTaxId('1111111111')
            ->setAddress('ul. Handlowa 5, 01-001 Miasto')
            ->setCompany($company);
        $manager->persist($c1);

        $c2 = new Contractor();
        $c2
            ->setName('Jan Kowalski')
            ->setTaxId('2222222222')
            ->setAddress('ul. Klientów 7, 02-002 Miasto')
            ->setCompany($company);
        $manager->persist($c2);

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            CompanyFixtures::class,
        ];
    }
}
