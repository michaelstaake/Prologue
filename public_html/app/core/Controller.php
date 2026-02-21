<?php
class Controller {
    protected function view($view, $data = []) {
        if (!isset($data['csrf'])) {
            $data['csrf'] = $this->csrfToken();
        }

        extract($data);
        $viewFile = __DIR__ . "/../views/{$view}.php";
        if (!file_exists($viewFile)) {
            ErrorHandler::abort(500, 'View not found', [
                'view' => $view,
                'view_file' => $viewFile
            ]);
        }

        ob_start();
        require $viewFile;
        $content = ob_get_clean();

        require __DIR__ . '/../views/layouts/main.php';
    }

    protected function flash(string $key, string $value): void {
        $_SESSION['_flash'][$key] = $value;
    }

    protected function redirect($url) {
        header("Location: " . base_url($url));
        exit;
    }

    protected function json($data, $status = 200) {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    protected function csrfToken() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}