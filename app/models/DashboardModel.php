<?php
/**
 * Dashboard Model
 * Provides role-specific summary data for authenticated home screens.
 */

class DashboardModel
{
    private $db;

    public function __construct()
    {
        $this->db = new Database();
    }

    public function school($userId)
    {
        $stats = [];
        $this->db->query("SELECT COUNT(*) AS count FROM jobs WHERE school_id = :user_id AND status = 'open'");
        $this->db->bind(':user_id', $userId);
        $stats['active_jobs'] = (int)$this->db->single()->count;

        $this->db->query("SELECT COUNT(*) AS count FROM applications
                          JOIN jobs ON applications.job_id = jobs.job_id
                          WHERE jobs.school_id = :user_id");
        $this->db->bind(':user_id', $userId);
        $stats['total_applicants'] = (int)$this->db->single()->count;

        $this->db->query("SELECT COUNT(*) AS count FROM applications
                          JOIN jobs ON applications.job_id = jobs.job_id
                          WHERE jobs.school_id = :user_id AND applications.status = 'shortlisted'");
        $this->db->bind(':user_id', $userId);
        $stats['shortlisted'] = (int)$this->db->single()->count;

        $this->db->query("SELECT COUNT(*) AS count FROM applications
                  JOIN jobs ON applications.job_id = jobs.job_id
                  WHERE jobs.school_id = :user_id AND applications.status = 'rejected'");
        $this->db->bind(':user_id', $userId);
        $stats['rejected_applications'] = (int)$this->db->single()->count;

        $this->db->query("SELECT COUNT(*) AS count FROM application_interviews WHERE school_user_id = :user_id AND status = 'scheduled'");
        $this->db->bind(':user_id', $userId);
        $stats['interviews'] = (int)$this->db->single()->count;

        $this->db->query("SELECT COUNT(*) AS count FROM jobs WHERE school_id = :user_id AND status = 'draft'");
        $this->db->bind(':user_id', $userId);
        $stats['draft_jobs'] = (int)$this->db->single()->count;

        return $stats;
    }

    public function teacher($userId)
    {
        $this->db->query("SELECT COUNT(*) AS count FROM applications WHERE teacher_id = :user_id");
        $this->db->bind(':user_id', $userId);
        $applications = (int)$this->db->single()->count;

        $this->db->query("SELECT COUNT(*) AS count FROM applications WHERE teacher_id = :user_id AND status = 'shortlisted'");
        $this->db->bind(':user_id', $userId);
        $shortlisted = (int)$this->db->single()->count;

        $this->db->query("SELECT COUNT(*) AS count FROM saved_jobs WHERE user_id = :user_id");
        $this->db->bind(':user_id', $userId);
        $savedJobs = (int)$this->db->single()->count;

        return [
            'applications' => $applications,
            'shortlisted' => $shortlisted,
            'saved_jobs' => $savedJobs
        ];
    }
}
?>
