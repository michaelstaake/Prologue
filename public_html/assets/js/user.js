// Extracted from app.js for feature-focused organization.

async function sendFriendRequest(event) {
    event.preventDefault();
    const input = document.getElementById('friend-number');
    if (!input) return;
    await sendFriendRequestByValue(input.value);
}

async function sendFriendRequestByValue(userNumber) {
    const result = await postForm('/friends/request', {
        csrf_token: getCsrfToken(),
        user_number: userNumber
    });

    if (result.success) {
        showToast('Friend request sent', 'success');
    } else {
        showToast(result.error || 'Unable to send friend request', 'error');
    }
}

async function acceptFriendRequest(requesterId) {
    const result = await postForm('/friends/accept', {
        csrf_token: getCsrfToken(),
        requester_id: String(requesterId)
    });
    if (result.success) {
        window.location.href = `/c/${formatNumber(result.chat_number)}`;
        return;
    }
    showToast(result.error || 'Unable to accept request', 'error');
}

async function cancelFriendRequest(targetUserId) {
    const result = await postForm('/friends/cancel', {
        csrf_token: getCsrfToken(),
        target_user_id: String(targetUserId)
    });

    if (result.success) {
        showToast('Friend request cancelled', 'success');
        window.location.reload();
        return;
    }

    showToast(result.error || 'Unable to cancel request', 'error');
}

function openInviteReferralModal() {
    const modal = document.getElementById('invite-referral-modal');
    if (!modal) return;

    modal.classList.remove('hidden');
    modal.setAttribute('aria-hidden', 'false');
}

function closeInviteReferralModal() {
    const modal = document.getElementById('invite-referral-modal');
    if (!modal) return;

    modal.classList.add('hidden');
    modal.setAttribute('aria-hidden', 'true');
}

async function sendInviteReferralFriendRequest() {
    const modal = document.getElementById('invite-referral-modal');
    const submitButton = document.getElementById('invite-referral-modal-send');
    if (!modal || !submitButton || submitButton.disabled) return;

    const userNumber = String(modal.getAttribute('data-referrer-user-number') || '').trim();
    const username = String(modal.getAttribute('data-referrer-username') || '').trim();
    if (!userNumber) {
        closeInviteReferralModal();
        return;
    }

    submitButton.disabled = true;
    const previousLabel = submitButton.textContent;
    submitButton.textContent = 'Sending...';

    try {
        const result = await postForm('/friends/request', {
            csrf_token: getCsrfToken(),
            user_number: userNumber
        });

        if (result.success) {
            showToast(username ? `Friend request sent to ${username}` : 'Friend request sent', 'success');
        } else {
            showToast(result.error || 'Unable to send friend request', 'error');
        }

        closeInviteReferralModal();
    } finally {
        submitButton.disabled = false;
        submitButton.textContent = previousLabel;
    }
}

function bindInviteReferralModal() {
    const modal = document.getElementById('invite-referral-modal');
    const skipButton = document.getElementById('invite-referral-modal-skip');
    const sendButton = document.getElementById('invite-referral-modal-send');
    if (!modal || !skipButton || !sendButton) return;

    skipButton.addEventListener('click', (event) => {
        event.preventDefault();
        closeInviteReferralModal();
    });

    sendButton.addEventListener('click', (event) => {
        event.preventDefault();
        sendInviteReferralFriendRequest();
    });

    modal.addEventListener('click', (event) => {
        if (event.target !== modal) return;
        closeInviteReferralModal();
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        if (modal.classList.contains('hidden')) return;
        closeInviteReferralModal();
    });

    if (String(modal.getAttribute('data-auto-open') || '') === '1') {
        openInviteReferralModal();
    }
}

async function unfriendUser(userId) {
    const result = await postForm('/friends/unfriend', {
        csrf_token: getCsrfToken(),
        user_id: String(userId)
    });

    if (result.success) {
        showToast('Friend removed', 'success');
        window.location.reload();
        return;
    }

    showToast(result.error || 'Unable to unfriend user', 'error');
}

function openUnfriendModal(userId) {
    const safeUserId = Number(userId || 0);
    if (!Number.isFinite(safeUserId) || safeUserId <= 0) {
        showToast('Invalid user', 'error');
        return;
    }

    const modal = document.getElementById('unfriend-confirm-modal');
    if (!modal) {
        unfriendUser(safeUserId);
        return;
    }

    pendingUnfriendUserId = safeUserId;
    modal.classList.remove('hidden');
}

function closeUnfriendModal() {
    const modal = document.getElementById('unfriend-confirm-modal');
    if (!modal) return;
    modal.classList.add('hidden');
    pendingUnfriendUserId = 0;
}

function bindUnfriendModal() {
    const modal = document.getElementById('unfriend-confirm-modal');
    const cancel = document.getElementById('unfriend-confirm-cancel');
    const confirm = document.getElementById('unfriend-confirm-submit');
    if (!modal || !cancel || !confirm) return;

    cancel.addEventListener('click', (event) => {
        event.preventDefault();
        closeUnfriendModal();
    });

    modal.addEventListener('click', (event) => {
        if (event.target !== modal) return;
        closeUnfriendModal();
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        if (modal.classList.contains('hidden')) return;
        closeUnfriendModal();
    });

    confirm.addEventListener('click', async (event) => {
        event.preventDefault();
        if (confirm.disabled) return;
        if (pendingUnfriendUserId <= 0) {
            closeUnfriendModal();
            return;
        }

        confirm.disabled = true;
        const previousLabel = confirm.textContent;
        confirm.textContent = 'Removing...';

        try {
            await unfriendUser(pendingUnfriendUserId);
        } finally {
            confirm.disabled = false;
            confirm.textContent = previousLabel;
            closeUnfriendModal();
        }
    });
}

async function toggleFavoriteUser(userId, favorite) {
    const result = await postForm('/friends/favorite', {
        csrf_token: getCsrfToken(),
        user_id: String(userId),
        favorite: favorite ? '1' : '0'
    });

    if (result.success) {
        showToast(result.is_favorite ? 'Added to favorites' : 'Removed from favorites', 'success');
        window.location.reload();
        return;
    }

    showToast(result.error || 'Unable to update favorite', 'error');
}

let openProfilePostReactionPickerId = 0;

function hydrateProfilePostReactionEmojiMarkup(root = document) {
    if (!root || typeof renderReactionEmojiMarkup !== 'function') {
        return;
    }

    const options = Array.from(root.querySelectorAll('.js-profile-post-reaction-option'));
    options.forEach((option) => {
        const reactionCode = String(option.getAttribute('data-reaction-code') || '');
        const markup = renderReactionEmojiMarkup(reactionCode, 'w-7 h-7');
        if (!markup) return;
        option.innerHTML = markup;
    });

    const badges = Array.from(root.querySelectorAll('.js-profile-post-reaction-badge'));
    badges.forEach((badge) => {
        const reactionCode = String(badge.getAttribute('data-reaction-code') || '');
        const markup = renderReactionEmojiMarkup(reactionCode, 'w-6 h-6');
        if (!markup) return;

        const existingCount = badge.querySelector('span:last-child');
        const countText = String(existingCount?.textContent || '').trim();
        badge.innerHTML = `${markup}<span>${escapeHtml(countText || '0')}</span>`;
    });
}

