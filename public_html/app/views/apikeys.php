<?php
    $keyList = $keys ?? [];
    $chatList = $groupChats ?? [];
    $toastMessage = '';
    $toastKind = 'info';

    $flashSuccess = flash_get('success');
    if ($flashSuccess === 'key_expired') {
        $toastMessage = 'API key expired successfully.';
        $toastKind = 'success';
    }

    $modalOverlayClass = 'fixed inset-0 z-50 hidden';
    $modalBackdropClass = 'absolute inset-0 bg-black/70';
    $modalCenterClass = 'relative z-10 flex min-h-full items-center justify-center p-4 lg:p-8';
    $modalBoxClass = 'w-full max-w-lg rounded-2xl border border-zinc-700 bg-zinc-900 p-6 max-h-[90vh] overflow-y-auto max-lg:!max-w-none max-lg:!rounded-none max-lg:!border-0 max-lg:min-h-[100dvh] max-lg:!max-h-none max-lg:flex max-lg:flex-col';
?>

<div class="p-8 overflow-auto space-y-6">

    <div class="flex items-center justify-between flex-wrap gap-4">
        <h1 class="text-3xl font-bold">API Keys</h1>
        <div class="flex gap-3">
            <a href="<?= htmlspecialchars(base_url('/apikeys/docs')) ?>"
               class="inline-flex items-center gap-2 rounded-xl border border-zinc-700 bg-zinc-800 px-4 py-2 text-sm font-medium text-zinc-200 hover:bg-zinc-700 transition">
                <i class="fa-solid fa-book"></i>
                Documentation
            </a>
            <button type="button" data-modal-open="ak-create-modal"
                    class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-500 transition">
                <i class="fa-solid fa-plus"></i>
                Create API Key
            </button>
        </div>
    </div>

    <p class="text-sm text-zinc-400">
        API keys allow external applications to interact with your account.
        <strong>Bot</strong> keys can send messages to group chats you own.
        <strong>User</strong> keys have full access to your chats.
    </p>

    <?php if (empty($keyList)): ?>
        <div class="rounded-2xl border border-zinc-700 bg-zinc-900 p-8 text-center">
            <i class="fa-solid fa-key text-4xl text-zinc-600 mb-3"></i>
            <p class="text-zinc-400">No API keys yet. Create one to get started.</p>
        </div>
    <?php else: ?>
        <div class="space-y-3" id="ak-key-list">
            <?php foreach ($keyList as $key):
                $isActive = $key->status === 'active';
                $isExpired = !$isActive;
                $expiresAtLabel = '';
                if ($key->expires_at === null && $isActive) {
                    $expiresAtLabel = 'Never';
                } elseif ($key->expires_at !== null) {
                    $expiresAtLabel = date('M j, Y g:i A', strtotime($key->expires_at));
                }
                $createdAtLabel = date('M j, Y g:i A', strtotime($key->created_at));
                $ipLabel = ($key->allowed_ips === null || $key->allowed_ips === '') ? 'Any' : $key->allowed_ips;
            ?>
                <div class="rounded-2xl border <?= $isExpired ? 'border-zinc-800 opacity-60' : 'border-zinc-700' ?> bg-zinc-900 p-5">
                    <div class="flex items-start justify-between gap-4 flex-wrap">
                        <div class="min-w-0 space-y-2 flex-1">
                            <div class="flex items-center gap-3 flex-wrap">
                                <h3 class="text-lg font-semibold truncate"><?= htmlspecialchars($key->name) ?></h3>
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium <?= $key->type === 'bot' ? 'bg-blue-500/20 text-blue-300' : 'bg-purple-500/20 text-purple-300' ?>">
                                    <?= $key->type === 'bot' ? 'Bot' : 'User' ?>
                                </span>
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium <?= $isActive ? 'bg-emerald-500/20 text-emerald-300' : 'bg-red-500/20 text-red-300' ?>">
                                    <?= $isActive ? 'Active' : 'Expired' ?>
                                </span>
                            </div>
                            <div class="flex items-center gap-4 text-sm text-zinc-400 flex-wrap">
                                <span class="font-mono text-zinc-500"><?= htmlspecialchars($key->key_prefix) ?>...</span>
                                <span><i class="fa-regular fa-calendar mr-1"></i>Created: <?= htmlspecialchars($createdAtLabel) ?></span>
                                <?php if ($expiresAtLabel !== ''): ?>
                                    <span><i class="fa-regular fa-clock mr-1"></i><?= $isExpired ? 'Expired' : 'Expires' ?>: <?= htmlspecialchars($expiresAtLabel) ?></span>
                                <?php endif; ?>
                                <span><i class="fa-solid fa-globe mr-1"></i>IPs: <?= htmlspecialchars(mb_strlen($ipLabel) > 40 ? mb_substr($ipLabel, 0, 40) . '...' : $ipLabel) ?></span>
                            </div>
                        </div>
                        <?php if ($isActive): ?>
                            <button type="button"
                                    onclick="expireKey(<?= (int)$key->id ?>, '<?= htmlspecialchars(addslashes($key->name)) ?>')"
                                    class="shrink-0 inline-flex items-center gap-2 rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-2 text-sm font-medium text-red-400 hover:bg-red-500/20 transition">
                                <i class="fa-solid fa-ban"></i>
                                Expire
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>

