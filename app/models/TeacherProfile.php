<?php
/**
 * TeacherProfile Model
 */

class TeacherProfile
{
    private $db;

    public function __construct()
    {
        $this->db = new Database();
    }

    public function getByUserId($userId)
    {
        $this->db->query("SELECT user_id, role_title, bio, skills, teaching_levels, experience_years, preferred_employment_type, teaching_mode, availability, location, city, state, preferred_location, qualification, cv_url, phone, education AS legacy_education, teaching_experience AS legacy_teaching_experience, trcn_number, trcn_status, available_from, profile_visibility, setup_completed, created_at, updated_at FROM teacher_profiles WHERE user_id = :user_id");
        $this->db->bind(':user_id', $userId);
        $profile = $this->db->single();
        if (!$profile) {
            return false;
        }

        $profile->skills = $this->asArray($profile->skills);
        $profile->teaching_levels = $this->asArray($profile->teaching_levels);
        $profile->subjects = $this->getSubjects($userId);
        $profile->education_history = $this->getEducationHistory($userId);
        $profile->teaching_experience = $this->getTeachingExperience($userId);
        $profile->work_experience = $profile->teaching_experience;
        $profile->experience_history = $profile->teaching_experience;
        return $profile;
    }

    private function asArray($value)
    {
        if ($value === null || $value === '') return [];
        $decoded = json_decode($value, true);
        if (is_array($decoded)) return array_values(array_unique(array_filter(array_map('trim', $decoded))));
        return array_values(array_unique(array_filter(array_map('trim', explode(',', $value)))));
    }

    public function getSubjects($userId)
    {
        $this->db->query("SELECT subject FROM teacher_subjects WHERE teacher_user_id = :user_id ORDER BY subject ASC");
        $this->db->bind(':user_id', $userId);
        return array_map(static function ($row) {
            return $row->subject;
        }, $this->db->resultSet());
    }

    public function getEducationHistory($userId)
    {
        $this->db->query("SELECT education_id, user_id, degree, other_degree, institution, field_of_study, start_year, end_year, status, class_of_degree, created_at, updated_at FROM teacher_education WHERE user_id = :user_id ORDER BY start_year DESC, created_at DESC");
        $this->db->bind(':user_id', $userId);
        return $this->db->resultSet();
    }

    public function getTeachingExperience($userId)
    {
        $this->db->query("SELECT experience_id, user_id, role, school, location, start_date, end_date, description, created_at, updated_at FROM teacher_experience WHERE user_id = :user_id ORDER BY start_date DESC, created_at DESC");
        $this->db->bind(':user_id', $userId);
        return $this->db->resultSet();
    }

    public function updateComplete($userId, $data)
    {
        if (!$this->getByUserId($userId)) {
            if (!$this->createOrUpdate($userId, ['bio' => null])) return false;
        }

        $this->db->beginTransaction();
        try {
            $fields = [];
            $bindings = [];
            $scalarMap = [
                'role_title' => 'role_title', 'bio' => 'bio', 'experience_years' => 'experience_years',
                'preferred_employment_type' => 'preferred_employment_type', 'teaching_mode' => 'teaching_mode', 'availability' => 'availability',
                'location' => 'location', 'city' => 'city', 'state' => 'state', 'preferred_location' => 'preferred_location', 'cv_url' => 'cv_url', 'qualification' => 'qualification',
                'phone' => 'phone', 'trcn_number' => 'trcn_number', 'trcn_status' => 'trcn_status',
                'available_from' => 'available_from', 'profile_visibility' => 'profile_visibility',
                'setup_completed' => 'setup_completed'
            ];
            foreach ($scalarMap as $input => $column) {
                if (array_key_exists($input, $data)) {
                    $fields[] = $column . ' = :' . $input;
                    $bindings[':' . $input] = $input === 'setup_completed' ? (int)(bool)$data[$input] : $data[$input];
                }
            }
            if (array_key_exists('skills', $data)) {
                $fields[] = 'skills = :skills';
                $bindings[':skills'] = implode(', ', $this->normalizeList($data['skills']));
            }
            if (array_key_exists('teaching_levels', $data)) {
                $fields[] = 'teaching_levels = :teaching_levels';
                $bindings[':teaching_levels'] = json_encode($this->normalizeList($data['teaching_levels']));
            }
            if ($fields) {
                $this->db->query('UPDATE teacher_profiles SET ' . implode(', ', $fields) . ' WHERE user_id = :profile_user_id');
                $this->db->bind(':profile_user_id', $userId);
                foreach ($bindings as $key => $value) $this->db->bind($key, $value);
                if (!$this->db->execute()) throw new RuntimeException('Teacher profile update failed');
            }
            if (array_key_exists('education_history', $data)) $this->syncEducation($userId, $data['education_history']);
            if (array_key_exists('teaching_experience', $data)) $this->syncExperience($userId, $data['teaching_experience']);
            if (array_key_exists('subjects', $data)) $this->replaceSubjects($userId, $data['subjects']);
            $this->db->commit();
            return true;
        } catch (Throwable $error) {
            $this->db->rollBack();
            return false;
        }
    }

