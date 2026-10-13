<?php
/**
 * Teacher Directory Model
 * Handles school-side teacher discovery and invitations.
 */

class TeacherDirectory
{
    private $db;

    public function __construct()
    {
        $this->db = new Database();
    }

    public function search($filters = [])
    {
        $sql = "SELECT COUNT(*) OVER() AS total_count, initkey.user_id, initkey.full_name, initkey.email, initkey.phone,
                       teacher_profiles.profile_id, teacher_profiles.bio,
                       teacher_profiles.skills, teacher_profiles.experience_years,
                       teacher_profiles.qualification, teacher_profiles.location,
                       teacher_profiles.cv_url, teacher_profiles.trcn_status,
                       teacher_profiles.availability, teacher_profiles.profile_visibility
                FROM teacher_profiles
                JOIN initkey ON teacher_profiles.user_id = initkey.user_id
                WHERE initkey.role = 'teacher'
                  AND initkey.status = 'active'
                                    AND initkey.active = 1
                                    AND teacher_profiles.profile_visibility = 'schools'";

        $conditions = [];
        if (!empty($filters['search'])) {
            $conditions[] = "(initkey.full_name LIKE :search_name
                OR teacher_profiles.skills LIKE :search_skills
                OR teacher_profiles.qualification LIKE :search_qualification)";
        }
        if (!empty($filters['location'])) {
            $conditions[] = "teacher_profiles.location LIKE :location";
        }
        if ($filters['experience_years'] !== null && $filters['experience_years'] !== '') {
            $conditions[] = "teacher_profiles.experience_years >= :experience_years";
        }
        if (!empty($filters['qualification'])) {
            $conditions[] = "teacher_profiles.qualification LIKE :qualification";
        }
        if (!empty($filters['subject'])) $conditions[] = "teacher_profiles.skills LIKE :subject";
        if ($filters['experience_min'] !== null && $filters['experience_min'] !== '') $conditions[] = "teacher_profiles.experience_years >= :experience_min";
        if ($filters['experience_max'] !== null && $filters['experience_max'] !== '') $conditions[] = "teacher_profiles.experience_years <= :experience_max";
        if (!empty($filters['trcn_status'])) $conditions[] = "teacher_profiles.trcn_status = :trcn_status";

        if (!empty($conditions)) {
            $sql .= ' AND ' . implode(' AND ', $conditions);
        }
        $sql .= ' ORDER BY teacher_profiles.experience_years DESC, initkey.full_name ASC LIMIT :limit OFFSET :offset';

        $page = max(1, (int)($filters['page'] ?? 1));
        $perPage = min(50, max(1, (int)($filters['per_page'] ?? 10)));
        $offset = ($page - 1) * $perPage;

        $this->db->query($sql);
        if (!empty($filters['search'])) {
            $value = '%' . trim($filters['search']) . '%';
            $this->db->bind(':search_name', $value);
            $this->db->bind(':search_skills', $value);
            $this->db->bind(':search_qualification', $value);
        }
        if (!empty($filters['location'])) {
            $this->db->bind(':location', '%' . trim($filters['location']) . '%');
        }
        if ($filters['experience_years'] !== null && $filters['experience_years'] !== '') {
            $this->db->bind(':experience_years', (int)$filters['experience_years']);
        }
        if (!empty($filters['qualification'])) {
            $this->db->bind(':qualification', '%' . trim($filters['qualification']) . '%');
        }
        if (!empty($filters['subject'])) $this->db->bind(':subject', '%' . trim($filters['subject']) . '%');
        if ($filters['experience_min'] !== null && $filters['experience_min'] !== '') $this->db->bind(':experience_min', (int)$filters['experience_min']);
        if ($filters['experience_max'] !== null && $filters['experience_max'] !== '') $this->db->bind(':experience_max', (int)$filters['experience_max']);
        if (!empty($filters['trcn_status'])) $this->db->bind(':trcn_status', $filters['trcn_status']);
        $this->db->bind(':limit', $perPage, PDO::PARAM_INT);
        $this->db->bind(':offset', $offset, PDO::PARAM_INT);

