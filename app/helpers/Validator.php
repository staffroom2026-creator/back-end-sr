<?php
/**
 * Validator Helper Class
 * Provides comprehensive, strong validation and clean JSON error responses across the StaffRoom API.
 */

class Validator
{
    private $errors = [];

    /**
     * Validate data array against rule definitions.
     * Rules can be pipe-separated string or array.
     * Examples:
     * ['email' => 'required|email', 'password' => 'required|min:6', 'role' => 'required|role', 'status' => 'application_status', 'id' => 'numeric_id']
     *
     * @param array $data
     * @param array $rules
     * @return bool
     */
    public function validate($data, $rules)
    {
        $this->errors = [];

        foreach ($rules as $field => $ruleItem) {
            $ruleList = is_array($ruleItem) ? $ruleItem : explode('|', $ruleItem);
            $value = isset($data[$field]) ? $data[$field] : null;

            foreach ($ruleList as $rule) {
                $rule = trim($rule);
                if (empty($rule)) continue;

                if ($rule === 'required') {
                    if (!self::validateRequired($value)) {
                        $this->errors[$field][] = "The {$field} field is required.";
                    }
                } elseif ($rule === 'email') {
                    if ($value !== null && $value !== '' && !self::validateEmail($value)) {
                        $this->errors[$field][] = "The {$field} must be a valid email address.";
                    }
                } elseif ($rule === 'numeric' || $rule === 'numeric_id') {
                    if ($value !== null && $value !== '' && !self::validateNumericId($value)) {
                        $this->errors[$field][] = "The {$field} must be a valid numeric ID greater than 0.";
                    }
                } elseif ($rule === 'role') {
                    if ($value !== null && $value !== '' && !self::validateRole($value)) {
                        $this->errors[$field][] = "The {$field} must be one of: teacher, school, admin.";
                    }
                } elseif ($rule === 'application_status' || $rule === 'status') {
                    if ($value !== null && $value !== '' && !self::validateApplicationStatus($value)) {
                        $this->errors[$field][] = "The {$field} is not a valid application status.";
                    }
                } elseif (strpos($rule, 'min:') === 0) {
                    $min = (int)substr($rule, 4);
                    if ($value !== null && $value !== '' && !self::validatePasswordLength($value, $min)) {
                        $this->errors[$field][] = "The {$field} must be at least {$min} characters.";
                    }
                } elseif (strpos($rule, 'max:') === 0) {
                    $max = (int)substr($rule, 4);
                    if ($value !== null && $value !== '' && (is_string($value) && strlen($value) > $max)) {
                        $this->errors[$field][] = "The {$field} may not be greater than {$max} characters.";
                    }
                } elseif (strpos($rule, 'in:') === 0) {
                    $allowed = explode(',', substr($rule, 3));
                    if ($value !== null && $value !== '' && !in_array($value, $allowed)) {
                        $this->errors[$field][] = "The selected {$field} is invalid. Allowed: " . implode(', ', $allowed) . ".";
                    }
                }
            }
        }

        return empty($this->errors);
    }

    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * 1. Validate required fields
     */
    public static function validateRequired($value)
    {
        if (is_array($value)) {
            return !empty($value);
        }
        return ($value !== null && trim((string)$value) !== '');
    }

    /**
     * Helper to verify all required keys exist and are non-empty in an array
     */
    public static function checkRequired($data, $requiredKeys)
    {
        $errors = [];
        foreach ((array)$requiredKeys as $key) {
            $val = isset($data[$key]) ? $data[$key] : null;
            if (!self::validateRequired($val)) {
                $errors[$key][] = "The {$key} field is required.";
            }
        }
        if (!empty($errors)) {
            Response::error('Validation failed', 400, $errors);
        }
    }

    /**
     * 2. Validate valid email
     */
    public static function validateEmail($email)
    {
        if (empty($email) || !is_string($email)) {
            return false;
        }
        return (filter_var(trim($email), FILTER_VALIDATE_EMAIL) !== false);
    }

    public static function checkEmail($email, $fieldName = 'email')
    {
        if (!self::validateEmail($email)) {
            Response::error('Validation failed', 400, [$fieldName => ["The {$fieldName} must be a valid email address."]]);
        }
    }