    private function normalizeList($values)
    {
        $result = [];
        foreach ((array)$values as $value) {
            $value = trim((string)$value);
            if ($value !== '' && !in_array($value, $result, true)) $result[] = $value;
        }
        return $result;
    }

    private function syncEducation($userId, $records)
    {
        $keep = [];
        foreach ($records as $record) {
            $id = trim((string)($record['education_id'] ?? ''));
            if ($id === '') $id = hash('sha256', uniqid('', true) . microtime(true));
            $this->assertOwned('teacher_education', 'education_id', $id, $userId);
            $keep[] = $id;
            $this->db->query("INSERT INTO teacher_education (education_id, user_id, degree, other_degree, institution, field_of_study, start_year, end_year, status, class_of_degree) VALUES (:id, :user_id, :degree, :other_degree, :institution, :field_of_study, :start_year, :end_year, :status, :class_of_degree) ON DUPLICATE KEY UPDATE degree = VALUES(degree), other_degree = VALUES(other_degree), institution = VALUES(institution), field_of_study = VALUES(field_of_study), start_year = VALUES(start_year), end_year = VALUES(end_year), status = VALUES(status), class_of_degree = VALUES(class_of_degree)");
            $this->db->bind(':id', $id); $this->db->bind(':user_id', $userId); $this->db->bind(':degree', trim($record['degree']));
            $this->db->bind(':other_degree', trim((string)($record['other_degree'] ?? '')) ?: null); $this->db->bind(':institution', trim($record['institution']));
            $this->db->bind(':field_of_study', trim((string)($record['field_of_study'] ?? '')) ?: null); $this->db->bind(':start_year', (int)$record['start_year']);
            $this->db->bind(':end_year', $record['end_year'] === null || $record['end_year'] === '' ? null : (int)$record['end_year']);
            $this->db->bind(':status', trim($record['status'])); $this->db->bind(':class_of_degree', trim((string)($record['class_of_degree'] ?? '')) ?: null);
            if (!$this->db->execute()) throw new RuntimeException('Education update failed');
        }
        $this->deleteOmitted('teacher_education', 'education_id', $userId, $keep);
    }

    private function syncExperience($userId, $records)
    {
        $keep = [];
        foreach ($records as $record) {
            $id = trim((string)($record['experience_id'] ?? ''));
            if ($id === '') $id = hash('sha256', uniqid('', true) . microtime(true));
            $this->assertOwned('teacher_experience', 'experience_id', $id, $userId);
            $keep[] = $id;
            $this->db->query("INSERT INTO teacher_experience (experience_id, user_id, role, school, location, start_date, end_date, description) VALUES (:id, :user_id, :role, :school, :location, :start_date, :end_date, :description) ON DUPLICATE KEY UPDATE role = VALUES(role), school = VALUES(school), location = VALUES(location), start_date = VALUES(start_date), end_date = VALUES(end_date), description = VALUES(description)");
            $this->db->bind(':id', $id); $this->db->bind(':user_id', $userId); $this->db->bind(':role', trim($record['role'])); $this->db->bind(':school', trim($record['school']));
            $this->db->bind(':location', trim($record['location'] ?? '') ?: null); $this->db->bind(':start_date', $record['start_date']);
            $this->db->bind(':end_date', $record['end_date'] ?? null); $this->db->bind(':description', trim($record['description'] ?? '') ?: null);
            if (!$this->db->execute()) throw new RuntimeException('Experience update failed');
        }
        $this->deleteOmitted('teacher_experience', 'experience_id', $userId, $keep);
    }

