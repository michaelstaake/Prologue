// Extracted from app.js for feature-focused organization.

const PENDING_PAGE_TOAST_STORAGE_KEY = 'pending_page_toast';

function queuePendingPageToast(message, kind = 'info') {
    const payload = {
        message: String(message || '').trim(),
        kind: String(kind || 'info').trim() || 'info'
    };

    if (!payload.message) return;

    try {
        sessionStorage.setItem(PENDING_PAGE_TOAST_STORAGE_KEY, JSON.stringify(payload));
    } catch {
        // Best effort only.
    }
}

function consumePendingPageToast() {
    try {
        const raw = sessionStorage.getItem(PENDING_PAGE_TOAST_STORAGE_KEY);
        if (!raw) return null;

        sessionStorage.removeItem(PENDING_PAGE_TOAST_STORAGE_KEY);

        const parsed = JSON.parse(raw);
        const message = String(parsed?.message || '').trim();
        if (!message) return null;

        return {
            message,
            kind: String(parsed?.kind || 'info').trim() || 'info'
        };
    } catch {
        return null;
    }
}

function normalizeNotificationIds(ids) {
    if (!Array.isArray(ids)) return [];

    const unique = new Set();
    for (const value of ids) {
        const id = Number(value);
        if (Number.isFinite(id) && id > 0) {
            unique.add(id);
        }
    }

    return Array.from(unique);
}

async function markNotificationsSeen(ids) {
    const normalizedIds = normalizeNotificationIds(ids);
    if (normalizedIds.length === 0) return;

    await postForm('/api/notifications/seen', {
        csrf_token: getCsrfToken(),
        ids: normalizedIds.join(',')
    });
}


function isNotificationHistoryExpanded() {
    return Boolean(window.NOTIFICATION_SIDEBAR_EXPANDED);
}

function syncToastHostPosition() {
    const host = document.getElementById('toast-host');
    if (!host) return;

    if (isNotificationHistoryExpanded()) {
        host.style.right = 'calc(min(95vw, 24rem) + 1.25rem)';
        return;
    }

    host.style.right = '1.25rem';
}

function clearActiveToastPopup(toastId) {
    const activeToast = activeToastPopups.get(toastId);
    if (!activeToast) return;

    if (activeToast.timeoutId) {
        clearTimeout(activeToast.timeoutId);
    }
    if (activeToast.element) {
        activeToast.element.remove();
    }
    activeToastPopups.delete(toastId);
}

function isLoggedInNotificationView() {
    const panel = document.getElementById('notification-history-panel');
    if (!panel) return false;
    if (window.innerWidth < 1024) {
        return panel.classList.contains('mobile-open');
    }
    return true;
}

function scheduleToastExpirySweep() {
    if (toastExpirySweepTimeoutId) {
        clearTimeout(toastExpirySweepTimeoutId);
        toastExpirySweepTimeoutId = null;
    }

    const now = Date.now();
    let nextExpiryAt = 0;

    for (const toast of toastHistory) {
        const expiresAt = Number(toast?.expiresAt || 0);
        if (!Number.isFinite(expiresAt) || expiresAt <= now) {
            nextExpiryAt = now;
            break;
        }
        if (nextExpiryAt === 0 || expiresAt < nextExpiryAt) {
            nextExpiryAt = expiresAt;
        }
    }

    if (nextExpiryAt === 0) return;

    toastExpirySweepTimeoutId = setTimeout(() => {
        pruneExpiredToasts();
    }, Math.max(0, nextExpiryAt - now));
}

function pruneExpiredToasts() {
    const now = Date.now();
    const activeToasts = [];
    const expiredToastIds = [];

    for (const toast of toastHistory) {
        const expiresAt = Number(toast?.expiresAt || 0);
        if (Number.isFinite(expiresAt) && expiresAt > 0 && expiresAt <= now) {
            expiredToastIds.push(toast.id);
            continue;
        }
        activeToasts.push(toast);
    }

    if (expiredToastIds.length > 0) {
        toastHistory = activeToasts;
        expiredToastIds.forEach((toastId) => clearActiveToastPopup(toastId));
        renderToastHistory();
    }

    scheduleToastExpirySweep();
}

function dismissVisibleNotificationToasts() {
    const idsToDismiss = [];
    for (const [toastId, activeToast] of activeToastPopups.entries()) {
        if (activeToast?.metadata?.notificationId) {
            idsToDismiss.push(toastId);
        }
    }

    idsToDismiss.forEach((toastId) => clearActiveToastPopup(toastId));
}

function renderTextWithOpenMojiMarkup(value) {
    const text = String(value ?? '');

    if (typeof renderPlainTextWithEmoji === 'function') {
        try {
            return renderPlainTextWithEmoji(text);
        } catch {
            // Fallback below.
        }
    }

    return escapeHtml(text).replace(/\n/g, '<br>');
}

