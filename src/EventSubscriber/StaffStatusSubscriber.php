<?php

namespace App\EventSubscriber;

use App\Entity\Staff;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

class StaffLoginSubscriber implements EventSubscriberInterface
{
    private $security;
    private $urlGenerator;

    public function __construct(Security $security, UrlGeneratorInterface $urlGenerator)
    {
        $this->security = $security;
        $this->urlGenerator = $urlGenerator;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // Check during authentication
            CheckPassportEvent::class => ['onCheckPassport', -10],
            
            // Check on every request (for already logged-in users)
            KernelEvents::REQUEST => ['onKernelRequest', 10],
        ];
    }

    /**
     * Check staff status during authentication (before login completes)
     */
    public function onCheckPassport(CheckPassportEvent $event): void
    {
        $passport = $event->getPassport();
        $user = $passport->getUser();
        
        // Check if user has a staff account
        $staff = $user->getStaff();
        if ($staff instanceof Staff) {
            // If staff account is not active or disabled/archived
            if (!$staff->isAccountActive() || $staff->getStatus() !== 'active') {
                $status = $staff->getStatus();
                $message = match($status) {
                    'disabled' => 'Your account has been disabled. Please contact administrator.',
                    'archived' => 'Your account has been archived. Please contact administrator.',
                    default => 'Your account is not active. Please contact administrator.'
                };
                
                throw new CustomUserMessageAuthenticationException($message);
            }
        }
    }

    /**
     * Check staff status on every request (for already logged-in users)
     */
    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        // Skip check for logout route
        $currentRoute = $event->getRequest()->attributes->get('_route');
        if ($currentRoute === 'app_logout') {
            return;
        }

        $user = $this->security->getUser();
        
        if (!$user) {
            return;
        }

        // Check if user has a staff account
        $staff = $user->getStaff();
        if ($staff instanceof Staff) {
            // If staff account is not active or disabled/archived
            if (!$staff->isAccountActive() || $staff->getStatus() !== 'active') {
                // Force logout
                $session = $event->getRequest()->getSession();
                $session->getFlashBag()->add('error', 'Your account has been ' . $staff->getStatus() . '.');
                
                $event->setResponse(new RedirectResponse(
                    $this->urlGenerator->generate('app_logout')
                ));
            }
        }
    }
}