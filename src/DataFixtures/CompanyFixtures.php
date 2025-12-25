<?php

namespace App\DataFixtures;

use App\Entity\Company;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class CompanyFixtures extends Fixture
{
    public const COMPANY_DEMO = 'company_demo';

    public function load(ObjectManager $manager): void
    {
        $company = new Company();
        $company
            ->setName('Demo Sp. z o.o.')
            ->setTaxId('1234567890')
            ->setAddress('ul. Testowa 1, 00-000 Miasto')
            ->setActive(true)
        ;

        $manager->persist($company);
        $manager->flush();

        $this->addReference(self::COMPANY_DEMO, $company);
    }
}
