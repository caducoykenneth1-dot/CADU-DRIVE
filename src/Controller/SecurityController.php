<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    #[Route(path: '/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils, Request $request): Response
    {
        if ($this->getUser()) {
            return $this->redirectBasedOnRole();
        }

        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();
        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername, 
            'error' => $error,
            'target_path' => $request->query->get('target_path')
        ]);
    }

    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    /**
     * Redirect user based on their role after login
     */
    private function redirectBasedOnRole(): RedirectResponse
    {
        $user = $this->getUser();
        
        if (!$user) {
            return $this->redirectToRoute('app_home');
        }

        // Check for admin role first
        if (in_array('ROLE_ADMIN', $user->getRoles())) {
            return $this->redirectToRoute('app_admin_dashboard');
        }
        
        // Check for staff role
        if (in_array('ROLE_STAFF', $user->getRoles())) {
            return $this->redirectToRoute('app_staff_dashboard');
        }
        
        // Default redirect for regular users
        return $this->redirectToRoute('app_home');
    }

    /**
     * Route for showing unauthorized access page
     */
    #[Route(path: '/unauthorized', name: 'app_unauthorized')]
    public function unauthorized(): Response
    {
        $user = $this->getUser();
        $userRole = 'Guest';
        
        if ($user) {
            if (in_array('ROLE_ADMIN', $user->getRoles())) {
                $userRole = 'Administrator';
            } elseif (in_array('ROLE_STAFF', $user->getRoles())) {
                $userRole = 'Staff Member';
            } elseif (in_array('ROLE_USER', $user->getRoles())) {
                $userRole = 'User';
            }
        }

        return $this->render('security/unauthorized.html.twig', [
            'userRole' => $userRole
        ]);
    }

    /**
     * Route for showing access denied for specific admin sections
     */
    #[Route(path: '/unauthorized/{section}', name: 'app_unauthorized_section')]
    public function unauthorizedSection(string $section = null): Response
    {
        $user = $this->getUser();
        $userRole = 'Guest';
        
        if ($user) {
            if (in_array('ROLE_ADMIN', $user->getRoles())) {
                $userRole = 'Administrator';
            } elseif (in_array('ROLE_STAFF', $user->getRoles())) {
                $userRole = 'Staff Member';
            } elseif (in_array('ROLE_USER', $user->getRoles())) {
                $userRole = 'User';
            }
        }

        // Map section names to friendly names
        $sectionNames = [
            'customers' => 'Customer Management',
            'dashboard' => 'Admin Dashboard',
            'settings' => 'System Settings',
            'users' => 'User Management',
            'admin' => 'Admin Section',
        ];

        return $this->render('security/unauthorized.html.twig', [
            'userRole' => $userRole,
            'section' => $sectionNames[$section] ?? $section ?? 'Admin Section',
            'requiredRole' => 'Administrator',
            'isStaff' => in_array('ROLE_STAFF', $user ? $user->getRoles() : [])
        ]);
    }
}