function appendTextWithOpenMojiNodes(container, value) {
    if (!container) return;

    const source = String(value ?? '');
    const supportsGraphemeSegmentation = typeof getGraphemeClusters === 'function';
    const supportsOpenMojiLookup = typeof findOpenMojiForGrapheme === 'function';

    const appendPlainTextSegment = (segment) => {
        if (!segment) return;

        const graphemes = supportsGraphemeSegmentation
            ? getGraphemeClusters(String(segment ?? ''))
            : Array.from(String(segment ?? ''));

        for (const grapheme of graphemes) {
            if (grapheme === '\n') {
                container.appendChild(document.createElement('br'));
                continue;
            }

            if (supportsOpenMojiLookup) {
                const emojiMatch = findOpenMojiForGrapheme(grapheme);
                if (emojiMatch?.url) {
                    const img = document.createElement('img');
                    img.src = String(emojiMatch.url);
                    img.alt = grapheme;
                    img.className = 'inline-block w-7 h-7 align-[-0.2em] mx-[1px]';
                    img.loading = 'lazy';
                    img.decoding = 'async';
                    container.appendChild(img);
                    continue;
                }
            }

            container.appendChild(document.createTextNode(grapheme));
        }
    };

    const linkPattern = /(https?:\/\/[^\s<]+|\/c\/\d{4}-\d{4}-\d{4}-\d{4}\/delete)/gi;
    let cursor = 0;
    let match = linkPattern.exec(source);

    while (match) {
        const tokenStart = match.index;
        if (tokenStart > cursor) {
            appendPlainTextSegment(source.slice(cursor, tokenStart));
        }

        const matchedUrl = String(match[0] || '');
        const deleteChatPattern = /^\/c\/\d{4}-\d{4}-\d{4}-\d{4}\/delete$/i;
        const anchor = document.createElement('a');
        anchor.href = matchedUrl;

        if (deleteChatPattern.test(matchedUrl)) {
            anchor.className = 'text-red-400 hover:text-red-300 hover:underline underline-offset-2';
            anchor.textContent = 'Delete chat';
        } else {
            anchor.className = 'prologue-accent hover:text-emerald-300 hover:underline underline-offset-2';
            anchor.target = '_blank';
            anchor.rel = 'noopener noreferrer';
            anchor.textContent = matchedUrl;
        }

        container.appendChild(anchor);
        cursor = linkPattern.lastIndex;
        match = linkPattern.exec(source);
    }

    if (cursor < source.length) {
        appendPlainTextSegment(source.slice(cursor));
    }
}

function showToast(message, kind = 'info', metadata = null) {
    const host = document.getElementById('toast-host');
    if (!host) return;

    syncToastHostPosition();

    const toastId = toastHistoryNextId++;
    const excludeFromHistory = Boolean(metadata?.excludeFromHistory);
    const explicitPersistent = metadata?.persistent === true;
    const explicitTemporary = metadata?.temporary === true;
    const hasNotificationId = Boolean(metadata?.notificationId);
    const defaultDurationMs = 4000;
    const providedDurationMs = Number(metadata?.durationMs);
    const durationMs = Number.isFinite(providedDurationMs) && providedDurationMs > 0
        ? Math.trunc(providedDurationMs)
        : defaultDurationMs;
    const isTemporary = explicitTemporary || (!explicitPersistent && !hasNotificationId);
    const createdAt = Date.now();
    const expiresAt = isTemporary ? (createdAt + durationMs) : null;

    if (!excludeFromHistory) {
        toastHistory.unshift({
            id: toastId,
            message,
            kind,
            createdAt,
            isTemporary,
            expiresAt,
            metadata
        });
        if (toastHistory.length > MAX_TOAST_HISTORY) {
            toastHistory = toastHistory.slice(0, MAX_TOAST_HISTORY);
        }
        renderToastHistory();
        scheduleToastExpirySweep();
    }

    if (isLoggedInNotificationView()) {
        return;
    }

    const isNotificationToast = Boolean(metadata?.notificationId);
    if (isNotificationToast && isNotificationHistoryExpanded()) {
        return;
    }

    const el = document.createElement('div');
    const color = kind === 'error' ? 'border-red-700 text-red-200 bg-red-950/80' : kind === 'success' ? 'border-emerald-700 text-emerald-100 bg-emerald-950/80' : 'border-zinc-700 text-zinc-100 bg-zinc-900/95';
    el.className = `border ${color} px-4 py-3 rounded-xl shadow-xl`;
    appendTextWithOpenMojiNodes(el, message);
    host.appendChild(el);

    const timeoutId = setTimeout(() => {
        clearActiveToastPopup(toastId);
    }, durationMs);

    activeToastPopups.set(toastId, {
        element: el,
        timeoutId,
        metadata
    });
}

function formatCountBadgeValue(value) {
    const safe = Math.max(0, Number(value || 0));
    if (!Number.isFinite(safe)) return '0';
    return safe > 99 ? '99+' : String(Math.trunc(safe));
}

function getBaseDocumentTitle() {
    if (baseDocumentTitle) return baseDocumentTitle;

    const currentTitle = String(document.title || '').trim();
    baseDocumentTitle = currentTitle.replace(/^\(\d+\+?\)\s*/, '').trim() || 'Prologue';
    return baseDocumentTitle;
}

function updateNotificationCountInTitle(count) {
    const safeCount = Math.max(0, Number(count || 0));
    const titleBase = getBaseDocumentTitle();

    if (!Number.isFinite(safeCount) || safeCount <= 0) {
        document.title = titleBase;
        return;
    }

    document.title = `(${formatCountBadgeValue(safeCount)}) ${titleBase}`;
}

function setCountBadgeState(badge, count) {
    if (!badge) return;

    const safeCount = Math.max(0, Number(count || 0));
    if (!Number.isFinite(safeCount) || safeCount <= 0) {
        badge.textContent = '0';
        badge.classList.add('hidden');
        return;
    }

    badge.textContent = formatCountBadgeValue(safeCount);
    badge.classList.remove('hidden');
}

function updateFriendRequestBadges(count) {
    setCountBadgeState(document.getElementById('sidebar-friends-request-badge'), count);
    setCountBadgeState(document.getElementById('friends-requests-tab-badge'), count);
}

