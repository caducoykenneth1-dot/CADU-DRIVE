<?php

namespace App\EventListener;

use App\Entity\User;
use App\Entity\ActivityLog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Http\Event\LogoutEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: LogoutEvent::class)]
class LogoutListener
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function __invoke(LogoutEvent $event): void
    {
        // DEBUG: Create a file to prove this runs
        file_put_contents('debug_logout.txt', '[' . date('H:i:s') . '] Logout listener started' . PHP_EOL, FILE_APPEND);
        
        $user = $event->getToken() ? $event->getToken()->getUser() : null;
        
        if ($user instanceof User) {
            file_put_contents('debug_logout.txt', '[' . date('H:i:s') . '] User: ' . $user->getEmail() . PHP_EOL, FILE_APPEND);
            
            // Create activity log
            $activityLog = new ActivityLog();
            $activityLog->setUser($user);
            $activityLog->setAction('logout');
            $activityLog->setDescription('User logged out');
            $activityLog->setIpAddress($event->getRequest()->getClientIp());
            
            $this->entityManager->persist($activityLog);
            
            try {
                $this->entityManager->flush();
                file_put_contents('debug_logout.txt', '[' . date('H:i:s') . '] SUCCESS: Logout activity saved' . PHP_EOL, FILE_APPEND);
            } catch (\Exception $e) {
                file_put_contents('debug_logout.txt', '[' . date('H:i:s') . '] ERROR: ' . $e->getMessage() . PHP_EOL, FILE_APPEND);
            }
        } else {
            file_put_contents('debug_logout.txt', '[' . date('H:i:s') . '] No user found' . PHP_EOL, FILE_APPEND);
        }
        
        file_put_contents('debug_logout.txt', '[' . date('H:i:s') . '] Logout listener finished' . PHP_EOL . PHP_EOL, FILE_APPEND);
    }
}