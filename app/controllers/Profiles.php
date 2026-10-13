<?php
/**
 * Profiles Controller
 * Handles GET /api/profiles/me, PUT /api/profiles/teacher, PUT /api/profiles/school,
 * POST /api/profiles/upload-cv, POST /api/profiles/upload-logo
 */

require_once __DIR__ . '/../helpers/UploadHelper.php';

class Profiles extends Controller
{
    private $profileModel;
    private $userModel;
    private $profileViewModel;

    public function __construct()
    {
        $this->profileModel = $this->model('Profile');
        $this->userModel = $this->model('User');
        $this->profileViewModel = $this->model('TeacherProfileView');
    }

    /**
     * GET /api/profiles/me
     * Return current user along with teacher profile or school profile
     */
    public function me()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            ResponseHelper::error('Method Not Allowed', 405);
        }

        $user = AuthMiddleware::authenticate();

        // Convert user object to array excluding sensitive fields like password
        $userData = (array)$user;
        unset($userData['password']);
        unset($userData['id']);
        unset($userData['email_verification_code'], $userData['pending_email']);
        unset($userData['account_change_code'], $userData['account_change_expires_at']);
        unset($userData['password_reset_token'], $userData['password_reset_expires']);
        $userData['user_id'] = $user->user_id;

        $profile = null;
        if ($user->role === 'teacher') {
            $profile = $this->profileModel->getTeacherProfileByUserId($user->user_id);
        } elseif ($user->role === 'school') {
            $profile = $this->profileModel->getSchoolProfileByUserId($user->user_id);
        }

        $responseData = ['user' => $userData, 'profile' => $profile];
        $aliases = ['user' => $userData, 'profile' => $profile];
        if ($user->role === 'teacher') $aliases['teacher_profile'] = $profile;
        if ($user->role === 'school') $aliases['school_profile'] = $profile;
        ResponseHelper::success('Profile fetched successfully', $responseData, 200, $aliases);
    }

    /**
     * PUT /api/profiles/teacher
     */
    public function teacher()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'PUT' && $_SERVER['REQUEST_METHOD'] !== 'PATCH' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
            ResponseHelper::error('Method Not Allowed', 405);
        }

        $user = RoleMiddleware::allow('teacher');
        $data = $this->getData(true);
        if (!empty($data['__invalid_json'])) {
            ResponseHelper::error('Malformed JSON request body', 400);
        }

        $aliases = [
            'professional_title' => 'role_title',
            'professional_summary' => 'bio',
            'years_of_experience' => 'experience_years',
            'employment_preference' => 'preferred_employment_type',
            'city_location' => 'city',
            'state_region' => 'state',
            'available_from_date' => 'available_from'
        ];
        foreach ($aliases as $alias => $field) {
            if (!array_key_exists($field, $data) && array_key_exists($alias, $data)) $data[$field] = $data[$alias];
        }
        if (isset($data['education']) && !isset($data['education_history'])) $data['education_history'] = $data['education'];
        if (isset($data['experience']) && !isset($data['teaching_experience'])) $data['teaching_experience'] = $data['experience'];

        $errors = [];
        if (isset($data['experience_years']) && (filter_var($data['experience_years'], FILTER_VALIDATE_INT) === false || (int)$data['experience_years'] < 0)) {
            $errors['experience_years'] = 'Must be a non-negative integer';
        }
        if (isset($data['bio']) && mb_strlen((string)$data['bio']) > 5000) $errors['bio'] = 'Must not exceed 5000 characters';
        if (isset($data['role_title']) && mb_strlen((string)$data['role_title']) > 255) $errors['role_title'] = 'Must not exceed 255 characters';
        if (isset($data['preferred_location']) && mb_strlen((string)$data['preferred_location']) > 255) $errors['preferred_location'] = 'Must not exceed 255 characters';
        if (isset($data['trcn_status']) && !in_array($data['trcn_status'], ['pending', 'verified', 'rejected'], true)) {
            $errors['trcn_status'] = 'Must be pending, verified, or rejected';
        }
        if (isset($data['availability']) && !in_array($data['availability'], ['Open', 'full_time', 'part_time', 'contract', 'substitute'], true)) {
            $errors['availability'] = 'Unsupported availability value';
        }
        if (isset($data['profile_visibility']) && !in_array($data['profile_visibility'], ['schools', 'applying_schools', 'nobody'], true)) {
            $errors['profile_visibility'] = 'Unsupported visibility value';
        }
        if (isset($data['skills']) && !is_array($data['skills'])) $errors['skills'] = 'Expected an array of skill names';
        if (isset($data['teaching_levels']) && !is_array($data['teaching_levels'])) $errors['teaching_levels'] = 'Expected an array';
        if (isset($data['subjects']) && !is_array($data['subjects'])) $errors['subjects'] = 'Expected an array of subject names';
        foreach (['skills', 'teaching_levels'] as $listField) {
            if (isset($data[$listField]) && is_array($data[$listField])) {
                foreach ($data[$listField] as $value) if (!is_string($value) || mb_strlen($value) > 150) $errors[$listField] = 'Values must be strings of 150 characters or fewer';
            }
        }
        if (isset($data['education_history']) && !is_array($data['education_history'])) $errors['education_history'] = 'Expected an array';
        if (isset($data['teaching_experience']) && !is_array($data['teaching_experience'])) $errors['teaching_experience'] = 'Expected an array';
        if (isset($data['teaching_mode']) && !is_string($data['teaching_mode'])) $errors['teaching_mode'] = 'Expected a string';
        if (isset($data['education_history']) && is_array($data['education_history'])) $this->validateEducation($data['education_history'], $errors);
        if (isset($data['teaching_experience']) && is_array($data['teaching_experience'])) $this->validateExperience($data['teaching_experience'], $errors);
        if ($errors) ResponseHelper::error('Profile validation failed', 400, $errors);

        $updated = $this->profileModel->updateCompleteTeacherProfile($user->user_id, $data);

        if (!$updated) {
            ResponseHelper::error('Failed to update teacher profile', 500);
        }

        $profile = $this->profileModel->getTeacherProfileByUserId($user->user_id);
        ResponseHelper::success('Teacher profile updated successfully', $profile, 200, ['profile' => $profile]);
    }

    public function profileViews()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            ResponseHelper::error('Method Not Allowed', 405);
        }

        $user = RoleMiddleware::allow('teacher');
        $summary = $this->profileViewModel->summary($user->user_id);
        $history = $this->profileViewModel->history(
            $user->user_id,
            $_GET['page'] ?? 1,
            $_GET['per_page'] ?? 10
        );

        ResponseHelper::success('Teacher profile views fetched successfully', [
            'summary' => $summary,
            'views' => $history['items'],
            'pagination' => $history['pagination']
        ], 200, [
            'profile_views' => $history['items'],
            'total_views' => $summary['total_views'],
            'unique_schools' => $summary['unique_schools'],
            'last_viewed_at' => $summary['last_viewed_at']
        ]);
    }

    private function validateEducation($records, &$errors)
    {
        $seen = [];
        foreach ($records as $index => $record) {
            $prefix = 'education_history.' . $index;
            if (!is_array($record)) { $errors[$prefix] = 'Each record must be an object'; continue; }
            foreach (['degree', 'institution', 'start_year', 'status'] as $field) if (trim((string)($record[$field] ?? '')) === '') $errors[$prefix . '.' . $field] = 'Required';
            $start = $record['start_year'] ?? null; $end = $record['end_year'] ?? null;
            if ($start !== null && !preg_match('/^\d{4}$/', (string)$start)) $errors[$prefix . '.start_year'] = 'Must be a four-digit year';
            if ($end !== null && $end !== '' && !preg_match('/^\d{4}$/', (string)$end)) $errors[$prefix . '.end_year'] = 'Must be a four-digit year or null';
            if ($end !== null && $end !== '' && (int)$end < (int)$start) $errors[$prefix . '.end_year'] = 'Cannot be earlier than start_year';
            if (!in_array($record['status'] ?? '', ['Completed', 'In Progress', 'Pending'], true)) $errors[$prefix . '.status'] = 'Must be Completed, In Progress, or Pending';
            $key = strtolower(trim((string)($record['degree'] ?? '')) . '|' . trim((string)($record['institution'] ?? '')) . '|' . (string)$start);
            if (isset($seen[$key])) $errors[$prefix] = 'Duplicate education record';
            $seen[$key] = true;
        }
    }

    private function validateExperience($records, &$errors)
    {
        $seen = [];
        foreach ($records as $index => $record) {
            $prefix = 'teaching_experience.' . $index;
            if (!is_array($record)) { $errors[$prefix] = 'Each record must be an object'; continue; }
            foreach (['role', 'school', 'start_date'] as $field) if (trim((string)($record[$field] ?? '')) === '') $errors[$prefix . '.' . $field] = 'Required';
            if (!preg_match('/^\d{4}-\d{2}$/', (string)($record['start_date'] ?? ''))) $errors[$prefix . '.start_date'] = 'Must use YYYY-MM';
            $end = $record['end_date'] ?? null;
            if ($end !== null && $end !== '' && !preg_match('/^\d{4}-\d{2}$/', (string)$end)) $errors[$prefix . '.end_date'] = 'Must use YYYY-MM or null';
            if ($end !== null && $end !== '' && strcmp($end, (string)$record['start_date']) < 0) $errors[$prefix . '.end_date'] = 'Cannot be earlier than start_date';
            if (mb_strlen((string)($record['description'] ?? '')) > 5000) $errors[$prefix . '.description'] = 'Must not exceed 5000 characters';
            $key = strtolower(trim((string)($record['role'] ?? '')) . '|' . trim((string)($record['school'] ?? '')) . '|' . (string)($record['start_date'] ?? ''));
            if (isset($seen[$key])) $errors[$prefix] = 'Duplicate experience record';
            $seen[$key] = true;
        }
    }

    public function education($educationId = null)
    {
        $user = RoleMiddleware::allow('teacher');
        $profile = $this->profileModel->getTeacherProfileByUserId($user->user_id);
        $records = array_map(static function ($record) { return (array)$record; }, $profile->education_history ?? []);
        $method = $_SERVER['REQUEST_METHOD'];
        if ($method === 'GET') ResponseHelper::success('Education history fetched successfully', $records, 200, ['education_history' => $records]);
        $data = $this->getData(true);
        if (!empty($data['__invalid_json'])) ResponseHelper::error('Malformed JSON request body', 400);
        $aliases = [
            'degree_certificate' => 'degree',
            'other_degree_certificate' => 'other_degree',
            'institution_university' => 'institution',
            'class_degree' => 'class_of_degree'
        ];
        foreach ($aliases as $alias => $field) {
            if (!array_key_exists($field, $data) && array_key_exists($alias, $data)) $data[$field] = $data[$alias];
        }
        if ($method === 'POST') $records[] = $data;
        if ($method === 'PUT' || $method === 'PATCH') {
            $found = false;
            foreach ($records as &$record) if ((string)($record['education_id'] ?? '') === (string)$educationId) { $record = array_merge($record, $data); $found = true; }
            if (!$found) ResponseHelper::error('Education record not found', 404);
        }
        if ($method === 'DELETE') $records = array_values(array_filter($records, static function ($record) use ($educationId) { return (string)($record['education_id'] ?? '') !== (string)$educationId; }));
        if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) ResponseHelper::error('Method Not Allowed', 405);
        $errors = []; $this->validateEducation($records, $errors); if ($errors) ResponseHelper::error('Education validation failed', 400, $errors);
        if (!$this->profileModel->updateCompleteTeacherProfile($user->user_id, ['education_history' => $records])) ResponseHelper::error('Failed to update education history', 500);
        $updated = $this->profileModel->getTeacherProfileByUserId($user->user_id);
        ResponseHelper::success('Education history updated successfully', $updated->education_history, 200, ['education_history' => $updated->education_history]);
    }

    public function experience($experienceId = null)
    {
        $user = RoleMiddleware::allow('teacher');
        $profile = $this->profileModel->getTeacherProfileByUserId($user->user_id);
        $records = array_map(static function ($record) { return (array)$record; }, $profile->teaching_experience ?? []);
        $method = $_SERVER['REQUEST_METHOD'];
        if ($method === 'GET') ResponseHelper::success('Teaching experience fetched successfully', $records, 200, ['teaching_experience' => $records]);
        $data = $this->getData(true);
        if (!empty($data['__invalid_json'])) ResponseHelper::error('Malformed JSON request body', 400);
        $aliases = [
            'job_title' => 'role',
            'school_institution' => 'school',
            'start_month_year' => 'start_date',
            'end_month_year' => 'end_date'
        ];
        foreach ($aliases as $alias => $field) {
            if (!array_key_exists($field, $data) && array_key_exists($alias, $data)) $data[$field] = $data[$alias];
        }
        if ($method === 'POST') $records[] = $data;
        if ($method === 'PUT' || $method === 'PATCH') {
            $found = false;
            foreach ($records as &$record) if ((string)($record['experience_id'] ?? '') === (string)$experienceId) { $record = array_merge($record, $data); $found = true; }
            if (!$found) ResponseHelper::error('Experience record not found', 404);
        }
        if ($method === 'DELETE') $records = array_values(array_filter($records, static function ($record) use ($experienceId) { return (string)($record['experience_id'] ?? '') !== (string)$experienceId; }));
        if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) ResponseHelper::error('Method Not Allowed', 405);
        $errors = []; $this->validateExperience($records, $errors); if ($errors) ResponseHelper::error('Experience validation failed', 400, $errors);
        if (!$this->profileModel->updateCompleteTeacherProfile($user->user_id, ['teaching_experience' => $records])) ResponseHelper::error('Failed to update teaching experience', 500);
        $updated = $this->profileModel->getTeacherProfileByUserId($user->user_id);
        ResponseHelper::success('Teaching experience updated successfully', $updated->teaching_experience, 200, ['teaching_experience' => $updated->teaching_experience]);
    }

    /**
     * PUT /api/profiles/school
     */
    public function school()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'PUT' && $_SERVER['REQUEST_METHOD'] !== 'PATCH' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
            ResponseHelper::error('Method Not Allowed', 405);
        }

        $user = RoleMiddleware::allow('school');
        $data = $this->getData();
        $aliases = [
            'institution_type' => 'school_type',
            'location_address' => 'address',
            'school_city' => 'lga',
            'website_url' => 'website'
        ];
        foreach ($aliases as $alias => $field) {
            if (!array_key_exists($field, $data) && array_key_exists($alias, $data)) $data[$field] = $data[$alias];
        }
        if (isset($data['country']) && !is_string($data['country'])) {
            ResponseHelper::error('country must be a string', 400, ['country' => 'Expected a country name']);
        }

        if (isset($data['setup_completed'])) {
            $data['setup_completed'] = filter_var($data['setup_completed'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($data['setup_completed'] === null) {
                ResponseHelper::error('setup_completed must be a boolean', 400, ['setup_completed' => 'Expected true or false']);
            }
        }

        $updated = $this->profileModel->updateSchoolProfile($user->user_id, $data);

        if (!$updated) {
            ResponseHelper::error('Failed to update school profile', 500);
        }

        $profile = $this->profileModel->getSchoolProfileByUserId($user->user_id);
        ResponseHelper::success('School profile updated successfully', $profile, 200, ['profile' => $profile]);
    }

    /**
     * POST /api/profiles/upload-cv
     */
    public function upload_cv()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            ResponseHelper::error('Method Not Allowed', 405);
        }

        $user = RoleMiddleware::allow('teacher');

        $file = $_FILES['cv'] ?? ($_FILES['file'] ?? null);
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            ResponseHelper::error('No valid CV file uploaded. Ensure field name is "cv"', 400);
        }

        $cvUrl = UploadHelper::uploadCV($file, $user->user_id);
        if (!$cvUrl) {
            ResponseHelper::error('Failed to upload CV file', 500);
        }

        $this->profileModel->updateCV($user->user_id, $cvUrl);

        $profile = $this->profileModel->getTeacherProfileByUserId($user->user_id);
        ResponseHelper::success('CV uploaded successfully', ['cv_url' => $cvUrl, 'profile' => $profile], 200, [
            'cv_url' => $cvUrl,
            'profile' => $profile
        ]);
    }

    /**
     * POST /api/profiles/upload-logo
     */
    public function upload_logo()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            ResponseHelper::error('Method Not Allowed', 405);
        }

        $user = RoleMiddleware::allow('school');

        $file = $_FILES['logo'] ?? ($_FILES['file'] ?? null);
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            ResponseHelper::error('No valid logo file uploaded. Ensure field name is "logo"', 400);
        }

        $logoUrl = UploadHelper::uploadLogo($file, $user->user_id);
        if (!$logoUrl) {
            ResponseHelper::error('Failed to upload logo file', 500);
        }

        $this->profileModel->updateLogo($user->user_id, $logoUrl);

        $profile = $this->profileModel->getSchoolProfileByUserId($user->user_id);
        ResponseHelper::success('School logo uploaded successfully', ['logo_url' => $logoUrl, 'profile' => $profile], 200, [
            'logo_url' => $logoUrl,
            'profile' => $profile
        ]);
    }
}
?>
