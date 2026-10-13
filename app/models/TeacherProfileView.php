<?php
/**
 * Teacher profile view tracking and teacher-owned view analytics.
 */

class TeacherProfileView
{
    private $db;

    public function __construct()
    {
        $this->db = new Database();
    }

    public function record($teacherUserId, $schoolUserId, $meta = [])
    {
        $viewId = hash('sha256', uniqid('', true) . microtime(true));
        $this->db->query('INSERT INTO teacher_profile_views
            (view_id, teacher_user_id, school_user_id, viewed_at, ip_address, user_agent)
            VALUES (:view_id, :teacher_user_id, :school_user_id, NOW(), :ip_address, :user_agent)');
        $this->db->bind(':view_id', $viewId);
        $this->db->bind(':teacher_user_id', $teacherUserId);
        $this->db->bind(':school_user_id', $schoolUserId);
        $this->db->bind(':ip_address', !empty($meta['ip_address']) ? $meta['ip_address'] : null);
        $this->db->bind(':user_agent', !empty($meta['user_agent']) ? $meta['user_agent'] : null);
        return $this->db->execute();
    }

    public function summary($teacherUserId)
    {
        $this->db->query('SELECT COUNT(*) AS total_views,
                                 COUNT(DISTINCT school_user_id) AS unique_schools,
                                 MAX(viewed_at) AS last_viewed_at
                          FROM teacher_profile_views
                          WHERE teacher_user_id = :teacher_user_id');
        $this->db->bind(':teacher_user_id', $teacherUserId);
        $row = $this->db->single();

        return [
            'total_views' => (int)($row->total_views ?? 0),
            'unique_schools' => (int)($row->unique_schools ?? 0),
            'last_viewed_at' => $row->last_viewed_at ?? null
        ];
    }

    public function history($teacherUserId, $page = 1, $perPage = 10)
    {
        $page = max(1, (int)$page);
        $perPage = min(50, max(1, (int)$perPage));
        $offset = ($page - 1) * $perPage;

        $this->db->query('SELECT COUNT(*) AS total
                          FROM teacher_profile_views
                          WHERE teacher_user_id = :teacher_user_id');
        $this->db->bind(':teacher_user_id', $teacherUserId);
        $total = (int)($this->db->single()->total ?? 0);

        $this->db->query('SELECT views.view_id, views.viewed_at, views.ip_address,
                                 schools.user_id AS school_user_id,
                                 schools.school_name, schools.school_type,
                                 schools.state, schools.lga, schools.logo_url
                          FROM teacher_profile_views views
                          JOIN school_profiles schools ON schools.user_id = views.school_user_id
                          WHERE views.teacher_user_id = :teacher_user_id
                          ORDER BY views.viewed_at DESC
                          LIMIT :limit OFFSET :offset');
        $this->db->bind(':teacher_user_id', $teacherUserId);
        $this->db->bind(':limit', $perPage, PDO::PARAM_INT);
        $this->db->bind(':offset', $offset, PDO::PARAM_INT);
        $items = $this->db->resultSet();
        $lastPage = $total > 0 ? (int)ceil($total / $perPage) : 1;

        return [
            'items' => $items,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => $lastPage,
                'has_more' => $page < $lastPage
            ]
        ];
    }
}
?>