<?php

namespace App\Controller;

use App\Entity\Car;
use App\Entity\Rental;
use App\Form\CarType;
use App\Form\RentalType;
use App\Repository\CarRepository;
use App\Repository\CarStatusRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Handles CRUD interactions and rental flow for the Car entity.
 *
 * Every route defined here is automatically prefixed with `/car`.
 */
#[Route('/car')]
final class CarController extends AbstractController
{
    /**
     * Display the full fleet so users can browse every car.
     */
    #[Route('/', name: 'app_car_index', methods: ['GET'])]
    public function index(CarRepository $carRepository): Response
    {
        return $this->render('car/index.html.twig', [
            'cars' => $carRepository->findAll(),
        ]);
    }

    /**
     * Create a new car record and optionally attach an uploaded image.
     */
    #[Route('/new', name: 'app_car_new', methods: ['GET','POST'])]  // �o. FIXED
    public function new(Request $request, EntityManagerInterface $em, CarStatusRepository $statusRepository): Response
    {
        $car = new Car();
        if (null !== ($defaultStatus = $statusRepository->findOneByCode('available'))) {
            $car->setStatus($defaultStatus);
        }
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

    /**
     * Show the complete details for a single car.
     */
    #[Route('/{id}', name: 'app_car_show', methods: ['GET'])]
    public function show(Car $car): Response
    {
        return $this->render('car/show.html.twig', [
            'car' => $car,
        ]);
    }

    /**
     * Update a car and replace the stored photo when a new file is provided.
     */
    #[Route('/{id}/edit', name: 'app_car_edit', methods: ['GET','POST'])]  // �o. FIXED
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

    /**
     * Remove a car once the CSRF check passes.
     */
    #[Route('/{id}', name: 'app_car_delete', methods: ['POST'])]
    public function delete(Request $request, Car $car, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$car->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($car);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_car_index');
    }

    /**
     * Collect rental information, persist it, and flip the car status.
     */
    #[Route('/{id}/rent', name: 'app_car_rent', methods: ['GET', 'POST'])]
    public function rent(Request $request, Car $car, EntityManagerInterface $entityManager, CarStatusRepository $statusRepository): Response
    {
        $rental = new Rental();
        $form = $this->createForm(RentalType::class, $rental);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $rental->setCar($car);
            if (null === ($rentedStatus = $statusRepository->findOneByCode('rented'))) {
                $this->addFlash('warning', 'Car status "rented" is not configured. Please update status definitions.');
            } else {
                $car->setStatus($rentedStatus);
            }

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
