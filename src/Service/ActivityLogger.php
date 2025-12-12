<?php
// src/Service/ActivityLogger.php

namespace App\Service;

use App\Entity\ActivityLog;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

class ActivityLogger
{
    public function __construct(
        private EntityManagerInterface $em,
        private Security $security,
        private RequestStack $requestStack
    ) {}

    public function log(
        string $action,
        ?string $description = null,
        ?string $targetData = null,
        $userOverride = null   // optional 4th argument
    ): void {

        // SAFELY determine user
        if ($userOverride instanceof User) {
            $user = $userOverride;
        } else {
            $user = $this->security->getUser();
        }

        $request = $this->requestStack->getCurrentRequest();

        // Default user type
        $userType = 'UNKNOWN';

        // Only process roles if $user is a valid User object
        if ($user instanceof User) {
            $rolesLower = array_map('strtolower', $user->getRoles());

            if (in_array('role_admin', $rolesLower) || in_array('admin', $rolesLower)) {
                $userType = 'ADMIN';
            } elseif (in_array('role_staff', $rolesLower) || in_array('staff', $rolesLower)) {
                $userType = 'STAFF';
            } elseif (in_array('role_user', $rolesLower) || in_array('user', $rolesLower)) {
                $userType = 'USER';
            }
        }

        // Create log entry
        $log = new ActivityLog();
        $log->setAction($action);
        $log->setDescription($description);
        $log->setTargetData($targetData);
        $log->setCreatedAt(new \DateTimeImmutable());

        if ($request) {
            $log->setIpAddress($request->getClientIp());
        }

        // Save user info if valid User object
        if ($user instanceof User) {
            $log->setUser($user);
            $log->setUsername($user->getUserIdentifier());
            $log->setUserRoles(implode(', ', $user->getRoles()));
            $log->setUserType($userType);
        }

        // Save to DB
        $this->em->persist($log);
        $this->em->flush();
    }
}
