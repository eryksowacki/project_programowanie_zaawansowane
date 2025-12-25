<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher,
    ) {}

    public function load(ObjectManager $manager): void
    {
        $admin = new User();
        $admin->setEmail('admin@system.local');
        $admin->setRole(User::ROLE_SYSTEM_ADMIN); // "SYSTEM_ADMIN"

        $hashed = $this->passwordHasher->hashPassword($admin, 'admin123');
        $admin->setPassword($hashed);

        $manager->persist($admin);
        $manager->flush();
    }
}
