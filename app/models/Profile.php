<?php
/**
 * Profile Model (Bridge/Composite around TeacherProfile and SchoolProfile)
 */

require_once __DIR__ . '/TeacherProfile.php';
require_once __DIR__ . '/SchoolProfile.php';

class Profile
{
    private $teacherModel;
    private $schoolModel;

    public function __construct()
    {
        $this->teacherModel = new TeacherProfile();
        $this->schoolModel = new SchoolProfile();
    }

    public function getTeacherProfileByUserId($userId)
    {
        return $this->teacherModel->getByUserId($userId);
    }

    public function getSchoolProfileByUserId($userId)
    {
        return $this->schoolModel->getByUserId($userId);
    }

    public function createOrUpdateTeacherProfile($userId, $data)
    {
        return $this->teacherModel->createOrUpdate($userId, $data);
    }

    public function updateTeacherProfile($userId, $data)
    {
        return $this->teacherModel->createOrUpdate($userId, $data);
    }

    public function updateCompleteTeacherProfile($userId, $data)
    {
        return $this->teacherModel->updateComplete($userId, $data);
    }

    public function createOrUpdateSchoolProfile($userId, $data)
    {
        return $this->schoolModel->createOrUpdate($userId, $data);
    }

    public function updateSchoolProfile($userId, $data)
    {
        return $this->schoolModel->createOrUpdate($userId, $data);
    }

    public function updateCV($userId, $cvUrl)
    {
        return $this->teacherModel->updateCV($userId, $cvUrl);
    }

    public function updateCvUrl($userId, $cvUrl)
    {
        return $this->updateCV($userId, $cvUrl);
    }

    public function updateLogo($userId, $logoUrl)
    {
        return $this->schoolModel->updateLogo($userId, $logoUrl);
    }

    public function updateLogoUrl($userId, $logoUrl)
    {
        return $this->updateLogo($userId, $logoUrl);
    }

    public function getTeacherIdByUserId($userId)
    {
        return $this->teacherModel->getTeacherIdByUserId($userId);
    }

    public function getSchoolIdByUserId($userId)
    {
        return $this->schoolModel->getSchoolIdByUserId($userId);
    }

    public function getPendingVerifications()
    {
        return $this->schoolModel->getPendingVerifications();
    }

    public function verifySchool($id, $status = 'verified')
    {
        return $this->schoolModel->verifySchool($id, $status);
    }
}
?>
