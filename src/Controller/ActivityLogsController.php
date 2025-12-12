<?php
// src/Controller/Admin/ActivityLogsController.php

namespace App\Controller;

use App\Entity\ActivityLog;
use App\Repository\ActivityLogRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/activity-logs')]
class ActivityLogsController extends AbstractController
{
   #[Route('/', name: 'admin_activity_logs_index', methods: ['GET'])]
public function index(ActivityLogRepository $activityLogRepository, Request $request): Response
{
    $page = $request->query->getInt('page', 1);
    $limit = $request->query->getInt('limit', 25);
    $search = $request->query->get('search', '');
    $sort = $request->query->get('sort', 'created_desc');
    
    // Get logs with sorting
    $logs = $activityLogRepository->findWithPagination(
        $page, 
        $limit, 
        $search, 
        $sort
    );
    
    $totalLogs = $activityLogRepository->countWithSearch($search);
    $totalPages = ceil($totalLogs / $limit);
    
    return $this->render('activity_logs/index.html.twig', [
        'logs' => $logs,
        'totalLogs' => $totalLogs,
        'currentPage' => $page,
        'totalPages' => $totalPages,
        'limit' => $limit,
        'search' => $search,
        'sort' => $sort,
    ]);
}

    #[Route('/{id}', name: 'admin_activity_logs_show', methods: ['GET'])]
    public function show(ActivityLog $activityLog): Response
    {
        return $this->render('activity_logs/show.html.twig', [
            'log' => $activityLog,
        ]);
    }

    #[Route('/user/{userId}', name: 'admin_activity_logs_by_user', methods: ['GET'])]
    public function byUser(int $userId, ActivityLogRepository $activityLogRepository): Response
    {
        $logs = $activityLogRepository->findByUser($userId);
        
        return $this->render('activity_logs/user_logs.html.twig', [
            'logs' => $logs,
            'userId' => $userId,
        ]);
    }
}