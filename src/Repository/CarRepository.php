<?php

namespace App\Repository;

use App\Entity\Car;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Car>
 */
class CarRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Car::class);
    }

    /**
     * Return the most recent cars limited by $limit and filtered by optional type/price constraints.
     *
     * @param string|null $makeFilter  Case-insensitive match on the car's make/build.
     * @param array|null  $priceFilter Expecting keys min/max (max may be null for open range).
     * @param int         $limit       Number of cars to return.
     *
     * @return Car[]
     */
    public function findFeaturedCars(?string $makeFilter, ?array $priceFilter, int $limit = 3): array
    {
        $qb = $this->createQueryBuilder('c')
            ->orderBy('c.id', 'DESC')
            ->setMaxResults($limit);

        if ($makeFilter) {
            $normalizedMake = function_exists('mb_strtolower')
                ? mb_strtolower($makeFilter)
                : strtolower($makeFilter);

            $qb->andWhere('LOWER(c.make) = :make')
                ->setParameter('make', $normalizedMake);
        }

        if ($priceFilter) {
            if (array_key_exists('min', $priceFilter) && null !== $priceFilter['min']) {
                $qb->andWhere('c.price >= :minPrice')
                    ->setParameter('minPrice', $priceFilter['min']);
            }

            if (array_key_exists('max', $priceFilter) && null !== $priceFilter['max']) {
                $qb->andWhere('c.price <= :maxPrice')
                    ->setParameter('maxPrice', $priceFilter['max']);
            }
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Fetch a sorted list of distinct car makes (used as "types" in the UI).
     *
     * @return string[]
     */
    public function getDistinctMakes(): array
    {
        $rows = $this->createQueryBuilder('c')
            ->select('DISTINCT c.make AS make')
            ->where('c.make IS NOT NULL')
            ->orderBy('c.make', 'ASC')
            ->getQuery()
            ->getScalarResult();

        return array_values(array_filter(array_map(static fn(array $row) => $row['make'], $rows)));
    }

    /**
     * Determine the minimum and maximum recorded price.
     *
     * @return array{min: int|null, max: int|null}
     */
    public function getPriceBounds(): array
    {
        $result = $this->createQueryBuilder('c')
            ->select('MIN(c.price) AS minPrice', 'MAX(c.price) AS maxPrice')
            ->getQuery()
            ->getOneOrNullResult();

        return [
            'min' => isset($result['minPrice']) ? (int) $result['minPrice'] : null,
            'max' => isset($result['maxPrice']) ? (int) $result['maxPrice'] : null,
        ];
    }
}
