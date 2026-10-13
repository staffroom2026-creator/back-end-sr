<?php
/**
 * Account Controller
 * Handles authenticated personal settings and security changes.
 */

require_once __DIR__ . '/../helpers/UploadHelper.php';

class Account extends Controller
{
    private $userModel;

    public function __construct()
    {
        $this->userModel = $this->model('User');
    }

    public function profile()
    {
        $user = AuthMiddleware::authenticate();
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            ResponseHelper::success('Account profile fetched successfully', $this->safeUser($user), 200, ['user' => $this->safeUser($user)]);
        }
        if ($_SERVER['REQUEST_METHOD'] === 'PUT' || $_SERVER['REQUEST_METHOD'] === 'PATCH') {
            $data = $this->getData();
            if (!array_key_exists('phone', $data) && array_key_exists('new_phone', $data)) $data['phone'] = $data['new_phone'];
            if (array_key_exists('new_email', $data)) {
                ResponseHelper::error('Use POST /api/account/email to request a verified email change', 400, ['new_email' => 'Email changes require verification']);
            }
            $updated = $this->userModel->updateAccount($user->user_id, $data);
            if (!$updated) {
                ResponseHelper::error('Failed to update account profile', 500);
            }
            $freshUser = $this->userModel->findById($user->user_id);
            ResponseHelper::success('Account profile updated successfully', $this->safeUser($freshUser), 200, ['user' => $this->safeUser($freshUser)]);
        }
        ResponseHelper::error('Method Not Allowed', 405);
    }

    public function profilePhoto()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            ResponseHelper::error('Method Not Allowed', 405);
        }
        $user = AuthMiddleware::authenticate();
        $file = $_FILES['profile_photo'] ?? ($_FILES['file'] ?? null);
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            ResponseHelper::error('No valid profile photo uploaded. Ensure field name is "profile_photo"', 400);
        }
        $photoUrl = UploadHelper::uploadLogo($file, $user->user_id);
        if (!$photoUrl || !$this->userModel->updateAccount($user->user_id, ['profile_photo' => $photoUrl])) {
            ResponseHelper::error('Failed to upload profile photo', 500);
        }
        $freshUser = $this->userModel->findById($user->user_id);
        ResponseHelper::success('Profile photo uploaded successfully', ['profile_photo' => $photoUrl, 'user' => $this->safeUser($freshUser)], 200);
    }

    public function password()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            ResponseHelper::error('Method Not Allowed', 405);
        }
        $user = AuthMiddleware::authenticate();
        $data = $this->getData();
        $currentPassword = $data['current_password'] ?? '';
        $newPassword = $data['new_password'] ?? '';
        $confirmPassword = $data['confirm_password'] ?? '';
        if (!password_verify($currentPassword, $user->password ?? '')) {
            ResponseHelper::error('Current password is incorrect', 401);
        }
        if (strlen($newPassword) < 8 || !preg_match('/[A-Za-z]/', $newPassword) || !preg_match('/\d/', $newPassword)) {
            ResponseHelper::error('Password must be at least 8 characters and contain a letter and a number', 400);
        }
        if ($newPassword !== $confirmPassword) {
            ResponseHelper::error('Passwords do not match', 400);
        }
        if (!$this->userModel->updatePasswordByUserId($user->user_id, $newPassword)) {
            ResponseHelper::error('Failed to change password', 500);
        }
        ResponseHelper::success([], 'Password changed successfully', 200);
    }

    public function preferences()
    {
        $user = AuthMiddleware::authenticate();
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $preferences = json_decode($user->notification_preferences ?? '{}', true) ?: [];
            ResponseHelper::success('Notification preferences fetched successfully', $preferences, 200, ['preferences' => $preferences]);
        }
        if ($_SERVER['REQUEST_METHOD'] === 'PUT' || $_SERVER['REQUEST_METHOD'] === 'PATCH') {
            $data = $this->getData();
            if (!$this->userModel->updateNotificationPreferences($user->user_id, $data)) {
                ResponseHelper::error('Failed to save notification preferences', 500);
            }
            ResponseHelper::success('Notification preferences saved successfully', $data, 200, ['preferences' => $data]);
        }
        ResponseHelper::error('Method Not Allowed', 405);
    }

    public function email()
    {
        $user = AuthMiddleware::authenticate();
        $data = $this->getData();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $email = trim($data['email'] ?? '');
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                ResponseHelper::error('A valid email address is required', 400);
            }
            if ($this->userModel->findByEmail($email)) {
                ResponseHelper::error('Email is already registered', 409);
            }
            $code = random_int(100000, 999999);
            $expiresAt = date('Y-m-d H:i:s', time() + 600);
            if (!$this->userModel->requestEmailChange($user->user_id, $email, password_hash((string)$code, PASSWORD_BCRYPT), $expiresAt)) {
                ResponseHelper::error('Failed to start email change', 500);
            }
            $this->sendOTPEmail(['email' => $email, 'otp' => $code]);
            ResponseHelper::success([], 'A verification code has been sent to your new email address', 200);
        }

        if ($_SERVER['REQUEST_METHOD'] === 'PATCH') {
            $code = trim($data['code'] ?? '');
            if (!preg_match('/^\d{6}$/', $code)) {
                ResponseHelper::error('Verification code must be 6 digits', 400);
            }
            $pendingUser = $this->userModel->findById($user->user_id);
            if (empty($pendingUser->pending_email) || empty($pendingUser->account_change_code) ||
                (!empty($pendingUser->account_change_expires_at) && strtotime($pendingUser->account_change_expires_at) < time()) ||
                !password_verify($code, $pendingUser->account_change_code)) {
                ResponseHelper::error('Invalid or expired verification code', 400);
            }
            if (!$this->userModel->completeEmailChange($user->user_id)) {
                ResponseHelper::error('Failed to change email address', 500);
            }
            ResponseHelper::success([], 'Email successfully changed', 200);
        }

        ResponseHelper::error('Method Not Allowed', 405);
    }

    public function loginActivity()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            ResponseHelper::error('Method Not Allowed', 405);
        }

        $user = AuthMiddleware::authenticate();
        $limit = isset($_GET['limit']) ? max(1, min((int)$_GET['limit'], 50)) : 10;
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $offset = ($page - 1) * $limit;

        $items = $this->userModel->getLoginActivity($user->user_id, $limit, $offset);

        ResponseHelper::success('Login activity fetched successfully', [
            'items' => $items,
            'limit' => $limit,
            'page' => $page,
            'total' => count($items)
        ], 200, ['login_activity' => $items]);
    }

    private function safeUser($user)
    {
        $data = (array)$user;
        unset($data['password'], $data['email_verification_code'], $data['password_reset_token']);
        unset($data['pending_email'], $data['account_change_code'], $data['account_change_expires_at']);
        unset($data['id']);
        return $data;
    }
}
?>
