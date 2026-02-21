<div class="p-8 overflow-auto">
    <h1 class="text-3xl font-bold mb-6">Reports</h1>

    <?php
        $filter = $selectedFilter ?? 'new';
        $reportList = $reports ?? [];
        $tabClass = static function($isActive) {
            return $isActive
                ? 'bg-emerald-600 text-white border-emerald-500'
                : 'bg-zinc-900 text-zinc-300 border-zinc-700 hover:bg-zinc-800';
        };

        $formatTarget = static function($report) {
            $targetType = strtolower((string)($report->target_type ?? ''));

            if ($targetType === 'user') {
                $targetUserNumber = trim((string)($report->target_user_number ?? ''));
                $targetUsername = trim((string)($report->target_user_username ?? ''));
                if ($targetUserNumber !== '') {
                    $label = $targetUsername !== ''
                        ? ($targetUsername . ' (' . User::formatUserNumber($targetUserNumber) . ')')
                        : User::formatUserNumber($targetUserNumber);

                    return [
                        'label' => $label,
                        'href' => base_url('/u/' . User::formatUserNumber($targetUserNumber))
                    ];
                }

                return [
                    'label' => 'User #' . (int)($report->target_id ?? 0),
                    'href' => null
                ];
            }

            if ($targetType === 'chat') {
                $chatNumber = trim((string)($report->target_chat_number ?? ''));
                if ($chatNumber !== '') {
                    $formattedChatNumber = User::formatUserNumber($chatNumber);
                    return [
                        'label' => 'Chat ' . $formattedChatNumber,
                        'href' => base_url('/c/' . $formattedChatNumber)
                    ];
                }

                return [
                    'label' => 'Chat #' . (int)($report->target_id ?? 0),
                    'href' => null
                ];
            }

            if ($targetType === 'message') {
                $messageChatNumber = trim((string)($report->target_message_chat_number ?? ''));
                if ($messageChatNumber !== '') {
                    $formattedChatNumber = User::formatUserNumber($messageChatNumber);
                    return [
                        'label' => 'Message #' . (int)($report->target_id ?? 0) . ' in Chat ' . $formattedChatNumber,
                        'href' => base_url('/c/' . $formattedChatNumber)
                    ];
                }

                return [
                    'label' => 'Message #' . (int)($report->target_id ?? 0),
                    'href' => null
                ];
            }

            return [
                'label' => ucfirst($targetType) . ' #' . (int)($report->target_id ?? 0),
                'href' => null
            ];
        };
    ?>

    <?php if (!empty($toastMessage ?? '')): ?>
        <div
            id="page-toast"
            data-toast-message="<?= htmlspecialchars($toastMessage, ENT_QUOTES, 'UTF-8') ?>"
            data-toast-kind="<?= htmlspecialchars($toastKind ?? 'info', ENT_QUOTES, 'UTF-8') ?>"
            class="hidden"
            aria-hidden="true"
        ></div>
    <?php endif; ?>

    <div class="flex flex-wrap items-center gap-3 mb-4">
        <a href="<?= htmlspecialchars(base_url('/reports?filter=new'), ENT_QUOTES, 'UTF-8') ?>" class="px-5 py-2.5 rounded-xl border transition <?= htmlspecialchars($tabClass($filter === 'new'), ENT_QUOTES, 'UTF-8') ?>">New</a>
        <a href="<?= htmlspecialchars(base_url('/reports?filter=handled'), ENT_QUOTES, 'UTF-8') ?>" class="px-5 py-2.5 rounded-xl border transition <?= htmlspecialchars($tabClass($filter === 'handled'), ENT_QUOTES, 'UTF-8') ?>">Handled</a>
        <a href="<?= htmlspecialchars(base_url('/reports?filter=all'), ENT_QUOTES, 'UTF-8') ?>" class="px-5 py-2.5 rounded-xl border transition <?= htmlspecialchars($tabClass($filter === 'all'), ENT_QUOTES, 'UTF-8') ?>">All</a>
    </div>

    <section class="bg-zinc-900 border border-zinc-700 rounded-2xl p-5 max-w-5xl">
        <div class="space-y-3">
            <?php if (empty($reportList)): ?>
                <p class="text-zinc-400 text-sm">No reports in this list.</p>
            <?php else: ?>
                <?php foreach ($reportList as $report): ?>
                    <?php
                        $reporter = (object) [
                            'username' => $report->reporter_username,
                            'user_number' => $report->reporter_user_number,
                            'avatar_filename' => $report->reporter_avatar_filename
                        ];
                        $reporterAvatar = User::avatarUrl($reporter);
                        $target = $formatTarget($report);
                        $isPending = strtolower((string)($report->status ?? '')) === 'pending';
                    ?>
                    <div class="bg-zinc-800 rounded-xl p-4">
                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-3 mb-3">
                                    <?php if ($reporterAvatar): ?>
                                        <img src="<?= htmlspecialchars($reporterAvatar, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($report->reporter_username, ENT_QUOTES, 'UTF-8') ?> avatar" class="w-9 h-9 rounded-full object-cover border border-zinc-700">
                                    <?php else: ?>
                                        <div class="w-9 h-9 rounded-full border border-zinc-700 flex items-center justify-center font-semibold text-sm <?= htmlspecialchars(User::avatarColorClasses($report->reporter_user_number), ENT_QUOTES, 'UTF-8') ?>">
                                            <?= htmlspecialchars(User::avatarInitial($report->reporter_username), ENT_QUOTES, 'UTF-8') ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="min-w-0">
                                        <div class="text-sm text-zinc-400">Reporter</div>
                                        <a href="<?= htmlspecialchars(base_url('/u/' . User::formatUserNumber((string)$report->reporter_user_number)), ENT_QUOTES, 'UTF-8') ?>" class="font-medium prologue-accent hover:text-emerald-300 hover:underline underline-offset-2">
                                            <?= htmlspecialchars($report->reporter_username, ENT_QUOTES, 'UTF-8') ?>
                                        </a>
                                    </div>
                                </div>

                                <div class="text-sm text-zinc-300 mb-1">
                                    <span class="text-zinc-500">Target:</span>
                                    <?php if (!empty($target['href'])): ?>
                                        <a href="<?= htmlspecialchars($target['href'], ENT_QUOTES, 'UTF-8') ?>" class="prologue-accent hover:text-emerald-300 hover:underline underline-offset-2">
                                            <?= htmlspecialchars($target['label'], ENT_QUOTES, 'UTF-8') ?>
                                        </a>
                                    <?php else: ?>
                                        <span><?= htmlspecialchars($target['label'], ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php endif; ?>
                                </div>

                                <div class="text-sm text-zinc-300 mb-1">
                                    <span class="text-zinc-500">Reason:</span>
                                    <span><?= nl2br(htmlspecialchars($report->reason, ENT_QUOTES, 'UTF-8')) ?></span>
                                </div>

                                <div class="text-xs text-zinc-500 mt-2">
                                    Submitted <?= htmlspecialchars((string)$report->created_at, ENT_QUOTES, 'UTF-8') ?>
                                </div>
                            </div>

                            <div class="shrink-0 flex flex-col items-end gap-2">
                                <span class="text-xs px-2 py-1 rounded-full border <?= $isPending ? 'border-amber-600 text-amber-300 bg-amber-900/30' : 'border-emerald-600 text-emerald-300 bg-emerald-900/30' ?>">
                                    <?= $isPending ? 'New' : 'Handled' ?>
                                </span>

                                <?php if ($isPending): ?>
                                    <form action="<?= htmlspecialchars(base_url('/reports/mark-handled'), ENT_QUOTES, 'UTF-8') ?>" method="POST">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="report_id" value="<?= (int)$report->id ?>">
                                        <input type="hidden" name="filter" value="<?= htmlspecialchars($filter, ENT_QUOTES, 'UTF-8') ?>">
                                        <button type="submit" class="bg-emerald-600 hover:bg-emerald-500 text-white text-sm px-3 py-1.5 rounded-lg">Mark as Handled</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>
</div>
