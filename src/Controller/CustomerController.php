<?php

namespace App\Controller;

use App\Entity\Customer;
use App\Entity\User;
use App\Form\CustomerType;
use App\Repository\CustomerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/* CRUD endpoints for customer profiles */
#[Route('/customer')]
class CustomerController extends AbstractController
{
    #[Route('/', name: 'app_customer_index', methods: ['GET'])]
    public function index(Request $request, CustomerRepository $customerRepository): Response
    {
        $term = $request->query->get('search');

        return $this->render('customer/index.html.twig', [
            'customers' => $customerRepository->search($term),
            'searchTerm' => $term,
        ]);
    }

     /* Register a new customer profile */
    #[Route('/register', name: 'app_customer_register', methods: ['GET', 'POST'])]
    public function register(
        Request $request,
        EntityManagerInterface $entityManager
    ): Response
    {
        $customer = new Customer();
        $form = $this->createForm(CustomerType::class, $customer);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = new User();
            $user->setEmail((string) $customer->getEmail());
            $user->setFirstName($customer->getFirstName());
            $user->setLastName($customer->getLastName());
            $user->setPhone($customer->getPhone());

            $user->setCustomer($customer);

            $entityManager->persist($user);
            $entityManager->persist($customer);
            $entityManager->flush();

            $this->addFlash('success', 'Thanks! Your customer profile has been created.');

            return $this->redirectToRoute('app_customer_register');
        }

        return $this->render('customer/register.html.twig', [
            'form' => $form,
        ]);
    }
          /*Showing Customer Profile */
    #[Route('/{id}', name: 'app_customer_show', methods: ['GET'])]
    public function show(Customer $customer): Response
    {
        return $this->render('customer/show.html.twig', [
            'customer' => $customer,
        ]);
    }
  /*Editing Customer Profile*/
    #[Route('/{id}/edit', name: 'app_customer_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Customer $customer, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(CustomerType::class, $customer);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $customer->setUpdatedAt(new \DateTimeImmutable());
            $entityManager->flush();

            $this->addFlash('success', 'Customer profile updated.');

            return $this->redirectToRoute('app_customer_index');
        }

        return $this->render('customer/edit.html.twig', [
            'customer' => $customer,
            'form' => $form,
        ]);
    }
          /*Deleting Customer */
    #[Route('/{id}', name: 'app_customer_delete', methods: ['POST'])]
    public function delete(Request $request, Customer $customer, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$customer->getId(), $request->request->get('_token'))) {
            $entityManager->remove($customer);
            $entityManager->flush();

            $this->addFlash('success', 'Customer profile removed.');
        }

        return $this->redirectToRoute('app_customer_index');
    }
}

