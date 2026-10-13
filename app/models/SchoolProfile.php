<?php
/**
 * SchoolProfile Model
 */

class SchoolProfile
{
    private $db;

    public function __construct()
    {
        $this->db = new Database();
    }

    public function getByUserId($userId)
    {
        $this->db->query("SELECT user_id, school_name, school_type, country, address, lga, state, website, logo_url, verification_status, is_verified, setup_completed, created_at, updated_at FROM school_profiles WHERE user_id = :user_id");
        $this->db->bind(':user_id', $userId);
        return $this->db->single();
    }

    public function getById($id)
    {
        $this->db->query("SELECT user_id, school_name, school_type, country, address, lga, state, website, logo_url, verification_status, is_verified, setup_completed, created_at, updated_at FROM school_profiles WHERE profile_id = :id");
        $this->db->bind(':id', $id);
        return $this->db->single();
    }

    public function createOrUpdate($userId, $data)
    {
        $existing = $this->getByUserId($userId);

        if ($existing) {
            $sql = "UPDATE school_profiles SET ";
            $fields = [];
            if (isset($data['school_name'])) $fields[] = "school_name = :school_name";
            if (isset($data['school_type'])) $fields[] = "school_type = :school_type";
            if (isset($data['country'])) $fields[] = "country = :country";
            if (isset($data['address'])) $fields[] = "address = :address";
            if (isset($data['lga'])) $fields[] = "lga = :lga";
            if (isset($data['state'])) $fields[] = "state = :state";
            if (isset($data['website'])) $fields[] = "website = :website";
            if (isset($data['logo_url'])) $fields[] = "logo_url = :logo_url";
            if (isset($data['setup_completed'])) $fields[] = "setup_completed = :setup_completed";
            if (isset($data['verification_status'])) {
                $fields[] = "verification_status = :verification_status";
                $fields[] = "is_verified = :is_verified";
            }

            if (empty($fields)) return true;

            $sql .= implode(', ', $fields) . " WHERE user_id = :user_id";
            $this->db->query($sql);
            $this->db->bind(':user_id', $userId);
            if (isset($data['school_name'])) $this->db->bind(':school_name', $data['school_name']);
            if (isset($data['school_type'])) $this->db->bind(':school_type', $data['school_type']);
            if (isset($data['country'])) $this->db->bind(':country', $data['country']);
            if (isset($data['address'])) $this->db->bind(':address', $data['address']);
            if (isset($data['lga'])) $this->db->bind(':lga', $data['lga']);
            if (isset($data['state'])) $this->db->bind(':state', $data['state']);
            if (isset($data['website'])) $this->db->bind(':website', $data['website']);
            if (isset($data['logo_url'])) $this->db->bind(':logo_url', $data['logo_url']);
            if (isset($data['setup_completed'])) $this->db->bind(':setup_completed', (int)(bool)$data['setup_completed']);
            if (isset($data['verification_status'])) {
                $this->db->bind(':verification_status', $data['verification_status']);
                $this->db->bind(':is_verified', ($data['verification_status'] === 'verified') ? 1 : 0);
            }

            return $this->db->execute();
        } else {
            $profileId = hash('sha256', uniqid('', true) . microtime(true));
            $this->db->query("INSERT INTO school_profiles (profile_id, user_id, school_name, school_type, country, address, lga, state, website, logo_url, verification_status, is_verified, setup_completed) VALUES (:profile_id, :user_id, :school_name, :school_type, :country, :address, :lga, :state, :website, :logo_url, :verification_status, :is_verified, :setup_completed)");
            $this->db->bind(':profile_id', $profileId);
            $this->db->bind(':user_id', $userId);
            $this->db->bind(':school_name', $data['school_name'] ?? 'New School');
            $this->db->bind(':school_type', $data['school_type'] ?? 'secondary');
            $this->db->bind(':country', $data['country'] ?? null);
            $this->db->bind(':address', $data['address'] ?? null);
            $this->db->bind(':lga', $data['lga'] ?? null);
            $this->db->bind(':state', $data['state'] ?? null);
            $this->db->bind(':website', $data['website'] ?? null);
            $this->db->bind(':logo_url', $data['logo_url'] ?? null);
            $this->db->bind(':verification_status', $data['verification_status'] ?? 'pending');
            $this->db->bind(':is_verified', isset($data['verification_status']) && $data['verification_status'] === 'verified' ? 1 : 0);
            $this->db->bind(':setup_completed', (int)(bool)($data['setup_completed'] ?? false));

            return $this->db->execute();
        }
    }

    public function updateLogo($userId, $logoUrl)
    {
        return $this->createOrUpdate($userId, ['logo_url' => $logoUrl]);
    }

    public function getSchoolIdByUserId($userId)
    {
        $row = $this->getByUserId($userId);
        return $row ? $row->user_id : false;
    }

    public function getPendingVerifications()
    {
        $sql = "SELECT school_profiles.*, 
                      initkey.email,
                      initkey.phone AS user_phone, 
                      initkey.status AS user_status 
                FROM school_profiles 
                  JOIN initkey ON school_profiles.user_id = initkey.user_id 
                WHERE school_profiles.is_verified = 0 OR school_profiles.verification_status = 'pending' 
                ORDER BY school_profiles.profile_id ASC";
        $this->db->query($sql);
        return $this->db->resultSet();
    }

    public function verifySchool($id, $status = 'verified')
    {
        $this->db->query("SELECT * FROM school_profiles WHERE profile_id = :id OR user_id = :user_id");
        $this->db->bind(':id', $id);
        $this->db->bind(':user_id', $id);
        $school = $this->db->single();

        if (!$school) {
            return false;
        }

        $isVerified = ($status === 'verified') ? 1 : 0;
        $this->db->query("UPDATE school_profiles SET verification_status = :status, is_verified = :is_verified WHERE profile_id = :school_id");
        $this->db->bind(':status', $status);
        $this->db->bind(':is_verified', $isVerified);
        $this->db->bind(':school_id', $school->profile_id);

        if ($this->db->execute()) {
            $school->verification_status = $status;
            $school->is_verified = $isVerified;
            return $school;
        }
        return false;
    }
}
?>