function closeAllProfilePostReactionPickers(resetState = true) {
    document.querySelectorAll('.js-profile-post-reaction-picker').forEach((picker) => {
        picker.classList.add('hidden');
    });

    if (resetState) {
        openProfilePostReactionPickerId = 0;
    }
}

function toggleProfilePostReactionPicker(postId) {
    const safePostId = Number(postId || 0);
    if (!Number.isFinite(safePostId) || safePostId <= 0) return;

    const picker = document.querySelector(`.js-profile-post-reaction-picker[data-post-reaction-picker-for="${safePostId}"]`);
    if (!picker) return;

    const willOpen = picker.classList.contains('hidden');
    closeAllProfilePostReactionPickers(false);

    if (willOpen) {
        picker.classList.remove('hidden');
        openProfilePostReactionPickerId = safePostId;
        return;
    }

    openProfilePostReactionPickerId = 0;
}

function normalizeProfilePostReactionCode(value) {
    if (typeof normalizeReactionCode === 'function') {
        return normalizeReactionCode(value);
    }

    const normalized = String(value || '').toUpperCase().replace(/[^0-9A-F]/g, '');
    const allowed = new Set(['1F44D', '1F44E', '2665', '1F923', '1F622', '1F436', '1F4A9']);
    return allowed.has(normalized) ? normalized : '';
}

async function reactToProfilePost(postId, reactionCode) {
    const safePostId = Number(postId || 0);
    const safeReactionCode = normalizeProfilePostReactionCode(reactionCode);
    if (!Number.isFinite(safePostId) || safePostId <= 0 || !safeReactionCode) {
        return;
    }

    const result = await postForm('/api/posts/react', {
        csrf_token: getCsrfToken(),
        post_id: String(safePostId),
        reaction_code: safeReactionCode
    });

    if (!result.success) {
        showToast(result.error || 'Unable to react to post', 'error');
        return;
    }

    window.location.reload();
}

async function createProfilePost(content) {
    const text = String(content || '').trim();
    const length = Array.from(text).length;

    if (!text) {
        showToast('Post content is required', 'error');
        return false;
    }
    if (length > 500) {
        showToast('Posts can be at most 500 characters', 'error');
        return false;
    }

    const result = await postForm('/api/posts', {
        csrf_token: getCsrfToken(),
        content: text
    });

    if (!result.success) {
        showToast(result.error || 'Unable to create post', 'error');
        return false;
    }

    if (typeof queuePendingPageToast === 'function') {
        queuePendingPageToast('Post published', 'success');
    } else {
        showToast('Post published', 'success');
    }
    window.location.reload();
    return true;
}

async function deleteProfilePost(postId) {
    const safePostId = Number(postId || 0);
    if (!Number.isFinite(safePostId) || safePostId <= 0) {
        showToast('Invalid post', 'error');
        return false;
    }

    const result = await postForm('/api/posts/delete', {
        csrf_token: getCsrfToken(),
        post_id: String(safePostId)
    });

    if (!result.success) {
        showToast(result.error || 'Unable to delete post', 'error');
        return false;
    }

    if (typeof queuePendingPageToast === 'function') {
        queuePendingPageToast('Post deleted', 'success');
    } else {
        showToast('Post deleted', 'success');
    }

    window.location.reload();
    return true;
}

function openProfilePostDeleteModal(postId, username = '') {
    const safePostId = Number(postId || 0);
    if (!Number.isFinite(safePostId) || safePostId <= 0) {
        showToast('Invalid post', 'error');
        return;
    }

    const modal = document.getElementById('profile-post-delete-modal');
    const description = document.getElementById('profile-post-delete-description');
    if (!modal) {
        deleteProfilePost(safePostId).catch(() => {
            showToast('Unable to delete post', 'error');
        });
        return;
    }

    const safeUsername = String(username || '').trim();
    if (description) {
        description.textContent = safeUsername
            ? `Are you sure you want to delete @${safeUsername}'s post? This cannot be undone.`
            : 'Are you sure you want to delete this post? This cannot be undone.';
    }

    pendingProfilePostDeleteId = safePostId;
    modal.classList.remove('hidden');
    modal.setAttribute('aria-hidden', 'false');
}

function closeProfilePostDeleteModal() {
    const modal = document.getElementById('profile-post-delete-modal');
    if (!modal) return;

    modal.classList.add('hidden');
    modal.setAttribute('aria-hidden', 'true');
    pendingProfilePostDeleteId = 0;
}

function bindProfilePostDeleteModal() {
    const modal = document.getElementById('profile-post-delete-modal');
    const cancel = document.getElementById('profile-post-delete-cancel');
    const submit = document.getElementById('profile-post-delete-submit');
    if (!modal || !cancel || !submit) return;

    cancel.addEventListener('click', (event) => {
        event.preventDefault();
        closeProfilePostDeleteModal();
    });

    modal.addEventListener('click', (event) => {
        if (event.target !== modal) return;
        closeProfilePostDeleteModal();
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        if (modal.classList.contains('hidden')) return;
        closeProfilePostDeleteModal();
    });

    submit.addEventListener('click', async (event) => {
        event.preventDefault();
        if (submit.disabled) return;
        if (!Number.isFinite(pendingProfilePostDeleteId) || pendingProfilePostDeleteId <= 0) {
            closeProfilePostDeleteModal();
            return;
        }

        submit.disabled = true;
        const previousLabel = submit.textContent;
        submit.textContent = 'Deleting...';

        try {
            const success = await deleteProfilePost(pendingProfilePostDeleteId);
            if (success) {
                closeProfilePostDeleteModal();
            }
        } finally {
            submit.disabled = false;
            submit.textContent = previousLabel;
        }
    });
}

function openNewPostModal() {
    const modal = document.getElementById('new-post-modal');
    if (!modal) return;
    modal.classList.remove('hidden');
    modal.setAttribute('aria-hidden', 'false');
    const input = document.getElementById('new-post-modal-input');
    if (input) {
        input.value = '';
        updateNewPostModalCounter();
        setTimeout(() => input.focus(), 0);
    }
}

function closeNewPostModal() {
    const modal = document.getElementById('new-post-modal');
    if (!modal) return;
    modal.classList.add('hidden');
    modal.setAttribute('aria-hidden', 'true');
    const submit = document.getElementById('new-post-modal-submit');
    if (submit) {
        submit.disabled = false;
        submit.textContent = 'Publish';
    }
}

function updateNewPostModalCounter() {
    const input = document.getElementById('new-post-modal-input');
    const counter = document.getElementById('new-post-modal-counter');
    if (!input || !counter) return;
    const count = Array.from(String(input.value || '')).length;
    counter.textContent = `${count}/500`;
    counter.classList.toggle('text-red-300', count > 500);
}

