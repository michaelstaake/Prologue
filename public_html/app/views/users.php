<div class="p-8 overflow-auto">
    <h1 class="text-3xl font-bold mb-6">Users</h1>

    <?php $userList = $users ?? []; ?>

    <div class="mb-4 max-w-3xl">
        <label for="admin-users-search-input" class="sr-only">Search usernames</label>
        <div class="relative">
            <input
                type="text"
                id="admin-users-search-input"
                placeholder="Search usernames"
                autocomplete="off"
                class="w-full bg-zinc-900 border border-zinc-700 rounded-xl px-5 py-2.5"
            >
            <div id="admin-users-typeahead" class="hidden absolute z-20 mt-2 w-full bg-zinc-900 border border-zinc-700 rounded-xl overflow-hidden"></div>
        </div>
    </div>

    <section class="bg-zinc-900 border border-zinc-700 rounded-2xl p-5 max-w-4xl">
        <div id="admin-users-list" class="space-y-3">
            <?php if (empty($userList)): ?>
                <p class="text-zinc-400 text-sm">No users found.</p>
            <?php else: ?>
                <?php foreach ($userList as $managedUser): ?>
                    <?php $managedAvatar = User::avatarUrl($managedUser); ?>
                    <?php $managedRole = strtolower((string)($managedUser->role ?? 'user')); ?>
                    <?php $managedIsAdmin = $managedRole === 'admin'; ?>
                    <?php $managedIsBanned = (int)($managedUser->is_banned ?? 0) === 1; ?>
                    <div
                        id="admin-user-card-<?= (int)$managedUser->id ?>"
                        class="admin-user-item bg-zinc-800 rounded-xl p-3"
                        data-admin-user-id="<?= (int)$managedUser->id ?>"
                        data-admin-username="<?= htmlspecialchars(strtolower((string)$managedUser->username), ENT_QUOTES, 'UTF-8') ?>"
                        data-admin-user-number="<?= htmlspecialchars((string)$managedUser->user_number, ENT_QUOTES, 'UTF-8') ?>"
                        data-admin-role="<?= htmlspecialchars($managedRole, ENT_QUOTES, 'UTF-8') ?>"
                        data-admin-is-banned="<?= $managedIsBanned ? '1' : '0' ?>"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <div class="flex items-center gap-3 min-w-0">
                                <?php if ($managedAvatar): ?>
                                    <img src="<?= htmlspecialchars($managedAvatar, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($managedUser->username, ENT_QUOTES, 'UTF-8') ?> avatar" class="w-10 h-10 rounded-full object-cover border border-zinc-700">
                                <?php else: ?>
                                    <div class="w-10 h-10 rounded-full border border-zinc-700 flex items-center justify-center font-semibold text-sm <?= htmlspecialchars(User::avatarColorClasses($managedUser->user_number), ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars(User::avatarInitial($managedUser->username), ENT_QUOTES, 'UTF-8') ?>
                                    </div>
                                <?php endif; ?>
                                <div class="min-w-0">
                                    <div class="font-medium truncate"><?= htmlspecialchars($managedUser->username, ENT_QUOTES, 'UTF-8') ?></div>
                                    <div class="text-xs text-zinc-400"><?= htmlspecialchars(User::formatUserNumber($managedUser->user_number), ENT_QUOTES, 'UTF-8') ?></div>
                                    <div class="text-xs <?= htmlspecialchars($managedUser->effective_status_text_class ?? 'text-zinc-500', ENT_QUOTES, 'UTF-8') ?> mt-0.5">
                                        <?= htmlspecialchars($managedUser->effective_status_label ?? 'Offline', ENT_QUOTES, 'UTF-8') ?>
                                    </div>
                                    <div class="mt-1 flex flex-wrap items-center gap-2">
                                        <span id="admin-user-role-badge-<?= (int)$managedUser->id ?>" class="text-[11px] px-2 py-0.5 rounded-full <?= $managedIsAdmin ? 'bg-emerald-700 text-emerald-100' : 'bg-zinc-700 text-zinc-200' ?>">
                                            <?= htmlspecialchars($managedIsAdmin ? 'admin' : 'user', ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                        <span id="admin-user-banned-badge-<?= (int)$managedUser->id ?>" class="text-[11px] px-2 py-0.5 rounded-full bg-red-800 text-red-100 <?= $managedIsBanned ? '' : 'hidden' ?>">banned</span>
                                    </div>
                                </div>
                            </div>

                            <div class="relative shrink-0">
                                <button type="button" class="bg-zinc-700 hover:bg-zinc-600 text-sm px-3 py-1.5 rounded-lg" onclick="toggleAdminUserMenu(<?= (int)$managedUser->id ?>)">Actions</button>
                                <div id="admin-user-menu-<?= (int)$managedUser->id ?>" data-admin-user-menu class="hidden absolute right-0 mt-2 w-56 bg-zinc-900 border border-zinc-700 rounded-xl p-3 z-30 space-y-3">
                                    <div class="space-y-2">
                                        <button type="button" id="admin-user-role-action-<?= (int)$managedUser->id ?>" class="w-full text-left text-sm px-3 py-2 rounded-lg <?= $managedIsAdmin ? 'bg-amber-700 hover:bg-amber-600 text-amber-100' : 'bg-emerald-700 hover:bg-emerald-600 text-emerald-100' ?>" onclick="confirmAdminUserRoleAction(<?= (int)$managedUser->id ?>)"><?= $managedIsAdmin ? 'Demote' : 'Promote' ?></button>
                                        <button type="button" id="admin-user-ban-action-<?= (int)$managedUser->id ?>" class="w-full text-left bg-amber-700 hover:bg-amber-600 text-amber-100 text-sm px-3 py-2 rounded-lg" onclick="confirmAdminUserBanAction(<?= (int)$managedUser->id ?>)"><?= $managedIsBanned ? 'Unban' : 'Ban' ?></button>
                                        <button type="button" class="w-full text-left bg-red-700 hover:bg-red-600 text-red-100 text-sm px-3 py-2 rounded-lg" onclick="deleteAdminUser(<?= (int)$managedUser->id ?>)">Delete user</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>
</div>

<div id="admin-user-action-modal" class="hidden fixed inset-0 bg-black/70 z-50 p-4 md:p-6" aria-hidden="true">
    <div class="h-full w-full flex items-center justify-center">
        <div class="w-full max-w-md bg-zinc-900 border border-zinc-700 rounded-2xl shadow-2xl p-6" role="dialog" aria-modal="true" aria-labelledby="admin-user-action-modal-title">
            <h2 id="admin-user-action-modal-title" class="text-lg font-semibold text-zinc-100">Confirm action</h2>
            <p id="admin-user-action-modal-description" class="mt-2 text-sm text-zinc-400">Are you sure?</p>
            <div class="mt-5 flex items-center justify-end gap-3">
                <button type="button" id="admin-user-action-modal-cancel" class="px-4 py-2 rounded-xl bg-zinc-800 border border-zinc-700 hover:bg-zinc-700 text-zinc-200">Cancel</button>
                <button type="button" id="admin-user-action-modal-submit" class="px-4 py-2 rounded-xl bg-emerald-700 hover:bg-emerald-600 text-white">Confirm</button>
            </div>
        </div>
    </div>
</div>