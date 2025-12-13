<?php

namespace App\Controller;

use App\Entity\Car;
use App\Form\CarType;
use App\Repository\CarRepository;
use App\Repository\CarStatusRepository;
use App\Service\ActivityLogger;
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
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }
        return $this->render('car/index.html.twig', [
            'cars' => $carRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_car_new', methods: ['GET','POST'])]
    public function new(
        Request $request, 
        EntityManagerInterface $em, 
        CarStatusRepository $statusRepository,
        ActivityLogger $activityLogger
    ): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
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

            // ✅ LOG: Car created - USING getLabel() for display
            $activityLogger->log(
                'CAR_CREATED',
                'Admin added new car to fleet',
                sprintf('ID: %d | %s %s | Year: %d | $%s/day | Status: %s',
                    $car->getId(),
                    $car->getMake(),
                    $car->getModel(),
                    $car->getYear(),
                    $car->getPrice(),
                    $car->getStatus()->getLabel()  // ← CHANGED: getLabel() not getName()
                )
            );

            $this->addFlash('success', 'Car added successfully!');
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

    #[Route('/{id}/edit', name: 'app_car_edit', methods: ['GET','POST'])]
    public function edit(
        Request $request, 
        Car $car, 
        EntityManagerInterface $em,
        ActivityLogger $activityLogger
    ): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        // Store old values for comparison
        $oldData = [
            'make' => $car->getMake(),
            'model' => $car->getModel(),
            'year' => $car->getYear(),
            'price' => $car->getPrice(),
            'status' => $car->getStatus()->getLabel()  // ← CHANGED: getLabel()
        ];
        
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

            // Get new values for comparison
            $newData = [
                'make' => $car->getMake(),
                'model' => $car->getModel(),
                'year' => $car->getYear(),
                'price' => $car->getPrice(),
                'status' => $car->getStatus()->getLabel()  // ← CHANGED: getLabel()
            ];

            // Detect changes
            $changes = [];
            foreach ($oldData as $key => $oldValue) {
                if ($oldData[$key] != $newData[$key]) {
                    $changes[] = sprintf('%s: "%s" → "%s"', 
                        ucfirst($key), $oldValue, $newData[$key]
                    );
                }
            }

            // ✅ LOG: Car updated
            $activityLogger->log(
                'CAR_UPDATED',
                'Admin updated car details',
                sprintf('ID: %d | Changes: %s',
                    $car->getId(),
                    empty($changes) ? 'No changes detected' : implode(', ', $changes)
                )
            );

            $this->addFlash('success', 'Car updated successfully!');
            return $this->redirectToRoute('app_admin_cars');
        }

        return $this->render('car/edit.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_car_delete', methods: ['POST'])]
    public function delete(
        Request $request, 
        Car $car, 
        EntityManagerInterface $entityManager,
        ActivityLogger $activityLogger
    ): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        if ($this->isCsrfTokenValid('delete'.$car->getId(), $request->getPayload()->getString('_token'))) {
            
            // Store car info before deletion
            $carInfo = sprintf('%s %s %d ($%s/day)', 
                $car->getMake(),
                $car->getModel(),
                $car->getYear(),
                $car->getPrice()
            );
            $carId = $car->getId();
            
            // Delete image file if exists
            if ($car->getImage()) {
                @unlink($this->getParameter('car_images_directory') . '/' . $car->getImage());
            }
            
            $entityManager->remove($car);
            $entityManager->flush();

            // ✅ LOG: Car deleted
            $activityLogger->log(
                'CAR_DELETED',
                'Admin removed car from fleet',
                sprintf('ID: %d | Car: %s', $carId, $carInfo)
            );

            $this->addFlash('success', 'Car deleted successfully!');
        }

        return $this->redirectToRoute('app_admin_cars');
    }

    #[Route('/{id}/toggle-status', name: 'app_car_toggle_status', methods: ['POST'])]
    public function toggleStatus(
        Request $request,
        Car $car,
        EntityManagerInterface $entityManager,
        CarStatusRepository $statusRepository,
        ActivityLogger $activityLogger
    ): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        if ($this->isCsrfTokenValid('toggle-status'.$car->getId(), $request->request->get('_token'))) {
            $oldStatus = $car->getStatus() ? $car->getStatus()->getLabel() : 'Unknown';
            $oldStatusCode = $car->getStatus() ? $car->getStatus()->getCode() : null;
            
            // Toggle between available and disabled
            if ($oldStatusCode === 'available') {
                $newStatus = $statusRepository->findOneByCode('disabled');
                $action = 'CAR_DISABLED';
                $message = 'Car disabled';
            } else {
                $newStatus = $statusRepository->findOneByCode('available');
                $action = 'CAR_ENABLED';
                $message = 'Car enabled';
            }
            
            // Check if new status was found
            if (!$newStatus) {
                $this->addFlash('error', 'Could not find the target status in the database. Please check CarStatus table.');
                return $this->redirectToRoute('app_admin_cars');
            }
            
            $car->setStatus($newStatus);
            $car->setUpdatedAt(new \DateTime());
            $entityManager->flush();
            
            // LOG: Car status changed
            $activityLogger->log(
                $this->getUser()->getEmail(),
                $action,
                sprintf('Changed status for car: %s %s', $car->getMake(), $car->getModel()),
                [
                    'car_id' => $car->getId(),
                    'make' => $car->getMake(),
                    'model' => $car->getModel(),
                    'year' => $car->getYear(),
                    'old_status' => $oldStatus,
                    'new_status' => $car->getStatus()->getLabel()
                ],
                'ADMIN'
            );
            
            $this->addFlash('success', $message);
        }
        
        return $this->redirectToRoute('app_admin_cars');
    }
}