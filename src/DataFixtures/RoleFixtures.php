<?php

namespace App\DataFixtures;

use App\Entity\Role;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class RoleFixtures extends Fixture
{
    public const REF_ADMIN = 'role_admin';
    public const REF_MANAGER = 'role_manager';
    public const REF_EMPLOYEE = 'role_employee';

    public function load(ObjectManager $manager): void
    {
        $repo = $manager->getRepository(Role::class);

        $admin = $repo->findOneBy(['code' => 'ROLE_SYSTEM_ADMIN']) ?? new Role();
        $admin->setCode('ROLE_SYSTEM_ADMIN')->setName('Admin');
        $manager->persist($admin);

        $managerRole = $repo->findOneBy(['code' => 'ROLE_MANAGER']) ?? new Role();
        $managerRole->setCode('ROLE_MANAGER')->setName('Manager');
        $manager->persist($managerRole);

        $employee = $repo->findOneBy(['code' => 'ROLE_EMPLOYEE']) ?? new Role();
        $employee->setCode('ROLE_EMPLOYEE')->setName('Employee');
        $manager->persist($employee);

        $manager->flush();

        $this->addReference(self::REF_ADMIN, $admin);
        $this->addReference(self::REF_MANAGER, $managerRole);
        $this->addReference(self::REF_EMPLOYEE, $employee);
    }
}
