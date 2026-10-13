<?php

class User
{
    private $db;

    public function __construct()
    {
        $this->db = new Database();
    }

    /**
     * Find user by email
     *
     * @param string $email
     * @return object|false
     */
    public function findByEmail($email)
    {
        $this->db->query("SELECT * FROM initkey WHERE email = :email");
        $this->db->bind(':email', $email);
        $row = $this->db->single();
        return ($this->db->rowCount() > 0) ? $row : false;
    }
 public function findByEmail2($email)
{
    $this->db->query("SELECT * FROM initkey WHERE email LIKE :email");
    $this->db->bind(':email', "%{$email}%");

    return $this->db->single() ?: false;
}

    /**
     * Find user by ID (user_id)
     *
     * @param string $id
     * @return object|false
     */
    public function findById($id)
    {
        $this->db->query("SELECT user_id, full_name, first_name, last_name, name, email, phone, profile_photo, password, role, status, active, deleted_at, email_verified_at, notification_preferences, pending_email, account_change_code, account_change_expires_at, created_at, updated_at FROM initkey WHERE user_id = :user_id");
        $this->db->bind(':user_id', $id);
        $row = $this->db->single();
        return ($this->db->rowCount() > 0) ? $row : false;
    }

    /**
     * Find user by user_id
     *
     * @param string $userId
     * @return object|false
     */
    public function findByUserId($userId)
    {
        return $this->findById($userId);
    }