    private function deleteOmitted($table, $idColumn, $userId, $keep)
    {
        if (!$keep) {
            $this->db->query("DELETE FROM {$table} WHERE user_id = :user_id");
            $this->db->bind(':user_id', $userId);
            if (!$this->db->execute()) throw new RuntimeException('History cleanup failed');
            return;
        }
        $placeholders = [];
        foreach ($keep as $index => $id) $placeholders[] = ':keep_' . $index;
        $this->db->query("DELETE FROM {$table} WHERE user_id = :user_id AND {$idColumn} NOT IN (" . implode(',', $placeholders) . ")");
        $this->db->bind(':user_id', $userId);
        foreach ($keep as $index => $id) $this->db->bind(':keep_' . $index, $id);
        if (!$this->db->execute()) throw new RuntimeException('History cleanup failed');
    }

    private function assertOwned($table, $idColumn, $id, $userId)
    {
        $this->db->query("SELECT user_id FROM {$table} WHERE {$idColumn} = :id");
        $this->db->bind(':id', $id);
        $existing = $this->db->single();
        if ($existing && (string)$existing->user_id !== (string)$userId) {
            throw new RuntimeException('History record ownership violation');
        }
    }

    public function createOrUpdate($userId, $data)
    {
        $existing = $this->getByUserId($userId);

        if ($existing) {
            $sql = "UPDATE teacher_profiles SET ";
            $fields = [];
            if (isset($data['bio'])) $fields[] = "bio = :bio";
            if (isset($data['skills'])) $fields[] = "skills = :skills";
            if (isset($data['experience_years'])) $fields[] = "experience_years = :experience_years";
            if (isset($data['qualification'])) $fields[] = "qualification = :qualification";
            if (isset($data['location'])) $fields[] = "location = :location";
            if (isset($data['cv_url'])) $fields[] = "cv_url = :cv_url";
            if (isset($data['phone'])) $fields[] = "phone = :phone";
            if (isset($data['education'])) $fields[] = "education = :education";
            if (isset($data['teaching_experience'])) $fields[] = "teaching_experience = :teaching_experience";
            if (isset($data['trcn_number'])) $fields[] = "trcn_number = :trcn_number";
            if (isset($data['trcn_status'])) $fields[] = "trcn_status = :trcn_status";
            if (isset($data['availability'])) $fields[] = "availability = :availability";
            if (isset($data['available_from'])) $fields[] = "available_from = :available_from";
            if (isset($data['profile_visibility'])) $fields[] = "profile_visibility = :profile_visibility";
            if (isset($data['setup_completed'])) $fields[] = "setup_completed = :setup_completed";

            if (empty($fields)) return true;

            $sql .= implode(', ', $fields) . " WHERE user_id = :user_id";
            $this->db->query($sql);
            $this->db->bind(':user_id', $userId);
            if (isset($data['bio'])) $this->db->bind(':bio', $data['bio']);
            if (isset($data['skills'])) $this->db->bind(':skills', is_array($data['skills']) ? implode(',', $data['skills']) : $data['skills']);
            if (isset($data['experience_years'])) $this->db->bind(':experience_years', (int)$data['experience_years']);
            if (isset($data['qualification'])) $this->db->bind(':qualification', $data['qualification']);
            if (isset($data['location'])) $this->db->bind(':location', $data['location']);
            if (isset($data['cv_url'])) $this->db->bind(':cv_url', $data['cv_url']);
            if (isset($data['phone'])) $this->db->bind(':phone', $data['phone']);
            if (isset($data['education'])) $this->db->bind(':education', $data['education']);
            if (isset($data['teaching_experience'])) $this->db->bind(':teaching_experience', $data['teaching_experience']);
            if (isset($data['trcn_number'])) $this->db->bind(':trcn_number', $data['trcn_number']);
            if (isset($data['trcn_status'])) $this->db->bind(':trcn_status', $data['trcn_status']);
            if (isset($data['availability'])) $this->db->bind(':availability', $data['availability']);
            if (isset($data['available_from'])) $this->db->bind(':available_from', $data['available_from']);
            if (isset($data['profile_visibility'])) $this->db->bind(':profile_visibility', $data['profile_visibility']);
            if (isset($data['setup_completed'])) $this->db->bind(':setup_completed', (int)(bool)$data['setup_completed']);

            if (!$this->db->execute()) {
                return false;
            }
        } else {
            $profileId = hash('sha256', uniqid('', true) . microtime(true));
            $this->db->query("INSERT INTO teacher_profiles (profile_id, user_id, bio, skills, experience_years, qualification, location, cv_url, phone, education, teaching_experience, trcn_number, trcn_status, availability, available_from, profile_visibility, setup_completed) VALUES (:profile_id, :user_id, :bio, :skills, :experience_years, :qualification, :location, :cv_url, :phone, :education, :teaching_experience, :trcn_number, :trcn_status, :availability, :available_from, :profile_visibility, :setup_completed)");
            $this->db->bind(':profile_id', $profileId);
            $this->db->bind(':user_id', $userId);
            $this->db->bind(':bio', $data['bio'] ?? null);
            $this->db->bind(':skills', isset($data['skills']) ? (is_array($data['skills']) ? implode(',', $data['skills']) : $data['skills']) : null);
            $this->db->bind(':experience_years', (int)($data['experience_years'] ?? 0));
            $this->db->bind(':qualification', $data['qualification'] ?? null);
            $this->db->bind(':location', $data['location'] ?? null);
            $this->db->bind(':cv_url', $data['cv_url'] ?? null);
            $this->db->bind(':phone', $data['phone'] ?? null);
            $this->db->bind(':education', $data['education'] ?? null);
            $this->db->bind(':teaching_experience', $data['teaching_experience'] ?? null);
            $this->db->bind(':trcn_number', $data['trcn_number'] ?? null);
            $this->db->bind(':trcn_status', $data['trcn_status'] ?? 'pending');
            $this->db->bind(':availability', $data['availability'] ?? null);
            $this->db->bind(':available_from', $data['available_from'] ?? null);
            $this->db->bind(':profile_visibility', $data['profile_visibility'] ?? 'schools');
            $this->db->bind(':setup_completed', (int)(bool)($data['setup_completed'] ?? false));

            if (!$this->db->execute()) {
                return false;
            }
        }

        if (array_key_exists('subjects', $data)) {
            $this->replaceSubjects($userId, $data['subjects']);
        }

        return true;
    }

