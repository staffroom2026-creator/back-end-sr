<?php
/**
 * School-owned recruitment email templates.
 */
class EmailTemplate
{
    private $db;

    public function __construct()
    {
        $this->db = new Database();
    }

    public function getBySchool($schoolUserId)
    {
        $this->db->query("SELECT template_id, name, subject, body, cta_label, cta_url, created_at, updated_at
                          FROM email_templates
                          WHERE school_user_id = :school_user_id
                          ORDER BY updated_at DESC");
        $this->db->bind(':school_user_id', $schoolUserId);
        return $this->db->resultSet();
    }

    public function getById($templateId, $schoolUserId)
    {
        $this->db->query("SELECT template_id, name, subject, body, cta_label, cta_url, created_at, updated_at
                          FROM email_templates
                          WHERE template_id = :template_id AND school_user_id = :school_user_id");
        $this->db->bind(':template_id', $templateId);
        $this->db->bind(':school_user_id', $schoolUserId);
        return $this->db->single();
    }

    public function create($schoolUserId, $data)
    {
        $templateId = hash('sha256', uniqid('', true) . microtime(true));
        $this->db->query("INSERT INTO email_templates (template_id, school_user_id, name, subject, body, cta_label, cta_url)
                          VALUES (:template_id, :school_user_id, :name, :subject, :body, :cta_label, :cta_url)");
        $this->bindTemplate($templateId, $schoolUserId, $data);
        return $this->db->execute() ? $this->getById($templateId, $schoolUserId) : false;
    }

    public function update($templateId, $schoolUserId, $data)
    {
        $current = $this->getById($templateId, $schoolUserId);
        if (!$current) return false;

        $this->db->query("UPDATE email_templates SET name = :name, subject = :subject, body = :body, cta_label = :cta_label, cta_url = :cta_url
                          WHERE template_id = :template_id AND school_user_id = :school_user_id");
        $this->bindTemplate($templateId, $schoolUserId, [
            'name' => $data['name'] ?? $current->name,
            'subject' => $data['subject'] ?? $current->subject,
            'body' => $data['body'] ?? $current->body,
            'cta_label' => $data['cta_label'] ?? $current->cta_label,
            'cta_url' => $data['cta_url'] ?? $current->cta_url
        ]);
        return $this->db->execute() ? $this->getById($templateId, $schoolUserId) : false;
    }

    public function delete($templateId, $schoolUserId)
    {
        $this->db->query("DELETE FROM email_templates WHERE template_id = :template_id AND school_user_id = :school_user_id");
        $this->db->bind(':template_id', $templateId);
        $this->db->bind(':school_user_id', $schoolUserId);
        return $this->db->execute() && $this->db->rowCount() > 0;
    }

    private function bindTemplate($templateId, $schoolUserId, $data)
    {
        $this->db->bind(':template_id', $templateId);
        $this->db->bind(':school_user_id', $schoolUserId);
        $this->db->bind(':name', trim($data['name']));
        $this->db->bind(':subject', trim($data['subject']));
        $this->db->bind(':body', trim($data['body']));
        $this->db->bind(':cta_label', trim($data['cta_label'] ?? '') ?: null);
        $this->db->bind(':cta_url', trim($data['cta_url'] ?? '') ?: null);
    }
}
?>
