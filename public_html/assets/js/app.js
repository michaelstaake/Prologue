let currentChat = null;
let currentUserId = Number(window.CURRENT_USER_ID || 0) || null;
let localStream = null;
let screenStream = null;
let currentCallId = null;
let isMuted = false;
let isVideoEnabled = false;
let isScreenSharing = false;
let callOverlayMode = 'full';   // 'full' | 'half' | 'hidden'
let localPipMode = 'screen-main'; // 'screen-main' | 'webcam-main'
let localCameraStartedAtMs = 0;
let localScreenShareStartedAtMs = 0;
let remoteHasVideo = false;
let remoteIsScreenSharing = false;
let remoteSpotlighted = false;
let callParticipantsPanelOpen = true;
let localUsername = String(window.CURRENT_USERNAME || '');
let peerUsername = '';
let lastTypingPingAt = 0;
let typingStopTimeoutId = null;
let typingActive = false;
let seenNotificationIds = new Set();
let toastHistory = [];
const MAX_TOAST_HISTORY = 50;
let toastHistoryNextId = 1;
let toastExpirySweepTimeoutId = null;
const activeToastPopups = new Map();
let unreadNotificationCount = 0;
let baseDocumentTitle = '';
let openMojiByKey = new Map();
let openMojiCatalog = [];
let openMojiMetadataByKey = null;
let selectedPresenceStatus = 'online';
let pendingAttachments = [];
let chatCallStatusInFlight = false;
let callRingingAudio = null;
let callRingingDirection = null;
let lastIncomingCallAlertId = 0;
let hadCallPeerConnected = false;
let peerConnection = null;
let isCallOfferer = false;
let appliedPeerIceCandidatesCount = 0;
let callSignalPollInterval = null;
let peerAnswerApplied = false;
let peerOfferApplied = false;
let lastAppliedOfferSdp = null;
let lastAppliedAnswerSdp = null;
let initialSignalingComplete = false;
let declinedCallId = 0;
let callPeerConnections = new Map();
let callPeerStates = new Map();
let callSignalCursor = 0;
let remoteAudioElements = new Map();
let activeRemotePeerId = 0;
let selfFocusPreferredPrimaryType = '';
let latestChatCallId = 0;
let globalCallContext = null;
let callRestoreInFlight = false;
let globalCallStatusPollInterval = null;
let callDurationTickInterval = null;
let callDurationStartedAtMs = 0;
let callDurationBarState = null;
let openAdminUserMenuId = 0;
const REPORT_REASON_MAX_LENGTH = 200;
const NOTIFICATION_SOUND_FILE_BY_BUCKET = {
    friend_request: '/assets/sounds/friendrequest.wav',
    new_message: '/assets/sounds/newmessage.wav',
    call: '/assets/sounds/callringing.wav',
    other: '/assets/sounds/notification.wav'
};
const notificationSoundAudioByBucket = new Map();

const MESSAGE_SCROLL_BOTTOM_THRESHOLD_PX = 72;
const CHAT_STATUS_DOT_CLASSES = ['bg-emerald-500', 'bg-amber-500', 'bg-red-500', 'bg-zinc-500'];
const CHAT_STATUS_FALLBACK_POLL_MS = 15000;
let lastPersonalStatusFallbackAt = 0;
let personalStatusFallbackInFlight = false;
let pendingUnfriendUserId = 0;
let pendingReportTargetType = '';
let pendingReportTargetId = 0;
let pendingAdminUserAction = null;

const DEFAULT_EMOJI_KEYS = [
    '1F600', '1F603', '1F604', '1F601', '1F606', '1F605', '1F923', '1F602', '1F642', '1F609',
    '1F60A', '1F607', '1F970', '1F60D', '1F60E', '1F618', '1F617', '1F619', '1F61A', '1F973',
    '1F60B', '1F61B', '1F61C', '1F92A', '1F61D', '1F911', '1F917', '1F92D', '1F92B', '1F914',
    '1FAE1', '1F910', '1F928', '1F610', '1F611', '1F636', '1FAE5', '1F60F', '1F612', '1F644',
    '1F62C', '1F62E-200D-1F4A8', '1F925', '1F60C', '1F614', '1F62A', '1F924', '1F634', '1F637', '1F912',
    '1F915', '1F922', '1F92E', '1F927', '1F975', '1F976', '1F974', '1F635', '1F92F', '1F635-200D-1F4AB',
    '1F920', '1F973', '1F60E', '1F47B', '1F4A9', '2764', '1F90D', '1F44D', '1F44E', '1F44F'
];