function updateMobileNotificationState() {
    const mobileBadge = document.getElementById('notification-history-count-mobile');
    const mobileButton = document.getElementById('notification-history-button-mobile');
    const count = Math.max(unreadNotificationCount, toastHistory.length);

    if (mobileBadge) {
        setCountBadgeState(mobileBadge, count);
    }
    if (mobileButton) {
        if (count > 0) {
            mobileButton.disabled = false;
            mobileButton.classList.remove('opacity-30', 'pointer-events-none');
        } else {
            mobileButton.disabled = true;
            mobileButton.classList.add('opacity-30', 'pointer-events-none');
        }
    }
}


function applyNotificationSettingState(setting, enabled) {
    const isEnabled = Boolean(enabled);
    if (setting === 'browser_notifications') {
        window.BROWSER_NOTIFICATIONS_ENABLED = isEnabled;
        return;
    }
    if (setting === 'sound_friend_request') {
        window.NOTIFICATION_SOUND_FRIEND_REQUEST_ENABLED = isEnabled;
        return;
    }
    if (setting === 'sound_new_message') {
        window.NOTIFICATION_SOUND_NEW_MESSAGE_ENABLED = isEnabled;
        return;
    }
    if (setting === 'sound_other_notifications') {
        window.NOTIFICATION_SOUND_OTHER_ENABLED = isEnabled;
        return;
    }
    if (setting === 'sound_outgoing_call_ring') {
        window.NOTIFICATION_SOUND_OUTGOING_CALL_RING_ENABLED = isEnabled;
    }
}

function setNotificationSettingsStatus(text, kind = 'info') {
    const statusEl = document.getElementById('notification-settings-status');
    if (!statusEl) return;

    const kindClassMap = {
        info: 'text-zinc-500',
        success: 'text-emerald-400',
        error: 'text-red-400'
    };

    statusEl.classList.remove('text-zinc-500', 'text-emerald-400', 'text-red-400');
    statusEl.classList.add(kindClassMap[kind] || kindClassMap.info);
    statusEl.textContent = text;
}

function bindNotificationSettingsToggles() {
    const toggleNodes = Array.from(document.querySelectorAll('[data-notification-setting]'));
    if (toggleNodes.length === 0) return;

    toggleNodes.forEach((toggleNode) => {
        toggleNode.addEventListener('change', async () => {
            const setting = String(toggleNode.dataset.notificationSetting || '').trim();
            if (!setting) return;

            const enabled = Boolean(toggleNode.checked);
            const previousEnabled = !enabled;

            toggleNode.disabled = true;
            setNotificationSettingsStatus('Saving…', 'info');

            try {
                const result = await postForm('/settings/notifications', {
                    csrf_token: getCsrfToken(),
                    setting,
                    enabled: enabled ? '1' : '0'
                });

                if (!result.success) {
                    throw new Error(result.error || 'Unable to save notification setting');
                }

                applyNotificationSettingState(setting, enabled);
                if (setting === 'browser_notifications' && enabled && typeof Notification !== 'undefined' && Notification.permission === 'default') {
                    Notification.requestPermission();
                }
                setNotificationSettingsStatus('Saved', 'success');
            } catch (error) {
                toggleNode.checked = previousEnabled;
                setNotificationSettingsStatus('Failed to save. Please try again.', 'error');
                showToast(error.message || 'Unable to save notification setting', 'error');
            } finally {
                toggleNode.disabled = false;
            }
        });
    });
}

function getNotificationSoundBucket(notificationOrType) {
    const notification = notificationOrType && typeof notificationOrType === 'object'
        ? notificationOrType
        : null;
    const normalizedType = String(notification?.type || notificationOrType || '').trim().toLowerCase();
    const normalizedTitle = String(notification?.title || '').trim().toLowerCase();

    if (normalizedType === 'call' && normalizedTitle === 'call declined') {
        return 'other';
    }

    if (normalizedType === 'friend_request') return 'friend_request';
    if (normalizedType === 'message') return 'new_message';
    if (normalizedType === 'call') return 'call';
    return 'other';
}

function isNotificationSoundEnabled(bucket) {
    if (bucket === 'friend_request') {
        return Boolean(window.NOTIFICATION_SOUND_FRIEND_REQUEST_ENABLED);
    }
    if (bucket === 'new_message') {
        return Boolean(window.NOTIFICATION_SOUND_NEW_MESSAGE_ENABLED);
    }
    if (bucket === 'call') {
        return Boolean(window.NOTIFICATION_SOUND_OTHER_ENABLED);
    }
    return Boolean(window.NOTIFICATION_SOUND_OTHER_ENABLED);
}

function shouldSuppressNotificationSoundPlayback() {
    if (typeof shouldSuppressNotificationSoundsDuringCall !== 'function') {
        return false;
    }

    try {
        return Boolean(shouldSuppressNotificationSoundsDuringCall());
    } catch {
        return false;
    }
}

function shouldSuppressBrowserNotificationPlayback() {
    if (typeof shouldSuppressNotificationSoundsDuringCall !== 'function') {
        return false;
    }

    try {
        return Boolean(shouldSuppressNotificationSoundsDuringCall());
    } catch {
        return false;
    }
}

function stopAllNotificationSounds() {
    notificationSoundAudioByBucket.forEach((audio) => {
        if (!audio) return;
        audio.pause();
        audio.currentTime = 0;
    });
}

function playNotificationSoundBucket(bucket) {
    if (shouldSuppressNotificationSoundPlayback()) {
        stopAllNotificationSounds();
        return;
    }

    if (
        bucket === 'call'
        && typeof callRingingAudio !== 'undefined'
        && callRingingAudio
        && !callRingingAudio.paused
    ) {
        return;
    }

    const soundFile = NOTIFICATION_SOUND_FILE_BY_BUCKET[bucket] || NOTIFICATION_SOUND_FILE_BY_BUCKET.other;
    if (!soundFile) return;

    let audio = notificationSoundAudioByBucket.get(bucket);
    if (!audio) {
        audio = new Audio(soundFile);
        audio.preload = 'auto';
        notificationSoundAudioByBucket.set(bucket, audio);
    }

    audio.currentTime = 0;
    audio.play().catch(() => {});
}

