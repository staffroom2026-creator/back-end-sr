<?php
/**
 * Internal admin dashboard data access. All values are bound through PDO.
 */
class InternalAdmin
{
    private $db;

    public function __construct()
    {
        $this->db = new Database();
    }

    private function id($prefix)
    {
        return $prefix . '-' . bin2hex(random_bytes(16));
    }

    private function page($value, $default = 1)
    {
        return max(1, (int)($value ?: $default));
    }

    private function perPage($value, $default = 20)
    {
        return min(100, max(1, (int)($value ?: $default)));
    }

    private function pagination($page, $perPage, $total)
    {
        $last = $total > 0 ? (int)ceil($total / $perPage) : 1;
        return ['current_page' => $page, 'per_page' => $perPage, 'total' => $total, 'last_page' => $last, 'has_more' => $page < $last];
    }

    public function profile($userId)
    {
        $this->db->query('SELECT * FROM internal_admin_profiles WHERE user_id = :user_id');
        $this->db->bind(':user_id', $userId);
        return $this->db->single() ?: false;
    }

    public function permissions($userId, $adminRole)
    {
        $full = ['view' => true, 'create' => true, 'edit' => true, 'approve' => true, 'delete' => true];
        if ($adminRole === 'Super Admin') return array_fill_keys(['users','schools','jobs','applications','verification','reports','admins','settings'], $full);
        $allowed = [
            'Operations Admin' => ['users','schools','jobs','applications','reports'],
            'Verification Admin' => ['users','schools','jobs','applications','verification'],
            'Support Admin' => ['users','schools','applications','reports']
        ];
        $result = [];
        foreach (($allowed[$adminRole] ?? []) as $module) {
            $result[$module] = ['view' => true, 'create' => false, 'edit' => true, 'approve' => false, 'delete' => false];
        }
        $this->db->query('SELECT module_name, permissions FROM internal_admin_permissions WHERE user_id = :user_id');
        $this->db->bind(':user_id', $userId);
        foreach ($this->db->resultSet() as $row) {
            $decoded = json_decode($row->permissions, true);
            if (is_array($decoded)) $result[$row->module_name] = array_merge($result[$row->module_name] ?? [], $decoded);
        }
        return $result;
    }

    public function overview()
    {
        $queries = [
            'total_teachers' => "SELECT COUNT(*) count FROM initkey WHERE role='teacher' AND status <> 'deleted'",
            'total_schools' => "SELECT COUNT(*) count FROM initkey WHERE role='school' AND status <> 'deleted'",
            'active_jobs' => "SELECT COUNT(*) count FROM jobs WHERE status IN ('published','open')",
            'pending_reviews' => "SELECT COUNT(*) count FROM jobs WHERE status IN ('pending_review','changes_requested','draft')"
        ];
        $stats = [];
        foreach ($queries as $key => $sql) { $this->db->query($sql); $stats[$key] = (int)$this->db->single()->count; }
        $summary = [];
        foreach (['published','pending_review','changes_requested','rejected','closed','reported'] as $status) {
            $statusSql = $status === 'published' ? "status IN ('published','open')" : ($status === 'pending_review' ? "status IN ('pending_review','draft')" : 'status = :status');
            $this->db->query("SELECT COUNT(*) count FROM jobs WHERE {$statusSql}"); if (!in_array($status, ['published','pending_review'], true)) $this->db->bind(':status', $status); $summary[$status === 'pending_review' ? 'pending' : $status] = (int)$this->db->single()->count;
        }
        $this->db->query("SELECT jobs.job_id, jobs.title, school_profiles.school_name, jobs.created_at submitted_at, jobs.status FROM jobs LEFT JOIN school_profiles ON school_profiles.user_id=jobs.school_id WHERE jobs.status IN ('pending_review','changes_requested','draft') ORDER BY jobs.created_at DESC LIMIT 20");
        $pending = $this->db->resultSet();
        $this->db->query("SELECT audit_id activity_id, actor_user_id actor_id, action, entity_type, entity_id, reason description, created_at FROM internal_audit_logs ORDER BY created_at DESC LIMIT 20");
        $activity = $this->db->resultSet();
        return ['stats' => $stats, 'job_summary' => $summary, 'quick_stats' => ['verification_requests' => $this->count('school_profiles', "verification_status='pending'"), 'new_reports' => $this->count('internal_reports', "status='new'")], 'pending_jobs' => $pending, 'recent_activity' => $activity];
    }