function bindNewPostModal() {
    const modal = document.getElementById('new-post-modal');
    const closeBtn = document.getElementById('new-post-modal-close');
    const cancelBtn = document.getElementById('new-post-modal-cancel');
    const form = document.getElementById('new-post-modal-form');
    const input = document.getElementById('new-post-modal-input');
    const submit = document.getElementById('new-post-modal-submit');
    if (!modal || !form || !input || !submit) return;

    input.addEventListener('input', updateNewPostModalCounter);

    const close = () => closeNewPostModal();

    if (closeBtn) closeBtn.addEventListener('click', (event) => { event.preventDefault(); close(); });
    if (cancelBtn) cancelBtn.addEventListener('click', (event) => { event.preventDefault(); close(); });

    modal.addEventListener('click', (event) => {
        if (event.target !== modal) return;
        close();
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        if (modal.classList.contains('hidden')) return;
        close();
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (submit.disabled) return;

        submit.disabled = true;
        const previousLabel = submit.textContent;
        submit.textContent = 'Publishing...';

        try {
            const success = await createProfilePost(input.value);
            if (success) {
                closeNewPostModal();
            }
        } finally {
            submit.disabled = false;
            submit.textContent = previousLabel;
        }
    });
}

function bindProfilePosts() {
    const root = document.getElementById('profile-posts-root');
    if (!root) return;

    hydrateProfilePostReactionEmojiMarkup(root);

    const canReactToPosts = String(root.getAttribute('data-can-react-posts') || '0') === '1';

    root.addEventListener('click', (event) => {
        const deleteButton = event.target.closest('.js-profile-post-delete-open');
        if (deleteButton) {
            event.preventDefault();
            const postId = Number(deleteButton.getAttribute('data-post-id') || 0);
            const username = String(deleteButton.getAttribute('data-post-username') || '');
            openProfilePostDeleteModal(postId, username);
            return;
        }

        const reactLink = event.target.closest('.js-profile-post-react-link');
        if (reactLink) {
            if (!canReactToPosts) return;
            event.preventDefault();
            const postId = Number(reactLink.getAttribute('data-post-id') || 0);
            toggleProfilePostReactionPicker(postId);
            return;
        }

        const option = event.target.closest('.js-profile-post-reaction-option');
        if (option) {
            if (!canReactToPosts) return;
            event.preventDefault();
            const postId = Number(option.getAttribute('data-post-id') || 0);
            const reactionCode = String(option.getAttribute('data-reaction-code') || '');
            reactToProfilePost(postId, reactionCode).catch(() => {
                showToast('Unable to react to post', 'error');
            });
            return;
        }

        const badge = event.target.closest('.js-profile-post-reaction-badge');
        if (badge) {
            if (!canReactToPosts) return;
            event.preventDefault();
            const postId = Number(badge.getAttribute('data-post-id') || 0);
            const reactionCode = String(badge.getAttribute('data-reaction-code') || '');
            reactToProfilePost(postId, reactionCode).catch(() => {
                showToast('Unable to update reaction', 'error');
            });
            return;
        }

        if (!event.target.closest('.js-profile-post-reaction-picker')) {
            closeAllProfilePostReactionPickers();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeAllProfilePostReactionPickers();
        }
    });
}

async function searchUsers(event) {
    event.preventDefault();
    const input = document.getElementById('user-search-input');
    const results = document.getElementById('user-search-results');
    const help = document.getElementById('user-search-help');
    if (!input || !results) return;

    const query = input.value.trim();
    if (!query) return;
    const isValidSearch = /^[A-Za-z0-9-]+$/.test(query);
    if (!isValidSearch) {
        input.setCustomValidity('Use only letters, numbers, and dashes.');
        input.reportValidity();
        showToast('Use only letters, numbers, and dashes.', 'error');
        return;
    }
    input.setCustomValidity('');
    if (help) {
        help.classList.add('hidden');
    }

    const res = await fetch(`/api/users/search?q=${encodeURIComponent(query)}`);
    const data = await res.json();
    const users = data.users || [];

    const usersMarkup = users.map(user => `
        <div class="bg-zinc-800 rounded-xl p-3">
            <div class="flex items-center gap-3 min-w-0">
                ${renderAvatarMarkup(user, 'w-10 h-10', 'text-sm')}
                <div class="min-w-0">
                    <div class="font-medium truncate">${escapeHtml(user.username)}</div>
                    <div class="text-xs text-zinc-400">${escapeHtml(user.formatted_user_number || formatNumber(user.user_number))}</div>
                    <div class="text-xs ${escapeHtml(user.effective_status_text_class || 'text-zinc-500')} mt-0.5">${escapeHtml(user.effective_status_label || 'Offline')}</div>
                </div>
            </div>
            ${renderSearchResultAction(user)}
        </div>
    `).join('') || '<p class="text-zinc-400 text-sm sm:col-span-2">No users found.</p>';

    replaceElementMarkup(results, usersMarkup);
}

function renderSearchResultAction(user) {
    const formattedUserNumber = String(user.formatted_user_number || formatNumber(user.user_number || ''));
    const friendshipStatus = String(user.friendship_status || '').toLowerCase();
    const personalChatNumber = String(user.personal_chat_number || '').trim();

    if (friendshipStatus === 'accepted' && personalChatNumber !== '') {
        return `<a href="/c/${escapeHtml(personalChatNumber)}" class="mt-3 w-full bg-emerald-600 hover:bg-emerald-500 px-4 py-2 rounded-lg text-sm inline-flex items-center justify-center">Personal Chat</a>`;
    }

    return `<button class="mt-3 w-full bg-emerald-600 hover:bg-emerald-500 px-4 py-2 rounded-lg text-sm" onclick="sendFriendRequestByValue('${escapeHtml(formattedUserNumber)}')">Add Friend</button>`;
}

function getAdminUserListNodes() {
    return Array.from(document.querySelectorAll('.admin-user-item'));
}

function closeAdminUserMenus() {
    const menus = document.querySelectorAll('[data-admin-user-menu]');
    if (menus.length === 0) return;

    menus.forEach((menu) => menu.classList.add('hidden'));
    openAdminUserMenuId = 0;
}

function toggleAdminUserMenu(userId) {
    const safeUserId = Number(userId || 0);
    if (!Number.isFinite(safeUserId) || safeUserId <= 0) return;

    const menu = document.getElementById(`admin-user-menu-${safeUserId}`);
    if (!menu) return;

    if (openAdminUserMenuId === safeUserId && !menu.classList.contains('hidden')) {
        closeAdminUserMenus();
        return;
    }

    closeAdminUserMenus();
    menu.classList.remove('hidden');
    openAdminUserMenuId = safeUserId;
}

function filterAdminUsersList(query) {
    const normalizedQuery = String(query || '').trim().toLowerCase();
    const nodes = getAdminUserListNodes();

    nodes.forEach((node) => {
        const username = String(node.dataset.adminUsername || '').toLowerCase();
        const userNumber = formatNumber(String(node.dataset.adminUserNumber || ''));
        const userNumberDigits = String(node.dataset.adminUserNumber || '');

        const shouldShow = normalizedQuery === ''
            || username.includes(normalizedQuery)
            || userNumber.includes(normalizedQuery)
            || userNumberDigits.includes(normalizedQuery.replace(/\D/g, ''));

        node.classList.toggle('hidden', !shouldShow);
    });
}