const EMOJI_KEYWORD_RULES = [
    { pattern: /^1F1E[6-9A-F]-1F1E[6-9A-F]$/i, keywords: ['flag', 'country'] },
    { pattern: /^1F44D/i, keywords: ['thumbs up', 'like', 'approve'] },
    { pattern: /^1F44E/i, keywords: ['thumbs down', 'dislike'] },
    { pattern: /^1F44F/i, keywords: ['clap', 'applause'] },
    { pattern: /^2764/i, keywords: ['heart', 'love'] },
    { pattern: /^1F4A9/i, keywords: ['poop'] },
    { pattern: /^1F525/i, keywords: ['fire', 'lit', 'hot'] },
    { pattern: /^1F389/i, keywords: ['party', 'celebration', 'confetti'] },
    { pattern: /^1F4AF/i, keywords: ['hundred', '100', 'perfect'] },
    { pattern: /^1F62D/i, keywords: ['cry', 'tears', 'sad'] },
    { pattern: /^1F621|^1F620/i, keywords: ['angry', 'mad'] },
    { pattern: /^1F62E|^1F631/i, keywords: ['surprised', 'shock'] },
    { pattern: /^1F680/i, keywords: ['rocket', 'launch'] }
];

function getCsrfToken() {
    return window.CSRF_TOKEN || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

function normalizeChatType(type) {
    const value = String(type || '').trim().toLowerCase();
    if (value === 'dm') return 'personal';
    return value === 'group' ? 'group' : 'personal';
}

async function postForm(url, data) {
    const formData = new URLSearchParams();
    Object.entries(data).forEach(([k, v]) => formData.append(k, v));

    const res = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData.toString()
    });

    const text = await res.text();
    try {
        return JSON.parse(text);
    } catch {
        return { success: res.ok, raw: text };
    }
}

function formatNumber(value) {
    const raw = String(value || '').replace(/\D/g, '').padStart(16, '0').slice(0, 16);
    return `${raw.slice(0, 4)}-${raw.slice(4, 8)}-${raw.slice(8, 12)}-${raw.slice(12, 16)}`;
}

function getProfileUrlByUserNumber(userNumber) {
    const rawUserNumber = String(userNumber || '').replace(/\D/g, '').slice(0, 16);
    if (!rawUserNumber) return '';
    return `/u/${formatNumber(rawUserNumber)}`;
}

function getProfileUrlForUser(user) {
    return getProfileUrlByUserNumber(user?.user_number || '');
}

function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function formatCompactMessageTimestamp(value) {
    const fullTimestamp = String(value || '').trim();
    if (!fullTimestamp) return '';

    const date = new Date(fullTimestamp.replace(' ', 'T') + 'Z');
    if (isNaN(date.getTime())) {
        return fullTimestamp.replace(/:(\d{2})(?!.*:\d{2})/, '');
    }

    const tz = String(window.USER_TIMEZONE || 'UTC+0');
    const m = tz.match(/^UTC([+-])(\d{1,2})(?::(\d{2}))?$/);
    const offsetMins = m
        ? (m[1] === '+' ? 1 : -1) * (parseInt(m[2], 10) * 60 + parseInt(m[3] || '0', 10))
        : 0;

    const local = new Date(date.getTime() + offsetMins * 60 * 1000);
    const year  = local.getUTCFullYear();
    const month = String(local.getUTCMonth() + 1).padStart(2, '0');
    const day   = String(local.getUTCDate()).padStart(2, '0');
    const hours = String(local.getUTCHours()).padStart(2, '0');
    const mins  = String(local.getUTCMinutes()).padStart(2, '0');
    return `${year}-${month}-${day} ${hours}:${mins}`;
}