    private function count($table, $where = '1=1')
    {
        $this->db->query("SELECT COUNT(*) count FROM {$table} WHERE {$where}");
        return (int)$this->db->single()->count;
    }

    public function jobs($filters)
    {
        $page = $this->page($filters['page'] ?? 1); $per = $this->perPage($filters['per_page'] ?? 20); $offset = ($page - 1) * $per;
        $where = ['1=1']; $bind = [];
        if (!empty($filters['status'])) { $jobStatusSql = $filters['status'] === 'published' ? "jobs.status IN ('published','open')" : ($filters['status'] === 'pending_review' ? "jobs.status IN ('pending_review','draft')" : 'jobs.status = :status'); $where[] = $jobStatusSql; if (!in_array($filters['status'], ['published','pending_review'], true)) $bind[':status'] = $filters['status']; }
        if (!empty($filters['school_id'])) { $where[] = 'jobs.school_id = :school_id'; $bind[':school_id'] = $filters['school_id']; }
        if (!empty($filters['location'])) { $where[] = 'jobs.location LIKE :location'; $bind[':location'] = '%' . $filters['location'] . '%'; }
        if (!empty($filters['search'])) { $where[] = '(jobs.title LIKE :search OR jobs.description LIKE :search_desc)'; $bind[':search'] = '%' . $filters['search'] . '%'; $bind[':search_desc'] = '%' . $filters['search'] . '%'; }
        if (!empty($filters['from_date'])) { $where[] = 'jobs.created_at >= :from_date'; $bind[':from_date'] = $filters['from_date'] . ' 00:00:00'; }
        if (!empty($filters['to_date'])) { $where[] = 'jobs.created_at <= :to_date'; $bind[':to_date'] = $filters['to_date'] . ' 23:59:59'; }
        $whereSql = implode(' AND ', $where);
        $this->db->query("SELECT COUNT(*) count FROM jobs LEFT JOIN school_profiles ON school_profiles.user_id=jobs.school_id WHERE {$whereSql}"); foreach ($bind as $k => $v) $this->db->bind($k, $v); $total = (int)$this->db->single()->count;
        $sort = ['created_at' => 'jobs.created_at', 'updated_at' => 'jobs.updated_at', 'title' => 'jobs.title']; $order = $sort[$filters['sort'] ?? 'created_at'] ?? 'jobs.created_at';
        $this->db->query("SELECT jobs.job_id, jobs.title, jobs.school_id, school_profiles.school_name, jobs.location, jobs.employment_type, jobs.salary_range, jobs.role_type subject, jobs.description, jobs.responsibilities, jobs.requirements, jobs.status, jobs.created_at submitted_at, jobs.updated_at, jobs.reviewed_at, jobs.reviewed_by FROM jobs LEFT JOIN school_profiles ON school_profiles.user_id=jobs.school_id WHERE {$whereSql} ORDER BY {$order} DESC LIMIT :limit OFFSET :offset"); foreach ($bind as $k => $v) $this->db->bind($k, $v); $this->db->bind(':limit', $per, PDO::PARAM_INT); $this->db->bind(':offset', $offset, PDO::PARAM_INT);
        return ['jobs' => $this->db->resultSet(), 'pagination' => $this->pagination($page, $per, $total)];
    }

    public function job($jobId)
    {
        $this->db->query('SELECT jobs.*, school_profiles.school_name, school_profiles.school_type, school_profiles.address, school_profiles.state, school_profiles.country, school_profiles.verification_status, school_profiles.admin_status FROM jobs LEFT JOIN school_profiles ON school_profiles.user_id=jobs.school_id WHERE jobs.job_id=:job_id'); $this->db->bind(':job_id', $jobId); $job = $this->db->single(); if (!$job) return false;
        $this->db->query('SELECT * FROM internal_job_review_history WHERE job_id=:job_id ORDER BY created_at DESC'); $this->db->bind(':job_id', $jobId); $job->review_history = $this->db->resultSet();
        $this->db->query("SELECT COUNT(*) count FROM applications WHERE job_id=:job_id"); $this->db->bind(':job_id', $jobId); $job->application_count = (int)$this->db->single()->count;
        return $job;
    }

