<?php
/*
 * App Core Class
 * Creates URL & loads core controller
 * URL FORMAT - /controller/method/params
 */

class Core
{
    protected $currentController = 'Health';
    protected $currentMethod = 'index';
    protected $params = [];

    public function __construct()
    {
        try {
            $url = $this->getUrl();

            // Strip prefix 'api' or 'staffroom_api' if present
            while (!empty($url) && (strcasecmp($url[0], 'api') === 0 || strcasecmp($url[0], 'staffroom_api') === 0)) {
                unset($url[0]);
                $url = array_values($url);
            }

            // Enforce general rate limit globally
            if (class_exists('RateLimitMiddleware')) {
                RateLimitMiddleware::check('general');
            }

            // First check if exact route matches in routes/api.php
            $routes = [];
            if (file_exists(dirname(__DIR__, 2) . '/routes/api.php')) {
                $routes = require dirname(__DIR__, 2) . '/routes/api.php';
            }

            $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
            $matchedRoute = null;
            $matchedMiddleware = [];

            // Only account creation and email verification are public endpoints.
            $normalizedPath = '/' . implode('/', $url);
            $publicAuthPaths = [
                '/auth/register',
                '/auth/register_sch',
                '/auth/login',
                '/auth/forgot-password',
                '/auth/verify-reset-otp',
                '/auth/reset-password',
                '/auth/verify-email',
                '/auth/resend-verification',
                '/contact',
                '/internal-admin/invitations/accept'
            ];
            $isPublicHealthCheck = $method === 'GET' && $normalizedPath === '/health';
            if (!$isPublicHealthCheck && !($method === 'POST' && in_array($normalizedPath, $publicAuthPaths, true))) {
                AuthMiddleware::authenticate();
            }

            foreach ($routes as $pattern => $actionConfig) {
                $actionStr = is_array($actionConfig) ? ($actionConfig['action'] ?? '') : $actionConfig;
                $middlewareList = is_array($actionConfig) ? ($actionConfig['middleware'] ?? []) : [];

                list($routeMethod, $routePath) = explode(' ', $pattern, 2);
                if (strcasecmp($routeMethod, $method) !== 0) continue;

                $routeSegments = explode('/', ltrim($routePath, '/'));
                while (!empty($routeSegments) && (strcasecmp($routeSegments[0], 'api') === 0 || strcasecmp($routeSegments[0], 'staffroom_api') === 0)) {
                    array_shift($routeSegments);
                }

                if (count($routeSegments) === count($url)) {
                    $match = true;
                    $routeParams = [];
                    for ($i = 0; $i < count($url); $i++) {
                        $seg = $routeSegments[$i];
                        if (strpos($seg, ':') === 0 || (strpos($seg, '{') === 0 && substr($seg, -1) === '}')) {
                            $routeParams[] = $url[$i];
                        } elseif (strcasecmp($seg, $url[$i]) !== 0) {
                            $match = false;
                            break;
                        }
                    }
                    if ($match) {
                        $matchedRoute = $actionStr;
                        $matchedMiddleware = $middlewareList;
                        $this->params = $routeParams;
                        break;
                    }
                }
            }

            // Execute middleware configured in routes/api.php if matched
            if (!empty($matchedMiddleware)) {
                foreach ((array)$matchedMiddleware as $mw) {
                    if ($mw === 'auth' || strcasecmp($mw, 'AuthMiddleware') === 0) {
                        AuthMiddleware::authenticate();
                    } elseif (strpos($mw, 'role:') === 0) {
                        $roles = explode(',', trim(substr($mw, 5)));
                        RoleMiddleware::allow($roles);
                    } elseif ($mw === 'admin') {
                        RoleMiddleware::allow('admin');
                    } elseif ($mw === 'school') {
                        RoleMiddleware::allow('school');
                    } elseif ($mw === 'teacher') {
                        RoleMiddleware::allow('teacher');
                    } elseif (strpos($mw, 'rate_limit:') === 0) {
                        $type = trim(substr($mw, 11));
                        RateLimitMiddleware::check($type);
                    } elseif ($mw === 'rate_limit') {
                        RateLimitMiddleware::check('general');
                    }
                }
            }

            if ($matchedRoute) {
                list($controllerName, $methodName) = explode('@', $matchedRoute);
                $this->currentController = $controllerName;
                $this->currentMethod = $methodName;
            } else {
                // Fallback / standard MVC lookup
                if (!empty($url)) {
                    $segment = $url[0];
                    $controllerCandidates = [
                        ucwords($segment) . 'Controller',
                        ucwords(rtrim($segment, 's')) . 'Controller',
                        ucwords($segment)
                    ];

                    $found = false;
                    foreach ($controllerCandidates as $candidate) {
                        if (file_exists(__DIR__ . '/../controllers/' . $candidate . '.php')) {
                            $this->currentController = $candidate;
                            unset($url[0]);
                            $url = array_values($url);
                            $found = true;
                            break;
                        }
                    }

                    if (!$found) {
                        Response::error('Endpoint not found: ' . $segment, 404);
                    }
                }
            }

            // Block legacy Users controller
            if (strcasecmp($this->currentController, 'Users') === 0 || strcasecmp($this->currentController, 'UsersController') === 0) {
                Response::error('Endpoint not found', 404);
            }

            require_once __DIR__ . '/../controllers/' . $this->currentController . '.php';
            $this->currentController = new $this->currentController();

            // Determine Method if not already set by routes file
            if (!$matchedRoute && isset($url[0])) {
                $methodName = str_replace('-', '_', $url[0]);

                if (method_exists($this->currentController, $methodName)) {
                    $this->currentMethod = $methodName;
                    unset($url[0]);
                    $url = array_values($url);
                }
            }

            // Get parameters if not already determined by exact route match
            if (!$matchedRoute) {
                $this->params = $url ? array_values($url) : [];
            }

            call_user_func_array([$this->currentController, $this->currentMethod], $this->params);
        } catch (Error $e) {
            ResponseHelper::error('Caught error: ' . $e->getMessage(), 500);
        } catch (Exception $e) {
            ResponseHelper::error('Caught exception: ' . $e->getMessage(), 500);
        }
    }

    public function getUrl()
    {
        if (isset($_GET['url'])) {
            $url = rtrim($_GET['url'], '/');
            $url = filter_var($url, FILTER_SANITIZE_URL);
            $url = explode('/', $url);
            return $url;
        }

        $requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        if (!empty($requestUri)) {
            $scriptDirectory = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
            if ($scriptDirectory !== '' && strpos($requestUri, $scriptDirectory) === 0) {
                $requestUri = substr($requestUri, strlen($scriptDirectory));
            }
            $requestUri = trim($requestUri, '/');
            if ($requestUri !== '') {
                return explode('/', filter_var($requestUri, FILTER_SANITIZE_URL));
            }
        }

        return [];
    }
}
?>