<!-- Create API Key Modal -->
<div id="ak-create-modal" class="<?= $modalOverlayClass ?>" role="dialog" aria-modal="true">
    <div class="<?= $modalBackdropClass ?>" data-modal-close="ak-create-modal"></div>
    <div class="<?= $modalCenterClass ?>">
        <div class="<?= $modalBoxClass ?>" data-modal-box>
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-xl font-semibold">Create API Key</h3>
                <button type="button" data-modal-close="ak-create-modal" class="rounded-lg px-2 py-1 text-zinc-400 hover:bg-zinc-800 hover:text-zinc-200">&times;</button>
            </div>

            <form id="ak-create-form" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

                <div>
                    <label class="block text-sm text-zinc-300 mb-1">Name</label>
                    <input type="text" name="name" maxlength="100" required data-modal-autofocus
                           placeholder="e.g. My Bot, Webhook Integration"
                           class="w-full rounded-xl border border-zinc-700 bg-zinc-800 px-4 py-3 text-zinc-100 placeholder-zinc-500 focus:outline-none focus:ring-2 focus:ring-emerald-500">
                </div>

                <div>
                    <label class="block text-sm text-zinc-300 mb-2">Type</label>
                    <div class="flex gap-3">
                        <label class="flex-1 cursor-pointer">
                            <input type="radio" name="type" value="bot" class="peer hidden" checked>
                            <div class="rounded-xl border border-zinc-700 bg-zinc-800 p-3 text-center peer-checked:border-emerald-500 peer-checked:bg-emerald-500/10 transition">
                                <i class="fa-solid fa-robot text-lg text-blue-400 mb-1"></i>
                                <div class="text-sm font-medium">Bot</div>
                                <div class="text-xs text-zinc-400 mt-0.5">Send messages to your group chats</div>
                            </div>
                        </label>
                        <label class="flex-1 cursor-pointer">
                            <input type="radio" name="type" value="user" class="peer hidden">
                            <div class="rounded-xl border border-zinc-700 bg-zinc-800 p-3 text-center peer-checked:border-emerald-500 peer-checked:bg-emerald-500/10 transition">
                                <i class="fa-solid fa-user text-lg text-purple-400 mb-1"></i>
                                <div class="text-sm font-medium">User</div>
                                <div class="text-xs text-zinc-400 mt-0.5">Full access to all your chats</div>
                            </div>
                        </label>
                    </div>
                </div>

                <div id="ak-chat-selector">
                    <label class="block text-sm text-zinc-300 mb-1">Allowed Group Chats</label>
                    <p class="text-xs text-zinc-400 mb-2">Select which group chats this bot can send messages to. Only chats you own are shown.</p>
                    <?php if (empty($chatList)): ?>
                        <p class="text-sm text-zinc-500 italic">You don't own any group chats yet.</p>
                    <?php else: ?>
                        <div class="max-h-48 overflow-y-auto space-y-1 rounded-xl border border-zinc-700 bg-zinc-800 p-3">
                            <?php foreach ($chatList as $gc): ?>
                                <label class="flex items-center gap-3 rounded-lg px-2 py-1.5 hover:bg-zinc-700/50 cursor-pointer">
                                    <input type="checkbox" name="chat_ids[]" value="<?= (int)$gc->id ?>"
                                           class="rounded border-zinc-600 bg-zinc-700 text-emerald-500 focus:ring-emerald-500">
                                    <span class="text-sm text-zinc-200 truncate"><?= htmlspecialchars($gc->title ?: 'Unnamed Group') ?></span>
                                    <span class="text-xs text-zinc-500 ml-auto shrink-0"><?= htmlspecialchars(User::formatUserNumber($gc->chat_number)) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div>
                    <label class="block text-sm text-zinc-300 mb-1">Expiry</label>
                    <select name="expiry"
                            class="w-full rounded-xl border border-zinc-700 bg-zinc-800 px-4 py-3 text-zinc-100 focus:outline-none focus:ring-2 focus:ring-emerald-500">
                        <option value="never">Never</option>
                        <option value="24h">24 Hours</option>
                        <option value="7d">7 Days</option>
                        <option value="30d" selected>30 Days</option>
                        <option value="1y">1 Year</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm text-zinc-300 mb-1">IP Restrictions <span class="text-zinc-500">(optional)</span></label>
                    <input type="text" name="allowed_ips"
                           placeholder="e.g. 192.168.1.1, 2001:db8::1"
                           class="w-full rounded-xl border border-zinc-700 bg-zinc-800 px-4 py-3 text-zinc-100 placeholder-zinc-500 focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    <p class="text-xs text-zinc-400 mt-1">Comma-separated IPv4 or IPv6 addresses. Leave blank to allow any IP.</p>
                </div>

                <div class="flex gap-2 pt-2">
                    <button type="submit"
                            class="flex-1 rounded-xl bg-emerald-600 px-6 py-2.5 text-sm font-medium text-white hover:bg-emerald-500 transition">
                        Create Key
                    </button>
                    <button type="button" data-modal-close="ak-create-modal"
                            class="rounded-xl bg-zinc-700 px-6 py-2.5 text-sm font-medium text-zinc-200 hover:bg-zinc-600 transition">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Key Created Modal -->
