<?php
/**
 * Job alert preferences model.
 */
class JobAlertPreference
{
    private $db;

    public function __construct()
    {
        $this->db = new Database();
    }

    public function get($teacherUserId)
    {
        $this->db->query("SELECT teacher_user_id, subjects, teaching_level, location, enabled, created_at, updated_at FROM job_alert_preferences WHERE teacher_user_id = :user_id");
        $this->db->bind(':user_id', $teacherUserId);
        $row = $this->db->single();
        if (!$row) {
            return (object)['teacher_user_id' => $teacherUserId, 'subjects' => [], 'teaching_level' => null, 'location' => null, 'enabled' => 0];
        }
        $row->subjects = json_decode($row->subjects ?: '[]', true) ?: [];
        $row->enabled = (bool)$row->enabled;
        return $row;
    }

    public function upsert($teacherUserId, $data)
    {
        $subjects = [];
        foreach ((array)($data['subjects'] ?? []) as $subject) {
            $subject = trim((string)$subject);
            if ($subject !== '' && !in_array($subject, $subjects, true)) $subjects[] = $subject;
        }
        $this->db->query("INSERT INTO job_alert_preferences (teacher_user_id, subjects, teaching_level, location, enabled)
                          VALUES (:user_id, :subjects, :level, :location, :enabled)
                          ON DUPLICATE KEY UPDATE subjects = VALUES(subjects), teaching_level = VALUES(teaching_level), location = VALUES(location), enabled = VALUES(enabled)");
        $this->db->bind(':user_id', $teacherUserId);
        $this->db->bind(':subjects', json_encode($subjects));
        $this->db->bind(':level', trim((string)($data['teaching_level'] ?? '')) ?: null);
        $this->db->bind(':location', trim((string)($data['location'] ?? '')) ?: null);
        $this->db->bind(':enabled', !empty($data['enabled']) ? 1 : 0);
        return $this->db->execute() ? $this->get($teacherUserId) : false;
    }
}
?>