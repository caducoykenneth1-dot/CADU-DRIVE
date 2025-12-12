<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Authorization\AccessDeniedHandlerInterface;
use Symfony\Component\Routing\RouterInterface;

class AccessDeniedHandler implements AccessDeniedHandlerInterface
{
    private $router;

    public function __construct(RouterInterface $router)
    {
        $this->router = $router;
    }

    public function handle(Request $request, AccessDeniedException $accessDeniedException): ?Response
    {
        // Debug log
        error_log("AccessDeniedHandler triggered for: " . $request->getPathInfo());
        
        $path = $request->getPathInfo();
        
        // ✅ FIX: For admin paths, redirect to UNAUTHORIZED PAGE, not staff dashboard
        if (strpos($path, '/admin') === 0) {
            // Extract section name from URL for better error message
            $section = $this->extractSectionFromPath($path);
            
            error_log("Staff accessing admin section: " . $section);
            
            // Redirect to unauthorized section page
            return new RedirectResponse($this->router->generate('app_unauthorized_section', [
                'section' => $section
            ]));
        }
        
        // Default unauthorized page for other access denied cases
        return new RedirectResponse($this->router->generate('app_unauthorized'));
    }
    
    private function extractSectionFromPath(string $path): string
    {
        // Remove /admin/ prefix
        $pathWithoutAdmin = substr($path, 7); // 7 = strlen('/admin/')
        
        // Get the first segment after /admin/
        $segments = explode('/', $pathWithoutAdmin);
        
        if (!empty($segments[0])) {
            $section = $segments[0];
            
            // Map URLs to friendly section names
            $sectionMap = [
                'dashboard' => 'dashboard',
                'staff' => 'staff_management',
                'customers' => 'customer_management',
                'cars' => 'car_management',
                'users' => 'user_management',
                'settings' => 'settings',
            ];
            
            return $sectionMap[$section] ?? $section;
        }
        
        return 'admin';
    }
}