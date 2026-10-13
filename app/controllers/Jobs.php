<?php
/**
 * Jobs Controller
 * Handles GET /api/jobs, GET /api/jobs/:id, POST /api/jobs, PUT /api/jobs/:id, DELETE /api/jobs/:id
 */

class Jobs extends Controller
{
    protected $jobModel;
    protected $profileModel;

    public function __construct()
    {
        $this->jobModel = $this->model('Job');
        $this->profileModel = $this->model('Profile');
        $this->userModel = $this->model('User');
        $this->serverKey  = 'secret_server_keysa'.date("H");
    }

    /**
     * Unified router action for GET /jobs, GET /jobs/:id, POST /jobs, PUT /jobs/:id, DELETE /jobs/:id
     */
    public function index($id = null)
    {
        $method = $_SERVER['REQUEST_METHOD'];

        if ($method === 'GET') {
            if ($id === null) {
                if (isset($_GET['recommended']) && $_GET['recommended'] === '1') {
                    $user = RoleMiddleware::allow('teacher');
                    $jobs = $this->jobModel->getRecommendedJobs($user->user_id, $_GET['page'] ?? 1, $_GET['per_page'] ?? 10);
                    return ResponseHelper::success('Recommended jobs fetched successfully', $jobs['data'], 200, ['jobs' => $jobs['data'], 'pagination' => $jobs['pagination']]);
                }
                return $this->listJobs();
            } else {
                return $this->show($id);
            }
        } elseif ($method === 'POST') {
            if ($id !== null) {
                ResponseHelper::error('Invalid endpoint for job creation', 400);
            }
            return $this->create();
        } elseif ($method === 'PUT' || $method === 'PATCH') {
            if ($id === null) {
                ResponseHelper::error('Job ID is required for update', 400);
            }
            return $this->update($id);
        } elseif ($method === 'DELETE') {
            if ($id === null) {
                ResponseHelper::error('Job ID is required for deletion', 400);
            }
            return $this->destroy($id);
        } else {
            ResponseHelper::error('Method Not Allowed', 405);
        }
    }

    /**
     * GET /api/jobs with optional query filters (subject, state)
     */
    public function listJobs()
    {
        $user = AuthMiddleware::authenticate();
        $filters = [
            'subject' => trim($_GET['subject'] ?? ''),
            'state' => trim($_GET['state'] ?? ''),
            'teaching_level' => trim($_GET['level'] ?? $_GET['teaching_level'] ?? ''),
            'location' => trim($_GET['location'] ?? ''),
            'experience' => trim($_GET['experience'] ?? $_GET['required_experience'] ?? ''),
            'page' => $_GET['page'] ?? 1,
            'per_page' => $_GET['per_page'] ?? 10
        ];

        if ($user->role === 'school') {
            $filters['school_id'] = $user->user_id;
        }

        $requestedStatus = trim($_GET['status'] ?? '');
        if ($user->role === 'school' && in_array($requestedStatus, ['open', 'draft', 'closed'], true)) {
            $filters['status'] = $requestedStatus;
        }

        $jobs = $this->jobModel->getJobs($filters);
        ResponseHelper::success('Jobs fetched successfully', $jobs['data'], 200, ['jobs' => $jobs['data'], 'pagination' => $jobs['pagination']]);
    }
    // public function listJobs()
    // {
        
    //     $user = $this->RouteProtection();
        
    //     $filters = [
    //         'subject' => trim($_GET['subject'] ?? ''),
    //         'state' => trim($_GET['state'] ?? '')
    //     ];
        
        

    //     $jobs = $this->jobModel->getJobs($filters, $user->user_id);
    //     ResponseHelper::success('Jobs fetched successfully', $jobs, 200, ['jobs' => $jobs]);
    // }

