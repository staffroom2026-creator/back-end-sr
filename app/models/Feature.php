<?php
/**
 * Feature Model (Bridge around SavedJob and Notification)
 */

require_once __DIR__ . '/SavedJob.php';
require_once __DIR__ . '/SavedTeacher.php';
require_once __DIR__ . '/Notification.php';
require_once __DIR__ . '/JobAlertPreference.php';
require_once __DIR__ . '/../helpers/MailHelper.php';
require_once __DIR__ . '/../helpers/NotificationEmailTemplate.php';

class Feature
{
    private $savedJobModel;
    private $savedTeacherModel;
    private $notificationModel;
    private $jobAlertModel;
    private $userModel;

    public function __construct()
    {
        $this->savedJobModel = new SavedJob();
        $this->savedTeacherModel = new SavedTeacher();
        $this->notificationModel = new Notification();
        $this->jobAlertModel = new JobAlertPreference();
        $this->userModel = new User();
    }

    public function saveJob($userId, $jobId)
    {
        return $this->savedJobModel->save($userId, $jobId);
    }

    public function getSavedJobs($userId)
    {
        return $this->savedJobModel->getByUser($userId);
    }

    public function deleteSavedJob($userId, $jobId)
    {
        return $this->savedJobModel->delete($userId, $jobId);
    }

    public function unsaveJob($userId, $jobId)
    {
        return $this->deleteSavedJob($userId, $jobId);
    }

    public function saveTeacher($schoolUserId, $teacherUserId)
    {
        return $this->savedTeacherModel->save($schoolUserId, $teacherUserId);
    }

    public function unsaveTeacher($schoolUserId, $teacherUserId)
    {
        return $this->savedTeacherModel->delete($schoolUserId, $teacherUserId);
    }

    public function getSavedTeachers($schoolUserId)
    {
        return $this->savedTeacherModel->getBySchool($schoolUserId);
    }

    public function getJobAlerts($teacherUserId)
    {
        return $this->jobAlertModel->get($teacherUserId);
    }

    public function saveJobAlerts($teacherUserId, $data)
    {
        return $this->jobAlertModel->upsert($teacherUserId, $data);
    }

    public function createNotification($userId, $title, $message, $type = 'general', $relatedId = null, $relatedType = null, $sendEmail = true)
    {
        $created = $this->notificationModel->create($userId, $title, $message, $type, $relatedId, $relatedType);
        if (!$created) {
            return false;
        }
        if (!$sendEmail) {
            return true;
        }

        $user = $this->userModel->findById($userId);
        if (!$user || empty($user->email)) {
            return true;
        }
        $preferences = json_decode($user->notification_preferences ?? '{}', true) ?: [];
        if (array_key_exists('email_notifications', $preferences) && !$preferences['email_notifications']) {
            return true;
        }

        $sent = MailHelper::send(
            $user->email,
            $title . ' - Staffroom',
            NotificationEmailTemplate::render($title, $message)
        );
        if (!$sent) {
            error_log('Notification email delivery failed for user ' . $userId);
        }
        return true;
    }

    public function getNotifications($userId, $page = 1, $perPage = 10)
    {
        return $this->notificationModel->getByUser($userId, $page, $perPage);
    }

    public function deleteNotification($id, $userId)
    {
        return $this->notificationModel->delete($id, $userId);
    }

    public function markNotificationAsRead($id, $userId)
    {
        return $this->notificationModel->markAsRead($id, $userId);
    }

    public function markNotificationRead($id, $userId)
    {
        return $this->markNotificationAsRead($id, $userId);
    }
}
?>