function renderAdminUsersTypeahead(query) {
    const container = document.getElementById('admin-users-typeahead');
    if (!container) return;

    const normalizedQuery = String(query || '').trim().toLowerCase();
    if (normalizedQuery === '') {
        container.innerHTML = '';
        container.classList.add('hidden');
        return;
    }

    const nodes = getAdminUserListNodes();
    const uniqueUsernames = [];
    const seen = new Set();

    for (const node of nodes) {
        const username = String(node.dataset.adminUsername || '').trim();
        if (!username || seen.has(username)) continue;
        if (!username.includes(normalizedQuery)) continue;
        seen.add(username);
        uniqueUsernames.push(username);
        if (uniqueUsernames.length >= 8) break;
    }

    if (uniqueUsernames.length === 0) {
        container.innerHTML = '';
        container.classList.add('hidden');
        return;
    }

    container.innerHTML = uniqueUsernames.map((username) => `
        <button type="button" class="w-full text-left px-4 py-2.5 hover:bg-zinc-800 text-sm" data-admin-typeahead="${escapeHtml(username)}">${escapeHtml(username)}</button>
    `).join('');
    container.classList.remove('hidden');
}

function getAdminUserCardById(userId) {
    const safeUserId = Number(userId || 0);
    if (!Number.isFinite(safeUserId) || safeUserId <= 0) return null;
    return document.getElementById(`admin-user-card-${safeUserId}`);
}

function getAdminUserState(userId) {
    const card = getAdminUserCardById(userId);
    if (!card) return null;

    const role = String(card.dataset.adminRole || 'user').trim().toLowerCase() === 'admin' ? 'admin' : 'user';
    const isBanned = String(card.dataset.adminIsBanned || '0') === '1';
    const username = String(card.dataset.adminUsername || '').trim();

    return {
        role,
        isBanned,
        username
    };
}

function setAdminUserRoleState(userId, role) {
    const safeUserId = Number(userId || 0);
    const nextRole = String(role || '').trim().toLowerCase() === 'admin' ? 'admin' : 'user';
    const isAdmin = nextRole === 'admin';

    const card = getAdminUserCardById(safeUserId);
    if (card) {
        card.dataset.adminRole = nextRole;
    }

    const badge = document.getElementById(`admin-user-role-badge-${safeUserId}`);
    if (badge) {
        badge.textContent = isAdmin ? 'admin' : 'user';
        badge.classList.remove('bg-emerald-700', 'text-emerald-100', 'bg-zinc-700', 'text-zinc-200');
        badge.classList.add(isAdmin ? 'bg-emerald-700' : 'bg-zinc-700', isAdmin ? 'text-emerald-100' : 'text-zinc-200');
    }

    const roleAction = document.getElementById(`admin-user-role-action-${safeUserId}`);
    if (roleAction) {
        roleAction.textContent = isAdmin ? 'Demote' : 'Promote';
        roleAction.classList.remove('bg-amber-700', 'hover:bg-amber-600', 'text-amber-100', 'bg-emerald-700', 'hover:bg-emerald-600', 'text-emerald-100');
        if (isAdmin) {
            roleAction.classList.add('bg-amber-700', 'hover:bg-amber-600', 'text-amber-100');
        } else {
            roleAction.classList.add('bg-emerald-700', 'hover:bg-emerald-600', 'text-emerald-100');
        }
    }
}

function setAdminUserBanState(userId, isBanned) {
    const safeUserId = Number(userId || 0);
    const nextIsBanned = Boolean(isBanned);

    const card = getAdminUserCardById(safeUserId);
    if (card) {
        card.dataset.adminIsBanned = nextIsBanned ? '1' : '0';
    }

    const bannedBadge = document.getElementById(`admin-user-banned-badge-${safeUserId}`);
    if (bannedBadge) {
        bannedBadge.classList.toggle('hidden', !nextIsBanned);
    }

    const banAction = document.getElementById(`admin-user-ban-action-${safeUserId}`);
    if (banAction) {
        banAction.textContent = nextIsBanned ? 'Unban' : 'Ban';
    }
}

function closeAdminUserActionModal() {
    const modal = document.getElementById('admin-user-action-modal');
    if (!modal) return;
    modal.classList.add('hidden');
    pendingAdminUserAction = null;
}

function openAdminUserActionModal(config) {
    const modal = document.getElementById('admin-user-action-modal');
    const title = document.getElementById('admin-user-action-modal-title');
    const description = document.getElementById('admin-user-action-modal-description');
    const submit = document.getElementById('admin-user-action-modal-submit');
    const retainMessagesWrap = document.getElementById('admin-user-retain-messages-wrap');
    const retainMessagesInput = document.getElementById('admin-user-retain-messages');
    if (!modal || !title || !description || !submit) return false;

    const submitLabel = String(config?.submitLabel || 'Confirm').trim() || 'Confirm';
    const submitTone = String(config?.submitTone || 'emerald').trim().toLowerCase();

    title.textContent = String(config?.title || 'Confirm action');
    description.textContent = String(config?.description || 'Are you sure?');
    submit.textContent = submitLabel;
    submit.classList.remove('bg-emerald-700', 'hover:bg-emerald-600', 'bg-amber-700', 'hover:bg-amber-600', 'bg-red-700', 'hover:bg-red-600');

    if (submitTone === 'red') {
        submit.classList.add('bg-red-700', 'hover:bg-red-600');
    } else if (submitTone === 'amber') {
        submit.classList.add('bg-amber-700', 'hover:bg-amber-600');
    } else {
        submit.classList.add('bg-emerald-700', 'hover:bg-emerald-600');
    }

    const showRetainMessages = Boolean(config?.showRetainMessages);
    if (retainMessagesWrap && retainMessagesInput) {
        retainMessagesWrap.classList.toggle('hidden', !showRetainMessages);
        retainMessagesWrap.classList.toggle('flex', showRetainMessages);
        retainMessagesInput.checked = showRetainMessages ? Boolean(config?.retainMessagesDefault) : false;
    }

    pendingAdminUserAction = config;
    modal.classList.remove('hidden');
    return true;
}

async function performAdminUserRoleAction(userId) {
    const safeUserId = Number(userId || 0);
    const currentState = getAdminUserState(safeUserId);
    if (!currentState) {
        showToast('Invalid user', 'error');
        return;
    }

    const nextRole = currentState.role === 'admin' ? 'user' : 'admin';

    const result = await postForm('/users/change-group', {
        csrf_token: getCsrfToken(),
        user_id: String(safeUserId),
        role: nextRole
    });

    if (!result.success) {
        showToast(result.error || 'Unable to change user group', 'error');
        return;
    }

    setAdminUserRoleState(safeUserId, nextRole);

    showToast(nextRole === 'admin' ? 'User promoted to admin' : 'User demoted to user', 'success');
    closeAdminUserMenus();
}