<div id="ak-created-modal" class="<?= $modalOverlayClass ?>" role="dialog" aria-modal="true">
    <div class="<?= $modalBackdropClass ?>"></div>
    <div class="<?= $modalCenterClass ?>">
        <div class="<?= $modalBoxClass ?>" data-modal-box>
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-xl font-semibold">API Key Created</h3>
                <button type="button" data-modal-close="ak-created-modal" class="rounded-lg px-2 py-1 text-zinc-400 hover:bg-zinc-800 hover:text-zinc-200">&times;</button>
            </div>

            <div class="rounded-xl border border-amber-500/40 bg-amber-500/10 px-4 py-3 text-sm text-amber-200 mb-4">
                <i class="fa-solid fa-triangle-exclamation mr-1"></i>
                This key will only be shown once. Copy it now and store it securely.
            </div>

            <div class="mb-4">
                <label class="block text-sm text-zinc-300 mb-1">Your API Key</label>
                <div class="flex gap-2">
                    <input type="text" id="ak-new-key-display" readonly
                           class="flex-1 rounded-xl border border-zinc-700 bg-zinc-800 px-4 py-3 font-mono text-sm text-zinc-100 select-all focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    <button type="button" id="ak-copy-btn" onclick="copyNewKey()"
                            class="shrink-0 rounded-xl bg-zinc-700 px-4 py-3 text-sm text-zinc-200 hover:bg-zinc-600 transition">
                        <i class="fa-regular fa-copy"></i>
                    </button>
                </div>
            </div>

            <div class="flex gap-2">
                <button type="button" data-modal-close="ak-created-modal" onclick="location.reload()"
                        class="flex-1 rounded-xl bg-emerald-600 px-6 py-2.5 text-sm font-medium text-white hover:bg-emerald-500 transition">
                    Done
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Expire Confirmation Modal -->
<div id="ak-expire-modal" class="<?= $modalOverlayClass ?>" role="dialog" aria-modal="true">
    <div class="<?= $modalBackdropClass ?>" data-modal-close="ak-expire-modal"></div>
    <div class="<?= $modalCenterClass ?>">
        <div class="<?= $modalBoxClass ?>" data-modal-box>
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-xl font-semibold">Expire API Key</h3>
                <button type="button" data-modal-close="ak-expire-modal" class="rounded-lg px-2 py-1 text-zinc-400 hover:bg-zinc-800 hover:text-zinc-200">&times;</button>
            </div>

            <p class="text-sm text-zinc-300 mb-4">
                Are you sure you want to expire <strong id="ak-expire-key-name"></strong>? This action cannot be undone. The key will no longer work for API requests.
            </p>

            <div class="flex gap-2">
                <button type="button" id="ak-expire-confirm-btn"
                        class="flex-1 rounded-xl bg-red-600 px-6 py-2.5 text-sm font-medium text-white hover:bg-red-500 transition">
                    Expire Key
                </button>
                <button type="button" data-modal-close="ak-expire-modal"
                        class="rounded-xl bg-zinc-700 px-6 py-2.5 text-sm font-medium text-zinc-200 hover:bg-zinc-600 transition">
                    Cancel
                </button>
            </div>
        </div>
    </div>
