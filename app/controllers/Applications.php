<?php
/**
 * Applications Controller
 * Handles POST /api/applications/apply/:jobId, GET /api/applications/my-applications,
 * GET /api/applications/job/:jobId, PATCH /api/applications/:id/status
 */

require_once __DIR__ . '/../helpers/MailHelper.php';

class Applications extends Controller
{
    protected $applicationModel;
    protected $profileModel;
    protected $jobModel;
    protected $featureModel;
    protected $userModel;

    public function __construct()
    {
        $this->applicationModel = $this->model('Application');
        $this->profileModel = $this->model('Profile');
        $this->jobModel = $this->model('Job');
        $this->featureModel = $this->model('Feature');
        $this->userModel = $this->model('User');
    }

    /**
     * Default dispatcher for /applications/:id/status
     */
    public function index($id = null, $subaction = null)
    {
        if ($id !== null && $subaction === 'status') {
            return $this->updateApplicationStatus($id);
        } else {
            ResponseHelper::error('Endpoint not found', 404);
        }
    }

    /**
     * PATCH /api/applications/:id/status
     */
    public function status($id = null)
    {
        if ($id === null) {
            ResponseHelper::error('Application ID is required', 400);
        }
        return $this->updateApplicationStatus($id);
    }

    /**
     * POST /api/applications/:id/reject
     */
    public function reject($id = null)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'PATCH') {
            ResponseHelper::error('Method Not Allowed', 405);
        }
        if ($id === null) {
            ResponseHelper::error('Application ID is required', 400);
        }

        return $this->updateApplicationStatus($id, 'rejected');
    }

    public function show($id = null)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            ResponseHelper::error('Method Not Allowed', 405);
        }
        if ($id === null) {
            ResponseHelper::error('Application ID is required', 400);
        }

        $user = AuthMiddleware::authenticate();
        $application = $this->applicationModel->getById($id);
        if (!$application) {
            ResponseHelper::error('Application not found', 404);
        }
        $isTeacherOwner = $user->role === 'teacher' && (string)$application->teacher_user_id === (string)$user->user_id;
        $isSchoolOwner = $user->role === 'school' && (string)$application->school_user_id === (string)$user->user_id;
        if (!$isTeacherOwner && !$isSchoolOwner && $user->role !== 'admin') {
            ResponseHelper::error('Access denied', 403);
        }
        ResponseHelper::success('Application fetched successfully', $application, 200, ['application' => $application]);
    }

    public function withdraw($id = null)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'DELETE' && $_SERVER['REQUEST_METHOD'] !== 'PATCH') {
            ResponseHelper::error('Method Not Allowed', 405);
        }
        if ($id === null) {
            ResponseHelper::error('Application ID is required', 400);
        }
        $user = RoleMiddleware::allow('teacher');
        if (!$this->applicationModel->withdraw($id, $user->user_id)) {
            ResponseHelper::error('Application not found or cannot be withdrawn', 404);
        }
        ResponseHelper::success([], 'Application withdrawn successfully', 200);
    }

    /**
     * Internal handler for updating application status
     */
    protected function updateApplicationStatus($id, $forcedStatus = null)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'PATCH' && $_SERVER['REQUEST_METHOD'] !== 'PUT' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
            ResponseHelper::error('Method Not Allowed', 405);
        }

        $user = AuthMiddleware::authorize(['school', 'admin']);
        $schoolId = ($user->role === 'school') ? $this->profileModel->getSchoolIdByUserId($user->user_id) : 0;
        if (!$schoolId && $user->role === 'school') {
            ResponseHelper::error('School profile not found.', 400);
        }

        $data = $this->getData();
        $status = $forcedStatus ?: trim((string)($data['status'] ?? ''));
        $rejectionMessage = trim($data['message'] ?? $data['rejection_message'] ?? '');

        $allowedStatuses = ['submitted', 'pending', 'reviewed', 'shortlisted', 'rejected', 'accepted', 'hired', 'interviewing'];
        if (empty($status) || !in_array($status, $allowedStatuses)) {
            ResponseHelper::error('Invalid status. Must be one of: ' . implode(', ', $allowedStatuses), 400);
        }
        if ($status === 'rejected' && $rejectionMessage === '') {
            ResponseHelper::error('A rejection message is required when rejecting an application', 400, ['message' => 'Required for rejected status']);
        }

        $current = $this->applicationModel->getById($id);
        if ($current && $current->status === $status) {
            ResponseHelper::error('Application already has this status', 409);
        }

        $app = $this->applicationModel->updateStatus($id, $status, $schoolId, $user->user_id, $user->role === 'admin', $rejectionMessage);
        if ($app === -1) {
            ResponseHelper::error('Access denied. Only the school that owns the related job can update status.', 403);
        }
        if (!$app) {
            ResponseHelper::error('Application not found or update failed', 404);
        }

        // Notify the teacher
        if (isset($app->teacher_user_id)) {
            $this->featureModel->createNotification(
                $app->teacher_user_id,
                'Application Status Updated',
                "Your application for '{$app->job_title}' status has been updated to: {$status}",
                'application_status',
                $id,
                'application',
                $status !== 'shortlisted'
            );

            $teacher = $this->userModel->findById($app->teacher_user_id);
            if ($teacher && !empty($teacher->email)) {
                if ($status === 'shortlisted') {
                    MailHelper::sendTemplate(
                        $teacher->email,
                        'Your Application Has Been Shortlisted - StaffRoom',
                        'application_shortlisted_email',
                        [
                            'TEACHER_NAME' => $teacher->full_name ?: $teacher->email,
                            'JOB_TITLE' => $app->job_title
                        ]
                    );
                } else {
                    $statusLabel = strtoupper(str_replace('_', ' ', $status));
                    MailHelper::sendTemplate(
                        $teacher->email,
                        'Application Status Update - StaffRoom',
                        'application_status_updated_email',
                        [
                            'TEACHER_NAME' => $teacher->full_name ?: $teacher->email,
                            'JOB_TITLE' => $app->job_title,
                            'STATUS' => ucfirst($status),
                            'STATUS_LABEL' => $statusLabel,
                            'MESSAGE' => $status === 'rejected'
                                ? ($rejectionMessage ?: 'The school has updated your application status.')
                                : 'The school has updated your application status.'
                        ]
                    );
                }
            }
        }

        return ResponseHelper::success('Application status updated successfully', $app, 200, ['application' => $app]);
    }

    /**
     * POST /api/applications/apply/:jobId
     */
    /**
     * GET /api/applications/apply/:jobId
     * Lets the frontend pre-fill and validate the apply form before submission.
     */
    public function applyRequirements($jobId = null)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            ResponseHelper::error('Method Not Allowed', 405);
        }
        if ($jobId === null) {
            ResponseHelper::error('Job ID is required', 400);
        }

        $user = RoleMiddleware::allow('teacher');
        $job = $this->jobModel->getJobById($jobId);
        if (!$job || ($job->status ?? null) !== 'open') {
            ResponseHelper::error('Job post not found or is no longer accepting applications', 404);
        }

        $profile = $this->profileModel->getTeacherProfileByUserId($user->user_id);
        $alreadyApplied = $this->applicationModel->hasApplied($jobId, $user->user_id);

        ResponseHelper::success('Application requirements fetched successfully', [], 200, [
            'job' => $job,
            'already_applied' => $alreadyApplied,
            'existing_cv_url' => $profile->cv_url ?? null,
            'requirements' => [
                'cover_letter' => 'required, minimum 30 characters',
                'cv' => $profile->cv_url ? 'optional (existing CV on file will be used unless a new one is uploaded)' : 'required, upload a CV with this application'
            ]
        ]);
    }

    public function apply($jobId = null)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            ResponseHelper::error('Method Not Allowed', 405);
        }

        if ($jobId === null) {
            ResponseHelper::error('Job ID is required', 400);
        }

        $user = RoleMiddleware::allow('teacher');
        $teacherId = $this->profileModel->getTeacherIdByUserId($user->user_id);

        if (!$teacherId) {
            ResponseHelper::error('Teacher profile not found. Please complete your profile first.', 400);
        }

        $job = $this->jobModel->getJobById($jobId);
        if (!$job || ($job->status ?? null) !== 'open') {
            ResponseHelper::error('Job post not found or is no longer accepting applications', 404);
        }

        if ($this->applicationModel->hasApplied($jobId, $user->user_id)) {
            ResponseHelper::error('You have already applied for this job', 400);
        }

        $data = $this->getData();
        $coverLetter = trim($data['cover_letter'] ?? '');
        $additionalInfo = trim($data['additional_info'] ?? '');

        if ($coverLetter === '') {
            ResponseHelper::error('A cover letter is required to apply', 400, ['cover_letter' => 'Required']);
        }
        if (strlen($coverLetter) < 30) {
            ResponseHelper::error('Cover letter must be at least 30 characters', 400, ['cover_letter' => 'Too short']);
        }

        $profile = $this->profileModel->getTeacherProfileByUserId($user->user_id);
        $cvUrl = $profile->cv_url ?? null;

        $uploadedCv = $_FILES['cv'] ?? null;
        if ($uploadedCv && $uploadedCv['error'] === UPLOAD_ERR_OK) {
            $newCvUrl = UploadHelper::uploadCV($uploadedCv, $user->user_id);
            if (!$newCvUrl) {
                ResponseHelper::error('Failed to upload CV file', 500);
            }
            $cvUrl = $newCvUrl;
            $this->profileModel->updateCV($user->user_id, $cvUrl);
        }

        if (empty($cvUrl)) {
            ResponseHelper::error('A CV is required to apply. Upload one with this application or add it to your profile first.', 400, ['cv' => 'Required']);
        }

        $appId = $this->applicationModel->apply($jobId, $user->user_id, $coverLetter, $cvUrl, $additionalInfo ?: null);
        if (!$appId) {
            ResponseHelper::error('Failed to submit application', 500);
        }

        $app = $this->applicationModel->getById($appId);

        // Notify the school that owns the job
        $schoolOwnerId = isset($job->school_user_id) ? $job->school_user_id : $job->school_id;
        $this->featureModel->createNotification(
            $schoolOwnerId,
            'New Job Application',
            "A teacher ({$user->email}) has applied for your job post: '{$job->title}'",
            'application_received',
            $appId,
            'application',
            false
        );

        $school = $this->userModel->findById($schoolOwnerId);
        if ($school && !empty($school->email)) {
            MailHelper::sendTemplate(
                $school->email,
                'New Job Application Received - StaffRoom',
                'application_received_email',
                [
                    'SCHOOL_NAME' => $school->full_name ?: $school->email,
                    'TEACHER_NAME' => $user->full_name ?: $user->email,
                    'TEACHER_EMAIL' => $user->email,
                    'JOB_TITLE' => $job->title,
                    'APPLICATION_ID' => $appId
                ]
            );
        }

        // Notify the teacher
        $this->featureModel->createNotification(
            $user->user_id,
            'Application Submitted',
            "You successfully applied for '{$job->title}' at {$job->school_name}"
        );

        $teacherUser = $this->userModel->findById($user->user_id);
        if ($teacherUser && !empty($teacherUser->email)) {
            MailHelper::sendTemplate(
                $teacherUser->email,
                'Application Submitted - StaffRoom',
                'application_submitted_email',
                [
                    'TEACHER_NAME' => $teacherUser->full_name ?: $teacherUser->email,
                    'JOB_TITLE' => $job->title,
                    'SCHOOL_NAME' => $job->school_name ?? 'the school',
                    'APPLICATION_ID' => $appId
                ]
            );
        }

        ResponseHelper::success('Application submitted successfully', $app, 201, ['application' => $app]);
    }

    /**
     * GET /api/applications/my-applications
     */
    public function my_applications()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            ResponseHelper::error('Method Not Allowed', 405);
        }

        $user = RoleMiddleware::allow('teacher');
        $teacherId = $this->profileModel->getTeacherIdByUserId($user->user_id);

        if (!$teacherId) {
            ResponseHelper::success('My applications fetched successfully', [], 200, ['applications' => []]);
        }

        $apps = $this->applicationModel->getMyApplications($teacherId, $_GET['page'] ?? 1, $_GET['per_page'] ?? 10);
        ResponseHelper::success('My applications fetched successfully', $apps['data'], 200, ['applications' => $apps['data'], 'pagination' => $apps['pagination']]);
    }

    /**
     * GET /api/applications/job/:jobId
     */
    public function job($jobId = null)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            ResponseHelper::error('Method Not Allowed', 405);
        }

        if ($jobId === null) {
            ResponseHelper::error('Job ID is required', 400);
        }

        $user = AuthMiddleware::authorize(['school', 'admin']);
        $schoolId = ($user->role === 'school') ? $this->profileModel->getSchoolIdByUserId($user->user_id) : 0;
        if (!$schoolId && $user->role === 'school') {
            ResponseHelper::error('School profile not found.', 400);
        }

        $apps = $this->applicationModel->getJobApplications($jobId, $schoolId, $user->user_id, $user->role === 'admin', $_GET['page'] ?? 1, $_GET['per_page'] ?? 10);
        if ($apps === false) {
            ResponseHelper::error('Access denied. Only the school that owns the job can view applications.', 403);
        }

        ResponseHelper::success('Job applications fetched successfully', $apps['data'], 200, ['applications' => $apps['data'], 'pagination' => $apps['pagination']]);
    }
}
?>