    public function updateJobStatus($jobId, $actor, $status, $note)
    {
        $this->db->query('SELECT status FROM jobs WHERE job_id=:job_id FOR UPDATE'); $this->db->bind(':job_id', $jobId); $job = $this->db->single(); if (!$job) return ['error' => 'Job not found'];
        if (!in_array($job->status, ['pending_review','changes_requested','draft'], true) || !in_array($status, ['published','rejected','changes_requested'], true)) return ['error' => 'Invalid job status transition'];
        $this->db->beginTransaction();
        $this->db->query('UPDATE jobs SET status=:status, reviewed_at=NOW(), reviewed_by=:reviewed_by, internal_review_note=:note WHERE job_id=:job_id'); $this->db->bind(':status',$status); $this->db->bind(':reviewed_by',$actor); $this->db->bind(':note',$note); $this->db->bind(':job_id',$jobId); $this->db->execute();
        $reviewId=$this->id('REV'); $this->db->query('INSERT INTO internal_job_review_history (review_id,job_id,reviewer_id,previous_status,new_status,internal_note) VALUES (:id,:job,:actor,:previous,:new_status,:note)'); foreach ([':id'=>$reviewId,':job'=>$jobId,':actor'=>$actor,':previous'=>$job->status,':new_status'=>$status,':note'=>$note] as $k=>$v) $this->db->bind($k,$v); $this->db->execute();
        $this->db->query('SELECT school_id FROM jobs WHERE job_id=:job_id'); $this->db->bind(':job_id',$jobId); $school=$this->db->single(); if ($school) $this->notify($school->school_id,'Job moderation update','Your job is now '.$status,'job_moderation',$jobId);
        $this->db->commit(); return ['job_id'=>$jobId,'status'=>$status];
    }

    public function schools($filters)
    {
        $page=$this->page($filters['page']??1);$per=$this->perPage($filters['per_page']??20);$offset=($page-1)*$per;$where=["initkey.role='school'"]; $bind=[];
        if (!empty($filters['status'])) {$where[]='school_profiles.admin_status=:status';$bind[':status']=$filters['status'];} if (!empty($filters['search'])) {$where[]='(school_profiles.school_name LIKE :search OR initkey.email LIKE :email)';$bind[':search']='%'.$filters['search'].'%';$bind[':email']='%'.$filters['search'].'%';} if (!empty($filters['location'])) {$where[]='(school_profiles.lga LIKE :location OR school_profiles.state LIKE :state OR school_profiles.address LIKE :address)';$v='%'.$filters['location'].'%';$bind[':location']=$v;$bind[':state']=$v;$bind[':address']=$v;}
        $w=implode(' AND ',$where);$this->db->query("SELECT COUNT(*) count FROM school_profiles JOIN initkey ON initkey.user_id=school_profiles.user_id WHERE {$w}");foreach($bind as $k=>$v)$this->db->bind($k,$v);$total=(int)$this->db->single()->count;
        $this->db->query("SELECT school_profiles.user_id school_id, school_profiles.school_name, school_profiles.school_type, initkey.email, initkey.phone, school_profiles.address, school_profiles.lga city, school_profiles.state, school_profiles.country, initkey.created_at joined_at, school_profiles.admin_status status, school_profiles.verification_status, school_profiles.created_at, (SELECT COUNT(*) FROM jobs j WHERE j.school_id=school_profiles.user_id AND j.status IN ('published','open')) active_jobs, (SELECT COUNT(*) FROM jobs j WHERE j.school_id=school_profiles.user_id AND j.status='closed') closed_jobs, (SELECT COUNT(*) FROM applications a JOIN jobs j2 ON j2.job_id=a.job_id WHERE j2.school_id=school_profiles.user_id) applications_count FROM school_profiles JOIN initkey ON initkey.user_id=school_profiles.user_id WHERE {$w} ORDER BY school_profiles.created_at DESC LIMIT :limit OFFSET :offset");foreach($bind as $k=>$v)$this->db->bind($k,$v);$this->db->bind(':limit',$per,PDO::PARAM_INT);$this->db->bind(':offset',$offset,PDO::PARAM_INT);$rows=$this->db->resultSet();return ['schools'=>$rows,'pagination'=>$this->pagination($page,$per,$total)];
    }