function playNotificationSoundForType(notificationOrType) {
    const bucket = getNotificationSoundBucket(notificationOrType);
    if (!isNotificationSoundEnabled(bucket)) return;
    playNotificationSoundBucket(bucket);
}

function bindNotificationSoundPreviewButtons() {
    const previewButtons = Array.from(document.querySelectorAll('[data-notification-sound-preview]'));
    if (previewButtons.length === 0) return;

    let activeButton = null;
    let activeAudio = null;

    function stopActive() {
        if (activeAudio) {
            activeAudio.pause();
            activeAudio.currentTime = 0;
        }
        if (activeButton) {
            activeButton.textContent = 'Preview';
        }
        activeButton = null;
        activeAudio = null;
    }

    previewButtons.forEach((previewButton) => {
        previewButton.addEventListener('click', () => {
            if (shouldSuppressNotificationSoundPlayback()) {
                stopActive();
                return;
            }

            const bucket = String(previewButton.dataset.notificationSoundPreview || '').trim();
            if (!bucket) return;

            if (activeButton === previewButton) {
                stopActive();
                return;
            }

            stopActive();

            const soundFile = NOTIFICATION_SOUND_FILE_BY_BUCKET[bucket] || NOTIFICATION_SOUND_FILE_BY_BUCKET.other;
            if (!soundFile) return;

            let audio = notificationSoundAudioByBucket.get(bucket);
            if (!audio) {
                audio = new Audio(soundFile);
                audio.preload = 'auto';
                notificationSoundAudioByBucket.set(bucket, audio);
            }

            audio.currentTime = 0;
            audio.play().catch(() => {});

            activeButton = previewButton;
            activeAudio = audio;
            previewButton.textContent = 'Pause';

            audio.addEventListener('ended', function onEnded() {
                audio.removeEventListener('ended', onEnded);
                if (activeButton === previewButton) {
                    stopActive();
                }
            });
        });
    });
}

function setBrowserPermissionStatus(type, label, kind = 'info') {
    const statusNode = document.querySelector(`[data-browser-permission-status="${type}"]`);
    if (!statusNode) return;

    statusNode.textContent = label;
    statusNode.classList.remove(
        'border-zinc-600',
        'bg-zinc-700/50',
        'text-zinc-200',
        'border-emerald-500/40',
        'bg-emerald-700/30',
        'text-emerald-100',
        'border-amber-500/40',
        'bg-amber-700/30',
        'text-amber-100',
        'border-red-500/40',
        'bg-red-700/30',
        'text-red-100'
    );

    if (kind === 'granted') {
        statusNode.classList.add('border-emerald-500/40', 'bg-emerald-700/30', 'text-emerald-100');
        return;
    }

    if (kind === 'prompt') {
        statusNode.classList.add('border-amber-500/40', 'bg-amber-700/30', 'text-amber-100');
        return;
    }

    if (kind === 'denied') {
        statusNode.classList.add('border-red-500/40', 'bg-red-700/30', 'text-red-100');
        return;
    }

    statusNode.classList.add('border-zinc-600', 'bg-zinc-700/50', 'text-zinc-200');
}

function setBrowserPermissionTestButtonState(type, kind = 'info') {
    const buttonNode = document.querySelector(`[data-browser-permission-test="${type}"]`);
    if (!buttonNode) return;

    const shouldShow = kind === 'prompt' || kind === 'denied';
    buttonNode.classList.toggle('hidden', !shouldShow);
}

async function queryBrowserPermissionState(permissionName) {
    if (!navigator?.permissions || typeof navigator.permissions.query !== 'function') {
        return null;
    }

    try {
        const permissionStatus = await navigator.permissions.query({ name: permissionName });
        return String(permissionStatus?.state || '').trim().toLowerCase() || null;
    } catch {
        return null;
    }
}

async function getSoundPlaybackPermissionState() {
    const testSource = NOTIFICATION_SOUND_FILE_BY_BUCKET.other;
    if (!testSource) {
        return { kind: 'info', label: 'Unavailable' };
    }

    try {
        const audio = new Audio(testSource);
        audio.preload = 'none';
        audio.muted = true;
        audio.volume = 0;

        const playResult = audio.play();
        if (playResult && typeof playResult.then === 'function') {
            await playResult;
        }

        audio.pause();
        return { kind: 'granted', label: 'Allowed' };
    } catch (error) {
        const errorName = String(error?.name || '').trim();
        if (errorName === 'NotAllowedError') {
            return { kind: 'prompt', label: 'Needs interaction' };
        }
        return { kind: 'info', label: 'Unknown' };
    }
}

function stopStreamTracks(stream) {
    if (!stream || typeof stream.getTracks !== 'function') return;

    const tracks = stream.getTracks();
    tracks.forEach((track) => {
        try {
            track.stop();
        } catch {
            // ignore
        }
    });
}

function mapPermissionStateToBadge(state) {
    if (state === 'granted') {
        return { kind: 'granted', label: 'Allowed' };
    }
    if (state === 'prompt') {
        return { kind: 'prompt', label: 'Ask first' };
    }
    if (state === 'denied') {
        return { kind: 'denied', label: 'Blocked' };
    }
    return { kind: 'info', label: 'Unknown' };
}

