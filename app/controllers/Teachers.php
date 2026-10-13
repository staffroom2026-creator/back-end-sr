<?php
/**
 * Teachers Controller
 * Handles school-side teacher discovery and invitations.
 */

class Teachers extends Controller
{
    private $teacherModel;
    private $featureModel;
    private $profileViewModel;
    private $profileModel;
    private $userModel;

    public function __construct()
    {
        $this->teacherModel = $this->model('TeacherDirectory');
        $this->featureModel = $this->model('Feature');
        $this->profileViewModel = $this->model('TeacherProfileView');
        $this->profileModel = $this->model('Profile');
        $this->userModel = $this->model('User');
    }

    public function index($userId = null, $action = null)
    {
        $user = RoleMiddleware::allow('school');

        if ($_SERVER['REQUEST_METHOD'] === 'GET' && $userId === null) {
            $filters = [
                'search' => trim($_GET['search'] ?? $_GET['q'] ?? ''),
                'location' => trim($_GET['location'] ?? ''),
                'experience_years' => $_GET['experience_years'] ?? null,
                'qualification' => trim($_GET['qualification'] ?? ''),
                'subject' => trim($_GET['subject'] ?? ''),
                'experience_min' => $_GET['experience_min'] ?? null,
                'experience_max' => $_GET['experience_max'] ?? null,
                'trcn_status' => trim($_GET['trcn_status'] ?? ''),
                'page' => $_GET['page'] ?? 1,
                'per_page' => $_GET['per_page'] ?? 10
            ];
            $teachers = $this->teacherModel->search($filters);
            ResponseHelper::success('Teachers fetched successfully', $teachers['data'], 200, ['teachers' => $teachers['data'], 'pagination' => $teachers['pagination']]);
        }

        if ($_SERVER['REQUEST_METHOD'] === 'GET' && $userId !== null) {
            $teacher = $this->teacherModel->getByUserId($userId);
            if (!$teacher) {
                ResponseHelper::error('Teacher not found', 404);
            }

            $teacherUser = $this->userModel->findById($userId);
            $profile = $this->profileModel->getTeacherProfileByUserId($userId);
            if (!$teacherUser || !$profile) {
                ResponseHelper::error('Teacher profile not found', 404);
            }

            $this->profileViewModel->record($userId, $user->user_id, [
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);

            $userData = $this->safeUser($teacherUser);
            $responseData = [
                'user' => $userData,
                'profile' => $profile
            ];
            ResponseHelper::success('Teacher profile fetched successfully', $responseData, 200, [
                'user' => $userData,
                'profile' => $profile,
                'teacher' => $teacher,
                'teacher_profile' => $profile
            ]);
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $userId !== null && $action === 'invite') {
            $data = $this->getData();
            $jobId = trim($data['job_id'] ?? '');
            $teacher = $this->teacherModel->getByUserId($userId);
            if (!$teacher) {
                ResponseHelper::error('Teacher not found', 404);
            }

            $message = trim($data['message'] ?? 'We were impressed by your profile and would love for you to apply for this opportunity.');
            $invitationId = $this->teacherModel->createInvitation($user->user_id, $userId, $jobId, $message);
            if (!$invitationId) {
                ResponseHelper::error('Failed to send invitation', 500);
            }

            $this->featureModel->createNotification(
                $userId,
                'Invitation to Apply',
                $message,
                'invitation'
            );

            $teacherUser = $this->model('User')->findById($userId);
            if ($teacherUser && !empty($teacherUser->email)) {
                $schoolUser = $this->model('User')->findById($user->user_id);
                MailHelper::sendTemplate(
                    $teacherUser->email,
                    'Invitation to Apply - StaffRoom',
                    'invitation_to_apply_email',
                    [
                        'TEACHER_NAME' => $teacherUser->full_name ?: $teacherUser->email,
                        'SCHOOL_NAME' => $schoolUser->full_name ?: ($schoolUser->name ?? 'StaffRoom School'),
                        'MESSAGE' => $message,
                        'JOB_ID' => $jobId ?: 'N/A'
                    ]
                );
            }

            ResponseHelper::success('Invitation sent successfully', [
                'invitation_id' => $invitationId,
                'teacher_id' => $userId,
                'job_id' => $jobId ?: null
            ], 201, ['invitation_id' => $invitationId]);
        }

        ResponseHelper::error('Endpoint not found or method not allowed', 405);
    }

    public function invite($userId = null)
    {
        return $this->index($userId, 'invite');
    }

    private function safeUser($user)
    {
        $data = (array)$user;
        unset($data['password'], $data['id']);
        unset($data['email_verification_code'], $data['password_reset_token']);
        unset($data['pending_email'], $data['account_change_code'], $data['account_change_expires_at']);
        return $data;
    }
}
?>