async function performAdminUserBanAction(userId) {
    const safeUserId = Number(userId || 0);
    const currentState = getAdminUserState(safeUserId);
    if (!currentState) {
        showToast('Invalid user', 'error');
        return;
    }

    const nextIsBanned = !currentState.isBanned;

    const result = await postForm('/users/ban', {
        csrf_token: getCsrfToken(),
        user_id: String(safeUserId),
        is_banned: nextIsBanned ? '1' : '0'
    });

    if (!result.success) {
        showToast(result.error || `Unable to ${nextIsBanned ? 'ban' : 'unban'} user`, 'error');
        return;
    }

    const effectiveIsBanned = Number(result.is_banned) === 1;
    setAdminUserBanState(safeUserId, effectiveIsBanned);

    showToast(effectiveIsBanned ? 'User banned and sessions deleted' : 'User unbanned', 'success');
    closeAdminUserMenus();
}

async function performDeleteAdminUser(userId, retainMessages = false) {
    const safeUserId = Number(userId || 0);
    if (!Number.isFinite(safeUserId) || safeUserId <= 0) {
        showToast('Invalid user', 'error');
        return;
    }

    const result = await postForm('/users/delete', {
        csrf_token: getCsrfToken(),
        user_id: String(safeUserId),
        retain_messages: retainMessages ? '1' : '0'
    });

    if (!result.success) {
        showToast(result.error || 'Unable to delete user', 'error');
        return;
    }

    const card = document.getElementById(`admin-user-card-${safeUserId}`);
    if (card) {
        card.remove();
    }

    showToast(retainMessages ? 'User deleted and messages retained' : 'User deleted and sessions removed', 'success');
    closeAdminUserMenus();
}

function confirmAdminUserRoleAction(userId) {
    const safeUserId = Number(userId || 0);
    const state = getAdminUserState(safeUserId);
    if (!state) {
        showToast('Invalid user', 'error');
        return;
    }

    const makeAdmin = state.role !== 'admin';
    const usernamePrefix = state.username ? `@${state.username} ` : 'this user ';
    const opened = openAdminUserActionModal({
        type: 'role',
        userId: safeUserId,
        title: makeAdmin ? 'Promote user' : 'Demote user',
        description: makeAdmin
            ? `Are you sure you want to make ${usernamePrefix}an admin?`
            : `Are you sure you want to make ${usernamePrefix}a user?`,
        submitLabel: makeAdmin ? 'Promote' : 'Demote',
        submitTone: makeAdmin ? 'emerald' : 'amber'
    });

    if (!opened) {
        performAdminUserRoleAction(safeUserId);
    }
}

function confirmAdminUserBanAction(userId) {
    const safeUserId = Number(userId || 0);
    const state = getAdminUserState(safeUserId);
    if (!state) {
        showToast('Invalid user', 'error');
        return;
    }

    const shouldBan = !state.isBanned;
    const usernamePrefix = state.username ? `@${state.username}` : 'this user';
    const opened = openAdminUserActionModal({
        type: 'ban',
        userId: safeUserId,
        title: shouldBan ? 'Ban user' : 'Unban user',
        description: shouldBan
            ? `Are you sure you want to ban ${usernamePrefix}?`
            : `Are you sure you want to unban ${usernamePrefix}?`,
        submitLabel: shouldBan ? 'Ban' : 'Unban',
        submitTone: 'amber'
    });

    if (!opened) {
        performAdminUserBanAction(safeUserId);
    }
}

function deleteAdminUser(userId) {
    const safeUserId = Number(userId || 0);
    const state = getAdminUserState(safeUserId);
    if (!state) {
        showToast('Invalid user', 'error');
        return;
    }

    const usernamePrefix = state.username ? `@${state.username}` : 'this user';
    const opened = openAdminUserActionModal({
        type: 'delete',
        userId: safeUserId,
        title: 'Delete user',
        description: `Are you sure you want to delete ${usernamePrefix}? This cannot be undone.`,
        submitLabel: 'Delete',
        submitTone: 'red',
        showRetainMessages: true,
        retainMessagesDefault: false
    });

    if (!opened) {
        performDeleteAdminUser(safeUserId, false);
    }
}

function changeAdminUserGroup(userId) {
    confirmAdminUserRoleAction(userId);
}

async function banAdminUser(userId) {
    confirmAdminUserBanAction(userId);
}

function bindAdminUserActionModal() {
    const modal = document.getElementById('admin-user-action-modal');
    const cancel = document.getElementById('admin-user-action-modal-cancel');
    const submit = document.getElementById('admin-user-action-modal-submit');
    if (!modal || !cancel || !submit) return;

    cancel.addEventListener('click', (event) => {
        event.preventDefault();
        closeAdminUserActionModal();
    });

    modal.addEventListener('click', (event) => {
        if (event.target !== modal) return;
        closeAdminUserActionModal();
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        if (modal.classList.contains('hidden')) return;
        closeAdminUserActionModal();
    });

    submit.addEventListener('click', async (event) => {
        event.preventDefault();
        if (submit.disabled) return;
        const retainMessagesInput = document.getElementById('admin-user-retain-messages');

        const action = pendingAdminUserAction;
        if (!action || !Number.isFinite(Number(action.userId || 0)) || Number(action.userId || 0) <= 0) {
            closeAdminUserActionModal();
            return;
        }

        const safeUserId = Number(action.userId || 0);
        const previousLabel = submit.textContent;
        submit.disabled = true;
        submit.textContent = String(action.submitLabel || previousLabel || 'Working...') + '...';

        try {
            if (action.type === 'role') {
                await performAdminUserRoleAction(safeUserId);
            } else if (action.type === 'ban') {
                await performAdminUserBanAction(safeUserId);
            } else if (action.type === 'delete') {
                const retainMessages = Boolean(retainMessagesInput?.checked);
                await performDeleteAdminUser(safeUserId, retainMessages);
            }
        } finally {
            submit.disabled = false;
            submit.textContent = previousLabel;
            closeAdminUserActionModal();
        }
    });
}

