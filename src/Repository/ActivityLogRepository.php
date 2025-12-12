<?php
// src/Repository/ActivityLogRepository.php

namespace App\Repository;

use App\Entity\ActivityLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ActivityLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ActivityLog::class);
    }

    public function findWithPagination(
        int $page = 1, 
        int $limit = 25, 
        string $search = '', 
        string $sort = 'created_desc'
    ): array {
        $offset = ($page - 1) * $limit;
        
        $queryBuilder = $this->createQueryBuilder('a')
            ->setFirstResult($offset)
            ->setMaxResults($limit);
        
        // Search functionality
        if ($search) {
            $queryBuilder
                ->where('a.action LIKE :search')
                ->orWhere('a.description LIKE :search')
                ->orWhere('a.username LIKE :search')
                ->orWhere('a.targetData LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        
        
        // Sorting functionality
        switch ($sort) {
            case 'created_asc':
                $queryBuilder->orderBy('a.createdAt', 'ASC');
                break;
            case 'action_asc':
                $queryBuilder->orderBy('a.action', 'ASC');
                break;
            case 'action_desc':
                $queryBuilder->orderBy('a.action', 'DESC');
                break;
            case 'user_asc':
                $queryBuilder->orderBy('a.username', 'ASC');
                break;
            case 'user_desc':
                $queryBuilder->orderBy('a.username', 'DESC');
                break;
            case 'created_desc':
            default:
                $queryBuilder->orderBy('a.createdAt', 'DESC');
                break;
        }
        
        return $queryBuilder->getQuery()->getResult();
    }

    // Add this to src/Repository/ActivityLogRepository.php

public function findRecentLogs(int $limit = 10): array
{
    return $this->createQueryBuilder('a')
        ->orderBy('a.createdAt', 'DESC')
        ->setMaxResults($limit)
        ->getQuery()
        ->getResult();
}
    
    public function countWithSearch(string $search = ''): int
    {
        $queryBuilder = $this->createQueryBuilder('a')
            ->select('COUNT(a.id)');
        
        if ($search) {
            $queryBuilder
                ->where('a.action LIKE :search')
                ->orWhere('a.description LIKE :search')
                ->orWhere('a.username LIKE :search')
                ->orWhere('a.targetData LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }
        
        return (int) $queryBuilder->getQuery()->getSingleScalarResult();
    }
    
    // Keep existing methods
    public function findAllOrderedByDate(int $maxResults = null): array
    {
        $qb = $this->createQueryBuilder('a')
            ->orderBy('a.createdAt', 'DESC');
        
        if ($maxResults) {
            $qb->setMaxResults($maxResults);
        }
        
        return $qb->getQuery()->getResult();
    }
}