    public function school($id){$this->db->query('SELECT school_profiles.*, initkey.email,initkey.phone,initkey.status user_status,initkey.created_at joined_at FROM school_profiles JOIN initkey ON initkey.user_id=school_profiles.user_id WHERE school_profiles.user_id=:user_id OR school_profiles.profile_id=:profile_id');$this->db->bind(':user_id',$id);$this->db->bind(':profile_id',$id);$school=$this->db->single();if(!$school)return false;$this->db->query('SELECT * FROM jobs WHERE school_id=:school_id ORDER BY created_at DESC LIMIT 20');$this->db->bind(':school_id',$school->user_id);$school->recent_jobs=$this->db->resultSet();return $school;}

    public function updateSchoolStatus($id,$actor,$status,$reason){$this->db->query('SELECT admin_status FROM school_profiles WHERE user_id=:user_id OR profile_id=:profile_id');$this->db->bind(':user_id',$id);$this->db->bind(':profile_id',$id);$row=$this->db->single();if(!$row||!in_array($status,['active','suspended'],true))return false;$this->db->query('UPDATE school_profiles SET admin_status=:status WHERE user_id=:user_id OR profile_id=:profile_id');$this->db->bind(':status',$status);$this->db->bind(':user_id',$id);$this->db->bind(':profile_id',$id);$this->db->execute();$this->db->query('SELECT user_id FROM school_profiles WHERE user_id=:user_id OR profile_id=:profile_id');$this->db->bind(':user_id',$id);$this->db->bind(':profile_id',$id);$school=$this->db->single();$this->db->query('INSERT INTO internal_school_status_history (history_id,school_id,actor_id,previous_status,new_status,reason) VALUES (:history,:school,:actor,:previous,:new_status,:reason)');foreach([':history'=>$this->id('SCH'),':school'=>$school->user_id,':actor'=>$actor,':previous'=>$row->admin_status,':new_status'=>$status,':reason'=>$reason] as $k=>$v)$this->db->bind($k,$v);$this->db->execute();$this->notify($school->user_id,'School status updated','Your school account is now '.$status,'school_moderation',$school->user_id);return ['school_id'=>$school->user_id,'status'=>$status];}

    public function teachers($filters){$page=$this->page($filters['page']??1);$per=$this->perPage($filters['per_page']??20);$offset=($page-1)*$per;$this->db->query("SELECT COUNT(*) count FROM initkey WHERE role='teacher' AND status<>'deleted'");$total=(int)$this->db->single()->count;$this->db->query("SELECT initkey.user_id,initkey.full_name,initkey.email,initkey.phone,initkey.created_at joined_at,initkey.status,teacher_profiles.role_title professional_title,teacher_profiles.skills primary_subject,teacher_profiles.location,teacher_profiles.trcn_status verification_status,teacher_profiles.profile_visibility FROM initkey LEFT JOIN teacher_profiles ON teacher_profiles.user_id=initkey.user_id WHERE initkey.role='teacher' AND initkey.status<>'deleted' ORDER BY initkey.created_at DESC LIMIT :limit OFFSET :offset");$this->db->bind(':limit',$per,PDO::PARAM_INT);$this->db->bind(':offset',$offset,PDO::PARAM_INT);return ['teachers'=>$this->db->resultSet(),'pagination'=>$this->pagination($page,$per,$total)];}
    public function teacher($id){$this->db->query('SELECT initkey.user_id,initkey.full_name,initkey.email,initkey.phone,initkey.created_at joined_at,initkey.status,teacher_profiles.* FROM initkey LEFT JOIN teacher_profiles ON teacher_profiles.user_id=initkey.user_id WHERE initkey.user_id=:id AND initkey.role="teacher"');$this->db->bind(':id',$id);$row=$this->db->single();if(!$row)return false;$this->db->query('SELECT application_id,job_id,status,created_at applied_at,updated_at FROM applications WHERE teacher_id=:id ORDER BY created_at DESC LIMIT 20');$this->db->bind(':id',$id);$row->recent_applications=$this->db->resultSet();return $row;}
    public function teacherApplications($id,$filters){$page=$this->page($filters['page']??1);$per=$this->perPage($filters['per_page']??20);$offset=($page-1)*$per;$this->db->query('SELECT COUNT(*) count FROM applications WHERE teacher_id=:id');$this->db->bind(':id',$id);$total=(int)$this->db->single()->count;$this->db->query('SELECT applications.application_id,applications.job_id,jobs.title job_title,school_profiles.school_name,applications.status application_status,applications.created_at applied_at,applications.updated_at FROM applications JOIN jobs ON jobs.job_id=applications.job_id JOIN school_profiles ON school_profiles.user_id=jobs.school_id WHERE applications.teacher_id=:id ORDER BY applications.created_at DESC LIMIT :limit OFFSET :offset');$this->db->bind(':id',$id);$this->db->bind(':limit',$per,PDO::PARAM_INT);$this->db->bind(':offset',$offset,PDO::PARAM_INT);return ['applications'=>$this->db->resultSet(),'pagination'=>$this->pagination($page,$per,$total)];}
    public function updateTeacherStatus($id,$actor,$status,$reason){$this->db->query('SELECT status FROM initkey WHERE user_id=:id AND role="teacher"');$this->db->bind(':id',$id);$row=$this->db->single();if(!$row||!in_array($status,['active','suspended'],true))return false;$this->db->query('UPDATE initkey SET status=:status,active=:active WHERE user_id=:id');$this->db->bind(':status',$status);$this->db->bind(':active',$status==='active'?1:0);$this->db->bind(':id',$id);$this->db->execute();$this->db->query('INSERT INTO internal_teacher_status_history (history_id,teacher_id,actor_id,previous_status,new_status,reason) VALUES (:history,:teacher,:actor,:previous,:new_status,:reason)');foreach([':history'=>$this->id('TEA'),':teacher'=>$id,':actor'=>$actor,':previous'=>$row->status,':new_status'=>$status,':reason'=>$reason] as $k=>$v)$this->db->bind($k,$v);$this->db->execute();return ['user_id'=>$id,'status'=>$status];}

