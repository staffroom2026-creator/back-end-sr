<?php
/**
 * Upload Helper Class
 * Handles safe file uploads for CVs and School Logos.
 */

class UploadHelper
{
    /**
     * Upload a teacher CV
     *
     * @param array $file
     * @param int $userId
     * @return string|false
     */
    public static function uploadCV($file, $userId)
    {
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            return false;
        }

        $allowedExtensions = ['pdf', 'doc', 'docx'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowedExtensions)) {
            if (class_exists('ResponseHelper')) {
                ResponseHelper::error('Invalid file type for CV. Allowed extensions: pdf, doc, docx', 400);
            } elseif (class_exists('Response')) {
                Response::error('Invalid file type for CV. Allowed extensions: pdf, doc, docx', 400);
            } else {
                return false;
            }
        }

        // Enforce file size limit (5MB)
        $maxSize = 5 * 1024 * 1024;
        if ($file['size'] > $maxSize) {
            Response::error('CV file size must not exceed 5MB', 400);
        }

        // Validate MIME type to prevent disguised file uploads
        $allowedMimes = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        ];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        if (!in_array($mimeType, $allowedMimes)) {
            Response::error('Invalid CV file type detected. File content does not match allowed types.', 400);
        }

        $rootUploadDir = dirname(__DIR__, 2) . '/uploads/cvs/';
        $publicUploadDir = dirname(__DIR__, 2) . '/public/uploads/cvs/';
        if (!is_dir($rootUploadDir)) {
            mkdir($rootUploadDir, 0777, true);
        }
        if (!is_dir($publicUploadDir)) {
            mkdir($publicUploadDir, 0777, true);
        }

        $filename = 'cv_' . bin2hex(random_bytes(12)) . '.' . $ext;
        $rootTarget = $rootUploadDir . $filename;
        $publicTarget = $publicUploadDir . $filename;

        if (move_uploaded_file($file['tmp_name'], $rootTarget) || copy($file['tmp_name'], $rootTarget)) {
            @copy($rootTarget, $publicTarget);
            return '/uploads/cvs/' . $filename;
        } elseif (move_uploaded_file($file['tmp_name'], $publicTarget) || copy($file['tmp_name'], $publicTarget)) {
            @copy($publicTarget, $rootTarget);
            return '/uploads/cvs/' . $filename;
        }

        return false;
    }

    /**
     * Upload a school logo
     *
     * @param array $file
     * @param int $userId
     * @return string|false
     */
    public static function uploadLogo($file, $userId)
    {
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            return false;
        }

        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowedExtensions)) {
            if (class_exists('ResponseHelper')) {
                ResponseHelper::error('Invalid file type for Logo. Allowed extensions: jpg, jpeg, png, webp', 400);
            } elseif (class_exists('Response')) {
                Response::error('Invalid file type for Logo. Allowed extensions: jpg, jpeg, png, webp', 400);
            } else {
                return false;
            }
        }

        // Enforce file size limit (5MB)
        $maxSize = 5 * 1024 * 1024;
        if ($file['size'] > $maxSize) {
            Response::error('Logo file size must not exceed 5MB', 400);
        }

        // Validate MIME type to prevent disguised file uploads
        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        if (!in_array($mimeType, $allowedMimes)) {
            Response::error('Invalid logo file type detected. File content does not match allowed image types.', 400);
        }

        $rootUploadDir = dirname(__DIR__, 2) . '/uploads/logos/';
        $publicUploadDir = dirname(__DIR__, 2) . '/public/uploads/logos/';
        if (!is_dir($rootUploadDir)) {
            mkdir($rootUploadDir, 0777, true);
        }
        if (!is_dir($publicUploadDir)) {
            mkdir($publicUploadDir, 0777, true);
        }

        $filename = 'logo_' . $userId . '_' . time() . '.' . $ext;
        $rootTarget = $rootUploadDir . $filename;
        $publicTarget = $publicUploadDir . $filename;

        if (move_uploaded_file($file['tmp_name'], $rootTarget) || copy($file['tmp_name'], $rootTarget)) {
            @copy($rootTarget, $publicTarget);
            return '/uploads/logos/' . $filename;
        } elseif (move_uploaded_file($file['tmp_name'], $publicTarget) || copy($file['tmp_name'], $publicTarget)) {
            @copy($publicTarget, $rootTarget);
            return '/uploads/logos/' . $filename;
        }

        return false;
    }
}
?>
