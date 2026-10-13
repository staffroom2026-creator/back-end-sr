<?php
/**
 * Job Model
 * Handles database operations for jobs (list, get by ID, create, update, delete/soft-delete).
 */

class Job
{
    private $db;

    public function __construct()
    {
        $this->db = new Database();
    }

    private function encodeResponsibilities($value)
    {
        if (is_array($value)) {
            $items = $value;
        } elseif ($value === null || $value === '') {
            return null;
        } else {
            $items = [(string)$value];
        }

        $items = array_values(array_filter(array_map(function ($item) {
            return is_scalar($item) ? trim((string)$item) : '';
        }, $items), function ($item) {
            return $item !== '';
        }));

        return $items ? json_encode($items, JSON_UNESCAPED_UNICODE) : null;
    }

    private function decodeResponsibilities($value)
    {
        if ($value === null || $value === '') {
            return [];
        }

        $decoded = json_decode((string)$value, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return array_values(array_filter(array_map(function ($item) {
                return is_scalar($item) ? trim((string)$item) : '';
            }, $decoded), function ($item) {
                return $item !== '';
            }));
        }

        return array_values(array_filter(array_map('trim', explode(',', (string)$value)), function ($item) {
            return $item !== '';
        }));
    }

    private function normalizeJob($job)
    {
        if ($job && property_exists($job, 'responsibilities')) {
            $job->responsibilities = $this->decodeResponsibilities($job->responsibilities);
        }
        if ($job && property_exists($job, 'requirements')) {
            $job->requirements = $this->decodeResponsibilities($job->requirements);
        }
        if ($job && property_exists($job, 'required_qualification')) {
            $job->required_qualification = $this->decodeResponsibilities($job->required_qualification);
        }
        return $job;
    }

