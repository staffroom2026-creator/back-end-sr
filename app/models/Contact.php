<?php
/**
 * Contact submission model.
 */

class Contact
{
    private $db;

    public function __construct()
    {
        $this->db = new Database();
    }

    public function create($data)
    {
        $contactId = 'CNT-' . bin2hex(random_bytes(16));

        $this->db->query(
            'INSERT INTO contact_submissions (contact_id, name, email, subject, message, status) '
            . 'VALUES (:contact_id, :name, :email, :subject, :message, :status)'
        );
        $this->db->bind(':contact_id', $contactId);
        $this->db->bind(':name', $data['name']);
        $this->db->bind(':email', $data['email']);
        $this->db->bind(':subject', $data['subject']);
        $this->db->bind(':message', $data['message']);
        $this->db->bind(':status', 'new');
        $this->db->execute();

        return $contactId;
    }
}
?>