document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-utc]').forEach(function (el) {
        const converted = formatCompactMessageTimestamp(el.dataset.utc);
        if (converted) el.textContent = converted;
    });
});

function formatFileSize(bytes) {
    const value = Number(bytes || 0);
    if (!Number.isFinite(value) || value <= 0) return '0 KB';

    if (value >= 1024 * 1024) {
        return `${(value / (1024 * 1024)).toFixed(2)} MB`;
    }

    return `${(value / 1024).toFixed(1)} KB`;
}

function getAttachmentCategory(ext) {
    return ['png', 'jpg', 'jpeg', 'webp'].includes(String(ext).toLowerCase()) ? 'image' : 'file';
}

function normalizeAttachment(attachment) {
    if (!attachment || typeof attachment !== 'object') return null;

    const id = Number(attachment.id || 0);
    if (!Number.isFinite(id) || id <= 0) return null;

    return {
        id,
        original_name: String(attachment.original_name || 'Attachment'),
        file_name: String(attachment.file_name || ''),
        file_extension: String(attachment.file_extension || ''),
        mime_type: String(attachment.mime_type || ''),
        file_size: Number(attachment.file_size || 0),
        width: Number(attachment.width || 0),
        height: Number(attachment.height || 0),
        url: String(attachment.url || '')
    };
}

function renderMessageAttachments(attachments) {
    if (!Array.isArray(attachments) || attachments.length === 0) {
        return '';
    }

    const cards = attachments
        .map(normalizeAttachment)
        .filter(Boolean)
        .map((attachment) => {
            const category = getAttachmentCategory(attachment.file_extension);
            const downloadAttr = escapeHtml(`${attachment.file_name}.${attachment.file_extension}`);

            if (category === 'image') {
                return `
                    <div class="w-44 bg-zinc-800/70 border border-zinc-700 rounded-xl p-2">
                        <button
                            type="button"
                            class="js-lightbox-trigger block w-full"
                            data-image-url="${escapeHtml(attachment.url)}"
                            data-image-title="${escapeHtml(attachment.original_name)}"
                        >
                            <img src="${escapeHtml(attachment.url)}" alt="${escapeHtml(attachment.original_name)}" class="w-full h-24 object-cover rounded-lg border border-zinc-700" loading="lazy" decoding="async">
                        </button>
                        <div class="mt-2 text-xs text-zinc-400 flex items-center justify-between gap-2">
                            <span>${escapeHtml(formatFileSize(attachment.file_size))}</span>
                            <a href="${escapeHtml(attachment.url)}" download="${downloadAttr}" class="text-zinc-300 hover:text-zinc-100" title="Download">
                                <i class="fa-solid fa-download"></i>
                            </a>
                        </div>
                    </div>
                `;
            }

            return `
                <div class="w-44 bg-zinc-800/70 border border-zinc-700 rounded-xl p-2">
                    <a href="${escapeHtml(attachment.url)}" download="${downloadAttr}" class="block w-full">
                        <div class="w-full h-24 rounded-lg border border-zinc-700 bg-zinc-800 flex flex-col items-center justify-center gap-1.5">
                            <i class="fa-solid fa-file text-2xl text-zinc-400"></i>
                            <span class="text-xs font-mono font-semibold text-zinc-300 uppercase">.${escapeHtml(attachment.file_extension)}</span>
                        </div>
                    </a>
                    <div class="mt-2 text-xs text-zinc-400 flex items-center justify-between gap-2">
                        <span class="truncate" title="${escapeHtml(attachment.original_name)}">${escapeHtml(attachment.original_name)}</span>
                        <a href="${escapeHtml(attachment.url)}" download="${downloadAttr}" class="text-zinc-300 hover:text-zinc-100 shrink-0" title="Download">
                            <i class="fa-solid fa-download"></i>
                        </a>
                    </div>
                </div>
            `;
        });

    if (!cards.length) return '';
    return `<div class="mt-3 flex flex-wrap gap-3">${cards.join('')}</div>`;
}