    /**
     * Get all jobs with optional filtering by subject and state.
     * Excludes soft-deleted jobs.
     *
    * @param array $filters Optional school_id restricts results to one school.
     * @return array
     */
    public function getJobs($filters = [])
    {
        $sql = "SELECT jobs.*, 
                       school_profiles.school_name, 
                       school_profiles.school_type, 
                       school_profiles.address AS school_address,
                       school_profiles.lga AS school_lga,
                       school_profiles.state AS school_state, 
                       school_profiles.website AS school_website,
                       school_profiles.is_verified, 
                       school_profiles.verification_status,
                       school_profiles.logo_url 
                FROM jobs 
                JOIN school_profiles ON jobs.school_id = school_profiles.user_id 
                WHERE jobs.status IN ('open', 'published')";

        $status = !empty($filters['status']) ? $filters['status'] : 'open';
        if (!empty($filters['school_id']) && in_array($status, ['open', 'draft', 'closed'], true)) {
            $sql = str_replace("jobs.status IN ('open', 'published')", 'jobs.status = :job_status', $sql);
        }

        $conditions = [];
        if (!empty($filters['subject'])) {
            // Map subject search to role_type/title/description
            $conditions[] = "(jobs.title LIKE :subject1 OR jobs.role_type LIKE :subject2 OR jobs.description LIKE :subject3)";
        }
        if (!empty($filters['state'])) {
            // Map state search to location/state
            $conditions[] = "(jobs.location LIKE :state1 OR school_profiles.state LIKE :state2 OR school_profiles.address LIKE :state3)";
        }
        if (!empty($filters['teaching_level'])) {
            $conditions[] = "jobs.teaching_level LIKE :teaching_level";
        }
        if (!empty($filters['location'])) {
            $conditions[] = "jobs.location LIKE :location";
        }
        if (!empty($filters['experience'])) {
            $conditions[] = "jobs.required_experience LIKE :experience";
        }
        if (!empty($filters['school_id'])) {
            $conditions[] = "jobs.school_id = :school_id";
        }

        if (!empty($conditions)) {
            $sql .= " AND " . implode(" AND ", $conditions);
        }

        $sql .= " ORDER BY jobs.created_at DESC LIMIT :limit OFFSET :offset";

        $page = max(1, (int)($filters['page'] ?? 1));
        $perPage = min(50, max(1, (int)($filters['per_page'] ?? 10)));
        $offset = ($page - 1) * $perPage;
        $countStatusSql = !empty($filters['school_id']) && in_array($status, ['open', 'draft', 'closed'], true) ? 'jobs.status = :count_status' : "jobs.status IN ('open', 'published')";
        $countSql = "SELECT COUNT(*) AS total FROM jobs JOIN school_profiles ON jobs.school_id = school_profiles.user_id WHERE {$countStatusSql}";
        if (!empty($filters['school_id'])) $countSql .= " AND jobs.school_id = :count_school_id";
        if (!empty($filters['subject'])) $countSql .= " AND (jobs.title LIKE :count_subject OR jobs.role_type LIKE :count_role OR jobs.description LIKE :count_description)";
        if (!empty($filters['state'])) $countSql .= " AND (jobs.location LIKE :count_state OR school_profiles.state LIKE :count_school_state OR school_profiles.address LIKE :count_address)";
        if (!empty($filters['teaching_level'])) $countSql .= " AND jobs.teaching_level LIKE :count_level";
        if (!empty($filters['location'])) $countSql .= " AND jobs.location LIKE :count_location";
        if (!empty($filters['experience'])) $countSql .= " AND jobs.required_experience LIKE :count_experience";
        $this->db->query($countSql);
        if (!empty($filters['school_id']) && in_array($status, ['open', 'draft', 'closed'], true)) $this->db->bind(':count_status', $status);
        if (!empty($filters['school_id'])) $this->db->bind(':count_school_id', $filters['school_id']);
        if (!empty($filters['subject'])) { $value = '%' . trim($filters['subject']) . '%'; $this->db->bind(':count_subject', $value); $this->db->bind(':count_role', $value); $this->db->bind(':count_description', $value); }
        if (!empty($filters['state'])) { $value = '%' . trim($filters['state']) . '%'; $this->db->bind(':count_state', $value); $this->db->bind(':count_school_state', $value); $this->db->bind(':count_address', $value); }
        if (!empty($filters['teaching_level'])) $this->db->bind(':count_level', '%' . trim($filters['teaching_level']) . '%');
        if (!empty($filters['location'])) $this->db->bind(':count_location', '%' . trim($filters['location']) . '%');
        if (!empty($filters['experience'])) $this->db->bind(':count_experience', '%' . trim($filters['experience']) . '%');
        $total = (int)$this->db->single()->total;

        $this->db->query($sql);
        if (!empty($filters['school_id'])) {
            $this->db->bind(':job_status', $status);
        }

        if (!empty($filters['subject'])) {
            $subjectVal = '%' . trim($filters['subject']) . '%';
            $this->db->bind(':subject1', $subjectVal);
            $this->db->bind(':subject2', $subjectVal);
            $this->db->bind(':subject3', $subjectVal);
        }
        if (!empty($filters['state'])) {
            $stateVal = '%' . trim($filters['state']) . '%';
            $this->db->bind(':state1', $stateVal);
            $this->db->bind(':state2', $stateVal);
            $this->db->bind(':state3', $stateVal);
        }
        if (!empty($filters['school_id'])) {
            $this->db->bind(':school_id', $filters['school_id']);
        }
        if (!empty($filters['teaching_level'])) $this->db->bind(':teaching_level', '%' . trim($filters['teaching_level']) . '%');
        if (!empty($filters['location'])) $this->db->bind(':location', '%' . trim($filters['location']) . '%');
        if (!empty($filters['experience'])) $this->db->bind(':experience', '%' . trim($filters['experience']) . '%');
        $this->db->bind(':limit', $perPage, PDO::PARAM_INT);
        $this->db->bind(':offset', $offset, PDO::PARAM_INT);

        $lastPage = $total > 0 ? (int)ceil($total / $perPage) : 1;
        $jobs = array_map([$this, 'normalizeJob'], $this->db->resultSet());
        return ['data' => $jobs, 'pagination' => ['current_page' => $page, 'per_page' => $perPage, 'total' => $total, 'last_page' => $lastPage, 'has_more' => $page < $lastPage]];
    }

    /**
     * Get single job by ID along with complete school profile info.
     *
     * @param int $id
     * @return object|false
     */
    public function getJobById($id, $ownerUserId = null, $openOnly = false)
    {
        $sql = "SELECT jobs.*, 
                       school_profiles.user_id AS school_user_id,
                       school_profiles.school_name, 
                       school_profiles.school_type, 
                       school_profiles.address AS school_address, 
                       school_profiles.lga AS school_lga, 
                       school_profiles.state AS school_state, 
                       school_profiles.website AS school_website, 
                       school_profiles.is_verified, 
                       school_profiles.verification_status,
                       school_profiles.logo_url 
                FROM jobs 
                JOIN school_profiles ON jobs.school_id = school_profiles.user_id 
                WHERE jobs.job_id = :id 
                  AND jobs.status != 'deleted'";

        if ($openOnly) {
            $sql .= " AND jobs.status IN ('open', 'published')";
        }

        if ($ownerUserId !== null) {
            $sql .= " AND jobs.school_id = :owner_user_id";
        }

        $this->db->query($sql);
        $this->db->bind(':id', $id);
        if ($ownerUserId !== null) {
            $this->db->bind(':owner_user_id', $ownerUserId);
        }
        $row = $this->db->single();
        return ($this->db->rowCount() > 0) ? $this->normalizeJob($row) : false;
    }

