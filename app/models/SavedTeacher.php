<?php
/**
 * SavedTeacher Model
 */

class SavedTeacher
{
    private $db;

    public function __construct()
    {
        $this->db = new Database();
    }

    public function save($schoolUserId, $teacherUserId)
    {
        $this->db->query("SELECT user_id FROM initkey WHERE user_id = :teacher_id AND role = 'teacher' AND status = 'active' AND active = 1");
        $this->db->bind(':teacher_id', $teacherUserId);
        if (!$this->db->single()) {
            return false;
        }

        $this->db->query("SELECT saved_teacher_id FROM saved_teachers WHERE school_user_id = :school_id AND teacher_user_id = :teacher_id");
        $this->db->bind(':school_id', $schoolUserId);
        $this->db->bind(':teacher_id', $teacherUserId);
        if ($this->db->single()) {
            return 'already_saved';
        }

        $savedTeacherId = hash('sha256', uniqid('', true) . microtime(true));
        $this->db->query("INSERT INTO saved_teachers (saved_teacher_id, school_user_id, teacher_user_id) VALUES (:saved_id, :school_id, :teacher_id)");
        $this->db->bind(':saved_id', $savedTeacherId);
        $this->db->bind(':school_id', $schoolUserId);
        $this->db->bind(':teacher_id', $teacherUserId);
        return $this->db->execute();
    }

    public function delete($schoolUserId, $teacherUserId)
    {
        $this->db->query("DELETE FROM saved_teachers WHERE school_user_id = :school_id AND teacher_user_id = :teacher_id");
        $this->db->bind(':school_id', $schoolUserId);
        $this->db->bind(':teacher_id', $teacherUserId);
        return $this->db->execute() && $this->db->rowCount() > 0;
    }

    public function getBySchool($schoolUserId)
    {
        $this->db->query("SELECT saved_teachers.saved_teacher_id, saved_teachers.teacher_user_id, saved_teachers.created_at AS saved_at,
                                 initkey.full_name, initkey.email, initkey.phone,
                                 teacher_profiles.bio, teacher_profiles.skills, teacher_profiles.experience_years,
                                 teacher_profiles.qualification, teacher_profiles.location, teacher_profiles.cv_url,
                                 teacher_profiles.trcn_status, teacher_profiles.availability
                          FROM saved_teachers
                          JOIN initkey ON initkey.user_id = saved_teachers.teacher_user_id
                          JOIN teacher_profiles ON teacher_profiles.user_id = saved_teachers.teacher_user_id
                          WHERE saved_teachers.school_user_id = :school_id
                          ORDER BY saved_teachers.created_at DESC");
        $this->db->bind(':school_id', $schoolUserId);
        return $this->db->resultSet();
    }
}
?>
