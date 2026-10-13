<?php
/**
 * School-owned recruitment email templates.
 */
class EmailTemplates extends Controller
{
    private $templateModel;

    public function __construct()
    {
        $this->templateModel = $this->model('EmailTemplate');
    }

    public function index($templateId = null)
    {
        $user = RoleMiddleware::allow('school');
        $method = $_SERVER['REQUEST_METHOD'];

        if ($method === 'GET' && $templateId === null) {
            $templates = $this->templateModel->getBySchool($user->user_id);
            ResponseHelper::success('Email templates fetched successfully', $templates, 200, ['templates' => $templates]);
        }
        if ($method === 'GET') {
            $template = $this->templateModel->getById($templateId, $user->user_id);
            if (!$template) ResponseHelper::error('Email template not found', 404);
            ResponseHelper::success('Email template fetched successfully', $template, 200, ['template' => $template]);
        }

        if ($method === 'POST') {
            if ($templateId !== null) ResponseHelper::error('Invalid endpoint for creating an email template', 400);
            $data = $this->validatedData($this->getData());
            $template = $this->templateModel->create($user->user_id, $data);
            if (!$template) ResponseHelper::error('Failed to create email template', 500);
            ResponseHelper::success('Email template created successfully', $template, 201, ['template' => $template]);
        }

        if ($method === 'PUT' || $method === 'PATCH') {
            if ($templateId === null) ResponseHelper::error('Email template ID is required', 400);
            $data = $this->validatedData($this->getData(), true);
            $template = $this->templateModel->update($templateId, $user->user_id, $data);
            if (!$template) ResponseHelper::error('Email template not found or could not be updated', 404);
            ResponseHelper::success('Email template updated successfully', $template, 200, ['template' => $template]);
        }

        if ($method === 'DELETE') {
            if ($templateId === null) ResponseHelper::error('Email template ID is required', 400);
            if (!$this->templateModel->delete($templateId, $user->user_id)) ResponseHelper::error('Email template not found', 404);
            ResponseHelper::success('Email template deleted successfully', ['template_id' => $templateId], 200);
        }

        ResponseHelper::error('Method Not Allowed', 405);
    }

    private function validatedData($data, $partial = false)
    {
        foreach (['name', 'subject', 'body'] as $field) {
            if (!$partial && trim($data[$field] ?? '') === '') {
                ResponseHelper::error($field . ' is required', 400, [$field => 'Required']);
            }
            if (isset($data[$field]) && trim($data[$field]) === '') {
                ResponseHelper::error($field . ' cannot be empty', 400, [$field => 'Cannot be empty']);
            }
        }
        if (isset($data['cta_url']) && trim($data['cta_url']) !== '' && !filter_var($data['cta_url'], FILTER_VALIDATE_URL)) {
            ResponseHelper::error('cta_url must be a valid URL', 400, ['cta_url' => 'Invalid URL']);
        }
        return $data;
    }
}
?>