async function updateBrowserPermissionsStatuses() {
    const sectionNode = document.getElementById('browser-permissions-list');
    if (!sectionNode) return;

    const soundState = await getSoundPlaybackPermissionState();
    setBrowserPermissionStatus('sound', soundState.label, soundState.kind);
    setBrowserPermissionTestButtonState('sound', soundState.kind);

    const hasMediaDevices = Boolean(navigator?.mediaDevices);
    const hasUserMedia = hasMediaDevices && typeof navigator.mediaDevices.getUserMedia === 'function';
    if (!hasUserMedia) {
        setBrowserPermissionStatus('camera', 'Unavailable', 'info');
        setBrowserPermissionTestButtonState('camera', 'info');
        setBrowserPermissionStatus('microphone', 'Unavailable', 'info');
        setBrowserPermissionTestButtonState('microphone', 'info');
    } else {
        const cameraState = await queryBrowserPermissionState('camera');
        const microphoneState = await queryBrowserPermissionState('microphone');
        const cameraBadge = mapPermissionStateToBadge(cameraState);
        const microphoneBadge = mapPermissionStateToBadge(microphoneState);
        setBrowserPermissionStatus('camera', cameraBadge.label, cameraBadge.kind);
        setBrowserPermissionTestButtonState('camera', cameraBadge.kind);
        setBrowserPermissionStatus('microphone', microphoneBadge.label, microphoneBadge.kind);
        setBrowserPermissionTestButtonState('microphone', microphoneBadge.kind);
    }

}

async function testBrowserPermission(type) {
    const permissionType = String(type || '').trim();
    if (!permissionType) return;

    if (permissionType === 'sound') {
        await getSoundPlaybackPermissionState();
        return;
    }

    if (!navigator?.mediaDevices) {
        throw new Error('Media devices are unavailable in this browser.');
    }

    if (permissionType === 'camera') {
        if (typeof navigator.mediaDevices.getUserMedia !== 'function') {
            throw new Error('Camera access is not supported in this browser.');
        }
        const stream = await navigator.mediaDevices.getUserMedia({ video: true });
        stopStreamTracks(stream);
        return;
    }

    if (permissionType === 'microphone') {
        if (typeof navigator.mediaDevices.getUserMedia !== 'function') {
            throw new Error('Microphone access is not supported in this browser.');
        }
        const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
        stopStreamTracks(stream);
        return;
    }

}

function bindBrowserPermissionChecks() {
    const sectionNode = document.getElementById('browser-permissions-list');
    if (!sectionNode) return;

    const testButtons = Array.from(document.querySelectorAll('[data-browser-permission-test]'));
    testButtons.forEach((buttonNode) => {
        buttonNode.addEventListener('click', async () => {
            const type = String(buttonNode.dataset.browserPermissionTest || '').trim();
            if (!type) return;

            const previousText = buttonNode.textContent;
            buttonNode.disabled = true;
            buttonNode.textContent = 'Testing…';

            try {
                await testBrowserPermission(type);
            } catch (error) {
                const errorName = String(error?.name || '').trim();
                const isDenied = errorName === 'NotAllowedError' || errorName === 'PermissionDeniedError';
                if (!isDenied) {
                    showToast(error.message || 'Unable to test permission', 'error');
                }
            } finally {
                await updateBrowserPermissionsStatuses().catch(() => {});
                buttonNode.disabled = false;
                buttonNode.textContent = previousText || 'Test';
            }
        });
    });

    updateBrowserPermissionsStatuses().catch(() => {
        setBrowserPermissionStatus('sound', 'Unknown', 'info');
        setBrowserPermissionTestButtonState('sound', 'info');
        setBrowserPermissionStatus('camera', 'Unknown', 'info');
        setBrowserPermissionTestButtonState('camera', 'info');
        setBrowserPermissionStatus('microphone', 'Unknown', 'info');
        setBrowserPermissionTestButtonState('microphone', 'info');
    });
}

async function fetchNotifications() {
    const res = await fetch('/notifications');
    const data = await res.json();
    if (data.csrf_token) {
        window.CSRF_TOKEN = data.csrf_token;
        const meta = document.querySelector('meta[name="csrf-token"]');
        if (meta) meta.setAttribute('content', data.csrf_token);
    }
    const notifications = data.notifications || [];
    const serverSeenIds = new Set(normalizeNotificationIds(data.seen_ids || []));
    unreadNotificationCount = notifications.reduce((total, notification) => {
        const notificationId = Number(notification.id);
        const isUnread = Number(notification.read) === 0;
        if (!Number.isFinite(notificationId) || notificationId <= 0) return total;
        if (!isUnread || serverSeenIds.has(notificationId)) return total;
        return total + 1;
    }, 0);
    updateNotificationCountInTitle(unreadNotificationCount);
    updateMobileNotificationState();
    const incomingFriendRequestCount = Number(data.incoming_friend_request_count);
    if (Number.isFinite(incomingFriendRequestCount) && incomingFriendRequestCount >= 0) {
        updateFriendRequestBadges(incomingFriendRequestCount);
    }
    seenNotificationIds = serverSeenIds;
    const newlySeenIds = [];

    // Track which notification IDs are already in toastHistory
    const existingNotificationIds = new Set();
    for (const toast of toastHistory) {
        const nid = Number(toast?.metadata?.notificationId || 0);
        if (nid > 0) existingNotificationIds.add(nid);
    }

    for (const notification of notifications) {
        const notificationId = Number(notification.id);
        if (!Number.isFinite(notificationId) || notificationId <= 0) continue;

        if (!seenNotificationIds.has(notificationId) && Number(notification.read) === 0) {
            seenNotificationIds.add(notificationId);
            newlySeenIds.push(notificationId);
            playNotificationSoundForType(notification);
            if (shouldSuppressNotificationToast(notification)) {
                continue;
            }
            showToast(notification.title + ': ' + notification.message, 'info', {
                notificationId,
                type: String(notification.type || ''),
                title: String(notification.title || ''),
                link: notification.link ? String(notification.link) : null,
                persistent: true
            });
            if (
                window.BROWSER_NOTIFICATIONS_ENABLED
                && !shouldSuppressBrowserNotificationPlayback()
                && typeof Notification !== 'undefined'
                && Notification.permission === 'granted'
            ) {
                new Notification(notification.title, { body: notification.message });
            }
        } else if (Number(notification.read) === 0 && !existingNotificationIds.has(notificationId)) {
            // Restore unread but already-seen notifications into toast history
            // so they persist across page reloads
            const toastId = toastHistoryNextId++;
            toastHistory.push({
                id: toastId,
                message: notification.title + ': ' + notification.message,
                kind: 'info',
                createdAt: notification.created_at || Date.now(),
                isTemporary: false,
                expiresAt: null,
                metadata: {
                    notificationId,
                    type: String(notification.type || ''),
                    title: String(notification.title || ''),
                    link: notification.link ? String(notification.link) : null,
                    persistent: true
                }
            });
            existingNotificationIds.add(notificationId);
        }
    }

    if (toastHistory.length > MAX_TOAST_HISTORY) {
        toastHistory = toastHistory.slice(0, MAX_TOAST_HISTORY);
    }
    renderToastHistory();

    if (newlySeenIds.length > 0) {
        await markNotificationsSeen(newlySeenIds);
    }
}


