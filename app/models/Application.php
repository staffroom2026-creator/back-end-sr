<?php
/**
 * Application Model
 * Handles database operations for job applications.
 */

class Application
{
    private $db;

    public function __construct()
    {
        $this->db = new Database();
    }

    /**
     * Check if teacher has already applied to a job
     *
     * @param int $jobId
     * @param int $teacherId
     * @return bool
     */
    public function hasApplied($jobId, $teacherId)
    {
        $this->db->query("SELECT application_id FROM applications WHERE job_id = :job_id AND teacher_id = :teacher_id");
        $this->db->bind(':job_id', $jobId);
        $this->db->bind(':teacher_id', $teacherId);
        $this->db->single();
        return $this->db->rowCount() > 0;
    }

    /**
     * Apply for a job
     *
     * @param int $jobId
     * @param int $teacherId
     * @param string $coverLetter
     * @param string|null $cvUrl
     * @param string|null $additionalInfo
     * @return int|false
     */
    public function apply($jobId, $teacherId, $coverLetter, $cvUrl = null, $additionalInfo = null)
    {
        $applicationId = hash('sha256', uniqid('', true) . microtime(true));
        $this->db->query("INSERT INTO applications (application_id, job_id, teacher_id, status, cover_letter, cv_url, additional_info) VALUES (:application_id, :job_id, :teacher_id, 'submitted', :cover_letter, :cv_url, :additional_info)");
        $this->db->bind(':application_id', $applicationId);
        $this->db->bind(':job_id', $jobId);
        $this->db->bind(':teacher_id', $teacherId);
        $this->db->bind(':cover_letter', trim($coverLetter));
        $this->db->bind(':cv_url', $cvUrl);
        $this->db->bind(':additional_info', $additionalInfo !== null ? trim($additionalInfo) : null);

        if ($this->db->execute()) {
            return $applicationId;
        }
        return false;
    }

    /**
     * Get applications submitted by a teacher
     *
     * @param int $teacherId
     * @return array
     */
    public function getMyApplications($teacherId, $page = 1, $perPage = 10)
    {
        $page = max(1, (int)$page);
        $perPage = min(50, max(1, (int)$perPage));
        $offset = ($page - 1) * $perPage;
        $this->db->query("SELECT COUNT(*) AS total FROM applications WHERE teacher_id = :teacher_id");
        $this->db->bind(':teacher_id', $teacherId);
        $total = (int)$this->db->single()->total;

        $sql = "SELECT applications.*, 
                       jobs.title AS job_title, 
                       jobs.role_type, 
                       jobs.employment_type, 
                       jobs.salary_range, 
                       jobs.location AS job_location, 
                       school_profiles.school_name, 
                       school_profiles.logo_url 
                FROM applications 
                JOIN jobs ON applications.job_id = jobs.job_id 
                JOIN school_profiles ON jobs.school_id = school_profiles.user_id 
                WHERE applications.teacher_id = :teacher_id 
                ORDER BY applications.created_at DESC
                LIMIT :limit OFFSET :offset";

        $this->db->query($sql);
        $this->db->bind(':teacher_id', $teacherId);
        $this->db->bind(':limit', $perPage, PDO::PARAM_INT);
        $this->db->bind(':offset', $offset, PDO::PARAM_INT);
        return ['data' => $this->db->resultSet(), 'pagination' => $this->pagination($page, $perPage, $total)];
    }

