<?php

namespace App\Controller;

use App\Repository\CarRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
/*Renders the pages such as home, contact, and about */
class HomeController extends AbstractController
{
    /* Show the landing page with dynamic featured-car filtersb */
    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function index(Request $request, CarRepository $carRepository): Response
    {
        $selectedType = $request->query->get('car_type') ?: null;
        $selectedPriceRangeKey = $request->query->get('price_range') ?: null;

        $selectedType = $selectedType !== null ? trim($selectedType) : null;
        $selectedPriceRangeKey = $selectedPriceRangeKey !== null ? trim($selectedPriceRangeKey) : null;

        if ($selectedType === '') {
            $selectedType = null;
        }

        if ($selectedPriceRangeKey === '') {
            $selectedPriceRangeKey = null;
        }

        $carTypes = $carRepository->getDistinctMakes();
        if ($selectedType && !in_array($selectedType, $carTypes, true)) {
            $carTypes[] = $selectedType;
            sort($carTypes, SORT_STRING | SORT_FLAG_CASE);
        }

        $bounds = $carRepository->getPriceBounds();
        $priceRanges = $this->buildPriceRanges($bounds['min'], $bounds['max']);
        $activePriceFilter = $selectedPriceRangeKey && isset($priceRanges[$selectedPriceRangeKey])
            ? $priceRanges[$selectedPriceRangeKey]
            : null;

        $cars = $carRepository->findFeaturedCars(
            $selectedType ?: null,
            $activePriceFilter,
            4
        );

        return $this->render('home/index.html.twig', [
            'cars' => $cars,
            'carTypes' => $carTypes,
            'priceRanges' => $priceRanges,
            'selectedType' => $selectedType,
            'selectedPriceRange' => $selectedPriceRangeKey,
        ]);
    // Check if user is admin/staff and redirect to admin dashboard
    if ($this->getUser()) {
        if ($this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_STAFF')) {
            return $this->redirectToRoute('app_admin_dashboard');
        }
    }
    return $this->render('home/index.html.twig');
}

    /* Render the contact form and process submissions with a flash message */
    #[Route('/contact', name: 'app_contact', methods: ['GET', 'POST'])]
    public function contact(Request $request): Response
    {
        if ($request->isMethod('POST') && $this->isCsrfTokenValid('contact-form', $request->request->get('_token'))) {
            $this->addFlash('success', 'Thank you for your message! We will get back to you soon.');

            return $this->redirectToRoute('app_contact');
        }

        return $this->render('home/contact.html.twig');
    }

    /* Provide the static "About" page */
    #[Route('/about', name: 'app_about', methods: ['GET'])]
    public function about(): Response
    {
        return $this->render('home/about.html.twig');
    }

    /*Build price buckets tailored to the current dataset. */
    private function buildPriceRanges(?int $minPrice, ?int $maxPrice): array
    {
        if ($minPrice === null || $maxPrice === null) {
            return [];
        }

        $currency = "\u{20B1}"; // Philippine Peso symbol

        if ($minPrice === $maxPrice) {
            return [
                'single' => [
                    'label' => sprintf('%s%s', $currency, number_format($minPrice)),
                    'min' => $minPrice,
                    'max' => $maxPrice,
                ],
            ];
        } // if both min and max are equal, just show one range

        $diff = $maxPrice - $minPrice;
        $firstMax = $minPrice + (int) floor($diff / 3);
        $secondMax = $minPrice + (int) floor(($diff * 2) / 3); //calulates my Price range 

        $formatLabel = static function (int $min, ?int $max) use ($currency): string {
            if ($max === null) {
                return sprintf('%s%s+', $currency, number_format($min));
            } 

            if ($min === $max) {
                return sprintf('%s%s', $currency, number_format($min));
            }

            return sprintf('%s%s - %s%s', $currency, number_format($min), $currency, number_format($max));
        };

        $ranges = [];

        $lowMax = min($maxPrice, $firstMax);
        $ranges['low'] = [
            'label' => $formatLabel($minPrice, $lowMax),
            'min' => $minPrice,
            'max' => $lowMax,
        ];

        $currentMax = $lowMax;

        if ($currentMax < $maxPrice) {
            $midMin = $currentMax + 1;
            $midMax = min($maxPrice, max($midMin, $secondMax));

            if ($midMin <= $midMax) {
                $ranges['mid'] = [
                    'label' => $formatLabel($midMin, $midMax),
                    'min' => $midMin,
                    'max' => $midMax,
                ];
                $currentMax = $midMax;
            }

            $highMin = $currentMax + 1;
            if ($highMin <= $maxPrice) {
                $ranges['high'] = [
                    'label' => $formatLabel($highMin, null),
                    'min' => $highMin,
                    'max' => null,
                ];
            }
        }

        return $ranges;
    }
}