    /**
     * 3. Validate minimum password length
     */
    public static function validatePasswordLength($password, $minLength = 6)
    {
        if ($password === null || !is_scalar($password)) {
            return false;
        }
        return (strlen((string)$password) >= $minLength);
    }

    public static function checkPassword($password, $minLength = 6, $fieldName = 'password')
    {
        if (!self::validatePasswordLength($password, $minLength)) {
            Response::error('Validation failed', 400, [$fieldName => ["The {$fieldName} must be at least {$minLength} characters."]]);
        }
    }

    /**
     * 4. Validate allowed role values
     */
    public static function validateRole($role, $allowedRoles = ['teacher', 'school', 'admin'])
    {
        if (empty($role) || !is_string($role)) {
            return false;
        }
        return in_array(strtolower(trim($role)), (array)$allowedRoles, true);
    }

    public static function checkRole($role, $allowedRoles = ['teacher', 'school', 'admin'], $fieldName = 'role')
    {
        if (!self::validateRole($role, $allowedRoles)) {
            Response::error('Validation failed', 400, [$fieldName => ["The {$fieldName} must be one of: " . implode(', ', (array)$allowedRoles) . "."]]);
        }
    }

    /**
     * 5. Validate allowed application status values
     */
    public static function validateApplicationStatus($status, $allowedStatuses = ['pending', 'reviewed', 'shortlisted', 'interviewing', 'hired', 'rejected'])
    {
        if (empty($status) || !is_string($status)) {
            return false;
        }
        return in_array(strtolower(trim($status)), (array)$allowedStatuses, true);
    }

    public static function checkApplicationStatus($status, $allowedStatuses = ['pending', 'reviewed', 'shortlisted', 'interviewing', 'hired', 'rejected'], $fieldName = 'status')
    {
        if (!self::validateApplicationStatus($status, $allowedStatuses)) {
            Response::error('Validation failed', 400, [$fieldName => ["The {$fieldName} is invalid. Allowed: " . implode(', ', (array)$allowedStatuses) . "."]]);
        }
    }

    /**
     * 6. Validate file type
     */
    public static function validateFileType($file, $allowedExtensions = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'webp'])
    {
        if (!isset($file['name']) || empty($file['name'])) {
            return false;
        }
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        return in_array($ext, (array)$allowedExtensions, true);
    }

    public static function checkFileType($file, $allowedExtensions, $fieldName = 'file')
    {
        if (!self::validateFileType($file, $allowedExtensions)) {
            Response::error('Validation failed', 400, [$fieldName => ["The {$fieldName} must be one of the allowed file types: " . implode(', ', (array)$allowedExtensions) . "."]]);
        }
    }

    /**
     * 7. Validate file size (in bytes, default e.g. 5MB = 5242880 bytes)
     */
    public static function validateFileSize($file, $maxBytes = 5242880)
    {
        if (!isset($file['size']) || !is_numeric($file['size'])) {
            return false;
        }
        return ((int)$file['size'] > 0 && (int)$file['size'] <= (int)$maxBytes);
    }

    public static function checkFileSize($file, $maxBytes = 5242880, $fieldName = 'file')
    {
        if (!self::validateFileSize($file, $maxBytes)) {
            $maxMb = round($maxBytes / 1048576, 2);
            Response::error('Validation failed', 400, [$fieldName => ["The {$fieldName} size must not exceed {$maxMb}MB."]]);
        }
    }

    /**
     * 8. Validate numeric IDs
     */
    public static function validateNumericId($id)
    {
        if ($id === null || $id === '') {
            return false;
        }
        return (is_numeric($id) && (int)$id > 0);
    }

    public static function checkNumericId($id, $fieldName = 'id')
    {
        if (!self::validateNumericId($id)) {
            Response::error('Validation failed', 400, [$fieldName => ["The {$fieldName} must be a valid positive integer ID."]]);
        }
    }

    /**
     * Static check function that validates $data against $rules or returns 400 JSON error immediately
     */
    public static function check($data, $rules)
    {
        $validator = new self();
        if (!$validator->validate($data, $rules)) {
            Response::error('Validation failed', 400, $validator->getErrors());
        }
    }
}
?>