function bindAdminUsersPage() {
    const input = document.getElementById('admin-users-search-input');
    const typeahead = document.getElementById('admin-users-typeahead');
    if (!input || !typeahead) return;

    bindAdminUserActionModal();

    filterAdminUsersList('');

    input.addEventListener('input', () => {
        const query = input.value || '';
        filterAdminUsersList(query);
        renderAdminUsersTypeahead(query);
    });

    input.addEventListener('focus', () => {
        renderAdminUsersTypeahead(input.value || '');
    });

    document.addEventListener('click', (event) => {
        const target = event.target;

        if (!(target instanceof Element)) {
            closeAdminUserMenus();
            typeahead.classList.add('hidden');
            return;
        }

        const typeaheadButton = target.closest('[data-admin-typeahead]');
        if (typeaheadButton instanceof HTMLElement) {
            const username = String(typeaheadButton.dataset.adminTypeahead || '').trim();
            input.value = username;
            filterAdminUsersList(username);
            typeahead.classList.add('hidden');
            return;
        }

        if (!target.closest('[data-admin-user-menu]') && !target.closest('button[onclick^="toggleAdminUserMenu"]')) {
            closeAdminUserMenus();
        }

        if (!target.closest('#admin-users-search-input') && !target.closest('#admin-users-typeahead')) {
            typeahead.classList.add('hidden');
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        closeAdminUserMenus();
        typeahead.classList.add('hidden');
    });
}

async function reportTarget(targetType, targetId) {
    const safeTargetType = String(targetType || '').trim().toLowerCase();
    const safeTargetId = Number(targetId || 0);
    if (!safeTargetType || !Number.isFinite(safeTargetId) || safeTargetId <= 0) {
        showToast('Unable to create report for this item', 'error');
        return;
    }

    if (typeof window.openReportModal === 'function') {
        window.openReportModal(safeTargetType, safeTargetId);
        return;
    }

    showToast('Report form is unavailable on this page', 'error');
}

function bindReportModal() {
    const modal = document.getElementById('report-modal');
    const description = document.getElementById('report-modal-description');
    const form = document.getElementById('report-form');
    const input = document.getElementById('report-reason-input');
    const counter = document.getElementById('report-reason-counter');
    const cancel = document.getElementById('report-cancel');
    const submit = document.getElementById('report-submit');
    if (!modal || !description || !form || !input || !cancel || !submit) return;

    const targetLabelByType = {
        user: 'this user',
        chat: 'this chat',
        message: 'this message'
    };

    const getTargetLabel = () => targetLabelByType[pendingReportTargetType] || 'this content';

    const updateReasonCounter = () => {
        const safeValue = String(input.value || '').slice(0, REPORT_REASON_MAX_LENGTH);
        if (safeValue !== input.value) {
            input.value = safeValue;
        }

        if (counter) {
            counter.textContent = `${safeValue.length}/${REPORT_REASON_MAX_LENGTH}`;
        }
    };

    const setOpenState = (isOpen) => {
        modal.classList.toggle('hidden', !isOpen);

        if (isOpen) {
            description.textContent = `Help us understand what is wrong with ${getTargetLabel()}.`;
            input.value = '';
            updateReasonCounter();
            setTimeout(() => {
                input.focus();
            }, 0);
            return;
        }

        submit.disabled = false;
        submit.textContent = 'Submit report';
    };

    const closeModal = () => {
        setOpenState(false);
    };

    cancel.addEventListener('click', (event) => {
        event.preventDefault();
        closeModal();
    });

    modal.addEventListener('click', (event) => {
        if (event.target !== modal) return;
        closeModal();
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        if (modal.classList.contains('hidden')) return;
        closeModal();
    });

    input.addEventListener('input', () => {
        updateReasonCounter();
    });

    updateReasonCounter();

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (submit.disabled) return;

        const reason = String(input.value || '').trim().slice(0, REPORT_REASON_MAX_LENGTH);
        if (!reason) {
            showToast('Please enter a reason', 'error');
            input.focus();
            return;
        }

        if (!pendingReportTargetType || !Number.isFinite(pendingReportTargetId) || pendingReportTargetId <= 0) {
            showToast('Unable to submit report right now', 'error');
            return;
        }

        submit.disabled = true;
        submit.textContent = 'Submitting...';

        try {
            const result = await postForm('/api/report', {
                csrf_token: getCsrfToken(),
                target_type: pendingReportTargetType,
                target_id: String(pendingReportTargetId),
                reason
            });

            if (result.success) {
                showToast('Report submitted', 'success');
                closeModal();
                return;
            }

            showToast(result.error || 'Failed to submit report', 'error');
        } finally {
            submit.disabled = false;
            submit.textContent = 'Submit report';
        }
    });

    window.openReportModal = (targetType, targetId) => {
        pendingReportTargetType = String(targetType || '').trim().toLowerCase();
        pendingReportTargetId = Number(targetId || 0);
        if (!pendingReportTargetType || !Number.isFinite(pendingReportTargetId) || pendingReportTargetId <= 0) {
            showToast('Unable to create report for this item', 'error');
            return;
        }

        setOpenState(true);
    };
}


function applySidebarStatus(payload) {
    const statusWrap = document.getElementById('sidebar-user-status');
    const statusDot = document.getElementById('sidebar-user-status-dot');
    const statusLabel = document.getElementById('sidebar-user-status-label');
    if (!statusWrap || !statusDot || !statusLabel) return;

    statusWrap.classList.remove('text-emerald-400', 'text-amber-400', 'text-red-400');
    statusDot.classList.remove('bg-emerald-500', 'bg-amber-500', 'bg-red-500');

    statusWrap.classList.add(String(payload?.effective_status_text_class || 'text-emerald-400'));
    statusDot.classList.add(String(payload?.effective_status_dot_class || 'bg-emerald-500'));
    statusLabel.textContent = String(payload?.effective_status_label || 'Online');
}

function updateStatusMenuChecks(status) {
    document.querySelectorAll('[data-status-check]').forEach((icon) => {
        const iconStatus = String(icon.getAttribute('data-status-check') || '');
        icon.classList.toggle('hidden', iconStatus !== status);
    });
}

async function savePresenceStatus(status, options = {}) {
    const safeStatus = String(status || '').toLowerCase();
    if (safeStatus !== 'online' && safeStatus !== 'busy' && safeStatus !== 'offline') {
        return;
    }

    const result = await postForm('/api/status', {
        csrf_token: getCsrfToken(),
        status: safeStatus
    });

    if (!result.success) {
        if (!options.silent) {
            showToast(result.error || 'Unable to update status', 'error', { excludeFromHistory: true });
        }
        return;
    }

    selectedPresenceStatus = safeStatus;
    applySidebarStatus(result);
    updateStatusMenuChecks(selectedPresenceStatus);

    if (!options.silent) {
        showToast(`Status set to ${result.effective_status_label || 'Online'}`, 'success', { excludeFromHistory: true });
    }
}

function bindStatusMenu() {
    const toggle = document.getElementById('status-menu-toggle');
    const menu = document.getElementById('status-menu');
    if (!toggle || !menu) return;

    const currentLabel = document.getElementById('sidebar-user-status-label');
    const currentText = String(currentLabel?.textContent || '').trim().toLowerCase();
    selectedPresenceStatus = currentText === 'busy' ? 'busy' : (currentText === 'offline' ? 'offline' : 'online');
    updateStatusMenuChecks(selectedPresenceStatus);

    const closeMenu = () => menu.classList.add('hidden');

    toggle.addEventListener('click', (event) => {
        event.preventDefault();
        menu.classList.toggle('hidden');
    });

    menu.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-status-choice]');
        if (!button) return;

        const nextStatus = String(button.getAttribute('data-status-choice') || '').toLowerCase();
        if (nextStatus === selectedPresenceStatus) {
            closeMenu();
            return;
        }

        await savePresenceStatus(nextStatus);
        closeMenu();
    });

    document.addEventListener('click', (event) => {
        if (menu.contains(event.target) || toggle.contains(event.target)) return;
        closeMenu();
    });
}

