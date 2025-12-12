<?php
// src/EventSubscriber/LoginSuccessSubscriber.php

namespace App\EventSubscriber;

use App\Entity\User;
use App\Service\ActivityLogger;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;

class LoginSuccessSubscriber implements EventSubscriberInterface
{
    private ActivityLogger $activityLogger;

    public function __construct(ActivityLogger $activityLogger)
    {
        $this->activityLogger = $activityLogger;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class => 'onLoginSuccess',
            LogoutEvent::class => 'onLogout',
        ];
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        /** @var User $user */
        $user = $event->getUser();
        
        $this->activityLogger->log(
            'USER_LOGIN',                     // Action
            'User logged in successfully',    // Description
            sprintf('User ID: %d | Email: %s', $user->getId(), $user->getEmail()) // Target data
        );
    }

    public function onLogout(LogoutEvent $event): void
    {
        $token = $event->getToken();
        
        if ($token) {
            $user = $token->getUser();
            
            if ($user instanceof User) {
                $this->activityLogger->log(
                    'USER_LOGOUT',                     // Action
                    'User logged out',                 // Description
                    sprintf('User ID: %d | Email: %s', $user->getId(), $user->getEmail()) // Target data
                );
            }
        }
    }
}