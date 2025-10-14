<?php

namespace App\Repository;

use App\Entity\Customer;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Customer>
 */
class CustomerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Customer::class);
    }

    /**
     * Simple search helper for listing tables.
     *
     * @return Customer[]
     */
    public function search(?string $term = null): array
    {
        $qb = $this->createQueryBuilder('c')
            ->orderBy('c.lastName', 'ASC')
            ->addOrderBy('c.firstName', 'ASC');

        if ($term) {
            $lower = function_exists('mb_strtolower') ? mb_strtolower($term) : strtolower($term);
            $likeTerm = '%' . $lower . '%';
            $qb->andWhere('LOWER(CONCAT(c.firstName, \' \', c.lastName)) LIKE :term OR LOWER(c.email) LIKE :term')
                ->setParameter('term', $likeTerm);
        }

        return $qb->getQuery()->getResult();
    }
}