function bindChatHeaderMenu() {
    const toggle = document.getElementById('chat-header-menu-toggle');
    const menu = document.getElementById('chat-header-menu');
    if (!toggle || !menu) return;

    const closeMenu = () => {
        menu.classList.add('hidden');
        toggle.setAttribute('aria-expanded', 'false');
    };

    toggle.addEventListener('click', (event) => {
        event.preventDefault();
        const willOpen = menu.classList.contains('hidden');
        menu.classList.toggle('hidden', !willOpen);
        toggle.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
    });

    menu.addEventListener('click', async (event) => {
        const actionButton = event.target.closest('[data-chat-action]');
        if (!actionButton) return;

        const action = String(actionButton.getAttribute('data-chat-action') || '');
        closeMenu();

        if (action === 'add-user') {
            if (typeof window.openAddUserModal === 'function') {
                window.openAddUserModal();
            }
            return;
        }

        if (action === 'rename-chat') {
            if (typeof window.openRenameChatModal === 'function') {
                window.openRenameChatModal();
            }
            return;
        }

        if (action === 'leave-group') {
            if (typeof window.leaveCurrentGroup === 'function') {
                await window.leaveCurrentGroup();
            }
            return;
        }

        if (action === 'delete-group') {
            if (typeof window.deleteCurrentGroup === 'function') {
                await window.deleteCurrentGroup();
            }
            return;
        }

        if (action === 'take-ownership') {
            if (typeof window.takeCurrentGroupOwnership === 'function') {
                await window.takeCurrentGroupOwnership();
            }
            return;
        }

        if (action === 'report-chat' && currentChat) {
            await reportTarget('chat', currentChat.id);
        }
    });

    document.addEventListener('click', (event) => {
        if (menu.contains(event.target) || toggle.contains(event.target)) return;
        closeMenu();
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        if (menu.classList.contains('hidden')) return;
        closeMenu();
    });
}


function logout() {
    window.location.href = '/logout';
}

async function copyTextToClipboard(text) {
    if (navigator.clipboard && window.isSecureContext) {
        await navigator.clipboard.writeText(text);
        return;
    }

    const tempInput = document.createElement('textarea');
    tempInput.value = text;
    tempInput.setAttribute('readonly', '');
    tempInput.style.position = 'absolute';
    tempInput.style.left = '-9999px';
    document.body.appendChild(tempInput);
    tempInput.select();
    document.execCommand('copy');
    tempInput.remove();
}

function bindInviteCopyButtons() {
    const buttons = document.querySelectorAll('[data-copy-invite]');
    if (!buttons.length) return;

    buttons.forEach((button) => {
        button.addEventListener('click', async () => {
            const value = button.dataset.copyValue || '';
            if (!value) return;

            try {
                await copyTextToClipboard(value);
                const icon = button.querySelector('i');
                if (icon) {
                    icon.classList.remove('fa-copy');
                    icon.classList.add('fa-check');
                    button.classList.remove('text-zinc-400', 'hover:text-zinc-200');
                    button.classList.add('text-emerald-400');

                    if (button._copyTimer) {
                        clearTimeout(button._copyTimer);
                    }

                    button._copyTimer = setTimeout(() => {
                        icon.classList.remove('fa-check');
                        icon.classList.add('fa-copy');
                        button.classList.remove('text-emerald-400');
                        button.classList.add('text-zinc-400', 'hover:text-zinc-200');
                        button._copyTimer = null;
                    }, 1200);
                }
                showToast('Invite code copied', 'success');
            } catch {
                showToast('Unable to copy invite code', 'error');
            }
        });
    });
}

function stripMessageMentions(content) {
    return String(content || '').replace(/@\[\d{16}\|([a-zA-Z][a-zA-Z0-9]{3,31})\]/g, '@$1');
}

function highlightSearchTerm(text, query) {
    if (!query || !text) return escapeHtml(text);
    const escapedText = escapeHtml(text);
    const escapedQuery = escapeHtml(query);
    const regexSafe = escapedQuery.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    return escapedText.replace(
        new RegExp(regexSafe, 'gi'),
        '<mark style="background:rgba(52,211,153,0.2);color:#6ee7b7;border-radius:2px;padding:0 2px;">$&</mark>'
    );
}

function replaceElementMarkup(element, markup) {
    if (!element) return;
    const fragment = document.createRange().createContextualFragment(String(markup || ''));
    element.replaceChildren(fragment);
}

async function searchPostsPage(query, page) {
    const results = document.getElementById('post-search-results');
    if (!results) return;

    results.innerHTML = '<p class="text-zinc-400 text-sm">Searching...</p>';

    try {
        const res = await fetch(`/api/posts/search?q=${encodeURIComponent(query)}&page=${page}`);
        if (!res.ok) {
            const data = await res.json().catch(() => ({}));
            showToast(data.error || 'Search failed', 'error');
            results.innerHTML = '';
            return;
        }

        const data = await res.json();
        const posts = data.posts || [];
        const totalPages = Number(data.total_pages || 1);
        const currentPage = Number(data.page || 1);
        const total = Number(data.total || 0);

        if (posts.length === 0) {
            results.innerHTML = '<p class="text-zinc-400 text-sm">No posts found.</p>';
            return;
        }

        const postItems = posts.map(post => {
            const authorUsername = escapeHtml(String(post.author_username || ''));
            const authorNumFormatted = escapeHtml(String(post.author_user_number_formatted || ''));
            const postId = Number(post.post_id || 0);
            const profileUrl = `/u/${authorNumFormatted}?post=${postId}`;
            const rawContent = String(post.content || '');
            const highlightedContent = highlightSearchTerm(rawContent, query);
            const createdAtRaw = String(post.created_at || '');
            const timestamp = formatCompactMessageTimestamp(createdAtRaw);
            const authorObj = {
                avatar_url: String(post.author_avatar_url || ''),
                username: String(post.author_username || ''),
                user_number: String(post.author_user_number || '')
            };
            const authorId = Number(post.author_id || 0);
            const currentUserId = Number(window.CURRENT_USER_ID || 0);
            const isOwnPost = authorId > 0 && currentUserId > 0 && authorId === currentUserId;
            const isFriend = post.is_friend === true || post.is_friend === 1;
            const friendLabel = isOwnPost ? 'You' : (isFriend ? 'Friend' : 'Not Friend');

            return `
                <a href="${profileUrl}" class="block bg-zinc-800 hover:bg-zinc-700/80 rounded-xl p-4 transition border border-zinc-700 hover:border-zinc-600">
                    <div class="flex items-center gap-2 mb-2 min-w-0">
                        ${renderAvatarMarkup(authorObj, 'w-7 h-7', 'text-xs')}
                        <span class="font-medium text-zinc-100 truncate">${authorUsername}</span>
                        <span class="shrink-0 text-xs text-zinc-500 bg-zinc-900 border border-zinc-700 px-1.5 py-0.5 rounded">${friendLabel}</span>
                        <span class="ml-auto shrink-0 text-xs text-zinc-500" data-utc="${escapeHtml(createdAtRaw)}" title="${escapeHtml(createdAtRaw)}">${escapeHtml(timestamp)}</span>
                    </div>
                    <div class="text-sm text-zinc-300 leading-5 overflow-hidden" style="display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;">${highlightedContent}</div>
                </a>
            `;
        }).join('');

        let paginationHtml = '';
        if (totalPages > 1) {
            const prevDisabled = currentPage <= 1;
            const nextDisabled = currentPage >= totalPages;
            const activeClass = 'inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-zinc-800 border border-zinc-700 text-zinc-300 hover:bg-zinc-700 text-sm transition js-post-search-page';
            const disabledClass = 'inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-zinc-800/40 border border-zinc-700/40 text-zinc-600 text-sm cursor-default';
            paginationHtml = `
                <div class="flex items-center justify-between mt-4 pt-4 border-t border-zinc-700">
                    <button type="button" class="${prevDisabled ? disabledClass : activeClass}" data-page="${currentPage - 1}" ${prevDisabled ? 'disabled' : ''}>
                        <i class="fa fa-arrow-left text-xs"></i> Previous
                    </button>
                    <span class="text-zinc-400 text-sm">Page ${currentPage} of ${totalPages}</span>
                    <button type="button" class="${nextDisabled ? disabledClass : activeClass}" data-page="${currentPage + 1}" ${nextDisabled ? 'disabled' : ''}>
                        Next <i class="fa fa-arrow-right text-xs"></i>
                    </button>
                </div>
            `;
        }

        replaceElementMarkup(results, `<div class="space-y-2">${postItems}</div>${paginationHtml}`);
        if (typeof window.refreshUtcTimestamps === 'function') {
            window.refreshUtcTimestamps(results);
        }

        results.querySelectorAll('.js-post-search-page').forEach(btn => {
            btn.addEventListener('click', () => {
                const targetPage = Number(btn.dataset.page || 1);
                searchPostsPage(query, targetPage);
            });
        });
    } catch {
        showToast('Search failed', 'error');
        results.innerHTML = '';
    }
}

