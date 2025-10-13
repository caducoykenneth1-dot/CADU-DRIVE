<?php

namespace App\Controller;

use App\Entity\Car;
use App\Entity\Rental;
use App\Form\CarType;
use App\Form\RentalType;
use App\Repository\CarRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/car')]
final class CarController extends AbstractController
{
    #[Route('/', name: 'app_car_index', methods: ['GET'])]
    public function index(CarRepository $carRepository): Response
    {
        return $this->render('car/index.html.twig', [
            'cars' => $carRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_car_new', methods: ['GET','POST'])]  // ✅ FIXED
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $car = new Car();
        $form = $this->createForm(CarType::class, $car);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $imageFile = $form->get('imageFile')->getData();

            if ($imageFile) {
                $newFilename = uniqid().'.'.$imageFile->guessExtension();
                $imageFile->move($this->getParameter('car_images_directory'), $newFilename);
                $car->setImage($newFilename);
            }

            $em->persist($car);
            $em->flush();

            return $this->redirectToRoute('app_car_index');
        }

        return $this->render('car/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_car_show', methods: ['GET'])]
    public function show(Car $car): Response
    {
        return $this->render('car/show.html.twig', [
            'car' => $car,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_car_edit', methods: ['GET','POST'])]  // ✅ FIXED
    public function edit(Request $request, Car $car, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(CarType::class, $car);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $imageFile = $form->get('imageFile')->getData();

            if ($imageFile) {
                if ($car->getImage()) {
                    @unlink($this->getParameter('car_images_directory') . '/' . $car->getImage());
                }

                $newFilename = uniqid().'.'.$imageFile->guessExtension();
                $imageFile->move($this->getParameter('car_images_directory'), $newFilename);
                $car->setImage($newFilename);
            }

            $em->flush();

            return $this->redirectToRoute('app_car_index');
        }

        return $this->render('car/edit.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_car_delete', methods: ['POST'])]
    public function delete(Request $request, Car $car, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$car->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($car);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_car_index');
    }

    #[Route('/{id}/rent', name: 'app_car_rent', methods: ['GET', 'POST'])]
    public function rent(Request $request, Car $car, EntityManagerInterface $entityManager): Response
    {
        $rental = new Rental();
        $form = $this->createForm(RentalType::class, $rental);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $rental->setCar($car);
            $car->setStatus('rented');

            $entityManager->persist($rental);
            $entityManager->flush();

            return $this->redirectToRoute('app_car_index');
        }

        return $this->render('car/rent.html.twig', [
            'car' => $car,
            'form' => $form,
        ]);
    }
}