    /**
     * Get applications received for a specific job post
     *
     * @param int $jobId
     * @param int $schoolId
     * @param int $userId
     * @param bool $isAdmin
     * @return array|false
     */
    public function getJobApplications($jobId, $schoolId, $userId, $isAdmin = false, $page = 1, $perPage = 10)
    {
        if (!$isAdmin) {
            // Check if job belongs to this school (either by profile id or user id)
            $this->db->query("SELECT job_id FROM jobs WHERE job_id = :job_id AND (school_id = :school_id OR school_id = :user_id)");
            $this->db->bind(':job_id', $jobId);
            $this->db->bind(':school_id', $schoolId);
            $this->db->bind(':user_id', $userId);
            $this->db->single();
            if ($this->db->rowCount() === 0) {
                return false;
            }
        }

        $page = max(1, (int)$page);
        $perPage = min(50, max(1, (int)$perPage));
        $offset = ($page - 1) * $perPage;
        $this->db->query("SELECT COUNT(*) AS total FROM applications WHERE job_id = :job_id");
        $this->db->bind(':job_id', $jobId);
        $total = (int)$this->db->single()->total;

        $sql = "SELECT applications.*, 
                       initkey.full_name AS teacher_name, 
                       initkey.email AS teacher_email, 
                       initkey.phone AS teacher_phone, 
                       teacher_profiles.bio, 
                       teacher_profiles.skills, 
                       teacher_profiles.experience_years, 
                       teacher_profiles.qualification, 
                       teacher_profiles.cv_url,
                       teacher_profiles.location AS teacher_location 
                FROM applications 
                JOIN teacher_profiles ON applications.teacher_id = teacher_profiles.user_id 
                JOIN initkey ON teacher_profiles.user_id = initkey.user_id 
                WHERE applications.job_id = :job_id 
                ORDER BY applications.created_at DESC
                LIMIT :limit OFFSET :offset";

        $this->db->query($sql);
        $this->db->bind(':job_id', $jobId);
        $this->db->bind(':limit', $perPage, PDO::PARAM_INT);
        $this->db->bind(':offset', $offset, PDO::PARAM_INT);
        return ['data' => $this->db->resultSet(), 'pagination' => $this->pagination($page, $perPage, $total)];
    }

    /**
     * Get application by ID along with job and teacher info
     *
     * @param int $id
     * @return object|false
     */
    public function getById($id)
    {
        $sql = "SELECT applications.*, 
                       jobs.title AS job_title, 
                       jobs.school_id, 
                       school_profiles.user_id AS school_user_id,
                       teacher_profiles.user_id AS teacher_user_id,
                       initkey.full_name AS teacher_name
                FROM applications 
                JOIN jobs ON applications.job_id = jobs.job_id 
                JOIN school_profiles ON jobs.school_id = school_profiles.user_id 
                JOIN teacher_profiles ON applications.teacher_id = teacher_profiles.user_id 
                JOIN initkey ON teacher_profiles.user_id = initkey.user_id 
                WHERE applications.application_id = :id";

        $this->db->query($sql);
        $this->db->bind(':id', $id);
        $row = $this->db->single();
        return ($this->db->rowCount() > 0) ? $row : false;
    }

    /**
     * Update application status
     *
     * @param int $id
     * @param string $status
     * @param int $schoolId
     * @param int $userId
     * @param bool $isAdmin
     * @return object|int|false Returns $app object on success, -1 if unauthorized, false if not found/error
     */
    public function updateStatus($id, $status, $schoolId, $userId, $isAdmin = false, $rejectionMessage = null)
    {
        $app = $this->getById($id);
        if (!$app) return false;

        if (!$isAdmin) {
            $isOwner = ((string)$app->school_id === (string)$schoolId || 
                        (string)$app->school_id === (string)$userId || 
                        (string)$app->school_user_id === (string)$userId || 
                        (string)$app->school_user_id === (string)$schoolId);
            if (!$isOwner) {
                return -1;
            }
        }

        $this->db->query("UPDATE applications SET status = :status, rejection_message = :rejection_message WHERE application_id = :id");
        $this->db->bind(':status', trim($status));
        $this->db->bind(':rejection_message', trim((string)$rejectionMessage) ?: null);
        $this->db->bind(':id', $id);

        if ($this->db->execute()) {
            $app->status = trim($status);
            $app->rejection_message = trim((string)$rejectionMessage) ?: null;
            return $app;
        }
        return false;
    }

    private function pagination($page, $perPage, $total)
    {
        $lastPage = $total > 0 ? (int)ceil($total / $perPage) : 1;
        return [
            'current_page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'last_page' => $lastPage,
            'has_more' => $page < $lastPage
        ];
    }

    public function withdraw($id, $teacherUserId)
    {
        $this->db->query("UPDATE applications SET status = 'withdrawn' WHERE application_id = :id AND teacher_id = :teacher_id AND status NOT IN ('rejected', 'accepted', 'hired', 'withdrawn')");
        $this->db->bind(':id', $id);
        $this->db->bind(':teacher_id', $teacherUserId);
        return $this->db->execute() && $this->db->rowCount() > 0;
    }
}
?>
