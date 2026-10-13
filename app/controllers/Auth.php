<?php
/**
 * Auth Controller
 * Handles POST /api/auth/register and POST /api/auth/login
 */

// error_reporting(E_ALL);
// ini_set('display_errors', '1');
// ini_set('display_startup_errors', '1');

if (!class_exists('AuthMiddleware')) {
    require_once __DIR__ . '/../middleware/AuthMiddleware.php';
}

class Auth extends Controller
{
    private $userModel;
    private $profileModel;

    public function __construct()
    {
        $this->userModel = $this->model('User');
        $this->profileModel = $this->model('Profile');
    }

    /**
     * Format user object (excludes sensitive fields like password and token)
     *
     * @param object $user
     * @return array
     */
    private function formatUser($user)
    {
        return [
            'user_id' => $user->user_id,
            'full_name' => !empty($user->full_name) ? $user->full_name : ($user->name ?? ''),
            'email' => $user->email,
            'email_verified' => !empty($user->email_verified_at),
            'phone' => !empty($user->phone) ? $user->phone : '',
            'profile_photo' => $user->profile_photo ?? null,
            'role' => $user->role,
            'status' => $user->status ?? ($user->active == 1 ? 'active' : 'inactive'),
            'created_at' => $user->created_at ?? date('Y-m-d H:i:s'),
            'updated_at' => $user->updated_at ?? date('Y-m-d H:i:s')
        ];
    }

    private function getJwtExpirySeconds()
    {
        $value = env('JWT_EXPIRES_IN', '1d');
        if (is_numeric($value)) {
            return (int)$value;
        }

        $normalized = strtolower(trim((string)$value));
        if (preg_match('/^(\d+)\s*(s|sec|secs|second|seconds)?$/', $normalized, $matches)) {
            return (int)$matches[1];
        }
        if (preg_match('/^(\d+)\s*(m|min|mins|minute|minutes)$/', $normalized, $matches)) {
            return (int)$matches[1] * 60;
        }
        if (preg_match('/^(\d+)\s*(h|hr|hrs|hour|hours)$/', $normalized, $matches)) {
            return (int)$matches[1] * 3600;
        }
        if (preg_match('/^(\d+)\s*(d|day|days)$/', $normalized, $matches)) {
            return (int)$matches[1] * 86400;
        }

        return 86400;
    }

        public function forgetPassword()
        {
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') ResponseHelper::error('Method Not Allowed', 405);
                $data = $this->getData(true);
                if (!empty($data['__invalid_json'])) ResponseHelper::error('Malformed JSON request body', 400);
                $email = trim($data['email'] ?? '');
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) ResponseHelper::error('A valid email address is required', 400, ['email' => 'Invalid email']);
                $user = $this->userModel->findByEmail($email);
                if (!$user || !empty($user->deleted_at) || ($user->status ?? '') === 'deleted') ResponseHelper::error('No active account was found for this email', 404);

