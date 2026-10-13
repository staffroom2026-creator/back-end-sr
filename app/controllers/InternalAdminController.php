<?php
/**
 * Internal Admin Dashboard API. Every action is protected by route middleware and
 * this controller's module permission check.
 */
require_once __DIR__ . '/../helpers/MailHelper.php';

class InternalAdminController extends Controller
{
    private $model;
    private $user;

    public function __construct()
    {
        $this->model = $this->model('InternalAdmin');
        $this->user = AuthMiddleware::$user ?: AuthMiddleware::authenticate();
    }

    private function guard($module, $action = 'view')
    {
        $profile = $this->model->profile($this->user->user_id);
        if (!$profile || $profile->status !== 'active') ResponseHelper::error('Forbidden: Internal admin profile required', 403);
        $permissions = $this->model->permissions($this->user->user_id, $profile->admin_role);
        if (empty($permissions[$module][$action])) ResponseHelper::error('Forbidden: Insufficient permission', 403);
        return $profile;
    }

    private function body()
    {
        $data = $this->getData(true);
        if (!empty($data['__invalid_json'])) ResponseHelper::error('Malformed JSON request body', 400);
        if (!is_array($data)) ResponseHelper::error('Validation failed', 400, ['body' => 'A JSON object is required']);
        return $data;
    }

    private function requireText($data, $field, $max = 5000)
    {
        if (!isset($data[$field]) || !is_string($data[$field]) || trim($data[$field]) === '') ResponseHelper::error('Validation failed', 400, [$field => 'This field is required']);
        if (mb_strlen($data[$field]) > $max) ResponseHelper::error('Validation failed', 400, [$field => "Must not exceed {$max} characters"]);
        return trim($data[$field]);
    }

    private function audit($action, $type, $id, $previous = null, $new = null, $reason = null)
    {
        $this->model->audit($this->user->user_id, $this->user->role, $action, $type, $id, $previous, $new, $reason);
    }

