<?php

namespace App\EventListener;

use App\Entity\User;
use App\Entity\ActivityLog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;

class LoginSuccessListener
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function __invoke(InteractiveLoginEvent $event): void
    {
        $user = $event->getAuthenticationToken()->getUser();
        $request = $event->getRequest();
        
        if (!$user instanceof User) {
            return;
        }
        
        // DEBUG: Create file to track how many times this runs
        file_put_contents('debug_login_count.txt', 
            '[' . date('H:i:s') . '] LoginSuccessListener called for: ' . $user->getEmail() . PHP_EOL, 
            FILE_APPEND
        );
        
        // ============ DUPLICATE PREVENTION ============
        // Check if we already logged this login in the last 3 seconds
        $threeSecondsAgo = new \DateTimeImmutable('-3 seconds');
        
        $existingLog = $this->entityManager->getRepository(ActivityLog::class)
            ->createQueryBuilder('a')
            ->where('a.user = :user')
            ->andWhere('a.action = :action')
            ->andWhere('a.createdAt > :time')
            ->setParameter('user', $user)
            ->setParameter('action', 'login')
            ->setParameter('time', $threeSecondsAgo)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
        
        if ($existingLog) {
            file_put_contents('debug_login_count.txt', 
                '[' . date('H:i:s') . '] SKIPPED: Already logged recently' . PHP_EOL, 
                FILE_APPEND
            );
            return; // Skip - already logged this login
        }
        
        // Update last login time
        $user->setLastLogin(new \DateTimeImmutable());
        
        // Create activity log
        $activityLog = new ActivityLog();
        $activityLog->setUser($user);
        $activityLog->setAction('login');
        $activityLog->setDescription('User logged in successfully');
        $activityLog->setIpAddress($request->getClientIp());
        $activityLog->setUsername($user->getEmail());  // Add this
        $activityLog->setUserRoles(implode(', ', $user->getRoles()));  // Add this
        
        $this->entityManager->persist($user);
        $this->entityManager->persist($activityLog);
        $this->entityManager->flush();
        
        file_put_contents('debug_login_count.txt', 
            '[' . date('H:i:s') . '] SUCCESS: Created login log for ' . $user->getEmail() . PHP_EOL, 
            FILE_APPEND
        );
        
        // Set redirect based on role
        $session = $request->getSession();
        
        if ($user->getPrimaryRole() === 'ROLE_ADMIN') {
            // Admin goes to admin profile
            $session->set('_security.main.target_path', '/admin/my-profile');
        } elseif ($user->getPrimaryRole() === 'ROLE_STAFF') {
            // Staff goes to homepage
            $session->set('_security.main.target_path', '/');
        } else {
            $session->set('_security.main.target_path', '/');
        }
    }
}