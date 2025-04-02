<?php

namespace App\DataFixtures\Admin;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class Fixtures extends Fixture implements FixtureGroupInterface
{
    public static function getGroups(): array
    {
        return ['admin'];
    }

    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $user = new User();
        $user->setEmail('admin@jabka.esports');
        $hashedPassword = $this->passwordHasher->hashPassword($user, 'Asdfasdf1.');
        $user->setPassword($hashedPassword);
        $manager->persist($user);
        $manager->flush();
    }
}
