<?php

namespace App\DataFixtures;

use App\Entity\User;
use App\Entity\Staff; // ADD THIS
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class PrivateFixtures extends Fixture
{
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
    }

    public function load(ObjectManager $manager): void
    {
        // Create Admin User
        $admin = new User();
        $admin->setEmail('admin@gmail.com');
        $admin->setRoles(['ROLE_ADMIN']);
        $hashedPassword = $this->passwordHasher->hashPassword($admin, 'adminpass');
        $admin->setPassword($hashedPassword);
        $manager->persist($admin);

        // Create Staff record for Admin
        $adminStaff = new Staff(); // NEW
        $adminStaff->setUser($admin);
        $adminStaff->setFirstName('Admin');
        $adminStaff->setLastName('User');
        $adminStaff->setEmail('admin@gmail.com');
        $adminStaff->setIsActive(true);
        $adminStaff->setCreatedAt(new \DateTime());
        $adminStaff->setUpdatedAt(new \DateTime());
        $manager->persist($adminStaff);

        // Create Staff User
        $worker = new User();
        $worker->setEmail('staff@gmail.com');
        $worker->setRoles(['ROLE_STAFF']);
        $hashedPassword = $this->passwordHasher->hashPassword($worker, 'staffpass');
        $worker->setPassword($hashedPassword);
        $manager->persist($worker);

        // Create Staff record for Staff User
        $workerStaff = new Staff(); // NEW
        $workerStaff->setUser($worker);
        $workerStaff->setFirstName('Staff');
        $workerStaff->setLastName('Member');
        $workerStaff->setEmail('staff@gmail.com');
        $workerStaff->setIsActive(true);
        $workerStaff->setCreatedAt(new \DateTime());
        $workerStaff->setUpdatedAt(new \DateTime());
        $manager->persist($workerStaff);

        $manager->flush();
    }
}