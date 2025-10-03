<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SimpleCarController extends AbstractController
{
    #[Route('/simple-cars', name: 'simple_car_index')]
    public function index(): Response
    {
        return $this->render('car/simple_index.html.twig', [
            'controller_name' => 'SimpleCarController',
        ]);
    }
}