function bindPageToast() {
    const toastData = document.getElementById('page-toast');
    const queuedToast = consumePendingPageToast();
    if (queuedToast) {
        showToast(queuedToast.message, queuedToast.kind, { temporary: true });
    }

    if (!toastData) return;

    const message = (toastData.dataset.toastMessage || '').trim();
    const kind = (toastData.dataset.toastKind || 'info').trim();
    if (!message) return;

    showToast(message, kind, { temporary: true });
}

function formatToastTime(timestamp) {
    if (typeof formatCompactMessageTimestamp === 'function') {
        return formatCompactMessageTimestamp(timestamp);
    }

    try {
        const parsed = new Date(timestamp);
        return isNaN(parsed.getTime()) ? '' : parsed.toLocaleString();
    } catch {
        return '';
    }
}

function getNotificationIconClass(toast) {
    const meta = toast?.metadata;
    const type = String(meta?.type || '').toLowerCase();
    const title = String(meta?.title || '').toLowerCase();

    if (type === 'message') return 'fa-solid fa-message';
    if (type === 'call' || title === 'incoming call') return 'fa-solid fa-phone';
    if (type === 'friend_request' || title === 'friend request'
        || type === 'friend_request_accepted' || title === 'friend request accepted') return 'fa-solid fa-user-plus';
    if ((type === 'report' && title === 'update available') || title === 'update available') return 'fa-solid fa-arrow-up-from-bracket';

    return 'fa-solid fa-bell';
}

function renderNotificationIconPreview() {
    const preview = document.getElementById('notification-icon-preview');
    if (!preview) return;

    if (isNotificationHistoryExpanded() || window.innerWidth < 1024) {
        preview.classList.add('hidden');
        return;
    }

    const now = Date.now();
    const visibleToasts = toastHistory.filter(toast => {
        const expiresAt = Number(toast?.expiresAt || 0);
        return !(Number.isFinite(expiresAt) && expiresAt > 0 && expiresAt <= now);
    });

    if (visibleToasts.length === 0) {
        preview.classList.add('hidden');
        preview.innerHTML = '';
        return;
    }

    preview.classList.remove('hidden');
    preview.innerHTML = visibleToasts.map(toast => {
        const iconClass = getNotificationIconClass(toast);
        const color = toast.kind === 'error' ? 'text-red-300' : toast.kind === 'success' ? 'text-emerald-300' : 'text-zinc-400';
        return `<div class="flex items-center justify-center border-b border-zinc-800 notification-icon-row"><i class="${iconClass} ${color} text-base"></i></div>`;
    }).join('');
}

