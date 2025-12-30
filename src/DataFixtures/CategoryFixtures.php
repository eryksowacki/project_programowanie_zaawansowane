<?php

namespace App\DataFixtures;

use App\Entity\Category;
use App\Repository\CategoryRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class CategoryFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        /** @var CategoryRepository $repo */
        $repo = $manager->getRepository(Category::class);

        $categories = [
            ['name' => 'Sprzedaż towarów i usług', 'type' => 'INCOME'],
            ['name' => 'Pozostałe przychody', 'type' => 'INCOME'],

            ['name' => 'Koszt zakupu towarów i materiałów', 'type' => 'COST'],
            ['name' => 'Koszty uboczne', 'type' => 'COST'],
            ['name' => 'Wynagrodzenia', 'type' => 'COST'],
            ['name' => 'Pozostałe koszty', 'type' => 'COST'],
            ['name' => 'Koszty działalności badawczo-rozwojowej', 'type' => 'COST'],
        ];

        foreach ($categories as $row) {
            $cat = $repo->findOneBy([
                'name' => $row['name'],
                'type' => $row['type'],
            ]) ?? new Category();

            $cat
                ->setName($row['name'])
                ->setType($row['type']);

            $manager->persist($cat);
        }

        $manager->flush();
    }
}
