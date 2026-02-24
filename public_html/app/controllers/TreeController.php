<?php
class TreeController extends Controller {
    private function requireAdminUser() {
        Auth::requireAuth();
        $user = Auth::user();
        if (!$user || strtolower((string)($user->role ?? '')) !== 'admin') {
            ErrorHandler::abort(403, 'Access denied');
        }

        return $user;
    }

    public function index() {
        $adminUser = $this->requireAdminUser();

        $rows = Database::query(
            "SELECT
                ic.creator_id,
                ic.used_by,
                ic.used_at,
                inviter.username AS inviter_username,
                inviter.user_number AS inviter_user_number,
                invitee.username AS invitee_username,
                invitee.user_number AS invitee_user_number
             FROM invite_codes ic
             JOIN users inviter ON inviter.id = ic.creator_id
             JOIN users invitee ON invitee.id = ic.used_by
             WHERE ic.used_by IS NOT NULL
             ORDER BY ic.used_at ASC, ic.id ASC"
        )->fetchAll();

        $usersById = [];
        $childrenByInviter = [];
        $invitedBy = [];

        foreach ($rows as $row) {
            $inviterId = (int)$row->creator_id;
            $inviteeId = (int)$row->used_by;

            if (!isset($usersById[$inviterId])) {
                $usersById[$inviterId] = [
                    'id' => $inviterId,
                    'username' => (string)$row->inviter_username,
                    'user_number' => (string)$row->inviter_user_number,
                ];
            }

            if (!isset($usersById[$inviteeId])) {
                $usersById[$inviteeId] = [
                    'id' => $inviteeId,
                    'username' => (string)$row->invitee_username,
                    'user_number' => (string)$row->invitee_user_number,
                ];
            }

            if (isset($invitedBy[$inviteeId])) {
                continue;
            }

            $invitedBy[$inviteeId] = $inviterId;
            if (!isset($childrenByInviter[$inviterId])) {
                $childrenByInviter[$inviterId] = [];
            }

            $childrenByInviter[$inviterId][] = [
                'user_id' => $inviteeId,
                'used_at' => (string)$row->used_at,
            ];
        }

        foreach ($childrenByInviter as &$children) {
            usort($children, static function($left, $right) use ($usersById) {
                $leftName = strtolower((string)($usersById[(int)$left['user_id']]['username'] ?? ''));
                $rightName = strtolower((string)($usersById[(int)$right['user_id']]['username'] ?? ''));

                return $leftName <=> $rightName;
            });
        }
        unset($children);

        $rootIds = [];
        foreach ($childrenByInviter as $inviterId => $_children) {
            if (!isset($invitedBy[(int)$inviterId])) {
                $rootIds[] = (int)$inviterId;
            }
        }

        sort($rootIds);

        foreach ($childrenByInviter as $inviterId => $_children) {
            if (!in_array((int)$inviterId, $rootIds, true)) {
                $rootIds[] = (int)$inviterId;
            }
        }

        $forest = [];
        foreach ($rootIds as $rootId) {
            $forest[] = $this->buildTreeNode($rootId, $usersById, $childrenByInviter, []);
        }

        usort($forest, static function($left, $right) {
            return strtolower((string)$left['user']['username']) <=> strtolower((string)$right['user']['username']);
        });

        $this->view('tree', [
            'user' => $adminUser,
            'tree' => $forest,
            'relationCount' => count($invitedBy),
            'csrf' => $this->csrfToken(),
        ]);
    }

    private function buildTreeNode($userId, $usersById, $childrenByInviter, $visited) {
        $userId = (int)$userId;
        if (!isset($usersById[$userId])) {
            return [
                'user' => [
                    'id' => $userId,
                    'username' => 'Unknown',
                    'user_number' => '0000000000000000',
                ],
                'invitees' => [],
            ];
        }

        if (isset($visited[$userId])) {
            return [
                'user' => $usersById[$userId],
                'invitees' => [],
            ];
        }

        $visited[$userId] = true;
        $invitees = [];

        foreach ($childrenByInviter[$userId] ?? [] as $child) {
            $childId = (int)$child['user_id'];
            $childNode = $this->buildTreeNode($childId, $usersById, $childrenByInviter, $visited);
            $childNode['used_at'] = (string)$child['used_at'];
            $invitees[] = $childNode;
        }

        return [
            'user' => $usersById[$userId],
            'invitees' => $invitees,
        ];
    }
}
