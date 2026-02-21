<?php
class Router {
    private $routes = [];

    public function get($uri, $action) {
        $this->addRoute('GET', $uri, $action);
    }

    public function post($uri, $action) {
        $this->addRoute('POST', $uri, $action);
    }

    public function group($prefix, $callback) {
        $oldRoutes = $this->routes;
        $callback($this);
        foreach ($this->routes as $key => $route) {
            if (!isset($oldRoutes[$key])) {
                $this->routes[$key]['uri'] = $prefix . $this->routes[$key]['uri'];
            }
        }
    }

    private function addRoute($method, $uri, $action) {
        $this->routes[] = ['method' => $method, 'uri' => $uri, 'action' => $action];
    }

    public function dispatch() {
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $method = $_SERVER['REQUEST_METHOD'];

        foreach ($this->routes as $route) {
            $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^/]+)', $route['uri']);
            $pattern = '#^' . $pattern . '$#';

            if ($route['method'] === $method && preg_match($pattern, $uri, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                $this->callAction($route['action'], $params);
                return;
            }
        }

        ErrorHandler::abort(404, 'No route matched this request', [
            'uri' => $uri,
            'method' => $method
        ]);
    }

    private function callAction($action, $params = []) {
        list($controllerName, $method) = explode('@', $action);
        require_once __DIR__ . "/../controllers/{$controllerName}.php";
        $controller = new $controllerName();

        if (!method_exists($controller, $method)) {
            ErrorHandler::abort(500, 'Route method not found', [
                'controller' => $controllerName,
                'method' => $method
            ]);
        }

        $reflection = new ReflectionMethod($controller, $method);
        if ($reflection->getNumberOfParameters() > 0) {
            $controller->$method($params);
            return;
        }

        $controller->$method();
    }
}