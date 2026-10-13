<?php
/**
 * Interviews Controller
 * Handles school interview scheduling for applications.
 */

require_once __DIR__ . '/../helpers/MailHelper.php';

class Interviews extends Controller
{
    private $applicationModel;
    private $interviewModel;
    private $featureModel;
    private $userModel;

    public function __construct()
    {
        $this->applicationModel = $this->model('Application');
        $this->interviewModel = $this->model('Interview');
        $this->featureModel = $this->model('Feature');
        $this->userModel = $this->model('User');
    }

    public function index($applicationId = null)
    {
        if ($applicationId === null) {
            ResponseHelper::error('Application ID is required', 400);
        }

        $user = RoleMiddleware::allow('school');
        $application = $this->applicationModel->getById($applicationId);
        if (!$application) {
            ResponseHelper::error('Application not found', 404);
        }
        if ((string)$application->school_user_id !== (string)$user->user_id) {
            ResponseHelper::error('Access denied. Only the job owner can manage this interview.', 403);
        }

        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $interviews = $this->interviewModel->getByApplication($applicationId);
            ResponseHelper::success('Interviews fetched successfully', $interviews, 200, ['interviews' => $interviews]);
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = $this->getData();
            $type = trim($data['interview_type'] ?? '');
            $date = trim($data['interview_date'] ?? '');
            $time = trim($data['interview_time'] ?? '');
            if (!in_array($type, ['virtual', 'physical'], true)) {
                ResponseHelper::error('interview_type must be virtual or physical', 400);
            }
            if ($date === '' || $time === '') {
                ResponseHelper::error('interview_date and interview_time are required', 400);
            }
            if ($type === 'virtual' && trim($data['meeting_link'] ?? '') === '') {
                ResponseHelper::error('meeting_link is required for a virtual interview', 400);
            }
            if ($type === 'physical' && trim($data['venue'] ?? '') === '') {
                ResponseHelper::error('venue is required for a physical interview', 400);
            }

            $interview = $this->interviewModel->create($applicationId, $user->user_id, [
                'interview_type' => $type,
                'interview_date' => $date,
                'interview_time' => $time,
                'venue' => trim($data['venue'] ?? '') ?: null,
                'meeting_link' => trim($data['meeting_link'] ?? '') ?: null,
                'instructions' => trim($data['instructions'] ?? '') ?: null,
                'candidate_message' => trim($data['candidate_message'] ?? '') ?: null,
                'template_name' => trim($data['template_name'] ?? '') ?: null
            ]);
            if (!$interview) {
                ResponseHelper::error('Failed to schedule interview', 500);
            }

            $this->applicationModel->updateStatus($applicationId, 'interviewing', $user->user_id, $user->user_id);
            $this->featureModel->createNotification(
                $application->teacher_user_id,
                'Interview Scheduled',
                'An interview has been scheduled for your application for ' . $application->job_title . '.',
                'interview',
                $interview->interview_id,
                'interview',
                false
            );

            $teacher = $this->userModel->findById($application->teacher_user_id);
            if ($teacher && !empty($teacher->email)) {
                MailHelper::sendTemplate(
                    $teacher->email,
                    'Interview Scheduled - StaffRoom',
                    'interview_scheduled_email',
                    [
                        'TEACHER_NAME' => $teacher->full_name ?: $teacher->email,
                        'JOB_TITLE' => $application->job_title,
                        'INTERVIEW_TYPE' => ucfirst($interview->interview_type),
                        'INTERVIEW_DATE' => $interview->interview_date,
                        'INTERVIEW_TIME' => $interview->interview_time,
                        'VENUE' => $interview->venue ?: 'Not applicable',
                        'MEETING_LINK' => $interview->meeting_link ?: 'Not applicable',
                        'INSTRUCTIONS' => $interview->instructions ?: 'No additional instructions provided.',
                        'CANDIDATE_MESSAGE' => $interview->candidate_message ?: 'No additional message provided.'
                    ]
                );
            }
            ResponseHelper::success('Interview scheduled successfully', $interview, 201, ['interview' => $interview]);
        }

        ResponseHelper::error('Method Not Allowed', 405);
    }
}
?>
