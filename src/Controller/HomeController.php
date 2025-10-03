<?php

namespace App\Controller;

use App\Repository\CarRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(CarRepository $carRepository): Response
    {
        return $this->render('home/index.html.twig', [
            'cars' => $carRepository->findBy([], ['id' => 'DESC'], 3),
        ]);
    }
}