    public function replaceSubjects($userId, $subjects)
    {
        $normalized = [];
        foreach ((array)$subjects as $subject) {
            $subject = trim((string)$subject);
            if ($subject !== '' && !in_array($subject, $normalized, true)) {
                $normalized[] = $subject;
            }
        }

        $this->db->query("DELETE FROM teacher_subjects WHERE teacher_user_id = :user_id");
        $this->db->bind(':user_id', $userId);
        if (!$this->db->execute()) {
            return false;
        }

        foreach ($normalized as $subject) {
            $this->db->query("INSERT INTO teacher_subjects (teacher_user_id, subject) VALUES (:user_id, :subject)");
            $this->db->bind(':user_id', $userId);
            $this->db->bind(':subject', $subject);
            if (!$this->db->execute()) {
                return false;
            }
        }
        return true;
    }

    public function updateCV($userId, $cvUrl)
    {
        $this->db->query('UPDATE teacher_profiles SET cv_url = :cv_url WHERE user_id = :user_id');
        $this->db->bind(':cv_url', $cvUrl);
        $this->db->bind(':user_id', $userId);
        return $this->db->execute();
    }

    public function getTeacherIdByUserId($userId)
    {
        $row = $this->getByUserId($userId);
        return $row ? $row->user_id : false;
    }
}
?>
