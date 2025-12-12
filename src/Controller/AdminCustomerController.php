<?php

namespace App\Controller;

use App\Repository\CustomerRepository; 
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AdminCustomerController extends AbstractController
{
    #[Route('/admin/customer', name: 'app_admin_customer')]
  // src/Controller/Admin/AdminCustomerController.php

public function index(CustomerRepository $customerRepository): Response
{
    // If you want all customers for the index page:
    $allCustomers = $customerRepository->findAll();
    
    // If you only want recent customers (you must define a method like findRecent() in your repository):
    // $recentCustomers = $customerRepository->findRecent(10); 
    
    return $this->render('admin/customer.html.twig', [
        'recentCustomers' => $allCustomers, 
    ]);
 }
}