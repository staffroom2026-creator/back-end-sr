<?php

class Health extends Controller
{
    /**
     * GET /health
     */
    public function index()
    {
        ResponseHelper::success('API is running', [
            'status' => 'ok',
            'service' => 'StaffRoom API',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
}
?>