function avatarColorClasses(stableValue) {
    const palette = [
        'bg-emerald-700 text-emerald-100',
        'bg-blue-700 text-blue-100',
        'bg-violet-700 text-violet-100',
        'bg-amber-700 text-amber-100',
        'bg-cyan-700 text-cyan-100',
        'bg-fuchsia-700 text-fuchsia-100',
        'bg-rose-700 text-rose-100',
        'bg-indigo-700 text-indigo-100'
    ];

    const source = String(stableValue ?? '');
    if (!source) return palette[0];

    const hash = crc32(source);
    const index = hash % palette.length;
    return palette[index];
}

let crc32Table = null;

function getCrc32Table() {
    if (crc32Table) return crc32Table;

    crc32Table = new Uint32Array(256);
    for (let i = 0; i < 256; i += 1) {
        let c = i;
        for (let j = 0; j < 8; j += 1) {
            c = (c & 1) ? (0xEDB88320 ^ (c >>> 1)) : (c >>> 1);
        }
        crc32Table[i] = c >>> 0;
    }

    return crc32Table;
}

function crc32(value) {
    const table = getCrc32Table();
    const bytes = new TextEncoder().encode(String(value));
    let crc = 0 ^ (-1);

    for (let i = 0; i < bytes.length; i += 1) {
        crc = (crc >>> 8) ^ table[(crc ^ bytes[i]) & 0xFF];
    }

    return (crc ^ (-1)) >>> 0;
}

function avatarInitial(username) {
    const value = String(username ?? '').trim();
    if (!value) return '?';
    return value.charAt(0).toUpperCase();
}

function renderAvatarMarkup(user, sizeClasses = 'w-10 h-10', textSizeClass = 'text-sm') {
    const avatarUrl = String(user?.avatar_url || '').trim();
    const username = String(user?.username || '');

    if (avatarUrl) {
        return `<img src="${escapeHtml(avatarUrl)}" alt="${escapeHtml(username)} avatar" class="${sizeClasses} rounded-full object-cover border border-zinc-700">`;
    }

    const stableValue = String(user?.user_number || user?.id || user?.user_id || '');
    return `<div class="${sizeClasses} rounded-full border border-zinc-700 flex items-center justify-center font-semibold ${textSizeClass} ${avatarColorClasses(stableValue)}">${escapeHtml(avatarInitial(username))}</div>`;
}