</div>

<?php if ($toastMessage !== ''): ?>
    <div id="page-toast"
         data-toast-message="<?= htmlspecialchars($toastMessage) ?>"
         data-toast-kind="<?= htmlspecialchars($toastKind) ?>"
         class="hidden" aria-hidden="true"></div>
<?php endif; ?>

<script>
(function () {
    // ---- Generic modal open/close ----
    var openButtons = document.querySelectorAll('[data-modal-open]');
    var closeButtons = document.querySelectorAll('[data-modal-close]');

    function openModal(modalId) {
        var modal = document.getElementById(modalId);
        if (!modal) return;
        modal.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
        var autofocusInput = modal.querySelector('[data-modal-autofocus]');
        if (autofocusInput && typeof autofocusInput.focus === 'function') {
            setTimeout(function () {
                autofocusInput.focus();
                if (typeof autofocusInput.select === 'function') autofocusInput.select();
            }, 0);
        }
    }

    function closeModal(modalId) {
        var modal = document.getElementById(modalId);
        if (!modal) return;
        modal.classList.add('hidden');
        if (!Array.from(document.querySelectorAll('[role="dialog"]')).some(function (el) {
            return !el.classList.contains('hidden');
        })) {
            document.body.classList.remove('overflow-hidden');
        }
    }

    openButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            var modalId = button.getAttribute('data-modal-open');
            if (modalId) openModal(modalId);
        });
    });

    closeButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            var modalId = button.getAttribute('data-modal-close');
            if (modalId) closeModal(modalId);
        });
    });

    document.querySelectorAll('[role="dialog"]').forEach(function (overlay) {
        overlay.addEventListener('mousedown', function (event) {
            if (!event.target.closest('[data-modal-box]')) {
                closeModal(overlay.id);
            }
        });
    });

    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') return;
        document.querySelectorAll('[role="dialog"]').forEach(function (modal) {
            if (!modal.classList.contains('hidden')) {
                modal.classList.add('hidden');
            }
        });
        document.body.classList.remove('overflow-hidden');
    });

    // ---- Type toggle: show/hide chat selector ----
    var typeRadios = document.querySelectorAll('input[name="type"]');
    var chatSelector = document.getElementById('ak-chat-selector');

    function toggleChatSelector() {
        var selected = document.querySelector('input[name="type"]:checked');
        if (chatSelector) {
            chatSelector.style.display = (selected && selected.value === 'bot') ? '' : 'none';
        }
    }

    typeRadios.forEach(function (radio) {
        radio.addEventListener('change', toggleChatSelector);
    });
    toggleChatSelector();

    // ---- Create form submit ----
    var createForm = document.getElementById('ak-create-form');
    if (createForm) {
        createForm.addEventListener('submit', function (e) {
            e.preventDefault();

            var formData = new FormData(createForm);
            var data = {};
            data.csrf_token = getCsrfToken();
            data.name = formData.get('name') || '';
            data.type = formData.get('type') || 'bot';
            data.expiry = formData.get('expiry') || '30d';
            data.allowed_ips = formData.get('allowed_ips') || '';

            var chatIds = formData.getAll('chat_ids[]');

            var body = new URLSearchParams();
            Object.entries(data).forEach(function (entry) {
                body.append(entry[0], entry[1]);
            });
            chatIds.forEach(function (id) {
                body.append('chat_ids[]', id);
            });

            fetch(<?= json_encode(base_url('/apikeys/create')) ?>, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: body.toString()
            })
            .then(function (res) { return res.json(); })
            .then(function (result) {
                if (result.success) {
                    closeModal('ak-create-modal');
                    createForm.reset();
                    toggleChatSelector();

                    var keyDisplay = document.getElementById('ak-new-key-display');
                    if (keyDisplay) keyDisplay.value = result.api_key;
                    openModal('ak-created-modal');
                } else {
                    showToast(result.error || 'Failed to create API key', 'error');
                }
            })
            .catch(function () {
                showToast('An error occurred', 'error');
            });
        });
    }

    // ---- Expire key ----
    var pendingExpireKeyId = 0;

    window.expireKey = function (keyId, keyName) {
        pendingExpireKeyId = keyId;
        var nameEl = document.getElementById('ak-expire-key-name');
        if (nameEl) nameEl.textContent = keyName;
        openModal('ak-expire-modal');
    };

    var expireConfirmBtn = document.getElementById('ak-expire-confirm-btn');
    if (expireConfirmBtn) {
        expireConfirmBtn.addEventListener('click', function () {
            if (pendingExpireKeyId <= 0) return;

            postForm(<?= json_encode(base_url('/apikeys/expire')) ?>, {
                csrf_token: getCsrfToken(),
                key_id: String(pendingExpireKeyId)
            }).then(function (result) {
                if (result.success) {
                    closeModal('ak-expire-modal');
                    showToast('API key expired successfully', 'success');
                    setTimeout(function () { location.reload(); }, 800);
                } else {
                    showToast(result.error || 'Failed to expire key', 'error');
                }
            }).catch(function () {
                showToast('An error occurred', 'error');
            });
        });
    }

    // ---- Copy key ----
    window.copyNewKey = function () {
        var keyDisplay = document.getElementById('ak-new-key-display');
        var copyBtn = document.getElementById('ak-copy-btn');
        if (!keyDisplay) return;

        navigator.clipboard.writeText(keyDisplay.value).then(function () {
            if (copyBtn) {
                copyBtn.innerHTML = '<i class="fa-solid fa-check"></i>';
                setTimeout(function () {
                    copyBtn.innerHTML = '<i class="fa-regular fa-copy"></i>';
                }, 2000);
            }
        });
    };
})();
</script>