    public function overview() { $this->guard('users'); ResponseHelper::success('Internal admin overview fetched successfully', $this->model->overview()); }
    public function jobs() { $this->guard('jobs'); ResponseHelper::success('Jobs fetched successfully', $this->model->jobs($_GET)); }
    public function job($jobId) { $this->guard('jobs'); $row=$this->model->job($jobId); if(!$row)ResponseHelper::error('Job not found',404); ResponseHelper::success('Job fetched successfully',$row); }
    public function jobStatus($jobId)
    {
        $this->guard('jobs','approve'); $data=$this->body(); $status=$this->requireText($data,'status',40); $note=$this->requireText($data,'internal_note',5000);
        if (!in_array($status,['published','rejected','changes_requested'],true)) ResponseHelper::error('Validation failed',400,['status'=>'Unsupported moderation status']);
        $result=$this->model->updateJobStatus($jobId,$this->user->user_id,$status,$note); if(isset($result['error']))ResponseHelper::error($result['error'],422); $this->audit('job_status_changed','job',$jobId,null,$result,$note); ResponseHelper::success('Job status updated successfully',$result);
    }
    public function schools() { $this->guard('schools'); ResponseHelper::success('Schools fetched successfully',$this->model->schools($_GET)); }
    public function school($id) { $this->guard('schools'); $row=$this->model->school($id);if(!$row)ResponseHelper::error('School not found',404);ResponseHelper::success('School fetched successfully',$row); }
    public function schoolStatus($id) { $this->guard('schools','edit');$data=$this->body();$status=$this->requireText($data,'status',30);$reason=$this->requireText($data,'reason',5000);if(!in_array($status,['active','suspended'],true))ResponseHelper::error('Validation failed',400,['status'=>'Must be active or suspended']);$result=$this->model->updateSchoolStatus($id,$this->user->user_id,$status,$reason);if(!$result)ResponseHelper::error('School not found or status update failed',422);$this->audit('school_status_changed','school',$id,null,$result,$reason);ResponseHelper::success('School status updated successfully',$result); }
    public function teachers() { $this->guard('users'); ResponseHelper::success('Teachers fetched successfully',$this->model->teachers($_GET)); }
    public function teacher($id) { $this->guard('users');$row=$this->model->teacher($id);if(!$row)ResponseHelper::error('Teacher not found',404);ResponseHelper::success('Teacher fetched successfully',$row); }
    public function teacherApplications($id) { $this->guard('applications');ResponseHelper::success('Teacher applications fetched successfully',$this->model->teacherApplications($id,$_GET)); }
    public function teacherStatus($id) { $this->guard('users','edit');$data=$this->body();$status=$this->requireText($data,'status',30);$reason=$this->requireText($data,'reason',5000);$result=$this->model->updateTeacherStatus($id,$this->user->user_id,$status,$reason);if(!$result)ResponseHelper::error('Teacher not found or status update failed',422);$this->audit('teacher_status_changed','teacher',$id,null,$result,$reason);ResponseHelper::success('Teacher status updated successfully',$result); }
    public function admins() { $this->guard('admins');ResponseHelper::success('Internal admins fetched successfully',$this->model->admins($_GET)); }
    public function invitations() { $this->guard('admins');ResponseHelper::success('Admin invitations fetched successfully',$this->model->invitations($_GET)); }
    public function inviteAdmin() { $this->guard('admins','create');$data=$this->body();foreach(['first_name','last_name','email','admin_role'] as $field)$this->requireText($data,$field,255);if(!filter_var($data['email'],FILTER_VALIDATE_EMAIL))ResponseHelper::error('Validation failed',400,['email'=>'Invalid email format']);if(!in_array($data['admin_role'],['Super Admin','Operations Admin','Verification Admin','Support Admin'],true))ResponseHelper::error('Validation failed',400,['admin_role'=>'Unsupported admin role']);$result=$this->model->createInvitation($data,$this->user->user_id);if(!$result)ResponseHelper::error('An active invitation already exists for this email',409);$rawToken=$result['token'];MailHelper::send($data['email'],'Staffroom internal admin invitation','You have been invited to Staffroom Internal Admin. Invitation token: '.$rawToken.'. This token expires in 72 hours.');unset($result['token']);$this->audit('admin_invited','admin_invitation',$result['invitation_id'],null,$result);ResponseHelper::success('Invitation created successfully',$result,201); }
    public function resendInvitation($id) { $this->guard('admins','create');if(!$this->model->updateInvitation($id,'pending'))ResponseHelper::error('Invitation not found or cannot be resent',422);$this->audit('admin_invitation_resent','admin_invitation',$id);ResponseHelper::success('Invitation resent successfully',['invitation_id'=>$id]); }
    public function revokeInvitation($id) { $this->guard('admins','edit');if(!$this->model->updateInvitation($id,'revoked'))ResponseHelper::error('Invitation not found or already closed',422);$this->audit('admin_invitation_revoked','admin_invitation',$id);ResponseHelper::success('Invitation revoked successfully',['invitation_id'=>$id]); }
    public function adminStatus($id) { $this->guard('admins','edit');$data=$this->body();$status=$this->requireText($data,'status',30);$reason=$this->requireText($data,'reason',5000);if(in_array($status,['suspended','revoked'],true)&&$this->model->isFinalSuperAdmin($id))ResponseHelper::error('The final active Super Admin cannot be suspended or revoked',422);if(!$this->model->updateAdmin($id,'status',$status))ResponseHelper::error('Admin status update failed',422);$this->audit('admin_status_changed','admin',$id,null,['status'=>$status],$reason);ResponseHelper::success('Admin status updated successfully',['admin_id'=>$id,'status'=>$status]); }
    public function adminRole($id) { $this->guard('admins','edit');$data=$this->body();$role=$this->requireText($data,'admin_role',50);if(!$this->model->updateAdmin($id,'admin_role',$role))ResponseHelper::error('Admin role update failed',422);$this->audit('admin_role_changed','admin',$id,null,['admin_role'=>$role]);ResponseHelper::success('Admin role updated successfully',['admin_id'=>$id,'admin_role'=>$role]); }
    public function reports() { $this->guard('reports');ResponseHelper::success('Reports fetched successfully',$this->model->reports($_GET)); }
    public function report($id) { $this->guard('reports');$row=$this->model->report($id);if(!$row)ResponseHelper::error('Report not found',404);ResponseHelper::success('Report fetched successfully',$row); }
    public function reportStatus($id) { $this->guard('reports','edit');$data=$this->body();$status=$this->requireText($data,'status',30);if(!$this->model->updateReport($id,$data))ResponseHelper::error('Invalid report status update',422);$this->audit('report_status_changed','report',$id,null,['status'=>$status],$data['resolution_note']??null);ResponseHelper::success('Report updated successfully',['report_id'=>$id,'status'=>$status]); }
    public function notifications() { $this->guard('users');ResponseHelper::success('Notifications fetched successfully',$this->model->notifications($this->user->user_id,$_GET)); }
    public function notificationRead($id) { $this->guard('users','edit');$this->model->markNotification($id,$this->user->user_id);ResponseHelper::success('Notification marked as read',['notification_id'=>$id]); }
    public function notificationReadAll() { $this->guard('users','edit');$this->model->markAllNotifications($this->user->user_id);ResponseHelper::success('Notifications marked as read',[]); }
    public function settings() { $this->guard('settings');ResponseHelper::success('Settings fetched successfully',$this->model->settings()); }
    public function updateSettings() { $this->guard('settings','edit');$data=$this->body();$this->model->updateSettings($data,$this->user->user_id);$this->audit('settings_updated','settings','platform',null,$data);ResponseHelper::success('Settings updated successfully',[]); }
    public function auditLog() { $this->guard('reports');ResponseHelper::success('Audit log fetched successfully',$this->model->auditLog($_GET)); }
    public function acceptInvitation() { $data=$this->body();$token=$this->requireText($data,'token',200);$password=$this->requireText($data,'password',255);if(strlen($password)<8)ResponseHelper::error('Validation failed',400,['password'=>'Must be at least 8 characters']);$userId=$this->model->acceptInvitation($token,$password);if(!$userId)ResponseHelper::error('Invalid or expired invitation',422);ResponseHelper::success('Invitation accepted successfully',['user_id'=>$userId],201); }
}
?>
