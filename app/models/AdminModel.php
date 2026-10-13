<?php
/**
 * AdminModel
 */

class AdminModel
{
    private $db;

    public function __construct()
    {
        $this->db = new Database();
    }

    /**
     * Get marketplace statistics for admin dashboard
     *
     * @return array
     */
    public function getStats()
    {
        $stats = [];

        // Total users
        $this->db->query("SELECT COUNT(*) AS count FROM initkey");
        $stats['total_users'] = (int)$this->db->single()->count;

        // Total teachers
        $this->db->query("SELECT COUNT(*) AS count FROM initkey WHERE role = 'teacher'");
        $stats['total_teachers'] = (int)$this->db->single()->count;

        // Total schools
        $this->db->query("SELECT COUNT(*) AS count FROM initkey WHERE role = 'school'");
        $stats['total_schools'] = (int)$this->db->single()->count;

        // Verified schools
        $this->db->query("SELECT COUNT(*) AS count FROM school_profiles WHERE is_verified = 1");
        $stats['verified_schools'] = (int)$this->db->single()->count;

        // Pending verifications
        $this->db->query("SELECT COUNT(*) AS count FROM school_profiles WHERE is_verified = 0 OR verification_status = 'pending'");
        $stats['pending_verifications'] = (int)$this->db->single()->count;

        // Total jobs
        $this->db->query("SELECT COUNT(*) AS count FROM jobs");
        $stats['total_jobs'] = (int)$this->db->single()->count;

        // Active jobs
        $this->db->query("SELECT COUNT(*) AS count FROM jobs WHERE status = 'open'");
        $stats['active_jobs'] = (int)$this->db->single()->count;

        // Total applications
        $this->db->query("SELECT COUNT(*) AS count FROM applications");
        $stats['total_applications'] = (int)$this->db->single()->count;

        // Shortlisted applications
        $this->db->query("SELECT COUNT(*) AS count FROM applications WHERE status = 'shortlisted'");
        $stats['shortlisted_applications'] = (int)$this->db->single()->count;

        return $stats;
    }
}
?>
