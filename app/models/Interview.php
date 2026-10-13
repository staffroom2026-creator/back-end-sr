<?php
/**
 * Interview Model
 * Stores interview details attached to a job application.
 */

class Interview
{
    private $db;

    public function __construct()
    {
        $this->db = new Database();
    }

    public function getByApplication($applicationId)
    {
        $this->db->query("SELECT * FROM application_interviews
                          WHERE application_id = :application_id
                          ORDER BY created_at DESC");
        $this->db->bind(':application_id', $applicationId);
        return $this->db->resultSet();
    }

    public function create($applicationId, $schoolUserId, $data)
    {
        $interviewId = hash('sha256', uniqid('', true) . microtime(true));
        $this->db->query("INSERT INTO application_interviews
            (interview_id, application_id, school_user_id, interview_type, interview_date,
             interview_time, venue, meeting_link, instructions, candidate_message, template_name, status)
            VALUES (:interview_id, :application_id, :school_user_id, :interview_type, :interview_date,
                    :interview_time, :venue, :meeting_link, :instructions, :candidate_message, :template_name, 'scheduled')");
        $this->db->bind(':interview_id', $interviewId);
        $this->db->bind(':application_id', $applicationId);
        $this->db->bind(':school_user_id', $schoolUserId);
        $this->db->bind(':interview_type', $data['interview_type']);
        $this->db->bind(':interview_date', $data['interview_date']);
        $this->db->bind(':interview_time', $data['interview_time']);
        $this->db->bind(':venue', $data['venue'] ?? null);
        $this->db->bind(':meeting_link', $data['meeting_link'] ?? null);
        $this->db->bind(':instructions', $data['instructions'] ?? null);
        $this->db->bind(':candidate_message', $data['candidate_message'] ?? null);
        $this->db->bind(':template_name', $data['template_name'] ?? null);

        if (!$this->db->execute()) {
            return false;
        }

        $this->db->query("SELECT * FROM application_interviews WHERE interview_id = :interview_id");
        $this->db->bind(':interview_id', $interviewId);
        return $this->db->single();
    }
}
?>
