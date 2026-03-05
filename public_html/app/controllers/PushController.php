<?php
class PushController extends Controller {
    public function status() {
        Auth::requireAuth();

        $userId = (int)Auth::user()->id;
        $publicKey = WebPushService::getVapidPublicKey();
        $configured = WebPushService::isConfigured();

        $this->json([
            'success' => true,
            'configured' => $configured,
            'vapid_public_key' => $configured ? $publicKey : '',
            'enabled' => (string)(Setting::get('web_push_notifications_' . $userId) ?? '0') === '1',
            'subscription_count' => PushSubscription::countForUser($userId),
            'csrf_token' => $this->csrfToken(),
        ]);
    }

    public function subscribe() {
        Auth::requireAuth();
        Auth::csrfValidate();

        if (!WebPushService::isConfigured()) {
            $this->json(['error' => 'Web push is not configured on this server'], 503);
        }

        $userId = (int)Auth::user()->id;
        $endpoint = trim((string)($_POST['endpoint'] ?? ''));
        $p256dh = trim((string)($_POST['p256dh'] ?? ''));
        $auth = trim((string)($_POST['auth'] ?? ''));
        $userAgent = trim((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));

        if ($endpoint === '' || $p256dh === '' || $auth === '') {
            $this->json(['error' => 'Invalid subscription payload'], 400);
        }

        $saved = PushSubscription::upsertForUser($userId, $endpoint, $p256dh, $auth, $userAgent);
        if (!$saved) {
            $this->json(['error' => 'Unable to save subscription'], 500);
        }

        Setting::set('web_push_notifications_' . $userId, '1');

        $this->json([
            'success' => true,
            'subscription_count' => PushSubscription::countForUser($userId),
            'csrf_token' => $this->csrfToken(),
        ]);
    }

    public function unsubscribe() {
        Auth::requireAuth();
        Auth::csrfValidate();

        $userId = (int)Auth::user()->id;
        $endpoint = trim((string)($_POST['endpoint'] ?? ''));
        if ($endpoint !== '') {
            PushSubscription::removeForUserByEndpoint($userId, $endpoint);
        }

        $remaining = PushSubscription::countForUser($userId);
        if ($remaining <= 0) {
            Setting::set('web_push_notifications_' . $userId, '0');
        }

        $this->json([
            'success' => true,
            'subscription_count' => $remaining,
            'csrf_token' => $this->csrfToken(),
        ]);
    }
}