    /**
     * Register a new user and initialize role-specific profile
     *
     * @param array $data
     * @return array|false
     */
    public function register($data)
    {
        
      
        $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
        $role = trim($data['role']);
        $fullName = trim($data['full_name'] ?? ($data['name'] ?? ''));
          $firstName = trim($data['first_name']);
        $lastName = trim($data['last_name']);
        $terms = trim($data['terms'] ?? '');
        $phone = trim($data['phone'] ?? '');

        if (!in_array($role, ['teacher', 'school'], true)) {
            return false;
        }

        // Generate secure 6-digit verification code and hash it
        $verificationCode = random_int(100000, 999999);
        $hashedCode = password_hash((string)$verificationCode, PASSWORD_BCRYPT);
        $expiresAt = date('Y-m-d H:i:s', time() + 600); // 10 minutes from now

        // Generate unique user_id (e.g. USR-2026-000001)
        do {
            $userId = $this->generateUniqueId();
            $this->db->query("SELECT user_id FROM initkey WHERE user_id = :user_id");
            $this->db->bind(':user_id', $userId);
            $this->db->single();
        } while ($this->db->rowCount() > 0);

            //   echo json_encode($userId);exit;
        $this->db->beginTransaction();

        // Insert into initkey table with verification code and pending status
        $this->db->query("INSERT INTO initkey (user_id, full_name,first_name,last_name,terms, name, email, phone, password, role, status, active, email_verification_code, email_verification_expires_at, email_verification_attempts) VALUES (:user_id, :full_name, :first_name, :last_name, :terms, :name, :email, :phone, :password, :role, 'pending', 1, :code, :expires, 0)");
        $this->db->bind(':user_id', $userId);
        $this->db->bind(':full_name', $fullName);
        $this->db->bind(':first_name', $data['first_name']);
        $this->db->bind(':last_name', $data['last_name']);
        $this->db->bind(':terms', $terms);
        $this->db->bind(':name', $fullName);
        $this->db->bind(':email', trim($data['email']));
        $this->db->bind(':phone', $phone);
        $this->db->bind(':password', $hashedPassword);
        $this->db->bind(':role', $role);
        $this->db->bind(':code', $hashedCode);
        $this->db->bind(':expires', $expiresAt);
        // echo json_encode($this->db->execute());exit;
        if (!$this->db->execute()) {
            $this->db->rollBack();
            return false;
        }
//   echo json_encode($data);exit;
        // Initialize corresponding profile
        $profile_id = $this->generateUniqueId();
        if ($role === 'teacher') {
            $this->db->query("INSERT INTO teacher_profiles (profile_id, user_id, phone) VALUES (:profile_id, :user_id, :phone)");
            $this->db->bind(':profile_id', $profile_id);
            $this->db->bind(':user_id', $userId);
            $this->db->bind(':phone', $phone);
            if (!$this->db->execute()) {
                $this->db->rollBack();
                return false;
            }
        } elseif ($role === 'school') {
            $profile_id = $this->generateUniqueId();
            $schoolName = !empty($fullName) ? $fullName : 'New School';
            $schoolType = !empty($data['school_type']) ? $data['school_type'] : 'secondary';
            $this->db->query("INSERT INTO school_profiles (profile_id, user_id, school_name, school_type) VALUES (:profile_id, :user_id, :school_name, :school_type)");
            $this->db->bind(':profile_id', $profile_id);
            $this->db->bind(':user_id', $userId);
            $this->db->bind(':school_name', $schoolName);
            $this->db->bind(':school_type', $schoolType);
            if (!$this->db->execute()) {
                $this->db->rollBack();
                return false;
            }
        }

        $this->db->commit();

        return [
            'user_id' => $userId,
            'code' => (string)$verificationCode
        ];
    }
    
    
    public function register_sch($data)
    {
        
      
        $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
        $role = trim($data['role']);
        $fullName = trim($data['full_name'] ?? ($data['name'] ?? ''));
        $terms = trim($data['terms'] ?? '');
        $phone = trim($data['phone'] ?? '');

        if (!in_array($role, ['teacher', 'school'], true)) {
            return false;
        }

        // Generate secure 6-digit verification code and hash it
        $verificationCode = random_int(100000, 999999);
        $hashedCode = password_hash((string)$verificationCode, PASSWORD_BCRYPT);
        $expiresAt = date('Y-m-d H:i:s', time() + 600); // 10 minutes from now

        // Generate unique user_id (e.g. USR-2026-000001)
        do {
            $userId = $this->generateUniqueId();
            $this->db->query("SELECT user_id FROM initkey WHERE user_id = :user_id");
            $this->db->bind(':user_id', $userId);
            $this->db->single();
        } while ($this->db->rowCount() > 0);

            //   echo json_encode($userId);exit;
        $this->db->beginTransaction();

        // Insert into initkey table with verification code and pending status
        $this->db->query("INSERT INTO initkey (user_id, full_name,terms, name, email, phone, password, role, status, active, email_verification_code, email_verification_expires_at, email_verification_attempts) VALUES (:user_id, :full_name, :terms, :name, :email, :phone, :password, :role, 'pending', 1, :code, :expires, 0)");
        $this->db->bind(':user_id', $userId);
        $this->db->bind(':full_name', $fullName);
        $this->db->bind(':terms', $terms);
        $this->db->bind(':name', $fullName);
        $this->db->bind(':email', trim($data['email']));
        $this->db->bind(':phone', $phone);
        $this->db->bind(':password', $hashedPassword);
        $this->db->bind(':role', $role);
        $this->db->bind(':code', $hashedCode);
        $this->db->bind(':expires', $expiresAt);
        // echo json_encode($this->db->execute());exit;
        if (!$this->db->execute()) {
            $this->db->rollBack();
            return false;
        }
//   echo json_encode($data);exit;
        // Initialize corresponding profile
        if ($role === 'teacher') {
            $profile_id = $this->generateUniqueId();
            $this->db->query("INSERT INTO teacher_profiles (profile_id, user_id, phone) VALUES (:profile_id, :user_id, :phone)");
            $this->db->bind(':profile_id', $profile_id);
            $this->db->bind(':user_id', $userId);
            $this->db->bind(':phone', $phone);
            if (!$this->db->execute()) {
                $this->db->rollBack();
                return false;
            }
        } elseif ($role === 'school') {
            $profile_id = $this->generateUniqueId();
            $schoolName = !empty($fullName) ? $fullName : 'New School';
            $schoolType = !empty($data['school_type']) ? $data['school_type'] : 'secondary';
            $this->db->query("INSERT INTO school_profiles (profile_id, user_id, school_name, school_type) VALUES (:profile_id, :user_id, :school_name, :school_type)");
            $this->db->bind(':profile_id', $profile_id);
            $this->db->bind(':user_id', $userId);
            $this->db->bind(':school_name', $schoolName);
            $this->db->bind(':school_type', $schoolType);
            if (!$this->db->execute()) {
                $this->db->rollBack();
                return false;
            }
        }

        $this->db->commit();

        return [
            'user_id' => $userId,
            'code' => (string)$verificationCode
        ];
    }

