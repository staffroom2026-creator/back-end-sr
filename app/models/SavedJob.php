<?php
/**
 * SavedJob Model
 */

class SavedJob
{
    private $db;

    public function __construct()
    {
        $this->db = new Database();
    }

    public function save($userId, $jobId)
    {
        // First check if job exists and is active/not deleted
        $this->db->query("SELECT job_id FROM jobs WHERE job_id = :job_id AND status = 'open'");
        $this->db->bind(':job_id', $jobId);
        if (!$this->db->single()) {
            return false;
        }

        // Check if already saved
        $this->db->query("SELECT saved_job_id FROM saved_jobs WHERE user_id = :user_id AND job_id = :job_id");
        $this->db->bind(':user_id', $userId);
        $this->db->bind(':job_id', $jobId);
        if ($this->db->single()) {
            return 'already_saved';
        }

        $savedJobId = hash('sha256', uniqid('', true) . microtime(true));
        $this->db->query("INSERT INTO saved_jobs (saved_job_id, user_id, job_id) VALUES (:saved_job_id, :user_id, :job_id)");
        $this->db->bind(':saved_job_id', $savedJobId);
        $this->db->bind(':user_id', $userId);
        $this->db->bind(':job_id', $jobId);
        return $this->db->execute();
    }

    public function getByUser($userId)
    {
        $sql = "SELECT saved_jobs.saved_job_id AS saved_job_id,
                       saved_jobs.job_id,
                       saved_jobs.user_id,
                       saved_jobs.created_at AS saved_at,
                       jobs.title, 
                       jobs.description, 
                       jobs.role_type,
                       jobs.salary_range, 
                       jobs.location, 
                       jobs.employment_type, 
                       school_profiles.school_name, 
                       school_profiles.logo_url,
                       school_profiles.address AS school_address,
                       school_profiles.state AS school_state
                FROM saved_jobs 
                JOIN jobs ON saved_jobs.job_id = jobs.job_id 
                JOIN school_profiles ON jobs.school_id = school_profiles.user_id 
                WHERE saved_jobs.user_id = :user_id 
                  AND jobs.status = 'open'
                ORDER BY saved_jobs.created_at DESC";

        $this->db->query($sql);
        $this->db->bind(':user_id', $userId);
        return $this->db->resultSet();
    }

    public function delete($userId, $jobId)
    {
        // Verify if it exists first
        $this->db->query("SELECT saved_job_id FROM saved_jobs WHERE user_id = :user_id AND job_id = :job_id");
        $this->db->bind(':user_id', $userId);
        $this->db->bind(':job_id', $jobId);
        if (!$this->db->single()) {
            return false;
        }

        $this->db->query("DELETE FROM saved_jobs WHERE user_id = :user_id AND job_id = :job_id");
        $this->db->bind(':user_id', $userId);
        $this->db->bind(':job_id', $jobId);
        return $this->db->execute();
    }

    public function unsaveJob($userId, $jobId)
    {
        return $this->delete($userId, $jobId);
    }
}
?>
