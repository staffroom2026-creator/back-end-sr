<?php
/**
 * Admin Controller
 * Handles Admin Statistics and School Verifications
 */

class Admin extends Controller
{
    private $adminModel;
    private $profileModel;
    private $featureModel;

    public function __construct()
    {
        $this->adminModel = $this->model('AdminModel');
        $this->profileModel = $this->model('Profile');
        $this->featureModel = $this->model('Feature');
    }

    /**
     * GET /api/admin/stats
     */
    public function stats()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            ResponseHelper::error('Method Not Allowed', 405);
        }

        RoleMiddleware::allow('admin');
        $stats = $this->adminModel->getStats();
        ResponseHelper::success('System statistics fetched successfully', $stats, 200, ['stats' => $stats]);
    }

    /**
     * GET /api/admin/verifications or PATCH /api/admin/verifications/:id
     */
    public function verifications($id = null)
    {
        $method = $_SERVER['REQUEST_METHOD'];
        RoleMiddleware::allow('admin');

        if ($method === 'GET' && ($id === null || $id === '')) {
            $pending = $this->profileModel->getPendingVerifications();
            ResponseHelper::success('Pending verifications fetched successfully', $pending, 200, ['verifications' => $pending]);

        } elseif (($method === 'PATCH' || $method === 'PUT' || $method === 'POST') && ($id !== null && $id !== '')) {
            $data = $this->getData();
            $status = strtolower(trim($data['status'] ?? 'verified'));

            $allowedStatuses = ['pending', 'verified', 'rejected'];
            if (!in_array($status, $allowedStatuses)) {
                ResponseHelper::error('Invalid verification status. Allowed statuses: pending, verified, rejected', 400);
            }

            $school = $this->profileModel->verifySchool($id, $status);
            if (!$school) {
                ResponseHelper::error('School profile not found or verification failed', 404);
            }

            // Create notification when school is verified
            if ($status === 'verified') {
                $this->featureModel->createNotification(
                    $school->user_id,
                    'School Verified',
                    "Congratulations! Your school profile '{$school->school_name}' has been verified by the StaffRoom admin team.",
                    'system'
                );

                $schoolUser = $this->model('User')->findById($school->user_id);
                if ($schoolUser && !empty($schoolUser->email)) {
                    MailHelper::sendTemplate(
                        $schoolUser->email,
                        'School Verification Approved - StaffRoom',
                        'school_verified_email',
                        [
                            'SCHOOL_NAME' => $school->school_name ?: 'Your School',
                            'ADMIN_MESSAGE' => 'Your school profile has been verified and is now visible to teachers on StaffRoom.'
                        ]
                    );
                }
            } elseif ($status === 'rejected') {
                $this->featureModel->createNotification(
                    $school->user_id,
                    'Verification Rejected',
                    "Your school profile verification for '{$school->school_name}' was not approved. Please update your details and submit again.",
                    'system'
                );

                $schoolUser = $this->model('User')->findById($school->user_id);
                if ($schoolUser && !empty($schoolUser->email)) {
                    MailHelper::sendTemplate(
                        $schoolUser->email,
                        'School Verification Update - StaffRoom',
                        'school_rejected_email',
                        [
                            'SCHOOL_NAME' => $school->school_name ?: 'Your School',
                            'ADMIN_MESSAGE' => 'Please update your school details and resubmit the profile for review.'
                        ]
                    );
                }
            }

            ResponseHelper::success('School profile verification status updated to ' . $status, [
                'profile_id' => $school->profile_id ?? null,
                'user_id' => $school->user_id,
                'verification_status' => $status,
                'is_verified' => ($status === 'verified') ? 1 : 0
            ], 200, [
                'school' => $school
            ]);

        } else {
            ResponseHelper::error('Endpoint not found or method not allowed', 405);
        }
    }
}
?>