    /**
     * GET /api/jobs/:id
     */
    public function show($id)
    {
        $user = AuthMiddleware::authenticate();
        $ownerUserId = $user->role === 'school' ? $user->user_id : null;
        $openOnly = $user->role === 'teacher';
        $job = $this->jobModel->getJobById($id, $ownerUserId, $openOnly);
        if (!$job) {
            ResponseHelper::error('Job not found', 404);
        }
        ResponseHelper::success('Job details fetched successfully', $job, 200, ['job' => $job]);
    }

    /**
     * POST /api/jobs (Only for school users)
     */
    public function create()
    {
        $user = RoleMiddleware::allow('school');
        $schoolId = $this->profileModel->getSchoolIdByUserId($user->user_id);

        if (!$schoolId) {
            ResponseHelper::error('School profile not found. Please complete your profile first.', 400);
        }
        $schoolProfile = $this->profileModel->getSchoolProfileByUserId($user->user_id);
        if (($schoolProfile->admin_status ?? 'active') === 'suspended') {
            ResponseHelper::error('Suspended schools cannot create jobs', 403);
        }

        $data = $this->getData();
        $aliases = [
            'job_title' => 'title',
            'subject' => 'role_type',
            'school_location' => 'location',
            'job_summary' => 'description',
            'key_responsibilities' => 'responsibilities',
            'educational_qualification' => 'required_qualification',
            'other_requirements' => 'requirements',
            'featured_job' => 'is_featured'
        ];
        foreach ($aliases as $alias => $field) {
            if (!array_key_exists($field, $data) && array_key_exists($alias, $data)) {
                $data[$field] = $data[$alias];
            }
        }
        $jobId = $this->generateUniqueId();
        $title = trim($data['title'] ?? '');
        $description = trim($data['description'] ?? '');
        $roleType = trim($data['role_type'] ?? '');
        $employmentType = trim($data['employment_type'] ?? '');
        $location = trim($data['location'] ?? '');
        $status = strtolower(trim((string)($data['status'] ?? 'open')));
        if (in_array($status, ['published', 'active'], true)) {
            $status = 'open';
        }

        if (isset($data['responsibilities']) && !is_array($data['responsibilities'])) {
            ResponseHelper::error('responsibilities must be an array', 400, ['responsibilities' => 'Expected an array of responsibility strings']);
        }
        foreach (['requirements', 'required_qualification'] as $listField) {
            if (isset($data[$listField]) && !is_array($data[$listField]) && !is_string($data[$listField])) {
                ResponseHelper::error($listField . ' must be a string or array', 400, [$listField => 'Expected a string or array of strings']);
            }
        }

        // Validation
        if (empty($title)) {
            ResponseHelper::error('title is required', 400, ['title' => 'title is required']);
        }
        if (empty($description)) {
            ResponseHelper::error('description is required', 400, ['description' => 'description is required']);
        }
        if (empty($roleType)) {
            ResponseHelper::error('role_type is required', 400, ['role_type' => 'role_type is required']);
        }
        if (empty($employmentType)) {
            ResponseHelper::error('employment_type is required', 400, ['employment_type' => 'employment_type is required']);
        }
        if (empty($location)) {
            ResponseHelper::error('location is required', 400, ['location' => 'location is required']);
        }
        if (!in_array($status, ['open', 'draft'], true)) {
            ResponseHelper::error('status must be open or draft', 400, ['status' => 'status must be open or draft']);
        }

        $jobId = $this->jobModel->createJob($schoolId, $data, $jobId, $status);
        if (!$jobId) {
            ResponseHelper::error('Failed to create job', 500);
        }

        $job = $this->jobModel->getJobById($jobId);
        ResponseHelper::success('Job created successfully', $job, 200, ['job' => $job]);
    }