    public function admins($filters){$page=$this->page($filters['page']??1);$per=$this->perPage($filters['per_page']??20);$offset=($page-1)*$per;$this->db->query("SELECT COUNT(*) count FROM initkey WHERE role='police'");$total=(int)$this->db->single()->count;$this->db->query("SELECT initkey.user_id admin_id,initkey.full_name,initkey.email,initkey.role,internal_admin_profiles.admin_role,internal_admin_profiles.permissions,internal_admin_profiles.status,internal_admin_profiles.last_active_at,initkey.created_at FROM initkey LEFT JOIN internal_admin_profiles ON internal_admin_profiles.user_id=initkey.user_id WHERE initkey.role='police' ORDER BY initkey.created_at DESC LIMIT :limit OFFSET :offset");$this->db->bind(':limit',$per,PDO::PARAM_INT);$this->db->bind(':offset',$offset,PDO::PARAM_INT);return ['admins'=>$this->db->resultSet(),'pagination'=>$this->pagination($page,$per,$total)];}
    public function invitations($filters){$this->db->query('SELECT invitation_id,first_name,last_name,email,admin_role,status,expires_at,invited_by,created_at FROM internal_admin_invitations ORDER BY created_at DESC');return $this->db->resultSet();}
    public function createInvitation($data,$actor){$this->db->query("SELECT invitation_id FROM internal_admin_invitations WHERE email=:email AND status='pending' AND expires_at>NOW()");$this->db->bind(':email',$data['email']);if($this->db->single())return false;$token=bin2hex(random_bytes(32));$id=$this->id('INV');$this->db->query('INSERT INTO internal_admin_invitations (invitation_id,first_name,last_name,email,admin_role,permissions,token_hash,expires_at,invited_by) VALUES (:id,:first,:last,:email,:role,:permissions,:hash,DATE_ADD(NOW(),INTERVAL 72 HOUR),:actor)');foreach([':id'=>$id,':first'=>$data['first_name'],':last'=>$data['last_name'],':email'=>$data['email'],':role'=>$data['admin_role'],':permissions'=>json_encode($data['permissions']??[]),':hash'=>hash('sha256',$token),':actor'=>$actor] as $k=>$v)$this->db->bind($k,$v);$this->db->execute();return ['invitation_id'=>$id,'token'=>$token,'expires_in'=>259200];}
    public function updateInvitation($id,$status){$this->db->query('UPDATE internal_admin_invitations SET status=:status WHERE invitation_id=:id AND status="pending"');$this->db->bind(':status',$status);$this->db->bind(':id',$id);return $this->db->execute()&&$this->db->rowCount()>0;}
    public function acceptInvitation($token, $password)
    {
        $this->db->query('SELECT * FROM internal_admin_invitations WHERE token_hash=:hash AND status="pending" AND expires_at>NOW()');
        $this->db->bind(':hash', hash('sha256', $token));
        $invitation = $this->db->single();
        if (!$invitation) return false;
        $userId = $this->id('USR');
        $this->db->beginTransaction();
        $this->db->query("INSERT INTO initkey (user_id,first_name,last_name,terms,full_name,name,email,password,role,status,active,email_verified_at) VALUES (:id,:first,:last,'1',:full_name,:name,:email,:password,'police','active',1,NOW())");
        foreach ([':id'=>$userId,':first'=>$invitation->first_name,':last'=>$invitation->last_name,':full_name'=>trim($invitation->first_name.' '.$invitation->last_name),':name'=>trim($invitation->first_name.' '.$invitation->last_name),':email'=>$invitation->email,':password'=>password_hash($password,PASSWORD_DEFAULT)] as $key=>$value) $this->db->bind($key,$value);
        if (!$this->db->execute()) { $this->db->rollBack(); return false; }
        $this->db->query('INSERT INTO internal_admin_profiles (user_id,admin_role,permissions,status,created_by) VALUES (:user_id,:role,:permissions,"active",:created_by)');
        $this->db->bind(':user_id',$userId);$this->db->bind(':role',$invitation->admin_role);$this->db->bind(':permissions',$invitation->permissions);$this->db->bind(':created_by',$invitation->invited_by);$this->db->execute();
        $this->db->query('UPDATE internal_admin_invitations SET status="accepted",accepted_by=:user_id WHERE invitation_id=:invitation_id');$this->db->bind(':user_id',$userId);$this->db->bind(':invitation_id',$invitation->invitation_id);$this->db->execute();$this->db->commit();
        return $userId;
    }
    public function updateAdmin($id,$field,$value){$allowed=['status','admin_role'];$column=$field==='admin_role'?'admin_role':'status';if(!in_array($value,$field==='status'?['active','suspended','pending','revoked']:['Super Admin','Operations Admin','Verification Admin','Support Admin'],true))return false;$this->db->query("UPDATE internal_admin_profiles SET {$column}=:value WHERE user_id=:id");$this->db->bind(':value',$value);$this->db->bind(':id',$id);return $this->db->execute();}
    public function isFinalSuperAdmin($id){$this->db->query("SELECT COUNT(*) count FROM internal_admin_profiles WHERE admin_role='Super Admin' AND status='active'");$total=(int)$this->db->single()->count;$this->db->query("SELECT COUNT(*) count FROM internal_admin_profiles WHERE user_id=:id AND admin_role='Super Admin' AND status='active'");$this->db->bind(':id',$id);return $total <= 1 && (int)$this->db->single()->count === 1;}