        $rows = $this->db->resultSet();
        $total = !empty($rows) ? (int)$rows[0]->total_count : 0;
        foreach ($rows as $row) unset($row->total_count);
        $lastPage = $total > 0 ? (int)ceil($total / $perPage) : 1;
        return ['data' => $rows, 'pagination' => ['current_page' => $page, 'per_page' => $perPage, 'total' => $total, 'last_page' => $lastPage, 'has_more' => $page < $lastPage]];
    }

    public function getByUserId($userId)
    {
        $this->db->query("SELECT initkey.user_id, initkey.full_name, initkey.email, initkey.phone,
                                 teacher_profiles.profile_id, teacher_profiles.bio,
                                 teacher_profiles.skills, teacher_profiles.experience_years,
                                 teacher_profiles.qualification, teacher_profiles.location,
                                 teacher_profiles.cv_url, teacher_profiles.trcn_status, 
                                 teacher_profiles.availability, teacher_profiles.profile_visibility
                          FROM teacher_profiles
                          JOIN initkey ON teacher_profiles.user_id = initkey.user_id
                          WHERE teacher_profiles.user_id = :user_id
                            AND initkey.role = 'teacher'
                            AND initkey.status = 'active'
                                                        AND initkey.active = 1
                                                        AND teacher_profiles.profile_visibility = 'schools'");
        $this->db->bind(':user_id', $userId);
        $teacher = $this->db->single();
        if (!$teacher) {
            return false;
        }

        $teacher->education_history = $this->getEducationHistory($userId);
        $teacher->teaching_experience = $this->getTeachingExperience($userId);
        $teacher->work_experience = $teacher->teaching_experience;
        $teacher->experience_history = $teacher->teaching_experience;

        return $teacher;
    }

    private function getEducationHistory($userId)
    {
        $this->db->query("SELECT education_id, user_id, degree, other_degree, institution, field_of_study,
                                 start_year, end_year, status, class_of_degree, created_at, updated_at
                          FROM teacher_education
                          WHERE user_id = :user_id
                          ORDER BY start_year DESC, created_at DESC");
        $this->db->bind(':user_id', $userId);
        return $this->db->resultSet();
    }

    private function getTeachingExperience($userId)
    {
        $this->db->query("SELECT experience_id, user_id, role, school, location, start_date, end_date,
                                 description, created_at, updated_at
                          FROM teacher_experience
                          WHERE user_id = :user_id
                          ORDER BY start_date DESC, created_at DESC");
        $this->db->bind(':user_id', $userId);
        return $this->db->resultSet();
    }

    public function createInvitation($schoolUserId, $teacherUserId, $jobId, $message)
    {
        if ($jobId !== '') {
            $this->db->query("SELECT job_id FROM jobs WHERE job_id = :job_id AND school_id = :school_id AND status = 'open'");
            $this->db->bind(':job_id', $jobId);
            $this->db->bind(':school_id', $schoolUserId);
            if (!$this->db->single()) {
                return false;
            }
        }

        $invitationId = hash('sha256', uniqid('', true) . microtime(true));
        $this->db->query("INSERT INTO teacher_invitations
            (invitation_id, school_user_id, teacher_user_id, job_id, message, status)
            VALUES (:invitation_id, :school_user_id, :teacher_user_id, :job_id, :message, 'sent')");
        $this->db->bind(':invitation_id', $invitationId);
        $this->db->bind(':school_user_id', $schoolUserId);
        $this->db->bind(':teacher_user_id', $teacherUserId);
        $this->db->bind(':job_id', $jobId ?: null);
        $this->db->bind(':message', trim($message));
        return $this->db->execute() ? $invitationId : false;
    }
}
?>
