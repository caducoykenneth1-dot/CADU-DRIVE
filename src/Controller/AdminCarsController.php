<?php

namespace App\Controller;

use App\Repository\CarRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class AdminCarsController extends AbstractController
{
    #[Route('/admin/cars', name: 'app_admin_cars')]
    public function index(CarRepository $carRepository): Response
    {
        $cars = $carRepository->findAll();

        return $this->render('admin/cars.html.twig', [
            'cars' => $cars,
        ]);
    }
}