    /**
     * Soft delete a user account without removing related data.
     *
     * @param string $userId
     * @return bool
     */
    public function softDeleteUser($userId)
    {
        $this->db->query("UPDATE initkey SET status = 'deleted', deleted_at = NOW(), active = 0, email_verification_code = NULL, email_verification_expires_at = NULL WHERE user_id = :user_id");
        $this->db->bind(':user_id', $userId);
        return $this->db->execute();
    }

    /**
     * Update user verification code and expiry
     *
     * @param string $userId
     * @param string $hashedCode
     * @param string $expiresAt
     * @return bool
     */
    public function updateVerificationCode($userId, $hashedCode, $expiresAt)
    {
        $this->db->query("UPDATE initkey SET email_verification_code = :code, email_verification_expires_at = :expires, email_verification_attempts = 0 WHERE user_id = :user_id");
        $this->db->bind(':code', $hashedCode);
        $this->db->bind(':expires', $expiresAt);
        $this->db->bind(':user_id', $userId);
        return $this->db->execute();
    }
    
    
  public function updateResetToken($data)
{
    $tokenTime = 10;

    $dateTime = new DateTime();

    $currentDate = $dateTime->format('Y-m-d H:i:s');

    $dateTime->modify("+{$tokenTime} minutes");

    $passwordResetExpires = $dateTime->format('Y-m-d H:i:s');

    $this->db->query('
        UPDATE initkey 
        SET 
            password_reset_token = :password_reset_token,
            password_reset_expires = :password_reset_expires
        WHERE email = :email
    ');

    $this->db->bind(':email', $data['email']);
    $this->db->bind(':password_reset_token', password_hash((string)$data['otp'], PASSWORD_DEFAULT));
    // $this->db->bind(':reset_token_set_date', $currentDate);
    $this->db->bind(':password_reset_expires', $passwordResetExpires);

    return $this->db->execute();
}

    public function clearResetToken($userId)
    {
        $this->db->query("UPDATE initkey SET password_reset_token = NULL, password_reset_expires = NULL WHERE user_id = :user_id");
        $this->db->bind(':user_id', $userId);
        return $this->db->execute();
    }
    /**
     * Increment verification attempts
     *
     * @param string $userId
     * @return bool
     */
    public function incrementVerificationAttempts($userId)
    {
        $this->db->query("UPDATE initkey SET email_verification_attempts = email_verification_attempts + 1 WHERE user_id = :user_id");
        $this->db->bind(':user_id', $userId);
        return $this->db->execute();
    }

    /**
     * Mark user email as verified
     *
     * @param string $userId
     * @return bool
     */
    public function verifyUserEmail($userId)
    {
        $this->db->query("UPDATE initkey SET email_verified_at = :verified_at, email_verification_code = NULL, email_verification_expires_at = NULL, email_verification_attempts = 0, status = 'active', deleted_at = NULL WHERE user_id = :user_id");
        $this->db->bind(':verified_at', date('Y-m-d H:i:s'));
        $this->db->bind(':user_id', $userId);
        return $this->db->execute();
    }

    /**
     * Update basic user info
     *
     * @param string $id
     * @param string $name
     * @return bool
     */
    public function updateName($id, $name)
    {
        $this->db->query("UPDATE initkey SET name = :name, full_name = :name WHERE user_id = :id");
        $this->db->bind(':name', trim($name));
        $this->db->bind(':id', $id);
        return $this->db->execute();
    }

    public function updateAccount($userId, $data)
    {
        $fields = [];
        if (isset($data['first_name'])) $fields[] = 'first_name = :first_name';
        if (isset($data['last_name'])) $fields[] = 'last_name = :last_name';
        if (isset($data['full_name'])) $fields[] = 'full_name = :full_name';
        if (isset($data['phone'])) $fields[] = 'phone = :phone';
        if (isset($data['profile_photo'])) $fields[] = 'profile_photo = :profile_photo';
        if (empty($fields)) return true;

        $this->db->query('UPDATE initkey SET ' . implode(', ', $fields) . ' WHERE user_id = :user_id');
        $this->db->bind(':user_id', $userId);
        if (isset($data['first_name'])) $this->db->bind(':first_name', trim($data['first_name']));
        if (isset($data['last_name'])) $this->db->bind(':last_name', trim($data['last_name']));
        if (isset($data['full_name'])) $this->db->bind(':full_name', trim($data['full_name']));
        if (isset($data['phone'])) $this->db->bind(':phone', trim($data['phone']));
        if (isset($data['profile_photo'])) $this->db->bind(':profile_photo', $data['profile_photo']);
        return $this->db->execute();
    }

    public function updatePasswordByUserId($userId, $password)
    {
        $this->db->query('UPDATE initkey SET password = :password WHERE user_id = :user_id');
        $this->db->bind(':password', password_hash($password, PASSWORD_DEFAULT));
        $this->db->bind(':user_id', $userId);
        return $this->db->execute();
    }

    public function updateNotificationPreferences($userId, $preferences)
    {
        $this->db->query('UPDATE initkey SET notification_preferences = :preferences WHERE user_id = :user_id');
        $this->db->bind(':preferences', json_encode($preferences));
        $this->db->bind(':user_id', $userId);
        return $this->db->execute();
    }

    public function requestEmailChange($userId, $email, $hashedCode, $expiresAt)
    {
        $this->db->query('UPDATE initkey SET pending_email = :pending_email, account_change_code = :code, account_change_expires_at = :expires_at WHERE user_id = :user_id');
        $this->db->bind(':pending_email', $email);
        $this->db->bind(':code', $hashedCode);
        $this->db->bind(':expires_at', $expiresAt);
        $this->db->bind(':user_id', $userId);
        return $this->db->execute();
    }

    public function completeEmailChange($userId)
    {
        $this->db->query('UPDATE initkey SET email = pending_email, pending_email = NULL, account_change_code = NULL, account_change_expires_at = NULL WHERE user_id = :user_id AND pending_email IS NOT NULL');
        $this->db->bind(':user_id', $userId);
        return $this->db->execute();
    }

    /**
     * Record a successful login for dashboard and account activity tracking.
     *
     * @param string $userId
     * @param array $meta
     * @return bool
     */
    public function logSuccessfulLogin($userId, $meta = [])
    {
        $activityId = $this->generateUniqueId();
        $ipAddress = trim((string)($meta['ip_address'] ?? $meta['ip'] ?? ''));
        $userAgent = trim((string)($meta['user_agent'] ?? ''));
        $deviceName = trim((string)($meta['device_name'] ?? ($meta['device'] ?? 'web')));
        $location = trim((string)($meta['location'] ?? ''));

        $this->db->query('INSERT INTO user_login_activity (id, user_id, login_at, ip_address, user_agent, device_name, location, created_at) VALUES (:id, :user_id, NOW(), :ip_address, :user_agent, :device_name, :location, NOW())');
        $this->db->bind(':id', $activityId);
        $this->db->bind(':user_id', $userId);
        $this->db->bind(':ip_address', $ipAddress !== '' ? $ipAddress : null);
        $this->db->bind(':user_agent', $userAgent !== '' ? $userAgent : null);
        $this->db->bind(':device_name', $deviceName !== '' ? $deviceName : 'web');
        $this->db->bind(':location', $location !== '' ? $location : null);

        return $this->db->execute();
    }

    /**
     * Fetch a user's recent login activity.
     *
     * @param string $userId
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function getLoginActivity($userId, $limit = 10, $offset = 0)
    {
        $limit = max(1, min((int)$limit, 50));
        $offset = max(0, (int)$offset);

        $this->db->query('SELECT id, user_id, login_at, ip_address, user_agent, device_name, location FROM user_login_activity WHERE user_id = :user_id ORDER BY login_at DESC, created_at DESC LIMIT :limit OFFSET :offset');
        $this->db->bind(':user_id', $userId);
        $this->db->bind(':limit', $limit, PDO::PARAM_INT);
        $this->db->bind(':offset', $offset, PDO::PARAM_INT);

        $rows = $this->db->resultSet();
        return is_array($rows) ? $rows : [];
    }
    
        public function generateUniqueId()
    {
        // Generate a UUID
        $uuid = uniqid('', true);

        // Get the current timestamp
        $timestamp = microtime(true);

        // Hash them together to ensure uniqueness
        $uniqueId = hash('sha256', $uuid . $timestamp);

        return $uniqueId;
    }
}
?>