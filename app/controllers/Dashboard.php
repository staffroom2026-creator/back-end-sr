<?php
/**
 * Dashboard Controller
 * Handles authenticated role-specific home screen summaries.
 */

class Dashboard extends Controller
{
    private $dashboardModel;

    public function __construct()
    {
        $this->dashboardModel = $this->model('DashboardModel');
    }

    public function index()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            ResponseHelper::error('Method Not Allowed', 405);
        }

        $user = AuthMiddleware::authenticate();
        if ($user->role === 'school') {
            $stats = $this->dashboardModel->school($user->user_id);
        } elseif ($user->role === 'teacher') {
            $stats = $this->dashboardModel->teacher($user->user_id);
        } else {
            $stats = [];
        }

        ResponseHelper::success('Dashboard fetched successfully', $stats, 200, ['stats' => $stats]);
    }
}
?>
