<?php

namespace App\Repository;

use App\Entity\CarStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CarStatus>
 */
class CarStatusRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CarStatus::class);
    }

    public function findOneByCode(string $code): ?CarStatus
    {
        return $this->createQueryBuilder('status')
            ->andWhere('status.code = :code')
            ->setParameter('code', $code)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