async function init() {
    bindGlobalCallBarInteractions();

    const chatView = document.getElementById('chat-view');
    if (chatView) {
        currentUserId = Number(chatView.dataset.currentUserId || 0);
        currentChat = {
            id: Number(chatView.dataset.chatId),
            chat_number: chatView.dataset.chatNumber,
            type: normalizeChatType(chatView.dataset.chatType),
            owner_user_id: Number(chatView.dataset.chatOwnerId || 0),
            personal_user_id: Number(chatView.dataset.personalUserId || 0),
            can_send_messages: String(chatView.dataset.canSendMessages || '1') === '1',
            message_restriction_reason: String(chatView.dataset.messageRestrictionReason || ''),
            can_start_calls: String(chatView.dataset.canStartCalls || '1') === '1'
        };

        // Extract usernames for call overlay labels
        localUsername = String(chatView.dataset.currentUsername || '');
        peerUsername = String(chatView.dataset.peerUsername || '');
        const localLabel = document.getElementById('local-username-label');
        if (localLabel) localLabel.textContent = localUsername || 'You';
        updateRemoteUsernameLabel();

        pendingAttachments = Array.isArray(window.PENDING_ATTACHMENTS)
            ? window.PENDING_ATTACHMENTS.map(normalizeAttachment).filter(Boolean)
            : [];
        initializeOpenMojiCatalog();
        applyEmojiRenderingToExistingMessages();
        applyInitialChatScrollPosition();
        bindEmojiDrawer();
        bindAttachmentsDrawer();
        bindAttachmentLightbox();
        bindAddUserModal();
        bindRenameChatModal();
        bindLeaveGroupModal();
        bindDeleteGroupModal();
        bindChatHeaderMenu();
        bindMessageQuotesAndReactions();
        setChatComposerEnabled(currentChat.can_send_messages !== false, currentChat.message_restriction_reason || '');
        setChatCallEnabled(currentChat.can_start_calls !== false);
        refreshChatCallStatusBar({ force: true });
        setInterval(pollMessages, 3000);
    }

    if (Number(currentUserId || 0) > 0) {
        initGlobalCallPersistence();
    }

    const messageForm = document.getElementById('message-form');
    if (messageForm) {
        messageForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            const input = document.getElementById('message-input');
            const quotedMessageInput = document.getElementById('quoted-message-id');
            const content = input?.value?.trim();
            if (!content || !currentChat) return;
            if (content.length > 16384) {
                showToast('Message exceeds the maximum length of 16,384 characters', 'error');
                return;
            }
            if (normalizeChatType(currentChat.type) === 'personal' && currentChat.can_send_messages === false) {
                showToast(getPersonalChatMessageRestrictionToast(currentChat.message_restriction_reason), 'error');
                return;
            }

            const result = await postForm('/api/messages', {
                csrf_token: getCsrfToken(),
                chat_id: String(currentChat.id),
                content,
                quoted_message_id: String(quotedMessageInput?.value || ''),
                attachment_ids: pendingAttachments.map((attachment) => attachment.id).join(',')
            });

            if (result.success) {
                input.value = '';
                pendingAttachments = [];
                renderPendingAttachmentList();
                clearQuoteDraft();
                typingActive = false;
                clearTypingStopTimer();
                sendTypingStatus(false).catch(() => {});
                await pollMessages({ scrollMode: 'bottom' });
                await loadSidebarChats();
            } else {
                showToast(result.error || 'Failed to send message', 'error');
            }
        });
    }

    const messageInput = document.getElementById('message-input');
    if (messageInput) {
        messageInput.addEventListener('input', handleMessageInputTyping);
        messageInput.addEventListener('blur', () => {
            if (!typingActive) return;
            typingActive = false;
            clearTypingStopTimer();
            sendTypingStatus(false).catch(() => {});
        });

        window.addEventListener('beforeunload', () => {
            if (!typingActive || !currentChat) return;

            const payload = new URLSearchParams({
                csrf_token: getCsrfToken(),
                chat_id: String(currentChat.id),
                is_typing: '0'
            });

            navigator.sendBeacon('/api/chats/typing', payload);
        });
    }

    const searchForm = document.getElementById('user-search-form');
    if (searchForm) {
        searchForm.addEventListener('submit', searchUsers);
    }

    bindInviteCopyButtons();
    bindUnfriendModal();
    bindProfilePosts();
    bindAdminUsersPage();
    bindReportModal();
    bindPageToast();
    bindTrashDeleteModal();
    bindStatusMenu();
    bindNotificationSettingsToggles();
    bindNotificationSoundPreviewButtons();
    bindBrowserPermissionChecks();

    const hasNotificationHistory = Boolean(document.getElementById('notification-history-button'));
    if (hasNotificationHistory) {
        if (window.BROWSER_NOTIFICATIONS_ENABLED && typeof Notification !== 'undefined' && Notification.permission === 'default') {
            Notification.requestPermission();
        }

        await fetchNotifications().catch(() => {});
        bindNotificationHistory();
        renderToastHistory();
        setInterval(fetchNotifications, 5000);
    }

    loadSidebarChats();
    setInterval(loadSidebarChats, 5000);

    bindSidebarToggle();
}