                $otp = (string)random_int(100000, 999999);
                if (!$this->userModel->updateResetToken(['email' => $email, 'otp' => $otp])) ResponseHelper::error('Could not start password reset', 500);
                if (!$this->passresetemailer(['email' => $email, 'otp' => $otp])) ResponseHelper::error('Could not send password reset email', 502);
                ResponseHelper::success(['email' => $email, 'otp_expires_in' => 600], 'A password reset OTP has been sent to your email.', 200);
                }

        public function verifyResetOtp()
        {
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') ResponseHelper::error('Method Not Allowed', 405);
                $data = $this->getData(true);
                if (!empty($data['__invalid_json'])) ResponseHelper::error('Malformed JSON request body', 400);
                $email = trim($data['email'] ?? '');
                $otp = trim((string)($data['otp'] ?? ''));
                if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/^\d{6}$/', $otp)) ResponseHelper::error('Email and a 6-digit OTP are required', 400);
                $user = $this->userModel->findByEmail($email);
                if (!$user || empty($user->password_reset_token) || empty($user->password_reset_expires) || strtotime($user->password_reset_expires) < time() || !password_verify($otp, $user->password_reset_token)) ResponseHelper::error('Invalid or expired OTP', 401);

                $refreshToken = JWT::encode(['user_id' => (string)$user->user_id, 'purpose' => 'password_reset', 'exp' => time() + 600], JWT_SECRET_KEY);
                ResponseHelper::success(['refresh_token' => $refreshToken, 'expires_in' => 600], 'OTP verified successfully. Use the refresh token to set a new password.', 200);
        }

        public function resetPassword()
        {
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') ResponseHelper::error('Method Not Allowed', 405);
                $data = $this->getData(true);
                if (!empty($data['__invalid_json'])) ResponseHelper::error('Malformed JSON request body', 400);
                $token = trim($data['refresh_token'] ?? '');
                $password = (string)($data['new_password'] ?? $data['password'] ?? '');
                $confirmation = (string)($data['confirm_password'] ?? '');
                if ($token === '' || $password === '' || $confirmation === '') ResponseHelper::error('refresh_token, new_password, and confirm_password are required', 400);
                if (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) ResponseHelper::error('Password must be at least 8 characters and contain a letter and a number', 400);
                if ($password !== $confirmation) ResponseHelper::error('Passwords do not match', 400);
                try { $claims = JWT::decode($token, JWT_SECRET_KEY); } catch (Throwable $error) { ResponseHelper::error('Invalid or expired refresh token', 401); }
                if (!$claims || ($claims->purpose ?? '') !== 'password_reset' || empty($claims->user_id)) ResponseHelper::error('Invalid password reset token', 401);
                $user = $this->userModel->findById($claims->user_id);
                if (!$user || !$this->userModel->updatePasswordByUserId($user->user_id, $password)) ResponseHelper::error('Password reset failed', 500);
                $this->userModel->clearResetToken($user->user_id);
                ResponseHelper::success([], 'Password reset successful', 200);
        }

    /**
     * POST /api/auth/register
     */
    public function register()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            ResponseHelper::error('Method Not Allowed', 405);
        }

        $data = $this->getData();
        

        $fullName = trim($data['full_name'] ?? ($data['name'] ?? ''));
        $firstName = trim($data['first_name']);
        $lastName = trim($data['last_name']);
        $email = trim($data['email'] ?? '');
        $terms = trim($data['terms'] ?? '');
        $phone = trim($data['phone'] ?? '');
        $password = $data['password'] ?? '';
        $role = trim($data['role'] ?? '');

        // Validation rules
        if (empty($terms)) {
            ResponseHelper::error('terms and conditions is required', 400, ['terms' => 'terms and condition is required']);
        }
        if (empty($firstName)) {
            ResponseHelper::error('first_name is required', 400, ['first_name' => 'first_name is required']);
        }
        if (empty($lastName)) {
            ResponseHelper::error('last_name is required', 400, ['last_name' => 'last_name is required']);
        }
        if (empty($fullName)) {
            
            $fullName = $firstName . " " . $lastName;
        }
        if (empty($email)) {
            ResponseHelper::error('email is required', 400, ['email' => 'email is required']);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            ResponseHelper::error('Invalid email format', 400, ['email' => 'Invalid email format']);
        }
        if (empty($phone)) {
            ResponseHelper::error('phone is required', 400, ['phone' => 'phone is required']);
        }
        if (empty($password)) {
            ResponseHelper::error('password is required', 400, ['password' => 'password is required']);
        }
        if (strlen($password) < 6) {
            ResponseHelper::error('password must be at least 6 characters', 400, ['password' => 'password must be at least 6 characters']);
        }
        if (empty($role)) {
            ResponseHelper::error('role is required', 400, ['role' => 'role is required']);
        }
        if ($role === 'admin') {
            ResponseHelper::error('Invalid account type selected.', 400);
        }
        if (!in_array($role, ['teacher', 'school'], true)) {
            ResponseHelper::error('Invalid role specified. Must be teacher or school.', 400, ['role' => 'Must be teacher or school']);
        }

        // Check email uniqueness
        if ($this->userModel->findByEmail($email)) {
            ResponseHelper::error('Email is already registered', 400, ['email' => 'Email is already registered']);
        }

        // Register user (automatically hashes password, generates verification code, and creates empty role profile)
        $regResult = $this->userModel->register($data);

        if (!$regResult || !isset($regResult['user_id'])) {
            ResponseHelper::error('Registration failed. Please try again.', 500);
        }

        $userId = $regResult['user_id'];
        $code = $regResult['code'];

        $this->sendOTPEmail(['email' => $email, 'otp' => $code]);

        $user = $this->userModel->findById($userId);
        $userObj = $this->formatUser($user);

        ResponseHelper::success([
            'user' => $userObj,
            'email_verified' => false
        ], 'Registration successful. Please check your email for the 6-digit verification code.', 201);
    }
    
    
    public function register_sch()
    { 
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            ResponseHelper::error('Method Not Allowed', 405);
        }

        $data = $this->getData();
        

        $fullName = trim($data['full_name'] ?? ($data['name'] ?? ''));
        $email = trim($data['email'] ?? '');
        $terms = trim($data['terms'] ?? '');
        $phone = trim($data['phone'] ?? '');
        $password = $data['password'] ?? '';
        $role = trim($data['role'] ?? '');

        // Validation rules
        if (empty($terms)) {
            ResponseHelper::error('terms and conditions is required', 400, ['terms' => 'terms and condition is required']);
        }

        if (empty($email)) {
            ResponseHelper::error('email is required', 400, ['email' => 'email is required']);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            ResponseHelper::error('Invalid email format', 400, ['email' => 'Invalid email format']);
        }
        if (empty($phone)) {
            ResponseHelper::error('phone is required', 400, ['phone' => 'phone is required']);
        }
        if (empty($password)) {
            ResponseHelper::error('password is required', 400, ['password' => 'password is required']);
        }
        if (strlen($password) < 6) {
            ResponseHelper::error('password must be at least 6 characters', 400, ['password' => 'password must be at least 6 characters']);
        }
        if (empty($role)) {
            ResponseHelper::error('role is required', 400, ['role' => 'role is required']);
        }
        if ($role === 'admin') {
            ResponseHelper::error('Invalid account type selected.', 400);
        }
        if (!in_array($role, ['teacher', 'school'], true)) {
            ResponseHelper::error('Invalid role specified. Must be teacher or school.', 400, ['role' => 'Must be teacher or school']);
        }

        // Check email uniqueness
        if ($this->userModel->findByEmail($email)) {
            ResponseHelper::error('Email is already registered', 400, ['email' => 'Email is already registered']);
        }

        // Register user (automatically hashes password, generates verification code, and creates empty role profile)
        $regResult = $this->userModel->register_sch($data);

        if (!$regResult || !isset($regResult['user_id'])) {
            ResponseHelper::error('Registration failed. Please try again.', 500);
        }

        $userId = $regResult['user_id'];
        $code = $regResult['code'];

        $this->sendOTPEmail(['email' => $email, 'otp' => $code]);

        $user = $this->userModel->findById($userId);
        $userObj = $this->formatUser($user);

        ResponseHelper::success([
            'user' => $userObj,
            'email_verified' => false
        ], 'Registration successful. Please check your email for the 6-digit verification code.', 201);
    }

    /**
     * POST /api/auth/login
     */
    public function login()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            ResponseHelper::error('Method Not Allowed', 405);
        }

        $data = $this->getData();

        $email = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';

        if (empty($email) || empty($password)) {
            ResponseHelper::error('Email and password are required', 400);
        }

        $user = $this->userModel->findByEmail($email);
        if (!$user) {
            ResponseHelper::error('Invalid email or password', 401);
        }

        if (!password_verify($password, $user->password)) {
            ResponseHelper::error('Invalid email or password', 401);
        }

        if (!empty($user->deleted_at) || strtolower((string)($user->status ?? '')) === 'deleted') {
            ResponseHelper::error('This account has been deleted.', 403);
        }

        if ((int)($user->active ?? 1) !== 1 || in_array(strtolower((string)($user->status ?? 'active')), ['inactive', 'suspended'])) {
            ResponseHelper::error('This account is inactive.', 403);
        }

        if (empty($user->email_verified_at)) {
            ResponseHelper::error('Please verify your email before logging in.', 403);
        }

        $tokenPayload = [
            'user_id' => (string)$user->user_id,
            'email' => $user->email,
            'role' => $user->role,
            'exp' => time() + $this->getJwtExpirySeconds()
        ];

        $token = JWT::encode($tokenPayload, JWT_SECRET_KEY);
        $this->userModel->logSuccessfulLogin($user->user_id, [
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'device_name' => 'web',
            'location' => null
        ]);
        $userObj = $this->formatUser($user);

        $setupCompleted = true;
        if ($user->role === 'school') {
            $schoolProfile = $this->profileModel->getSchoolProfileByUserId($user->user_id);
            $setupCompleted = (bool)($schoolProfile->setup_completed ?? false);
        }

        ResponseHelper::success([
            'user' => $userObj,
            'token' => $token,
            'setup_completed' => $setupCompleted,
            'onboarding_required' => !$setupCompleted
        ], 'Login successful', 200);
    }

    /**
     * DELETE /api/auth/delete-account
     */
    public function delete_account()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
            ResponseHelper::error('Method Not Allowed', 405);
        }

        $authUser = $this->getAuthenticatedUser();
        if (!$authUser || empty($authUser->user_id)) {
            ResponseHelper::error('Unauthorized: User not found', 401);
        }

        $data = $this->getData();
        $password = $data['password'] ?? null;
        if ($password !== null && $password !== '' && !password_verify($password, $authUser->password ?? '')) {
            ResponseHelper::error('Incorrect password.', 401);
        }

        $softDeleted = $this->userModel->softDeleteUser($authUser->user_id);
        if (!$softDeleted) {
            ResponseHelper::error('Failed to delete account. Please try again.', 500);
        }

        ResponseHelper::success([], 'Account deleted successfully.', 200);
    }

    /**
     * POST /api/auth/verify-email
     */
    public function verify_email()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            ResponseHelper::error('Method Not Allowed', 405);
        }

        $data = $this->getData();
        $email = trim($data['email'] ?? '');
        $code = trim($data['code'] ?? '');

        if (empty($email) || empty($code)) {
            ResponseHelper::error('Email and verification code are required', 400);
        }

        if (!preg_match('/^\d{6}$/', $code)) {
            ResponseHelper::error('Invalid verification code format. Must be 6 digits.', 400);
        }

        $user = $this->userModel->findByEmail($email);
        if (!$user) {
            ResponseHelper::error('User not found.', 404);
        }

        if (!empty($user->email_verified_at)) {
            ResponseHelper::success([
                'user_id' => $user->user_id,
                'email_verified' => true
            ], 'Email is already verified.', 200);
        }

        if (empty($user->email_verification_code)) {
            ResponseHelper::error('No verification code found. Please request a new code.', 400);
        }

        if (isset($user->email_verification_attempts) && (int)$user->email_verification_attempts >= 5) {
            ResponseHelper::error('Too many failed verification attempts. Please request a new verification code.', 429);
        }

        if (!empty($user->email_verification_expires_at) && strtotime($user->email_verification_expires_at) < time()) {
            ResponseHelper::error('Verification code has expired. Please request a new code.', 400);
        }

        if (!password_verify((string)$code, $user->email_verification_code)) {
            $this->userModel->incrementVerificationAttempts($user->user_id);
            ResponseHelper::error('Invalid verification code.', 400);
        }

        $verified = $this->userModel->verifyUserEmail($user->user_id);
        if (!$verified) {
            ResponseHelper::error('Failed to verify email. Please try again.', 500);
        }

        // Send welcome email on verification
        $this->welcome_emailer(['email' => $user->email], 'Welcome to StaffRoom!');

        ResponseHelper::success([
            'user_id' => $user->user_id,
            'email_verified' => true
        ], 'Email verified successfully', 200);
    }

    /**
     * POST /api/auth/resend-verification
     */
    public function resend_verification()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            ResponseHelper::error('Method Not Allowed', 405);
        }

        $data = $this->getData();
        $email = trim($data['email'] ?? '');

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            ResponseHelper::error('A valid email address is required', 400);
        }

        $user = $this->userModel->findByEmail($email);
        if (!$user) {
            // Return generic message for security
            ResponseHelper::success([], 'If your email address is registered, a new verification code has been sent.', 200);
        }

        if (!empty($user->email_verified_at)) {
            ResponseHelper::success([
                'user_id' => $user->user_id,
                'email_verified' => true
            ], 'Email is already verified.', 200);
        }

        $verificationCode = random_int(100000, 999999);
        $hashedCode = password_hash((string)$verificationCode, PASSWORD_BCRYPT);
        $expiresAt = date('Y-m-d H:i:s', time() + 600); // 10 minutes from now

        $updated = $this->userModel->updateVerificationCode($user->user_id, $hashedCode, $expiresAt);
        if (!$updated) {
            ResponseHelper::error('Failed to generate verification code. Please try again later.', 500);
        }

        $this->sendOTPEmail(['email' => $user->email, 'otp' => $verificationCode]);

        ResponseHelper::success([], 'A new 6-digit verification code has been sent to your email address.', 200);
    }
}
?>
