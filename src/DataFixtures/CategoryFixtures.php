<?php

namespace App\DataFixtures;

use App\Entity\Category;
use App\Entity\Company;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;

class CategoryFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        /** @var Company $company */
        $company = $this->getReference(CompanyFixtures::COMPANY_DEMO, Company::class);

        $incomeNames = ['Sprzedaż towarów i usług', 'Pozostałe przychody'];
        foreach ($incomeNames as $name) {
            $cat = new Category();
            $cat
                ->setName($name)
                ->setType('INCOME')
                ->setCompany($company);
            $manager->persist($cat);
        }

        $costNames = ['Koszty uboczne zakupu', 'Wynagrodzenia', 'Pozostałe wydatki'];
        foreach ($costNames as $name) {
            $cat = new Category();
            $cat
                ->setName($name)
                ->setType('COST')
                ->setCompany($company);
            $manager->persist($cat);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            CompanyFixtures::class,
        ];
    }
}