function renderToastHistory() {
    const list = document.getElementById('notification-history-list');
    const badge = document.getElementById('notification-history-count');
    const now = Date.now();
    const visibleToasts = [];
    const expiredToastIds = [];

    for (const toast of toastHistory) {
        const expiresAt = Number(toast?.expiresAt || 0);
        if (Number.isFinite(expiresAt) && expiresAt > 0 && expiresAt <= now) {
            expiredToastIds.push(toast.id);
            continue;
        }
        visibleToasts.push(toast);
    }

    if (expiredToastIds.length > 0) {
        toastHistory = visibleToasts;
        expiredToastIds.forEach((toastId) => clearActiveToastPopup(toastId));
    }

    if (badge) {
        if (visibleToasts.length > 0) {
            badge.textContent = String(Math.min(visibleToasts.length, 99));
            badge.classList.remove('hidden');
        } else {
            badge.classList.add('hidden');
        }
    }

    updateMobileNotificationState();

    const clearBtn = document.getElementById('notification-clear-all-btn');
    if (clearBtn) {
        if (visibleToasts.length > 0 && isNotificationHistoryExpanded()) {
            clearBtn.classList.remove('hidden');
        } else {
            clearBtn.classList.add('hidden');
        }
    }

    if (!list) return;

    list.innerHTML = visibleToasts.map((toast, index) => {
        const color = toast.kind === 'error' ? 'text-red-300' : toast.kind === 'success' ? 'text-emerald-300' : 'text-zinc-300';
        const action = getNotificationAction(toast);
        const isClickable = Boolean(action?.href);
        const isTemporary = Boolean(toast.isTemporary);
        const expiresAt = Number(toast?.expiresAt || 0);
        const remainingMs = isTemporary && Number.isFinite(expiresAt)
            ? Math.max(0, expiresAt - now)
            : 0;
        const expiryBorder = isTemporary
            ? `<div class="absolute bottom-0 left-0 h-0.5 bg-zinc-500 origin-left" style="width:100%; animation: notification-expire-bar ${remainingMs}ms linear forwards;"></div>`
            : '';
        const createdAt = String(toast.createdAt || '');
        return `
            <div class="relative px-4 py-3 border-b border-zinc-800 ${isClickable ? 'hover:bg-zinc-800 cursor-pointer' : ''}" data-toast-id="${toast.id}" ${isClickable ? `data-toast-link="${escapeHtml(action.href)}"` : ''}>
                <div class="text-sm ${color}" data-toast-message-index="${index}"></div>
                <div class="text-xs text-zinc-500 mt-1" data-utc="${escapeHtml(createdAt)}" title="${escapeHtml(createdAt)}">${escapeHtml(formatToastTime(createdAt))}</div>
                ${expiryBorder}
            </div>
        `;
    }).join('') || '<div class="px-4 py-3 text-zinc-400 text-sm">No notifications yet</div>';

    const messageNodes = list.querySelectorAll('[data-toast-message-index]');
    messageNodes.forEach((node) => {
        const index = Number(node.getAttribute('data-toast-message-index'));
        if (!Number.isFinite(index) || index < 0 || index >= visibleToasts.length) return;

        appendTextWithOpenMojiNodes(node, visibleToasts[index].message);
        node.removeAttribute('data-toast-message-index');
    });

    if (typeof window.refreshUtcTimestamps === 'function') {
        window.refreshUtcTimestamps(list);
    }

    scheduleToastExpirySweep();
    renderNotificationIconPreview();
}

function getNotificationAction(toast) {
    const meta = toast?.metadata;
    if (!meta || !meta.link) return null;

    const type = String(meta.type || '').toLowerCase();
    const title = String(meta.title || '').toLowerCase();

    if (type === 'message') {
        return { href: meta.link };
    }

    if (type === 'call' || title === 'incoming call') {
        return { href: meta.link };
    }

    if (type === 'friend_request' || title === 'friend request') {
        return { href: meta.link };
    }

    if (type === 'friend_request_accepted' || title === 'friend request accepted') {
        return { href: meta.link };
    }

    if (type === 'report' && title === 'update available' && String(meta.link).includes('github.com/michaelstaake/Prologue/releases')) {
        return { href: meta.link, openInNewTab: true };
    }

    if (type === 'report' || title === 'new report') {
        return { href: meta.link };
    }

    return null;
}

async function handleToastHistoryClick(event) {
    const row = event.target.closest('[data-toast-id]');
    if (!row) return;

    const toastId = Number(row.dataset.toastId || 0);
    if (!toastId) return;

    const clickedToast = toastHistory.find(item => item.id === toastId);
    if (!clickedToast) return;

    const action = getNotificationAction(clickedToast);

    toastHistory = toastHistory.filter(item => item.id !== toastId);
    renderToastHistory();
    clearActiveToastPopup(toastId);

    if (clickedToast.metadata?.notificationId) {
        const result = await postForm('/api/notifications/read', {
            csrf_token: getCsrfToken(),
            id: String(clickedToast.metadata.notificationId)
        });
        if (result.success) {
            unreadNotificationCount = Math.max(0, unreadNotificationCount - 1);
            updateNotificationCountInTitle(unreadNotificationCount);
            updateMobileNotificationState();
        }
    }

    if (action?.href) {
        // Close notification panel before navigating
        if (typeof window.collapseNotificationPanel === 'function') {
            window.collapseNotificationPanel();
        }

        if (action.openInNewTab) {
            window.open(action.href, '_blank', 'noopener,noreferrer');
        } else {
            const targetUrl = new URL(action.href, window.location.origin);
            if (targetUrl.origin === window.location.origin && typeof window.navigateApp === 'function') {
                window.navigateApp(targetUrl.pathname + targetUrl.search + targetUrl.hash, {
                    source: 'notification-click',
                    updateHistory: true
                }).catch(() => {
                    window.location.href = action.href;
                });
            } else {
                window.location.href = action.href;
            }
        }
    }
}

