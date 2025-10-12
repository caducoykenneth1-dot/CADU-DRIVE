<?php

namespace App\Controller;

use App\Repository\CarRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function index(CarRepository $carRepository): Response
    {
        return $this->render('home/index.html.twig', [
            'cars' => $carRepository->findBy([], ['id' => 'DESC'], 3),
        ]);
    }

    #[Route('/contact', name: 'app_contact', methods: ['GET', 'POST'])]
    public function contact(Request $request): Response
    {
        if ($request->isMethod('POST') && $this->isCsrfTokenValid('contact-form', $request->request->get('_token'))) {
            $this->addFlash('success', 'Thank you for your message! We will get back to you soon.');

            return $this->redirectToRoute('app_contact');
        }

        return $this->render('home/contact.html.twig');
    }

    
}