function bindTrashDeleteModal() {
    const modal = document.getElementById('trash-delete-modal');
    const form = document.getElementById('trash-delete-form');
    const cancel = document.getElementById('trash-delete-cancel');
    const submit = document.getElementById('trash-delete-submit');
    const chatIdInput = document.getElementById('trash-delete-chat-id');
    const description = document.getElementById('trash-delete-modal-description');
    const triggerButtons = Array.from(document.querySelectorAll('.js-trash-delete-open'));

    if (!modal || !form || !cancel || !submit || !chatIdInput || !description || triggerButtons.length === 0) return;

    const defaultDescription = 'Are you sure you want to permanently delete this chat and all associated data?';

    const setOpenState = (isOpen) => {
        modal.classList.toggle('hidden', !isOpen);

        if (!isOpen) {
            submit.disabled = false;
            submit.textContent = 'Delete Permanently';
            return;
        }

        submit.disabled = false;
        submit.textContent = 'Delete Permanently';
    };

    const closeModal = () => {
        setOpenState(false);
    };

    triggerButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const chatId = String(button.getAttribute('data-chat-id') || '').trim();
            const chatTitle = String(button.getAttribute('data-chat-title') || '').trim();

            if (!chatId) return;

            chatIdInput.value = chatId;
            description.textContent = chatTitle
                ? `Are you sure you want to permanently delete “${chatTitle}” and all associated data?`
                : defaultDescription;

            setOpenState(true);
        });
    });

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

    form.addEventListener('submit', () => {
        submit.disabled = true;
        submit.textContent = 'Deleting...';
    });
}

function bindSidebarToggle() {
    const sidebar = document.getElementById('app-sidebar');
    const toggleBtn = document.getElementById('sidebar-toggle-mobile');
    const backdrop = document.getElementById('mobile-overlay-backdrop');
    if (!sidebar || !toggleBtn || !backdrop) return;

    const isMobileLayout = () => window.innerWidth < 1024;

    const setSidebarOpen = (open) => {
        sidebar.classList.toggle('mobile-open', open);
        backdrop.classList.toggle('visible', open);
    };

    toggleBtn.addEventListener('click', () => {
        if (!isMobileLayout()) return;
        const isOpen = sidebar.classList.contains('mobile-open');
        // Close notification panel if open
        const notifPanel = document.getElementById('notification-history-panel');
        if (notifPanel) notifPanel.classList.remove('mobile-open');
        setSidebarOpen(!isOpen);
    });

    backdrop.addEventListener('click', () => {
        setSidebarOpen(false);
        // Also close the notification panel if it's open on mobile
        const notifPanel = document.getElementById('notification-history-panel');
        if (notifPanel) notifPanel.classList.remove('mobile-open');
    });
}

window.sendFriendRequest = sendFriendRequest;
window.sendFriendRequestByValue = sendFriendRequestByValue;
window.acceptFriendRequest = acceptFriendRequest;
window.cancelFriendRequest = cancelFriendRequest;
window.openUnfriendModal = openUnfriendModal;
window.toggleFavoriteUser = toggleFavoriteUser;
window.searchUsers = searchUsers;
window.toggleAdminUserMenu = toggleAdminUserMenu;
window.changeAdminUserGroup = changeAdminUserGroup;
window.confirmAdminUserRoleAction = confirmAdminUserRoleAction;
window.banAdminUser = banAdminUser;
window.confirmAdminUserBanAction = confirmAdminUserBanAction;
window.deleteAdminUser = deleteAdminUser;
window.createGroupChat = createGroupChat;
window.addGroupMemberByUsername = addGroupMemberByUsername;
window.removeGroupMember = removeGroupMember;
window.leaveCurrentGroup = leaveCurrentGroup;
window.deleteCurrentGroup = deleteCurrentGroup;
window.reportTarget = reportTarget;
window.startVoiceCall = startVoiceCall;
window.acceptCall = acceptCall;
window.declineCall = declineCall;
window.toggleMute = toggleMute;
window.toggleVideoInCall = toggleVideoInCall;
window.toggleScreenShare = toggleScreenShare;
window.startScreenShare = startScreenShare;
window.closeScreenShareModal = closeScreenShareModal;
window.endCall = endCall;
window.logout = logout;

let appInitStarted = false;

function startAppInitOnce() {
    if (appInitStarted) return;
    appInitStarted = true;
    init().catch(() => {});
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', startAppInitOnce, { once: true });
} else {
    startAppInitOnce();
}