    public function reports($filters){$page=$this->page($filters['page']??1);$per=$this->perPage($filters['per_page']??20);$offset=($page-1)*$per;$this->db->query('SELECT COUNT(*) count FROM internal_reports');$total=(int)$this->db->single()->count;$this->db->query('SELECT r.*,u.full_name reporter FROM internal_reports r LEFT JOIN initkey u ON u.user_id=r.reporter_id ORDER BY r.created_at DESC LIMIT :limit OFFSET :offset');$this->db->bind(':limit',$per,PDO::PARAM_INT);$this->db->bind(':offset',$offset,PDO::PARAM_INT);return ['reports'=>$this->db->resultSet(),'pagination'=>$this->pagination($page,$per,$total)];}
    public function report($id){$this->db->query('SELECT * FROM internal_reports WHERE report_id=:id');$this->db->bind(':id',$id);return $this->db->single()?:false;}
    public function updateReport($id,$data){$status=$data['status']??null;if(!in_array($status,['assigned','investigating','resolved','dismissed'],true))return false;$this->db->query('UPDATE internal_reports SET status=:status,resolution_note=:note,assigned_to=:assigned,resolved_at=IF(:status2="resolved",NOW(),resolved_at) WHERE report_id=:id');$this->db->bind(':status',$status);$this->db->bind(':status2',$status);$this->db->bind(':note',$data['resolution_note']??null);$this->db->bind(':assigned',$data['assigned_to']??null);$this->db->bind(':id',$id);return $this->db->execute();}
    public function notifications($userId,$filters){$page=$this->page($filters['page']??1);$per=$this->perPage($filters['per_page']??20);$offset=($page-1)*$per;$where='user_id=:user_id';if(!empty($filters['unread_only']))$where.=' AND is_read=0';$this->db->query("SELECT COUNT(*) count FROM internal_notifications WHERE {$where}");$this->db->bind(':user_id',$userId);$total=(int)$this->db->single()->count;$this->db->query("SELECT notification_id,title,message,type,is_read,related_id,created_at FROM internal_notifications WHERE {$where} ORDER BY created_at DESC LIMIT :limit OFFSET :offset");$this->db->bind(':user_id',$userId);$this->db->bind(':limit',$per,PDO::PARAM_INT);$this->db->bind(':offset',$offset,PDO::PARAM_INT);return ['notifications'=>$this->db->resultSet(),'pagination'=>$this->pagination($page,$per,$total)];}
    public function markNotification($id,$userId){$this->db->query('UPDATE internal_notifications SET is_read=1 WHERE notification_id=:id AND user_id=:user_id');$this->db->bind(':id',$id);$this->db->bind(':user_id',$userId);return $this->db->execute();}
    public function markAllNotifications($userId){$this->db->query('UPDATE internal_notifications SET is_read=1 WHERE user_id=:user_id');$this->db->bind(':user_id',$userId);return $this->db->execute();}
    public function settings(){ $this->db->query('SELECT setting_key,setting_value,value_type,description,updated_by,updated_at FROM internal_platform_settings WHERE setting_key NOT LIKE "%password%" AND setting_key NOT LIKE "%secret%" AND setting_key NOT LIKE "%key%"');return $this->db->resultSet(); }
    public function updateSettings($settings,$actor){foreach($settings as $key=>$value){if(preg_match('/password|secret|private|credential|token/i',$key))continue;$this->db->query('INSERT INTO internal_platform_settings (setting_key,setting_value,value_type,updated_by) VALUES (:key,:value,:type,:actor) ON DUPLICATE KEY UPDATE setting_value=:value2,value_type=:type2,updated_by=:actor2');$type=is_bool($value)?'boolean':(is_numeric($value)?'number':'string');foreach([':key'=>$key,':value'=>is_scalar($value)?(string)$value:json_encode($value),':type'=>$type,':actor'=>$actor,':value2'=>is_scalar($value)?(string)$value:json_encode($value),':type2'=>$type,':actor2'=>$actor] as $k=>$v)$this->db->bind($k,$v);$this->db->execute();}return true;}
    public function auditLog($filters){$page=$this->page($filters['page']??1);$per=$this->perPage($filters['per_page']??20);$offset=($page-1)*$per;$this->db->query('SELECT COUNT(*) count FROM internal_audit_logs');$total=(int)$this->db->single()->count;$this->db->query('SELECT audit_id,actor_user_id,actor_role,action,entity_type,entity_id,previous_value,new_value,reason,ip_address,user_agent,created_at FROM internal_audit_logs ORDER BY created_at DESC LIMIT :limit OFFSET :offset');$this->db->bind(':limit',$per,PDO::PARAM_INT);$this->db->bind(':offset',$offset,PDO::PARAM_INT);return ['audit_log'=>$this->db->resultSet(),'pagination'=>$this->pagination($page,$per,$total)];}
    public function notify($userId,$title,$message,$type,$relatedId=null){$this->db->query('INSERT INTO internal_notifications (notification_id,user_id,title,message,type,related_id) VALUES (:id,:user_id,:title,:message,:type,:related_id)');foreach([':id'=>$this->id('NTF'),':user_id'=>$userId,':title'=>$title,':message'=>$message,':type'=>$type,':related_id'=>$relatedId] as $k=>$v)$this->db->bind($k,$v);return $this->db->execute();}
    public function audit($actor,$role,$action,$type,$entity,$previous=null,$new=null,$reason=null){$this->db->query('INSERT INTO internal_audit_logs (audit_id,actor_user_id,actor_role,action,entity_type,entity_id,previous_value,new_value,reason,ip_address,user_agent) VALUES (:id,:actor,:role,:action,:type,:entity,:previous,:new_value,:reason,:ip,:agent)');foreach([':id'=>$this->id('AUD'),':actor'=>$actor,':role'=>$role,':action'=>$action,':type'=>$type,':entity'=>$entity,':previous'=>$previous?json_encode($previous):null,':new_value'=>$new?json_encode($new):null,':reason'=>$reason,':ip'=>$_SERVER['REMOTE_ADDR']??null,':agent'=>$_SERVER['HTTP_USER_AGENT']??null] as $k=>$v)$this->db->bind($k,$v);return $this->db->execute();}
}
?>