    public function getRecommendedJobs($teacherUserId, $page = 1, $perPage = 10)
    {
        $page = max(1, (int)$page);
        $perPage = min(50, max(1, (int)$perPage));
        $offset = ($page - 1) * $perPage;
        $this->db->query("SELECT jobs.*, school_profiles.school_name, school_profiles.logo_url,
                                 (CASE WHEN EXISTS (SELECT 1 FROM teacher_subjects ts WHERE ts.teacher_user_id = :teacher_id_subject AND LOWER(jobs.role_type) LIKE CONCAT('%', LOWER(ts.subject), '%')) THEN 3 ELSE 0 END
                                  + CASE WHEN EXISTS (SELECT 1 FROM teacher_profiles tp WHERE tp.user_id = :teacher_id_location AND tp.location IS NOT NULL AND tp.location <> '' AND LOWER(jobs.location) LIKE CONCAT('%', LOWER(tp.location), '%')) THEN 2 ELSE 0 END) AS relevance_score
                          FROM jobs
                          JOIN school_profiles ON jobs.school_id = school_profiles.user_id
                          WHERE jobs.status IN ('open', 'published') AND (jobs.application_deadline IS NULL OR jobs.application_deadline >= CURRENT_DATE)
                          ORDER BY relevance_score DESC, jobs.created_at DESC
                          LIMIT :limit OFFSET :offset");
        $this->db->bind(':teacher_id_subject', $teacherUserId);
        $this->db->bind(':teacher_id_location', $teacherUserId);
        $this->db->bind(':limit', $perPage, PDO::PARAM_INT);
        $this->db->bind(':offset', $offset, PDO::PARAM_INT);
        $rows = array_map([$this, 'normalizeJob'], $this->db->resultSet());
        return ['data' => $rows, 'pagination' => ['current_page' => $page, 'per_page' => $perPage, 'total' => count($rows), 'last_page' => $page, 'has_more' => count($rows) === $perPage]];
    }

    /**
     * Create a new job post
     *
     * @param int $schoolId
     * @param array $data
     * @return int|false
     */
    public function createJob($schoolId, $data, $id, $status = 'open')
    {
        $sql = "INSERT INTO jobs (job_id, school_id, title, description, role_type, employment_type, salary_range, location, requirements, responsibilities, teaching_level, required_experience, required_qualification, application_deadline, is_featured, status)
            VALUES (:job_id, :school_id, :title, :description, :role_type, :employment_type, :salary_range, :location, :requirements, :responsibilities, :teaching_level, :required_experience, :required_qualification, :application_deadline, :is_featured, :status)";

        $this->db->query($sql);
        $this->db->bind(':job_id', $id);
        $this->db->bind(':school_id', $schoolId);
        $this->db->bind(':title', trim($data['title'] ?? ''));
        $this->db->bind(':description', trim($data['description'] ?? ''));
        $this->db->bind(':role_type', trim($data['role_type'] ?? ''));
        $this->db->bind(':employment_type', trim($data['employment_type'] ?? 'full-time'));
        $this->db->bind(':salary_range', trim($data['salary_range'] ?? ''));
        $this->db->bind(':location', trim($data['location'] ?? ''));
        $this->db->bind(':requirements', $this->encodeResponsibilities($data['requirements'] ?? null));
        $this->db->bind(':responsibilities', $this->encodeResponsibilities($data['responsibilities'] ?? null));
        $this->db->bind(':teaching_level', trim($data['teaching_level'] ?? ''));
        $this->db->bind(':required_experience', trim($data['required_experience'] ?? ''));
        $this->db->bind(':required_qualification', $this->encodeResponsibilities($data['required_qualification'] ?? null));
        $this->db->bind(':application_deadline', trim($data['application_deadline'] ?? '') ?: null);
        $this->db->bind(':is_featured', !empty($data['is_featured']) ? 1 : 0);
        $this->db->bind(':status', $status);

        if ($this->db->execute()) {
            return $id;
        }
        return false;
    }

    /**
     * Update an existing job (partial updates supported)
     * Only the school that created the job can update it.
     *
     * @param int $id
     * @param int $schoolId
     * @param int $userId
     * @param array $data
     * @return bool
     */
    public function updateJob($id, $schoolId, $userId, $data)
    {
        $current = $this->getJobById($id);
        if (!$current) {
            return false;
        }

        // Check ownership (matches either profile ID or direct user ID)
        if ((string)$current->school_id !== (string)$schoolId && (string)$current->school_id !== (string)$userId) {
            return false;
        }

        $sql = "UPDATE jobs SET 
                    title = :title, 
                    description = :description, 
                    role_type = :role_type, 
                    employment_type = :employment_type, 
                    salary_range = :salary_range, 
                    location = :location, 
                    requirements = :requirements,
                    responsibilities = :responsibilities,
                    teaching_level = :teaching_level,
                    required_experience = :required_experience,
                    required_qualification = :required_qualification,
                    application_deadline = :application_deadline,
                    is_featured = :is_featured,
                    status = :status 
                WHERE job_id = :id AND (school_id = :school_id OR school_id = :user_id)";

        $this->db->query($sql);
        $this->db->bind(':title', isset($data['title']) ? trim($data['title']) : $current->title);
        $this->db->bind(':description', isset($data['description']) ? trim($data['description']) : $current->description);
        $this->db->bind(':role_type', isset($data['role_type']) ? trim($data['role_type']) : $current->role_type);
        $this->db->bind(':employment_type', isset($data['employment_type']) ? trim($data['employment_type']) : $current->employment_type);
        $this->db->bind(':salary_range', isset($data['salary_range']) ? trim($data['salary_range']) : $current->salary_range);
        $this->db->bind(':location', isset($data['location']) ? trim($data['location']) : $current->location);
        $this->db->bind(':requirements', array_key_exists('requirements', $data) ? $this->encodeResponsibilities($data['requirements']) : $this->encodeResponsibilities($current->requirements ?? null));
        $this->db->bind(':responsibilities', array_key_exists('responsibilities', $data) ? $this->encodeResponsibilities($data['responsibilities']) : $this->encodeResponsibilities($current->responsibilities ?? null));
        $this->db->bind(':teaching_level', isset($data['teaching_level']) ? trim($data['teaching_level']) : ($current->teaching_level ?? null));
        $this->db->bind(':required_experience', isset($data['required_experience']) ? trim($data['required_experience']) : ($current->required_experience ?? null));
        $this->db->bind(':required_qualification', array_key_exists('required_qualification', $data) ? $this->encodeResponsibilities($data['required_qualification']) : $this->encodeResponsibilities($current->required_qualification ?? null));
        $this->db->bind(':application_deadline', isset($data['application_deadline']) ? (trim($data['application_deadline']) ?: null) : ($current->application_deadline ?? null));
        $this->db->bind(':is_featured', isset($data['is_featured']) ? (!empty($data['is_featured']) ? 1 : 0) : (int)($current->is_featured ?? 0));
        $this->db->bind(':status', isset($data['status']) ? trim($data['status']) : ($current->status ?? 'open'));
        $this->db->bind(':id', $id);
        $this->db->bind(':school_id', $schoolId);
        $this->db->bind(':user_id', $userId);

        return $this->db->execute();
    }

    public function publishJob($id, $schoolId, $userId)
    {
        $this->db->query("UPDATE jobs SET status = 'open' WHERE job_id = :id AND (school_id = :school_id OR school_id = :user_id) AND status IN ('draft', 'pending_review')");
        $this->db->bind(':id', $id);
        $this->db->bind(':school_id', $schoolId);
        $this->db->bind(':user_id', $userId);

        if ($this->db->execute() && $this->db->rowCount() > 0) {
            return true;
        }

        $current = $this->getJobById($id);
        return $current && $current->status === 'open' && ((string)$current->school_id === (string)$schoolId || (string)$current->school_id === (string)$userId);
    }

    /**
     * Delete a job (Soft delete by setting status = 'deleted')
     * Only the school that created the job can delete it.
     *
     * @param int $id
     * @param int $schoolId
     * @param int $userId
     * @param bool $isAdmin
     * @return bool
     */
    public function deleteJob($id, $schoolId, $userId, $isAdmin = false)
    {
        $current = $this->getJobById($id);
        if (!$current) {
            return false;
        }

        if (!$isAdmin && (string)$current->school_id !== (string)$schoolId && (string)$current->school_id !== (string)$userId) {
            return false;
        }

        // Perform soft delete
        $sql = "UPDATE jobs SET status = 'closed' WHERE job_id = :id";
        $this->db->query($sql);
        $this->db->bind(':id', $id);
        if ($this->db->execute()) {
            return ($this->db->rowCount() > 0 || $current->status === 'closed');
        }
        return false;
    }

    /**
     * Check if a job is owned by a specific school or user ID
     */
    public function isOwner($id, $schoolId, $userId)
    {
        $current = $this->getJobById($id);
        if (!$current) return false;
        return ((string)$current->school_id === (string)$schoolId || (string)$current->school_id === (string)$userId);
    }
}
?>