    /**
     * PUT /api/jobs/:id (Only the school that created the job can update it, partial update allowed)
     */
    public function update($id)
    {
        $user = RoleMiddleware::allow('school');
        $schoolId = $this->profileModel->getSchoolIdByUserId($user->user_id);
        if (!$schoolId) {
            ResponseHelper::error('School profile not found.', 400);
        }

        $currentJob = $this->jobModel->getJobById($id);
        if (!$currentJob) {
            ResponseHelper::error('Job not found', 404);
        }

        if (!$this->jobModel->isOwner($id, $schoolId, $user->user_id)) {
            ResponseHelper::error('Access denied. Only the school that created this job can update it.', 403);
        }

        $data = $this->getData();
        $aliases = [
            'job_title' => 'title',
            'subject' => 'role_type',
            'school_location' => 'location',
            'job_summary' => 'description',
            'key_responsibilities' => 'responsibilities',
            'educational_qualification' => 'required_qualification',
            'other_requirements' => 'requirements',
            'featured_job' => 'is_featured'
        ];
        foreach ($aliases as $alias => $field) {
            if (!array_key_exists($field, $data) && array_key_exists($alias, $data)) {
                $data[$field] = $data[$alias];
            }
        }
        foreach (['responsibilities', 'requirements', 'required_qualification'] as $listField) {
            if (array_key_exists($listField, $data) && !is_array($data[$listField]) && !is_string($data[$listField])) {
                ResponseHelper::error($listField . ' must be a string or array', 400, [$listField => 'Expected a string or array of strings']);
            }
        }
        if (isset($data['status'])) {
            $status = strtolower(trim((string)$data['status']));
            if (in_array($status, ['published', 'active'], true)) {
                $data['status'] = 'open';
            } elseif (!in_array($status, ['open', 'draft', 'closed'], true)) {
                ResponseHelper::error('status must be open, draft, or closed', 400, ['status' => 'Unsupported job status']);
            } else {
                $data['status'] = $status;
            }
        }
        $updated = $this->jobModel->updateJob($id, $schoolId, $user->user_id, $data);

        if (!$updated) {
            ResponseHelper::error('Failed to update job', 500);
        }

        $job = $this->jobModel->getJobById($id);
        ResponseHelper::success('Job updated successfully', $job, 200, ['job' => $job]);
    }

    /**
     * POST /api/jobs/{id}/publish
     */
    public function publish($id = null)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $id === null) {
            ResponseHelper::error('Job ID is required', 400);
        }

        $user = RoleMiddleware::allow('school');
        $schoolId = $this->profileModel->getSchoolIdByUserId($user->user_id);
        $schoolProfile = $this->profileModel->getSchoolProfileByUserId($user->user_id);
        if (($schoolProfile->admin_status ?? 'active') === 'suspended') {
            ResponseHelper::error('Suspended schools cannot publish jobs', 403);
        }
        if (!$schoolId) {
            ResponseHelper::error('School profile not found.', 400);
        }
        if (!$this->jobModel->publishJob($id, $schoolId, $user->user_id)) {
            ResponseHelper::error('Job not found or could not be published', 404);
        }

        $job = $this->jobModel->getJobById($id);
        ResponseHelper::success('Job published successfully', $job, 200, ['job' => $job]);
    }

    /**
     * DELETE /api/jobs/:id (Only the school that created the job can delete it, uses soft delete)
     */
    public function destroy($id)
    {
        $user = RoleMiddleware::allow('school');
        $schoolId = $this->profileModel->getSchoolIdByUserId($user->user_id);
        if (!$schoolId) {
            ResponseHelper::error('School profile not found.', 400);
        }

        $currentJob = $this->jobModel->getJobById($id);
        if (!$currentJob) {
            ResponseHelper::error('Job not found', 404);
        }

        if (!$this->jobModel->isOwner($id, $schoolId, $user->user_id)) {
            ResponseHelper::error('Access denied. Only the school that created this job can delete it.', 403);
        }

        $deleted = $this->jobModel->deleteJob($id, $schoolId, $user->user_id, false);
        if ($deleted) {
            ResponseHelper::success('Job deleted successfully', null, 200);
        } else {
            ResponseHelper::error('Failed to delete job', 500);
        }
    }
}
?>