function bindNotificationHistory() {
    const button = document.getElementById('notification-history-button');
    const mobileButton = document.getElementById('notification-history-button-mobile');
    const panel = document.getElementById('notification-history-panel');
    const list = document.getElementById('notification-history-list');
    const title = document.getElementById('notification-history-title');
    const header = document.getElementById('notification-history-header');
    if (!button || !panel) return;
    if (button.dataset.bound === '1') return;
    button.dataset.bound = '1';

    const isMobileLayout = () => window.innerWidth < 1024;

    const setExpanded = (expanded) => {
        if (isMobileLayout()) {
            panel.classList.toggle('mobile-open', expanded);
            // Close sidebar if open, then show/hide shared backdrop
            const sidebar = document.getElementById('app-sidebar');
            if (sidebar) sidebar.classList.remove('mobile-open');
            const backdrop = document.getElementById('mobile-overlay-backdrop');
            if (backdrop) backdrop.classList.toggle('visible', expanded);
        } else {
            panel.classList.toggle('w-20', !expanded);
            panel.classList.toggle('w-96', expanded);
            panel.classList.toggle('max-w-[95vw]', expanded);
        }
        if (list) {
            list.classList.toggle('hidden', !expanded);
        }
        if (title) {
            title.classList.toggle('hidden', !expanded);
        }
        const clearBtn = document.getElementById('notification-clear-all-btn');
        if (clearBtn) {
            clearBtn.classList.toggle('hidden', !expanded || toastHistory.length === 0);
        }
        if (header) {
            header.classList.toggle('justify-center', !expanded);
            header.classList.toggle('justify-between', expanded);
        }

        const iconPreview = document.getElementById('notification-icon-preview');
        if (iconPreview) {
            iconPreview.classList.toggle('hidden', expanded || isMobileLayout());
        }

        syncToastHostPosition();
    };

    let expanded = Boolean(window.NOTIFICATION_SIDEBAR_EXPANDED);
    if (toastHistory.length === 0 || isMobileLayout()) {
        expanded = false;
    }
    window.NOTIFICATION_SIDEBAR_EXPANDED = expanded;
    setExpanded(expanded);

    const handleToggle = () => {
        expanded = !expanded;
        setExpanded(expanded);
        window.NOTIFICATION_SIDEBAR_EXPANDED = expanded;

        if (expanded) {
            dismissVisibleNotificationToasts();
        }

        postForm('/api/notifications/sidebar-state', {
            csrf_token: getCsrfToken(),
            expanded: expanded ? '1' : '0'
        }).catch(() => {});
    };

    button.addEventListener('click', handleToggle);

    if (mobileButton) {
        mobileButton.addEventListener('click', handleToggle);
    }

    document.addEventListener('click', (event) => {
        if (!expanded || toastHistory.length > 0) return;

        const target = event.target;
        if (!(target instanceof Element)) return;

        if (panel.contains(target)) return;
        if (button.contains(target)) return;
        if (mobileButton && mobileButton.contains(target)) return;

        if (typeof window.collapseNotificationPanel === 'function') {
            window.collapseNotificationPanel();
        }
    });

    if (list) {
        list.addEventListener('click', (event) => {
            handleToastHistoryClick(event);
        });
    }

    window.collapseNotificationPanel = () => {
        if (!expanded) return;
        expanded = false;
        window.NOTIFICATION_SIDEBAR_EXPANDED = false;
        setExpanded(false);
        postForm('/api/notifications/sidebar-state', {
            csrf_token: getCsrfToken(),
            expanded: '0'
        }).catch(() => {});
    };

    // Reset panel state when crossing the mobile/desktop breakpoint
    let wasMobile = isMobileLayout();
    window.addEventListener('resize', () => {
        const nowMobile = isMobileLayout();
        if (wasMobile === nowMobile) return;
        wasMobile = nowMobile;

        // Clean up mobile-specific state
        panel.classList.remove('mobile-open');
        const backdrop = document.getElementById('mobile-overlay-backdrop');
        if (backdrop) backdrop.classList.remove('visible');

        // Clean up desktop-specific width classes
        panel.classList.remove('w-96', 'max-w-[95vw]');
        panel.classList.add('w-20');

        // Reset to collapsed state
        expanded = false;
        window.NOTIFICATION_SIDEBAR_EXPANDED = false;
        setExpanded(false);
    });
}

function shouldSuppressNotificationToast(notification) {
    if (!currentChat || notification.type !== 'message' || !notification.link) return false;

    const linkMatch = String(notification.link).match(/\/c\/([^/?#]+)/);
    if (!linkMatch) return false;

    const notificationChatNumber = linkMatch[1].replace(/\D/g, '');
    const currentChatNumber = String(currentChat.chat_number || '').replace(/\D/g, '');
    return notificationChatNumber !== '' && notificationChatNumber === currentChatNumber;
}


function setTwofaSettingsStatus(text, kind) {
    var statusEl = document.getElementById('twofa-settings-status');
    if (!statusEl) return;

    var kindClassMap = {
        info: 'text-zinc-500',
        success: 'text-emerald-400',
        error: 'text-red-400'
    };

    statusEl.classList.remove('text-zinc-500', 'text-emerald-400', 'text-red-400');
    statusEl.classList.add(kindClassMap[kind] || kindClassMap.info);
    statusEl.textContent = text;
}

async function clearAllNotifications() {
    const btn = document.getElementById('notification-clear-all-btn');
    if (btn) btn.disabled = true;

    try {
        await postForm('/api/notifications/clear', {
            csrf_token: getCsrfToken()
        });
    } catch (e) {
        showToast('Failed to clear notifications', 'error');
        if (btn) btn.disabled = false;
        return;
    }

    for (const toast of toastHistory) {
        clearActiveToastPopup(toast.id);
    }
    toastHistory = [];
    unreadNotificationCount = 0;
    updateNotificationCountInTitle(0);
    renderToastHistory();

    if (btn) btn.disabled = false;
}

function bindTwofaFrequencyRadios() {
    const radios = Array.from(document.querySelectorAll('[data-twofa-frequency]'));
    if (radios.length === 0) return;

    radios.forEach((radio) => {
        radio.addEventListener('change', async () => {
            const frequency = radio.value;
            radios.forEach((r) => { r.disabled = true; });
            setTwofaSettingsStatus('Saving…', 'info');

            try {
                const result = await postForm('/settings/2fa/frequency', {
                    csrf_token: getCsrfToken(),
                    frequency
                });

                if (!result.success) {
                    throw new Error(result.error || 'Unable to save 2FA frequency');
                }

                setTwofaSettingsStatus('Saved', 'success');
            } catch (error) {
                const previous = frequency === 'always' ? 'trusted' : 'always';
                const previousRadio = radios.find((r) => r.value === previous);
                if (previousRadio) previousRadio.checked = true;
                setTwofaSettingsStatus('Failed to save', 'error');
                showToast(error.message || 'Unable to save 2FA frequency', 'error');
            } finally {
                radios.forEach((r) => { r.disabled = false; });
            }
        });
    });
}
