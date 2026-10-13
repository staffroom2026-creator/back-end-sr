<?php
/**
 * Features Controller
 * Handles Saved Jobs & Notifications endpoints
 */

class Features extends Controller
{
    private $featureModel;

    public function __construct()
    {
        $this->featureModel = $this->model('Feature');
    }

    /**
     * Handle POST /api/features/saved-jobs/:jobId, GET /api/features/saved-jobs, DELETE /api/features/saved-jobs/:jobId
     */
    public function saved_jobs($jobId = null)
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $user = AuthMiddleware::authenticate();

        if ($method === 'POST') {
            if ($jobId === null) {
                $data = $this->getData();
                $jobId = $data['job_id'] ?? ($_GET['job_id'] ?? null);
            }
            if ($jobId === null) {
                ResponseHelper::error('Job ID is required to save a job', 400);
            }

            $result = $this->featureModel->saveJob($user->user_id, $jobId);
            if ($result === 'already_saved') {
                ResponseHelper::success('Job is already saved', ['job_id' => (string)$jobId], 200, ['saved_job' => ['job_id' => (string)$jobId]]);
            } elseif (!$result) {
                ResponseHelper::error('Job not found or is no longer available', 404);
            }

            ResponseHelper::success('Job saved successfully', ['job_id' => (string)$jobId], 201, ['saved_job' => ['job_id' => (string)$jobId]]);

        } elseif ($method === 'GET') {
            $savedJobs = $this->featureModel->getSavedJobs($user->user_id);
            ResponseHelper::success('Saved jobs fetched successfully', $savedJobs, 200, ['saved_jobs' => $savedJobs]);

        } elseif ($method === 'DELETE') {
            if ($jobId === null) {
                ResponseHelper::error('Job ID is required to remove saved job', 400);
            }
            $deleted = $this->featureModel->unsaveJob($user->user_id, $jobId);
            if (!$deleted) {
                ResponseHelper::error('Saved job not found or already removed', 404);
            }
            ResponseHelper::success('Job removed from saved list', ['job_id' => (string)$jobId], 200, ['saved_job' => ['job_id' => (string)$jobId]]);

        } else {
            ResponseHelper::error('Method Not Allowed', 405);
        }
    }

    public function saved_teachers($teacherId = null)
    {
        $user = RoleMiddleware::allow('school');
        $method = $_SERVER['REQUEST_METHOD'];
        if ($method === 'GET') {
            $teachers = $this->featureModel->getSavedTeachers($user->user_id);
            ResponseHelper::success('Saved teachers fetched successfully', $teachers, 200, ['saved_teachers' => $teachers]);
        }
        if ($teacherId === null || $teacherId === '') {
            ResponseHelper::error('Teacher ID is required', 400);
        }
        if ($method === 'POST') {
            $result = $this->featureModel->saveTeacher($user->user_id, $teacherId);
            if ($result === 'already_saved') {
                ResponseHelper::success('Teacher is already saved', ['teacher_id' => $teacherId], 200);
            }
            if (!$result) {
                ResponseHelper::error('Teacher not found or could not be saved', 404);
            }
            ResponseHelper::success('Teacher saved successfully', ['teacher_id' => $teacherId], 201);
        }
        if ($method === 'DELETE') {
            if (!$this->featureModel->unsaveTeacher($user->user_id, $teacherId)) {
                ResponseHelper::error('Saved teacher not found or already removed', 404);
            }
            ResponseHelper::success('Teacher removed from saved list', ['teacher_id' => $teacherId], 200);
        }
        ResponseHelper::error('Method Not Allowed', 405);
    }

    public function job_alerts()
    {
        $user = RoleMiddleware::allow('teacher');
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $preferences = $this->featureModel->getJobAlerts($user->user_id);
            ResponseHelper::success('Job alert preferences fetched successfully', $preferences, 200, ['preferences' => $preferences]);
        }
        if ($_SERVER['REQUEST_METHOD'] === 'PUT' || $_SERVER['REQUEST_METHOD'] === 'PATCH') {
            $data = $this->getData();
            if (isset($data['subjects']) && !is_array($data['subjects'])) {
                ResponseHelper::error('subjects must be an array', 400, ['subjects' => 'Expected an array of subject names']);
            }
            $preferences = $this->featureModel->saveJobAlerts($user->user_id, $data);
            if (!$preferences) ResponseHelper::error('Failed to save job alert preferences', 500);
            ResponseHelper::success('Job alert preferences saved successfully', $preferences, 200, ['preferences' => $preferences]);
        }
        ResponseHelper::error('Method Not Allowed', 405);
    }

    /**
     * Handle GET /api/features/notifications and PATCH /api/features/notifications/:id/read
     */
    public function notifications($id = null, $subaction = null)
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $user = AuthMiddleware::authenticate();

        if ($method === 'GET' && ($id === null || $id === '')) {
            $notifications = $this->featureModel->getNotifications($user->user_id, $_GET['page'] ?? 1, $_GET['per_page'] ?? 10);
            ResponseHelper::success('Notifications fetched successfully', $notifications['data'], 200, [
                'notifications' => $notifications['data'],
                'pagination' => $notifications['pagination'],
                'unread_count' => $notifications['unread_count']
            ]);

        } elseif (($method === 'PATCH' || $method === 'PUT' || $method === 'POST') && ($id !== null && $id !== '')) {
            $notifId = ($id !== null && $id !== '') ? $id : $subaction;
            if (!$notifId && isset($_GET['url']) && preg_match('/notifications\/([^\/]+)\/read/', $_GET['url'], $m)) {
                $notifId = $m[1];
            }

            if (!$notifId) {
                ResponseHelper::error('Notification ID is required', 400);
            }

            $marked = $this->featureModel->markNotificationRead($notifId, $user->user_id);
            if ($marked) {
                ResponseHelper::success('Notification marked as read', ['id' => (int)$notifId, 'is_read' => 1], 200, ['notification' => ['id' => (int)$notifId, 'is_read' => 1]]);
            } else {
                ResponseHelper::error('Notification not found or access denied', 404);
            }

        } elseif ($method === 'DELETE' && ($id !== null && $id !== '')) {
            if (!$this->featureModel->deleteNotification($id, $user->user_id)) {
                ResponseHelper::error('Notification not found or access denied', 404);
            }
            ResponseHelper::success('Notification deleted successfully', ['notification_id' => $id], 200);

        } else {
            ResponseHelper::error('Endpoint not found or method not allowed', 405);
        }
    }
}
?>