async function searchPosts(event) {
    event.preventDefault();
    const input = document.getElementById('post-search-input');
    const help = document.getElementById('post-search-help');
    if (!input) return;

    const query = input.value.trim();
    if (query.length < 2) {
        showToast('Please enter at least 2 characters', 'error');
        return;
    }

    if (help) {
        help.classList.add('hidden');
    }

    await searchPostsPage(query, 1);
}

async function searchMessagesPage(query, page) {
    const results = document.getElementById('message-search-results');
    if (!results) return;

    results.innerHTML = '<p class="text-zinc-400 text-sm">Searching...</p>';

    try {
        const res = await fetch(`/api/messages/search?q=${encodeURIComponent(query)}&page=${page}`);
        if (!res.ok) {
            const data = await res.json().catch(() => ({}));
            showToast(data.error || 'Search failed', 'error');
            results.innerHTML = '';
            return;
        }

        const data = await res.json();
        const messages = data.messages || [];
        const totalPages = Number(data.total_pages || 1);
        const currentPage = Number(data.page || 1);

        if (messages.length === 0) {
            results.innerHTML = '<p class="text-zinc-400 text-sm">No messages found.</p>';
            return;
        }

        const messageItems = messages.map(msg => {
            const chatTitle = escapeHtml(String(msg.chat_title || msg.chat_number_formatted || ''));
            const chatType = String(msg.chat_type_normalized || '');
            const chatTypeLabel = chatType === 'group' ? 'Group Chat' : 'Private Chat';
            const chatNumFormatted = escapeHtml(String(msg.chat_number_formatted || ''));
            const msgId = Number(msg.message_id || 0);
            const chatUrl = `/c/${chatNumFormatted}?msg=${msgId}`;
            const rawContent = stripMessageMentions(String(msg.content || ''));
            const highlightedContent = highlightSearchTerm(rawContent, query);
            const senderUsername = escapeHtml(String(msg.sender_username || ''));
            const createdAtRaw = String(msg.created_at || '');
            const timestamp = formatCompactMessageTimestamp(createdAtRaw);

            return `
                <a href="${chatUrl}" class="block bg-zinc-800 hover:bg-zinc-700/80 rounded-xl p-4 transition border border-zinc-700 hover:border-zinc-600">
                    <div class="flex items-center gap-2 mb-2 min-w-0">
                        <span class="font-medium text-zinc-100 truncate">${chatTitle}</span>
                        <span class="shrink-0 text-xs text-zinc-500 bg-zinc-900 border border-zinc-700 px-1.5 py-0.5 rounded">${chatTypeLabel}</span>
                        <span class="ml-auto shrink-0 text-xs text-zinc-500" data-utc="${escapeHtml(createdAtRaw)}" title="${escapeHtml(createdAtRaw)}">${escapeHtml(timestamp)}</span>
                    </div>
                    <div class="text-sm text-zinc-300 leading-5 overflow-hidden" style="display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;">${highlightedContent}</div>
                    <div class="mt-2 text-xs text-zinc-500">Sent by <span class="text-zinc-400">${senderUsername}</span></div>
                </a>
            `;
        }).join('');

        let paginationHtml = '';
        if (totalPages > 1) {
            const prevDisabled = currentPage <= 1;
            const nextDisabled = currentPage >= totalPages;
            const activeClass = 'inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-zinc-800 border border-zinc-700 text-zinc-300 hover:bg-zinc-700 text-sm transition js-msg-search-page';
            const disabledClass = 'inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-zinc-800/40 border border-zinc-700/40 text-zinc-600 text-sm cursor-default';
            paginationHtml = `
                <div class="flex items-center justify-between mt-4 pt-4 border-t border-zinc-700">
                    <button type="button" class="${prevDisabled ? disabledClass : activeClass}" data-page="${currentPage - 1}" ${prevDisabled ? 'disabled' : ''}>
                        <i class="fa fa-arrow-left text-xs"></i> Previous
                    </button>
                    <span class="text-zinc-400 text-sm">Page ${currentPage} of ${totalPages}</span>
                    <button type="button" class="${nextDisabled ? disabledClass : activeClass}" data-page="${currentPage + 1}" ${nextDisabled ? 'disabled' : ''}>
                        Next <i class="fa fa-arrow-right text-xs"></i>
                    </button>
                </div>
            `;
        }

        replaceElementMarkup(results, `<div class="space-y-2">${messageItems}</div>${paginationHtml}`);
        if (typeof window.refreshUtcTimestamps === 'function') {
            window.refreshUtcTimestamps(results);
        }

        results.querySelectorAll('.js-msg-search-page').forEach(btn => {
            btn.addEventListener('click', () => {
                const targetPage = Number(btn.dataset.page || 1);
                searchMessagesPage(query, targetPage);
            });
        });
    } catch {
        showToast('Search failed', 'error');
        results.innerHTML = '';
    }
}

async function searchMessages(event) {
    event.preventDefault();
    const input = document.getElementById('message-search-input');
    const help = document.getElementById('message-search-help');
    if (!input) return;

    const query = input.value.trim();
    if (query.length < 2) {
        showToast('Please enter at least 2 characters', 'error');
        return;
    }

    if (help) {
        help.classList.add('hidden');
    }

    await searchMessagesPage(query, 1);
}
