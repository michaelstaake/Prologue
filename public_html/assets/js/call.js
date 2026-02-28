// Extracted from app.js for feature-focused organization.

// ── Speaking detection state ──────────────────────────────────────────────────
let speakingAudioCtx = null;
let localSpeakingRaf = null;
let localParticipantSpeaking = false;
const remoteAnalysers = new Map();
const CALL_SESSION_STORAGE_KEY = 'prologue.activeCallSession';
const CALL_OVERLAY_STORAGE_KEY = 'prologue.activeCallOverlayMode';
const CALL_SELF_FOCUS_PEER_ID = -1;
let callParticipantsRenderSignature = '';
let callConnectingOverlayTimeout = null;

function hideCallConnectingOverlay() {
    if (callConnectingOverlayTimeout) {
        clearTimeout(callConnectingOverlayTimeout);
        callConnectingOverlayTimeout = null;
    }

    const overlay = document.getElementById('call-connecting-overlay');
    const title = document.getElementById('call-connecting-overlay-title');
    const subtitle = document.getElementById('call-connecting-overlay-subtitle');
    if (overlay) {
        overlay.classList.add('hidden');
    }
    if (title) {
        title.textContent = 'Connecting call';
    }
    if (subtitle) {
        subtitle.textContent = 'Please wait...';
    }
}

function showCallConnectingOverlay(options = {}) {
    const overlay = document.getElementById('call-connecting-overlay');
    const title = document.getElementById('call-connecting-overlay-title');
    const subtitle = document.getElementById('call-connecting-overlay-subtitle');
    if (!overlay) return;

    hideCallConnectingOverlay();

    const mode = String(options.mode || 'connecting').toLowerCase();
    const overlayTitle = mode === 'reconnecting' ? 'Reconnecting call' : 'Connecting call';
    if (title) {
        title.textContent = overlayTitle;
    }
    if (subtitle) {
        subtitle.textContent = 'Please wait...';
    }

    overlay.classList.remove('hidden');

    const safeDuration = Math.max(0, Number(options.durationMs || 3000));
    callConnectingOverlayTimeout = setTimeout(() => {
        hideCallConnectingOverlay();
    }, safeDuration);
}

function normalizeCallOverlayMode(mode) {
    return mode === 'hidden' || mode === 'half' || mode === 'full' ? mode : 'full';
}

function persistCallOverlayMode(mode, callId = null) {
    const safeCallId = Number(callId || currentCallId || globalCallContext?.id || 0);
    if (safeCallId <= 0) {
        sessionStorage.removeItem(CALL_OVERLAY_STORAGE_KEY);
        return;
    }

    const payload = {
        call_id: safeCallId,
        mode: normalizeCallOverlayMode(mode),
        updated_at: Date.now()
    };

    sessionStorage.setItem(CALL_OVERLAY_STORAGE_KEY, JSON.stringify(payload));
}

function clearPersistedCallOverlayMode() {
    sessionStorage.removeItem(CALL_OVERLAY_STORAGE_KEY);
}

function readPersistedCallOverlayMode(callId = null) {
    try {
        const raw = sessionStorage.getItem(CALL_OVERLAY_STORAGE_KEY);
        if (!raw) return null;
        const parsed = JSON.parse(raw);
        const persistedCallId = Number(parsed?.call_id || 0);
        if (persistedCallId <= 0) return null;

        const expectedCallId = Number(callId || 0);
        if (expectedCallId > 0 && persistedCallId !== expectedCallId) return null;

        return normalizeCallOverlayMode(parsed?.mode);
    } catch {
        return null;
    }
}

function clearStalePersistedCallOverlayMode(activeCallId = null) {
    try {
        const raw = sessionStorage.getItem(CALL_OVERLAY_STORAGE_KEY);
        if (!raw) return;

        const parsed = JSON.parse(raw);
        const persistedCallId = Number(parsed?.call_id || 0);
        const safeActiveCallId = Number(activeCallId || 0);

        if (persistedCallId <= 0 || safeActiveCallId <= 0 || persistedCallId !== safeActiveCallId) {
            clearPersistedCallOverlayMode();
        }
    } catch {
        clearPersistedCallOverlayMode();
    }
}

function persistActiveCallSession(call = null) {
    const safeCall = call || {};
    const safeCallId = Number(safeCall.call_id || safeCall.id || currentCallId || 0);
    const safeChatId = Number(safeCall.chat_id || currentChat?.id || globalCallContext?.chat_id || 0);
    if (safeCallId <= 0 || safeChatId <= 0) {
        sessionStorage.removeItem(CALL_SESSION_STORAGE_KEY);
        return;
    }

    const payload = {
        call_id: safeCallId,
        chat_id: safeChatId,
        chat_type: String(safeCall.chat_type || currentChat?.type || globalCallContext?.chat_type || 'personal'),
        started_at: String(safeCall.started_at || globalCallContext?.started_at || ''),
        started_by: Number(safeCall.started_by || globalCallContext?.started_by || 0),
        participant_count: Math.max(0, Number(safeCall.participant_count || globalCallContext?.participant_count || 0)),
        current_user_joined: Number(safeCall.current_user_joined || globalCallContext?.current_user_joined || 0) > 0 ? 1 : 0,
        signal_cursor: Number(callSignalCursor || 0),
        updated_at: Date.now()
    };

    sessionStorage.setItem(CALL_SESSION_STORAGE_KEY, JSON.stringify(payload));
}

function clearActiveCallSession() {
    sessionStorage.removeItem(CALL_SESSION_STORAGE_KEY);
    clearPersistedCallOverlayMode();
    globalCallContext = null;
}

function readPersistedActiveCallSession() {
    try {
        const raw = sessionStorage.getItem(CALL_SESSION_STORAGE_KEY);
        if (!raw) return null;
        const parsed = JSON.parse(raw);
        const callId = Number(parsed?.call_id || 0);
        const chatId = Number(parsed?.chat_id || 0);
        if (callId <= 0 || chatId <= 0) return null;
        return parsed;
    } catch {
        return null;
    }
}

function setGlobalCallContext(call) {
    if (!call) {
        globalCallContext = null;
        return;
    }

    globalCallContext = {
        id: Number(call.id || call.call_id || 0),
        chat_id: Number(call.chat_id || 0),
        chat_type: normalizeChatType(call.chat_type || 'personal'),
        started_at: String(call.started_at || ''),
        started_by: Number(call.started_by || 0),
        participant_count: Math.max(0, Number(call.participant_count || 0)),
        current_user_joined: Number(call.current_user_joined || 0) > 0 ? 1 : 0
    };
}

function ensureCurrentChatContext(call) {
    const safeChatId = Number(call?.chat_id || globalCallContext?.chat_id || 0);
    if (safeChatId <= 0) return;

    if (!currentChat || Number(currentChat.id || 0) !== safeChatId) {
        currentChat = {
            id: safeChatId,
            type: normalizeChatType(call?.chat_type || globalCallContext?.chat_type || 'personal'),
            can_start_calls: true,
            can_send_messages: true,
            message_restriction_reason: ''
        };
    }
}

function bindGlobalCallBarInteractions() {
    const callBar = document.getElementById('chat-call-status-bar');
    if (!callBar || callBar.dataset.bound === '1') return;
    callBar.dataset.bound = '1';

    callBar.addEventListener('click', (e) => {
        if (
            currentCallId &&
            callOverlayMode === 'hidden' &&
            !e.target.closest('#accept-call-btn') &&
            !e.target.closest('#decline-call-btn') &&
            !e.target.closest('#join-call-btn')
        ) {
            setCallOverlayMode('full');
        }
    });
}

function setChatCallEnabled(enabled) {
    const isEnabled = Boolean(enabled);

    if (currentChat) {
        currentChat.can_start_calls = isEnabled;
    }

    const voiceCallButton = document.getElementById('start-voice-call-button');

    [voiceCallButton].forEach((button) => {
        if (!button) return;

        button.disabled = !isEnabled;
        button.classList.toggle('opacity-50', !isEnabled);
        button.classList.toggle('cursor-not-allowed', !isEnabled);
        button.setAttribute('title', isEnabled ? '' : "You can't call a banned user");
    });
}


function setChatCallStatusBar(state, incomingAlert = false) {
    const bar = document.getElementById('chat-call-status-bar');
    const label = document.getElementById('chat-call-status-label');
    const acceptBtn = document.getElementById('accept-call-btn');
    const declineBtn = document.getElementById('decline-call-btn');
    const joinBtn = document.getElementById('join-call-btn');
    const showHint = document.getElementById('chat-call-show-overlay-hint');
    if (!bar || !label) return;
    callDurationBarState = state || null;

    bar.classList.remove(
        'hidden',
        'bg-emerald-500/20',
        'border-emerald-500/50',
        'text-emerald-200',
        'bg-zinc-700/50',
        'border-zinc-600',
        'text-zinc-200',
        'bg-amber-500/20',
        'border-amber-500/50',
        'text-amber-200',
        'border-transparent'
    );

    if (state !== 'ringing' && state !== 'active' && state !== 'muted' && state !== 'joinable') {
        label.textContent = '';
        bar.classList.add('hidden', 'border-transparent');
        acceptBtn?.classList.add('hidden');
        declineBtn?.classList.add('hidden');
        joinBtn?.classList.add('hidden');
        showHint?.classList.add('hidden');
        bar.style.cursor = '';
        bar.title = '';
        updateCallDurationUI();
        return;
    }

    const showJoinAction = state === 'joinable';
    const showCallActions = state === 'ringing' && incomingAlert;
    acceptBtn?.classList.toggle('hidden', !showCallActions);
    declineBtn?.classList.toggle('hidden', !showCallActions);
    joinBtn?.classList.toggle('hidden', !showJoinAction);

    // Show "open overlay" hint when overlay is hidden and call is active/muted
    const overlayIsHidden = callOverlayMode === 'hidden';
    const isActiveCall = state === 'active' || state === 'muted';
    if (showHint) showHint.classList.toggle('hidden', !(overlayIsHidden && isActiveCall));
    bar.style.cursor = (overlayIsHidden && isActiveCall) ? 'pointer' : '';
    bar.title = (overlayIsHidden && isActiveCall) ? 'Click to show call screen' : '';

    if (state === 'ringing') {
        label.textContent = 'Ringing..';
        bar.classList.add('bg-zinc-700/50', 'border-zinc-600', 'text-zinc-200');
        updateCallDurationUI();
        return;
    }

    if (state === 'joinable') {
        label.textContent = 'Call in progress';
        bar.classList.add('bg-emerald-500/20', 'border-emerald-500/50', 'text-emerald-200');
        updateCallDurationUI();
        return;
    }

    if (state === 'muted') {
        label.textContent = 'Call muted';
        bar.classList.add('bg-amber-500/20', 'border-amber-500/50', 'text-amber-200');
        updateCallDurationUI();
        return;
    }

    label.textContent = 'Call in progress';
    bar.classList.add('bg-emerald-500/20', 'border-emerald-500/50', 'text-emerald-200');
    updateCallDurationUI();
}

function formatCallDurationLabel(totalSeconds) {
    const safeTotalSeconds = Math.max(0, Math.floor(Number(totalSeconds || 0)));
    const hours = Math.floor(safeTotalSeconds / 3600);
    const minutes = Math.floor((safeTotalSeconds % 3600) / 60);
    const seconds = safeTotalSeconds % 60;

    if (hours > 0) {
        return `${hours}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
    }

    return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
}

function parseCallStartedAtMs(startedAt) {
    if (typeof startedAt === 'number' && Number.isFinite(startedAt) && startedAt > 0) {
        return startedAt > 1e12 ? Math.floor(startedAt) : Math.floor(startedAt * 1000);
    }

    const raw = String(startedAt || '').trim();
    if (!raw) return 0;

    if (/^\d+$/.test(raw)) {
        const numeric = Number(raw);
        if (Number.isFinite(numeric) && numeric > 0) {
            return numeric > 1e12 ? Math.floor(numeric) : Math.floor(numeric * 1000);
        }
    }

    const withTimeSeparator = raw.includes('T') ? raw : raw.replace(' ', 'T');
    const hasTimezone = /(?:Z|[+\-]\d{2}:?\d{2})$/i.test(withTimeSeparator);
    const normalized = hasTimezone ? withTimeSeparator : `${withTimeSeparator}Z`;
    const parsed = Date.parse(normalized);
    return Number.isFinite(parsed) ? parsed : 0;
}

function updateCallDurationUI() {
    const overlayDuration = document.getElementById('call-overlay-duration');
    const barDuration = document.getElementById('chat-call-status-duration');
    const hasStart = Number(callDurationStartedAtMs || 0) > 0;

    if (!hasStart) {
        if (overlayDuration) {
            overlayDuration.textContent = '00:00';
            overlayDuration.classList.add('hidden');
        }
        if (barDuration) {
            barDuration.textContent = '00:00';
            barDuration.classList.add('hidden');
        }
        return;
    }

    const elapsedSeconds = Math.max(0, Math.floor((Date.now() - callDurationStartedAtMs) / 1000));
    const label = formatCallDurationLabel(elapsedSeconds);

    if (overlayDuration) {
        overlayDuration.textContent = label;
        overlayDuration.classList.toggle('hidden', Number(currentCallId || 0) <= 0);
    }

    const showBarDuration = callDurationBarState === 'active' || callDurationBarState === 'muted';
    if (barDuration) {
        barDuration.textContent = label;
        barDuration.classList.toggle('hidden', !showBarDuration);
    }
}

function startCallDurationCounter(startedAt = null) {
    const parsedStartedAtMs = parseCallStartedAtMs(startedAt);
    if (parsedStartedAtMs > 0) {
        callDurationStartedAtMs = parsedStartedAtMs;
    } else if (!callDurationStartedAtMs) {
        callDurationStartedAtMs = Date.now();
    }

    if (callDurationTickInterval) {
        clearInterval(callDurationTickInterval);
        callDurationTickInterval = null;
    }

    updateCallDurationUI();
    callDurationTickInterval = setInterval(() => {
        updateCallDurationUI();
    }, 1000);
}

function stopCallDurationCounter() {
    if (callDurationTickInterval) {
        clearInterval(callDurationTickInterval);
        callDurationTickInterval = null;
    }

    callDurationStartedAtMs = 0;
    callDurationBarState = null;
    updateCallDurationUI();
}

function getChatCallState(call) {
    const callId = Number(call?.id || 0);
    if (!Number.isFinite(callId) || callId <= 0) {
        return { state: null, callId: 0, ringingDirection: null, incomingAlert: false };
    }

    const participantCount = Math.max(0, Number(call?.participant_count || 0));
    const startedBy = Number(call?.started_by || 0);
    const me = Number(currentUserId || 0);
    const safeCurrentCallId = Number(currentCallId || 0);
    const shouldInferCurrentUserJoined = Number.isFinite(safeCurrentCallId)
        && safeCurrentCallId > 0
        && safeCurrentCallId === callId
        && participantCount > 0;
    const currentUserJoined = Number(call?.current_user_joined || 0) > 0 || shouldInferCurrentUserJoined;
    const incomingAlert = startedBy > 0 && me > 0 && startedBy !== me && !currentUserJoined;

    if (!currentUserJoined) {
        if (callId === declinedCallId || participantCount > 1) {
            return {
                state: 'joinable',
                callId,
                ringingDirection: null,
                incomingAlert: false
            };
        }

        return {
            state: 'ringing',
            callId,
            ringingDirection: incomingAlert ? 'incoming' : 'outgoing',
            incomingAlert
        };
    }

    if (participantCount <= 1) {
        // If the current user has already accepted this call as the callee (currentCallId matches
        // and isCallOfferer is false), treat as active even if participant_count hasn't caught up
        // yet on the server, so the ringtone isn't restarted on every poll cycle.
        if (safeCurrentCallId > 0 && safeCurrentCallId === callId && !isCallOfferer) {
            return { state: 'active', callId, ringingDirection: null, incomingAlert: false };
        }
        return {
            state: 'ringing',
            callId,
            ringingDirection: incomingAlert ? 'incoming' : 'outgoing',
            incomingAlert
        };
    }

    const isLocalMutedCall = Boolean(isMuted)
        && Number.isFinite(safeCurrentCallId)
        && safeCurrentCallId > 0
        && safeCurrentCallId === callId;

    return {
        state: isLocalMutedCall ? 'muted' : 'active',
        callId,
        ringingDirection: null,
        incomingAlert: false
    };
}

function ensureCallRingingAudio() {
    if (callRingingAudio) {
        return callRingingAudio;
    }

    callRingingAudio = new Audio('/assets/sounds/callringing.wav');
    callRingingAudio.preload = 'auto';
    callRingingAudio.loop = true;
    return callRingingAudio;
}

function shouldSuppressNotificationSoundsDuringCall(call = null) {
    const safeCurrentCallId = Number(currentCallId || 0);
    if (Number.isFinite(safeCurrentCallId) && safeCurrentCallId > 0 && localStream) {
        return true;
    }

    const candidateCall = call || globalCallContext;
    if (!candidateCall) {
        return false;
    }

    const state = getChatCallState(candidateCall).state;
    return state === 'active' || state === 'muted';
}

function startCallRingingAudio(direction) {
    const nextDirection = direction === 'incoming' ? 'incoming' : 'outgoing';
    if (nextDirection === 'outgoing' && !window.NOTIFICATION_SOUND_OUTGOING_CALL_RING_ENABLED) {
        return;
    }
    if (callRingingDirection === nextDirection && callRingingAudio && !callRingingAudio.paused) {
        return;
    }

    const audio = ensureCallRingingAudio();
    callRingingDirection = nextDirection;
    if (typeof stopAllNotificationSounds === 'function') {
        stopAllNotificationSounds();
    }
    audio.currentTime = 0;
    audio.play().catch(() => {});
}

function stopCallRingingAudio() {
    callRingingDirection = null;
    if (!callRingingAudio) return;
    callRingingAudio.pause();
    callRingingAudio.currentTime = 0;
}

async function cleanupLocalCallSession(options = {}) {
    const restorePresence = options.restorePresence !== false;
    hideCallConnectingOverlay();

    if (screenStream) {
        screenStream.getTracks().forEach(track => track.stop());
        screenStream = null;
    }
    isScreenSharing = false;
    const screenVideo = document.getElementById('screen-share-video');
    if (screenVideo) {
        screenVideo.srcObject = null;
    }
    closeScreenShareModal();

    if (localStream) {
        localStream.getTracks().forEach(track => track.stop());
    }

    const callIdBeforeCleanup = Number(currentCallId || 0);
    localStream = null;
    currentCallId = null;
    isMuted = false;
    isVideoEnabled = false;
    localCameraStartedAtMs = 0;
    localScreenShareStartedAtMs = 0;
    hadCallPeerConnected = false;
    remoteHasVideo = false;
    remoteIsScreenSharing = false;
    remoteSpotlighted = false;
    peerUsername = '';
    localPipMode = 'screen-main';
    // Suppress ringing for this call ID after cleanup so a race-condition poll
    // can't restart the ringtone once the local session has ended.
    if (callIdBeforeCleanup > 0) {
        declinedCallId = callIdBeforeCleanup;
    }
    if (callSignalPollInterval) {
        clearInterval(callSignalPollInterval);
        callSignalPollInterval = null;
    }
    if (callSignalPollFastModeTimeout) {
        clearTimeout(callSignalPollFastModeTimeout);
        callSignalPollFastModeTimeout = null;
    }
    callSignalPollFastMode = false;
    if (leftAloneCallEndTimeout) {
        clearTimeout(leftAloneCallEndTimeout);
        leftAloneCallEndTimeout = null;
    }
    callSignalCursor = 0;

    callPeerConnections.forEach((pc) => {
        try { pc.close(); } catch (e) {}
    });
    callPeerConnections.clear();
    callPeerStates.clear();

    stopLocalSpeakingDetection();
    stopAllRemoteSpeakingDetection();

    remoteAudioElements.forEach((audioEl) => {
        if (audioEl?.parentNode) {
            audioEl.parentNode.removeChild(audioEl);
        }
    });
    remoteAudioElements.clear();
    activeRemotePeerId = 0;
    selfFocusPreferredPrimaryType = '';

    if (peerConnection) {
        peerConnection.close();
        peerConnection = null;
    }
    const remoteCameraVideo = document.getElementById('remote-camera-video');
    if (remoteCameraVideo) remoteCameraVideo.srcObject = null;
    const remoteScreenVideo = document.getElementById('remote-screen-video');
    if (remoteScreenVideo) remoteScreenVideo.srcObject = null;
    document.querySelectorAll('.js-call-participant-tile').forEach((tile) => tile.remove());
    const participantsList = document.getElementById('call-participants-list');
    participantsList?.replaceChildren();
    callParticipantsRenderSignature = '';
    isCallOfferer = false;
    appliedPeerIceCandidatesCount = 0;
    peerAnswerApplied = false;
    peerOfferApplied = false;
    lastAppliedOfferSdp = null;
    lastAppliedAnswerSdp = null;
    initialSignalingComplete = false;
    setChatCallStatusBar(null);
    stopCallDurationCounter();
    stopCallRingingAudio();
    lastIncomingCallAlertId = 0;

    const muteButton = document.getElementById('mute-btn');
    if (muteButton) {
        muteButton.innerHTML = '<i class="fa fa-microphone"></i>';
        muteButton.classList.remove('bg-amber-700', 'border-amber-600');
        muteButton.classList.add('bg-zinc-800', 'border-zinc-700');
    }
    updateVideoButton();
    updateScreenShareButton();
    updateLocalPipLayout();
    updateRemoteUsernameLabel();
    callParticipantsPanelOpen = true;
    updateCallParticipantsPanelVisibility();

    // Hide overlay and restore layout
    const overlay = document.getElementById('call-overlay');
    if (overlay) {
        overlay.classList.add('hidden');
        overlay.style.cssText = 'inset:0';
    }
    callOverlayMode = 'full';
    const appLayout = document.getElementById('app-layout');
    if (appLayout) { appLayout.style.marginTop = ''; appLayout.style.height = ''; }
    clearActiveCallSession();

    if (restorePresence) {
        await savePresenceStatus(selectedPresenceStatus, { silent: true });
    }
}

function syncCallRingingState(callState) {
    if (callState.state !== 'ringing') {
        stopCallRingingAudio();
        return;
    }

    if (callState.callId === declinedCallId) {
        stopCallRingingAudio();
        return;
    }

    startCallRingingAudio(callState.ringingDirection);

    if (callState.incomingAlert && callState.callId !== lastIncomingCallAlertId) {
        lastIncomingCallAlertId = callState.callId;
        showToast('Incoming call', 'info');
        if (window.BROWSER_NOTIFICATIONS_ENABLED && typeof Notification !== 'undefined' && Notification.permission === 'granted') {
            new Notification('Incoming call', { body: 'Someone is calling you.' });
        }
    }
}

async function applyActiveCallSnapshot(activeCall, options = {}) {
    const callState = getChatCallState(activeCall || null);
    latestChatCallId = Number(callState?.callId || 0);
    clearStalePersistedCallOverlayMode(latestChatCallId);

    const safeCurrentCallId = Number(currentCallId || 0);
    const safeActiveCallId = Number(activeCall?.id || 0);
    const participantCount = Math.max(0, Number(activeCall?.participant_count || 0));
    const currentUserJoined = Number(activeCall?.current_user_joined || 0) > 0;

    // Read the persisted session NOW, before persistActiveCallSession() overwrites it below.
    // isReconnectingCall is true only if the user was previously joined to this exact call
    // (current_user_joined: 1 in the snapshot saved during the call). This lets us reconnect
    // after a beacon-triggered leave without auto-joining callers who were never in the call.
    const priorPersistedSession = readPersistedActiveCallSession();
    const isReconnectingCall = Number(priorPersistedSession?.call_id || 0) === safeActiveCallId
        && Number(priorPersistedSession?.current_user_joined || 0) > 0;

    if (safeCurrentCallId > 0 && safeCurrentCallId === safeActiveCallId && participantCount >= 2) {
        hadCallPeerConnected = true;
    }

    const callEndedForCurrentUser = safeCurrentCallId > 0 && (!safeActiveCallId || safeActiveCallId !== safeCurrentCallId);
    if (callEndedForCurrentUser) {
        await cleanupLocalCallSession({ restorePresence: true });
        return;
    }

    const leftAloneAfterConnected = safeCurrentCallId > 0
        && safeCurrentCallId === safeActiveCallId
        && currentUserJoined
        && participantCount <= 1
        && hadCallPeerConnected;

    if (leftAloneAfterConnected) {
        // Don't end the call immediately — a peer may have navigated/refreshed and is
        // reconnecting. Give them 20 seconds to rejoin before we tear down the call.
        if (!leftAloneCallEndTimeout) {
            const endCallId = safeCurrentCallId;
            leftAloneCallEndTimeout = setTimeout(async () => {
                leftAloneCallEndTimeout = null;
                if (Number(currentCallId || 0) === endCallId) {
                    await postForm('/api/calls/end', {
                        csrf_token: getCsrfToken(),
                        call_id: String(endCallId)
                    }).catch(() => {});
                    await cleanupLocalCallSession({ restorePresence: true });
                }
            }, 20000);
        }
    } else if (leftAloneCallEndTimeout) {
        // Peer rejoined — cancel the pending end-call.
        clearTimeout(leftAloneCallEndTimeout);
        leftAloneCallEndTimeout = null;
    }

    if (activeCall) {
        setGlobalCallContext(activeCall);
        persistActiveCallSession(activeCall);
        if (currentUserJoined && safeActiveCallId > 0) {
            startCallDurationCounter(activeCall?.started_at || globalCallContext?.started_at || null);
        } else if (safeCurrentCallId <= 0) {
            stopCallDurationCounter();
        }
    } else if (!safeCurrentCallId) {
        clearActiveCallSession();
        stopCallDurationCounter();
    }

    setChatCallStatusBar(callState.state, callState.incomingAlert);
    syncCallRingingState(callState);
    if ((callState.state === 'active' || callState.state === 'muted') && typeof stopAllNotificationSounds === 'function') {
        stopAllNotificationSounds();
    }

    const shouldRestoreSession = Boolean(options.allowRestore)
        && !localStream
        && safeActiveCallId > 0
        && (currentUserJoined || isReconnectingCall);
    if (shouldRestoreSession) {
        await restoreCallSession(activeCall);
    }
}

async function fetchCurrentActiveCall() {
    const response = await fetch('/api/calls/current');
    const payload = await response.json();
    return payload?.call || null;
}

async function refreshGlobalCallState(options = {}) {
    if (chatCallStatusInFlight && !options.force) return;

    chatCallStatusInFlight = true;
    try {
        const activeCall = await fetchCurrentActiveCall();
        if (activeCall) {
            ensureCurrentChatContext(activeCall);
        }

        await applyActiveCallSnapshot(activeCall, { allowRestore: true });

        if (!activeCall && !currentCallId) {
            setChatCallStatusBar(null);
            stopCallRingingAudio();
        }
    } catch {
        if (!currentCallId) {
            setChatCallStatusBar(null);
            stopCallRingingAudio();
        }
    } finally {
        chatCallStatusInFlight = false;
    }
}

async function initGlobalCallPersistence() {
    const persisted = readPersistedActiveCallSession();
    if (persisted) {
        setGlobalCallContext(persisted);
        ensureCurrentChatContext(persisted);
        // Suppress ringing for this call while we reconnect. The beacon may have
        // set left_at, causing current_user_joined to return 0 on this page load,
        // which would otherwise trigger the incoming-call ring before restoreCallSession runs.
        declinedCallId = Number(persisted.call_id || 0);
    }

    await refreshGlobalCallState({ force: true });

    if (globalCallStatusPollInterval) {
        clearInterval(globalCallStatusPollInterval);
    }
    globalCallStatusPollInterval = setInterval(() => {
        refreshGlobalCallState().catch(() => {});
    }, 3000);
}

function initCallLeaveBeacon() {
    const fireLeaveBeacon = () => {
        const callId = Number(currentCallId || 0);
        if (callId <= 0) return;
        const payload = new URLSearchParams({
            csrf_token: getCsrfToken(),
            call_id: String(callId)
        });
        navigator.sendBeacon('/api/calls/leave', payload);
    };
    window.addEventListener('pagehide', fireLeaveBeacon);
    window.addEventListener('beforeunload', fireLeaveBeacon);
}

async function restoreCallSession(activeCall) {
    const safeCall = activeCall || globalCallContext;
    const safeChatId = Number(safeCall?.chat_id || 0);
    if (safeChatId <= 0 || callRestoreInFlight || localStream) return;

    callRestoreInFlight = true;
    try {
        ensureCurrentChatContext(safeCall);
        const restoredOverlayMode = readPersistedCallOverlayMode(Number(safeCall?.id || safeCall?.call_id || 0)) || 'full';
        await startVoiceCall({
            silentStart: true,
            initialOverlayMode: restoredOverlayMode,
            showJoinConnectingOverlay: true,
            joiningOverlayMode: 'reconnecting',
            reconnecting: true
        });
        setChatCallStatusBar(isMuted ? 'muted' : 'active');
    } catch {
    } finally {
        callRestoreInFlight = false;
    }
}

async function refreshChatCallStatusBar(options = {}) {
    if (!currentChat) {
        await refreshGlobalCallState(options);
        return;
    }
    if (chatCallStatusInFlight && !options.force) return;

    chatCallStatusInFlight = true;
    try {
        const response = await fetch(`/api/calls/active/${currentChat.id}`);
        const payload = await response.json();
        const activeCall = payload?.call || null;
        if (activeCall) {
            activeCall.chat_id = Number(activeCall?.chat_id || currentChat.id || 0);
            activeCall.chat_type = normalizeChatType(currentChat?.type || 'personal');
        }

        await applyActiveCallSnapshot(activeCall, { allowRestore: false });
    } catch {
        if (!currentCallId) {
            setChatCallStatusBar(null);
            stopCallRingingAudio();
        }
    } finally {
        chatCallStatusInFlight = false;
    }
}


async function startVoiceCall(options = {}) {
    if (!currentChat) return;
    if (normalizeChatType(currentChat.type) === 'personal' && currentChat.can_start_calls === false) {
        showToast("You can't call a banned user", 'error');
        return;
    }

    const start = await postForm('/api/calls/start', {
        csrf_token: getCsrfToken(),
        chat_id: String(currentChat.id)
    });

    if (!start.call_id) {
        showToast(start.error || 'Unable to start call', 'error');
        return;
    }

    currentCallId = start.call_id;
    startCallDurationCounter(Date.now());
    if (typeof stopAllNotificationSounds === 'function') {
        stopAllNotificationSounds();
    }
    isCallOfferer = !start.joined_existing;
    hadCallPeerConnected = false;
    const persistedCall = {
        call_id: Number(start.call_id || 0),
        chat_id: Number(currentChat?.id || 0),
        chat_type: normalizeChatType(currentChat?.type || 'personal'),
        started_at: new Date().toISOString(),
        started_by: Number(currentUserId || 0),
        current_user_joined: 1,
        participant_count: start.joined_existing ? 2 : 1
    };
    setGlobalCallContext({
        id: persistedCall.call_id,
        chat_id: persistedCall.chat_id,
        chat_type: persistedCall.chat_type,
        started_by: persistedCall.started_by,
        current_user_joined: persistedCall.current_user_joined,
        participant_count: persistedCall.participant_count
    });
    persistActiveCallSession(persistedCall);

    if (start.joined_existing) {
        stopCallRingingAudio();
        setChatCallStatusBar(isMuted ? 'muted' : 'active');
    } else {
        setChatCallStatusBar('ringing');
        syncCallRingingState({ state: 'ringing', callId: Number(currentCallId || 0), ringingDirection: 'outgoing', incomingAlert: false });
    }
    localStream = await navigator.mediaDevices.getUserMedia({ audio: true, video: false });
    bindCallAudioUnlockHandlers();
    startLocalSpeakingDetection();
    const localVideo = document.getElementById('local-video');
    if (localVideo) localVideo.srcObject = localStream;
    sendLocalMediaMetaSignal();

    const screenshareWrap = document.getElementById('screenshare-btn-wrap');
    if (screenshareWrap) {
        screenshareWrap.classList.toggle('hidden', isMobileDevice());
    }
    updateVideoButton();
    updateScreenShareButton();
    updateLocalPipLayout();
    updateCallVideoTileLayout();
    callParticipantsPanelOpen = true;
    updateCallParticipantsPanelVisibility();

    setCallOverlayMode(normalizeCallOverlayMode(options.initialOverlayMode || 'full'));
    if (start.joined_existing && (!options.silentStart || options.showJoinConnectingOverlay)) {
        showCallConnectingOverlay({
            durationMs: 3000,
            mode: options.joiningOverlayMode || 'connecting'
        });
    }
    applySidebarStatus({
        effective_status: 'busy',
        effective_status_label: 'Busy',
        effective_status_text_class: 'text-amber-400',
        effective_status_dot_class: 'bg-amber-500'
    });
    await startCallSignaling({ fast: Boolean(options.reconnecting) });
    refreshChatCallStatusBar({ force: true });
    if (!options.silentStart) {
        showToast('Call started', 'success');
    }
}

function toggleMute() {
    if (!localStream) return;
    isMuted = !isMuted;
    localStream.getAudioTracks().forEach(track => { track.enabled = !isMuted; });
    if (isMuted) {
        localParticipantSpeaking = false;
        setTileSpeaking(document.getElementById('local-media-container'), false);
        updateRemoteSpeakingIndicator(CALL_SELF_FOCUS_PEER_ID, false);
    }
    const btn = document.getElementById('mute-btn');
    if (btn) {
        btn.innerHTML = isMuted ? '<i class="fa fa-microphone-slash"></i>' : '<i class="fa fa-microphone"></i>';
        btn.classList.toggle('bg-amber-700', isMuted);
        btn.classList.toggle('border-amber-600', isMuted);
        btn.classList.toggle('bg-zinc-800', !isMuted);
        btn.classList.toggle('border-zinc-700', !isMuted);
    }
    refreshChatCallStatusBar({ force: true });
}

function isMobileDevice() {
    return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
}

function forEachCallPeerConnection(callback) {
    callPeerConnections.forEach((pc, peerId) => {
        if (!pc || pc.connectionState === 'closed') {
            return;
        }
        callback(pc, peerId);
    });
}

function getLiveLocalCameraTrack() {
    if (!localStream) return null;
    return localStream.getVideoTracks().find((track) => track.readyState === 'live') || null;
}

function getLiveLocalScreenTrack() {
    if (!screenStream) return null;
    return screenStream.getVideoTracks().find((track) => track.readyState === 'live') || null;
}

function getDesiredOutgoingVideoTracks() {
    const tracks = [];
    const cameraTrack = getLiveLocalCameraTrack();
    const screenTrack = getLiveLocalScreenTrack();

    if (isVideoEnabled && cameraTrack) {
        tracks.push({ track: cameraTrack, stream: localStream });
    }
    if (isScreenSharing && screenTrack) {
        tracks.push({ track: screenTrack, stream: screenStream });
    }

    return tracks;
}

function syncOutgoingVideoTracksAcrossPeers() {
    const desiredTracks = getDesiredOutgoingVideoTracks();
    const desiredTrackIds = new Set(desiredTracks.map((entry) => entry.track.id));

    forEachCallPeerConnection((pc) => {
        const senders = pc.getSenders().filter((sender) => sender.track && sender.track.kind === 'video');

        senders.forEach((sender) => {
            if (!desiredTrackIds.has(sender.track.id)) {
                pc.removeTrack(sender);
            }
        });

        const existingSenderTrackIds = new Set(
            pc.getSenders()
                .filter((sender) => sender.track && sender.track.kind === 'video')
                .map((sender) => sender.track.id)
        );

        desiredTracks.forEach(({ track, stream }) => {
            if (existingSenderTrackIds.has(track.id)) {
                return;
            }
            if (stream) {
                pc.addTrack(track, stream);
            }
        });
    });
}

function sendLocalMediaMetaSignal() {
    const cameraTrack = getLiveLocalCameraTrack();
    const screenTrack = getLiveLocalScreenTrack();

    sendMetaSignal({
        screen_sharing: Boolean(isScreenSharing && screenTrack),
        camera_enabled: Boolean(isVideoEnabled && cameraTrack),
        screen_track_id: screenTrack ? screenTrack.id : '',
        camera_track_id: cameraTrack ? cameraTrack.id : '',
        screen_started_at_ms: Number(localScreenShareStartedAtMs || 0),
        camera_started_at_ms: Number(localCameraStartedAtMs || 0)
    });
}

async function toggleVideoInCall() {
    if (!localStream) return;

    if (!isVideoEnabled) {
        try {
            const cameraStream = await navigator.mediaDevices.getUserMedia({ video: true });
            const videoTrack = cameraStream.getVideoTracks()[0];
            if (!videoTrack) return;

            localStream.addTrack(videoTrack);
            const localVideo = document.getElementById('local-video');
            if (localVideo) localVideo.srcObject = localStream;
            isVideoEnabled = true;
            localCameraStartedAtMs = Date.now();

            syncOutgoingVideoTracksAcrossPeers();
        } catch (e) {
            showToast('Could not enable camera', 'error');
            return;
        }
    } else {
        localStream.getVideoTracks().forEach(track => { track.stop(); localStream.removeTrack(track); });
        isVideoEnabled = false;
        localCameraStartedAtMs = 0;
        syncOutgoingVideoTracksAcrossPeers();
    }

    updateVideoButton();
    updateLocalPipLayout();
    sendLocalMediaMetaSignal();
}

function updateVideoButton() {
    const btn = document.getElementById('toggle-video-btn');
    if (!btn) return;
    btn.innerHTML = isVideoEnabled ? '<i class="fa fa-video"></i>' : '<i class="fa fa-video-slash"></i>';
    btn.classList.toggle('bg-emerald-700', isVideoEnabled);
    btn.classList.toggle('border-emerald-600', isVideoEnabled);
    btn.classList.toggle('bg-zinc-800', !isVideoEnabled);
    btn.classList.toggle('border-zinc-700', !isVideoEnabled);
}

function updateScreenShareButton() {
    const btn = document.getElementById('screenshare-btn');
    const screenshareWrap = document.getElementById('screenshare-btn-wrap');
    const onMobile = isMobileDevice();
    if (screenshareWrap) {
        screenshareWrap.classList.toggle('hidden', onMobile);
    }
    if (!btn) return;
    if (onMobile) return;
    btn.classList.toggle('bg-emerald-700', isScreenSharing);
    btn.classList.toggle('border-emerald-600', isScreenSharing);
    btn.classList.toggle('bg-zinc-800', !isScreenSharing);
    btn.classList.toggle('border-zinc-700', !isScreenSharing);
}

// ── Call overlay window management ──────────────────────────────────────────

function setCallOverlayMode(mode) {
    callOverlayMode = normalizeCallOverlayMode(mode);
    const overlay = document.getElementById('call-overlay');
    const appLayout = document.getElementById('app-layout');
    if (!overlay) return;

    if (callOverlayMode === 'hidden') {
        overlay.classList.add('hidden');
        overlay.style.cssText = 'inset:0';
        if (appLayout) { appLayout.style.marginTop = ''; appLayout.style.height = ''; }
    } else if (callOverlayMode === 'half') {
        overlay.classList.remove('hidden');
        overlay.style.cssText = 'top:0;left:0;right:0;bottom:auto;height:50vh';
        if (appLayout) { appLayout.style.marginTop = '50vh'; appLayout.style.height = '50vh'; }
    } else { // 'full'
        overlay.classList.remove('hidden');
        overlay.style.cssText = 'inset:0';
        if (appLayout) { appLayout.style.marginTop = ''; appLayout.style.height = ''; }
    }

    persistCallOverlayMode(callOverlayMode);

    updateCallOverlayModeButtons();
    updateCallParticipantsPanelVisibility();
    // Refresh status bar so the "Show" hint updates based on new mode
    refreshChatCallStatusBar({ force: true });
}

function updateCallOverlayModeButtons() {
    ['hidden', 'half', 'full'].forEach(m => {
        const btn = document.getElementById(`call-overlay-${m}-btn`);
        if (!btn) return;
        const active = callOverlayMode === m;
        btn.classList.toggle('bg-zinc-600', active);
        btn.classList.toggle('border-zinc-500', active);
        btn.classList.toggle('bg-zinc-800', !active);
        btn.classList.toggle('border-zinc-700', !active);
    });
}

function toggleCallParticipantsPanel() {
    setCallParticipantsPanelOpen(!callParticipantsPanelOpen);
}

function setCallParticipantsPanelOpen(isOpen) {
    callParticipantsPanelOpen = Boolean(isOpen);
    updateCallParticipantsPanelVisibility();
}

function updateCallParticipantsPanelVisibility() {
    const panel = document.getElementById('call-participants-panel');
    const toggleButton = document.getElementById('call-participants-toggle-btn');
    if (!panel || !toggleButton) {
        return;
    }

    toggleButton.classList.remove('hidden');

    panel.classList.toggle('hidden', !callParticipantsPanelOpen);
    panel.setAttribute('aria-hidden', callParticipantsPanelOpen ? 'false' : 'true');
    toggleButton.setAttribute('aria-expanded', callParticipantsPanelOpen ? 'true' : 'false');
    toggleButton.title = callParticipantsPanelOpen ? 'Hide participants panel' : 'Show participants panel';
    toggleButton.classList.toggle('bg-zinc-600', callParticipantsPanelOpen);
    toggleButton.classList.toggle('border-zinc-500', callParticipantsPanelOpen);
    toggleButton.classList.toggle('bg-zinc-800', !callParticipantsPanelOpen);
    toggleButton.classList.toggle('border-zinc-700', !callParticipantsPanelOpen);
}

function getSortedRemotePeerIds() {
    return Array.from(callPeerStates.keys())
        .map((peerId) => Number(peerId || 0))
        .filter((peerId) => peerId > 0)
        .sort((leftPeerId, rightPeerId) => {
            const leftUsername = String(callPeerStates.get(leftPeerId)?.username || 'Participant').toLowerCase();
            const rightUsername = String(callPeerStates.get(rightPeerId)?.username || 'Participant').toLowerCase();
            const byName = leftUsername.localeCompare(rightUsername);
            if (byName !== 0) return byName;
            return leftPeerId - rightPeerId;
        });
}

// ── Local PiP layout ─────────────────────────────────────────────────────────

function togglePipMode() {
    localPipMode = localPipMode === 'screen-main' ? 'webcam-main' : 'screen-main';
    updateLocalPipLayout();
}

function updateLocalPipLayout() {
    const container = document.getElementById('local-media-container');
    const localVideo = document.getElementById('local-video');
    const screenVideo = document.getElementById('screen-share-video');
    const pipBtn = document.getElementById('pip-toggle-btn');
    const placeholder = document.getElementById('local-video-placeholder');
    if (!container || !localVideo || !screenVideo) return;

    const isPip = isScreenSharing && isVideoEnabled;

    // Reset corner-video inline overrides
    [localVideo, screenVideo].forEach(v => {
        v.style.bottom = '';
        v.style.left = '';
        v.style.width = '';
        v.style.height = '';
        v.style.objectFit = '';
        v.style.borderRadius = '';
        v.style.border = '';
        v.style.cursor = '';
        v.style.zIndex = '';
        v.onclick = null;
        // Restore absolute fill (they are absolutely positioned in the container)
        v.style.position = 'absolute';
        v.style.inset = '0';
    });
    // Container always keeps its fixed size
    container.style.width = '320px';
    container.style.height = '180px';

    if (!isPip) {
        pipBtn?.classList.add('hidden');
        if (isScreenSharing) {
            screenVideo.classList.remove('hidden');
            localVideo.classList.add('hidden');
            if (placeholder) placeholder.classList.add('hidden');
        } else if (isVideoEnabled) {
            localVideo.classList.remove('hidden');
            screenVideo.classList.add('hidden');
            if (placeholder) placeholder.classList.add('hidden');
        } else {
            // Audio only: show mic placeholder
            localVideo.classList.add('hidden');
            screenVideo.classList.add('hidden');
            if (placeholder) placeholder.classList.remove('hidden');
        }
        return;
    }

    // PiP mode: both screen share and camera active
    pipBtn?.classList.remove('hidden');
    if (placeholder) placeholder.classList.add('hidden');

    const mainVideo = localPipMode === 'screen-main' ? screenVideo : localVideo;
    const cornerVideo = localPipMode === 'screen-main' ? localVideo : screenVideo;

    mainVideo.classList.remove('hidden');
    mainVideo.style.position = 'absolute';
    mainVideo.style.inset = '0';
    mainVideo.style.width = '100%';
    mainVideo.style.height = '100%';
    mainVideo.style.objectFit = 'contain';
    mainVideo.style.zIndex = '0';

    cornerVideo.classList.remove('hidden');
    cornerVideo.style.position = 'absolute';
    cornerVideo.style.inset = '';
    cornerVideo.style.bottom = '8px';
    cornerVideo.style.left = '8px';
    cornerVideo.style.width = '120px';
    cornerVideo.style.height = '80px';
    cornerVideo.style.objectFit = 'cover';
    cornerVideo.style.borderRadius = '8px';
    cornerVideo.style.border = '2px solid #52525b';
    cornerVideo.style.cursor = 'pointer';
    cornerVideo.style.zIndex = '1';
    cornerVideo.onclick = togglePipMode;
}

// ── Remote user username & spotlight ────────────────────────────────────────

function updateRemoteUsernameLabel() {
    const btn = document.getElementById('remote-username-btn');
    if (!btn) return;
    btn.textContent = peerUsername || 'Participant';
    btn.disabled = true;
    btn.style.cursor = 'default';
    btn.classList.remove('hover:text-white', 'hover:underline', 'underline-offset-2');
    btn.title = '';
}

function spotlightRemoteUser() {
    return;
}

function updateRemoteMediaPipLayout(state) {
    const container = document.getElementById('remote-video-container');
    const cameraVideo = document.getElementById('remote-camera-video');
    const screenVideo = document.getElementById('remote-screen-video');
    const placeholder = document.getElementById('remote-video-placeholder');
    if (!container || !cameraVideo || !screenVideo || !placeholder) return;

    const hasCamera = hasLiveVideoTrack(state?.cameraTrack || null);
    const hasScreen = hasLiveVideoTrack(state?.screenTrack || null);

    [cameraVideo, screenVideo].forEach((video) => {
        video.classList.add('hidden');
        video.style.position = 'absolute';
        video.style.inset = '0';
        video.style.width = '100%';
        video.style.height = '100%';
        video.style.objectFit = 'contain';
        video.style.bottom = '';
        video.style.left = '';
        video.style.border = '';
        video.style.borderRadius = '';
        video.style.cursor = '';
        video.style.zIndex = '';
        video.onclick = null;
    });

    if (!hasCamera && !hasScreen) {
        placeholder.classList.remove('hidden');
        return;
    }

    placeholder.classList.add('hidden');

    if (hasCamera && hasScreen) {
        const primaryType = resolvePrimaryRemoteFeedType(state);
        const mainVideo = primaryType === 'screen' ? screenVideo : cameraVideo;
        const cornerVideo = primaryType === 'screen' ? cameraVideo : screenVideo;

        mainVideo.classList.remove('hidden');
        mainVideo.style.objectFit = 'contain';
        mainVideo.style.zIndex = '0';

        cornerVideo.classList.remove('hidden');
        cornerVideo.style.inset = '';
        cornerVideo.style.bottom = '8px';
        cornerVideo.style.left = '8px';
        cornerVideo.style.width = '120px';
        cornerVideo.style.height = '80px';
        cornerVideo.style.objectFit = 'cover';
        cornerVideo.style.borderRadius = '8px';
        cornerVideo.style.border = '2px solid #52525b';
        cornerVideo.style.cursor = 'pointer';
        cornerVideo.style.zIndex = '1';
        cornerVideo.onclick = () => {
            state.preferredPrimaryType = primaryType === 'screen' ? 'camera' : 'screen';
            if (state?.isSelfFocus) {
                selfFocusPreferredPrimaryType = state.preferredPrimaryType;
            }
            updateRemoteMediaPipLayout(state);
            updateDynamicRemotePeerTiles(activeRemotePeerId);
        };
        return;
    }

    const singleVideo = hasScreen ? screenVideo : cameraVideo;
    singleVideo.classList.remove('hidden');
    singleVideo.style.objectFit = 'contain';
    singleVideo.style.zIndex = '0';
}

function updateCallVideoTileLayout() {
    const remoteTile = document.getElementById('remote-user-tile');
    const localTile = document.getElementById('local-user-tile');
    const callVideos = document.getElementById('call-videos');
    const remoteContainer = document.getElementById('remote-video-container');
    if (!remoteTile || !localTile || !callVideos) return;

    callVideos.style.position = 'relative';
    callVideos.style.display = 'block';

    remoteTile.style.width = '100%';
    remoteTile.style.height = '100%';
    remoteTile.style.display = 'flex';
    remoteTile.style.flexDirection = 'column';
    remoteTile.style.alignItems = 'stretch';
    remoteTile.style.gap = '8px';

    if (remoteContainer) {
        remoteContainer.style.flex = '1';
        remoteContainer.style.width = '100%';
        remoteContainer.style.height = '';
        remoteContainer.style.borderRadius = '12px';
    }

    localTile.style.display = 'none';
}

// ── Meta signaling (screen_sharing status) ───────────────────────────────────

async function sendMetaSignal(data) {
    if (!currentCallId) return;
    await postForm('/api/calls/signal', {
        csrf_token: getCsrfToken(),
        call_id: String(currentCallId),
        type: 'meta',
        data: JSON.stringify(data)
    }).catch(() => {});

    const peerIds = Array.from(callPeerConnections.keys());
    for (const peerId of peerIds) {
        await sendSignalToPeer(peerId, 'meta', data);
    }
}

// ── Screen share toggle ───────────────────────────────────────────────────────

function toggleScreenShare() {
    if (isMobileDevice()) {
        return;
    }
    if (!currentCallId) {
        showToast('Start a call first', 'info');
        return;
    }
    if (isScreenSharing) {
        stopScreenShare(true);
    } else {
        document.getElementById('screenshare-modal')?.classList.remove('hidden');
    }
}

function closeScreenShareModal() {
    document.getElementById('screenshare-modal')?.classList.add('hidden');
}

async function startScreenShare(quality) {
    if (isMobileDevice()) {
        return;
    }
    closeScreenShareModal();

    let videoConstraints;
    switch (quality) {
        case '720p-10fps':
            videoConstraints = { width: { ideal: 1280 }, height: { ideal: 720 }, frameRate: { ideal: 10, max: 10 } };
            break;
        case '1080p-30fps':
            videoConstraints = { width: { ideal: 1920 }, height: { ideal: 1080 }, frameRate: { ideal: 30, max: 30 } };
            break;
        case 'native-60fps':
            videoConstraints = { frameRate: { ideal: 60, max: 60 } };
            break;
        default:
            videoConstraints = true;
    }

    try {
        screenStream = await navigator.mediaDevices.getDisplayMedia({ video: videoConstraints, audio: false });
    } catch (e) {
        if (e.name !== 'NotAllowedError') {
            showToast('Could not start screen share', 'error');
        }
        return;
    }

    const screenTrack = screenStream.getVideoTracks()[0];
    if (!screenTrack) {
        showToast('No screen selected', 'error');
        screenStream = null;
        return;
    }

    const screenVideo = document.getElementById('screen-share-video');
    if (screenVideo) {
        screenVideo.srcObject = screenStream;
    }

    isScreenSharing = true;
    localScreenShareStartedAtMs = Date.now();
    syncOutgoingVideoTracksAcrossPeers();
    updateScreenShareButton();
    updateLocalPipLayout();
    sendLocalMediaMetaSignal();

    screenTrack.onended = () => { stopScreenShare(true); };
}

async function stopScreenShare(restoreCamera = true) {
    if (screenStream) {
        screenStream.getTracks().forEach(track => track.stop());
        screenStream = null;
    }
    isScreenSharing = false;
    localScreenShareStartedAtMs = 0;

    const screenVideo = document.getElementById('screen-share-video');
    if (screenVideo) {
        screenVideo.srcObject = null;
    }

    if (restoreCamera && isVideoEnabled && localStream) {
        try {
            const cameraStream = await navigator.mediaDevices.getUserMedia({ video: true });
            const cameraTrack = cameraStream.getVideoTracks()[0];
            if (cameraTrack) {
                localStream.getVideoTracks().forEach(t => { t.stop(); localStream.removeTrack(t); });
                localStream.addTrack(cameraTrack);
                const localVideo = document.getElementById('local-video');
                if (localVideo) localVideo.srcObject = localStream;
                localCameraStartedAtMs = Date.now();
            }
        } catch (e) {}
    }

    syncOutgoingVideoTracksAcrossPeers();
    updateScreenShareButton();
    updateLocalPipLayout();
    sendLocalMediaMetaSignal();
}

async function endCall() {
    if (currentCallId) {
        await postForm('/api/calls/end', { csrf_token: getCsrfToken(), call_id: String(currentCallId) }).catch(() => {});
    }
    await cleanupLocalCallSession({ restorePresence: true });
}

function setupPeerConnection() {
    return null;
}

function shouldInitiatePeerOffer(peerId) {
    const me = Number(currentUserId || 0);
    const other = Number(peerId || 0);
    if (!(me > 0 && other > 0) || me === other) {
        return false;
    }
    return me < other;
}

function getLiveLocalAudioTrack() {
    if (!localStream) return null;
    return localStream.getAudioTracks().find((track) => track.readyState === 'live') || null;
}

function ensureOutgoingAudioTrackOnPeerConnection(pc) {
    if (!pc || !localStream) return;

    const audioTrack = getLiveLocalAudioTrack();
    if (!audioTrack) return;

    const audioSenders = pc.getSenders().filter((sender) => sender.track && sender.track.kind === 'audio');
    if (audioSenders.length === 0) {
        pc.addTrack(audioTrack, localStream);
        return;
    }

    audioSenders.forEach((sender) => {
        if (!sender.track || sender.track.id === audioTrack.id) {
            return;
        }
        sender.replaceTrack(audioTrack).catch(() => {});
    });
}

function parseSignalPayload(rawPayload) {
    try {
        return JSON.parse(String(rawPayload || ''));
    } catch (e) {
        return null;
    }
}

function ensureRemoteAudioElement(peerId) {
    let audioElement = remoteAudioElements.get(peerId);
    if (audioElement) {
        return audioElement;
    }

    audioElement = document.createElement('audio');
    audioElement.autoplay = true;
    audioElement.playsInline = true;
    audioElement.controls = false;
    audioElement.muted = false;
    audioElement.volume = 1;
    audioElement.dataset.peerId = String(peerId);
    audioElement.className = 'fixed w-px h-px opacity-0 pointer-events-none -z-10';
    document.body.appendChild(audioElement);
    remoteAudioElements.set(peerId, audioElement);
    return audioElement;
}

function requestRemoteAudioPlayback(peerId = 0) {
    if (!remoteAudioElements.size) return;

    if (peerId) {
        const audioElement = remoteAudioElements.get(Number(peerId));
        audioElement?.play?.().catch(() => {});
        return;
    }

    remoteAudioElements.forEach((audioElement) => {
        audioElement?.play?.().catch(() => {});
    });
}

function bindCallAudioUnlockHandlers() {
    if (document.body.dataset.callAudioUnlockBound === '1') {
        return;
    }
    document.body.dataset.callAudioUnlockBound = '1';

    const unlockPlayback = () => {
        requestRemoteAudioPlayback();
        if (speakingAudioCtx && speakingAudioCtx.state === 'suspended') {
            speakingAudioCtx.resume().catch(() => {});
        }
    };

    const listenerOptions = { passive: true };
    ['pointerdown', 'touchstart', 'keydown'].forEach((eventName) => {
        window.addEventListener(eventName, unlockPlayback, listenerOptions);
    });
}

function getOrCreatePeerState(peerId) {
    if (!callPeerStates.has(peerId)) {
        callPeerStates.set(peerId, {
            remoteStream: new MediaStream(),
            remoteCameraStream: new MediaStream(),
            remoteScreenStream: new MediaStream(),
            cameraTrackId: '',
            screenTrackId: '',
            cameraStartedAtMs: 0,
            screenStartedAtMs: 0,
            preferredPrimaryType: '',
            cameraTrack: null,
            screenTrack: null,
            pendingIce: [],
            username: '',
            screenSharing: false,
            cameraEnabled: false,
            makingOffer: false,
            isApplyingRemote: false,
        });
    }
    return callPeerStates.get(peerId);
}

function hasLiveVideoTrack(track) {
    return Boolean(track && track.kind === 'video' && track.readyState === 'live');
}

function resolvePrimaryRemoteFeedType(state) {
    if (!state) return 'camera';

    const hasCamera = hasLiveVideoTrack(state.cameraTrack);
    const hasScreen = hasLiveVideoTrack(state.screenTrack);

    if (!hasCamera && !hasScreen) return 'camera';
    if (hasCamera && !hasScreen) return 'camera';
    if (!hasCamera && hasScreen) return 'screen';

    if (state.preferredPrimaryType === 'camera' || state.preferredPrimaryType === 'screen') {
        return state.preferredPrimaryType;
    }

    const cameraStartedAtMs = Number(state.cameraStartedAtMs || 0);
    const screenStartedAtMs = Number(state.screenStartedAtMs || 0);
    if (cameraStartedAtMs > 0 && screenStartedAtMs > 0) {
        return cameraStartedAtMs <= screenStartedAtMs ? 'camera' : 'screen';
    }

    return 'camera';
}

function getPreferredParticipantStream(state) {
    if (!state) return null;

    const primaryType = resolvePrimaryRemoteFeedType(state);
    if (primaryType === 'screen' && state.remoteScreenStream.getVideoTracks().length > 0) {
        return state.remoteScreenStream;
    }
    if (primaryType === 'camera' && state.remoteCameraStream.getVideoTracks().length > 0) {
        return state.remoteCameraStream;
    }
    if (state.remoteScreenStream.getVideoTracks().length > 0) {
        return state.remoteScreenStream;
    }
    if (state.remoteCameraStream.getVideoTracks().length > 0) {
        return state.remoteCameraStream;
    }
    return state.remoteStream;
}

function setPeerVideoTrackForPurpose(state, purpose, track) {
    if (!state || !track || track.kind !== 'video') return;

    const isScreen = purpose === 'screen';
    const trackKey = isScreen ? 'screenTrack' : 'cameraTrack';
    const streamKey = isScreen ? 'remoteScreenStream' : 'remoteCameraStream';

    state[trackKey] = track;
    const stream = state[streamKey];
    if (!stream) return;

    stream.getVideoTracks().forEach((existingTrack) => {
        if (existingTrack.id !== track.id) {
            stream.removeTrack(existingTrack);
        }
    });

    if (!stream.getVideoTracks().some((existingTrack) => existingTrack.id === track.id)) {
        stream.addTrack(track);
    }
}

function clearEndedPeerVideoTrack(state, purpose, trackId) {
    if (!state) return;

    const isScreen = purpose === 'screen';
    const trackKey = isScreen ? 'screenTrack' : 'cameraTrack';
    const streamKey = isScreen ? 'remoteScreenStream' : 'remoteCameraStream';
    const currentTrack = state[trackKey];
    if (!currentTrack || currentTrack.id !== trackId) return;

    state[streamKey].getVideoTracks().forEach((existingTrack) => {
        if (existingTrack.id === trackId) {
            state[streamKey].removeTrack(existingTrack);
        }
    });
    state[trackKey] = null;
}

function applyPeerMediaMeta(state, payload) {
    if (!state || !payload || typeof payload !== 'object') return;

    if (Object.prototype.hasOwnProperty.call(payload, 'screen_sharing')) {
        state.screenSharing = Boolean(payload.screen_sharing);
    }
    if (Object.prototype.hasOwnProperty.call(payload, 'camera_enabled')) {
        state.cameraEnabled = Boolean(payload.camera_enabled);
    }

    if (Object.prototype.hasOwnProperty.call(payload, 'screen_track_id')) {
        state.screenTrackId = String(payload.screen_track_id || '');
    }
    if (Object.prototype.hasOwnProperty.call(payload, 'camera_track_id')) {
        state.cameraTrackId = String(payload.camera_track_id || '');
    }

    if (Object.prototype.hasOwnProperty.call(payload, 'screen_started_at_ms')) {
        state.screenStartedAtMs = Math.max(0, Number(payload.screen_started_at_ms || 0));
    }
    if (Object.prototype.hasOwnProperty.call(payload, 'camera_started_at_ms')) {
        state.cameraStartedAtMs = Math.max(0, Number(payload.camera_started_at_ms || 0));
    }
}

function assignIncomingVideoTrack(state, track) {
    if (!state || !track || track.kind !== 'video') return;

    const trackId = String(track.id || '');
    let purpose = 'camera';

    if (trackId && state.screenTrackId && trackId === state.screenTrackId) {
        purpose = 'screen';
    } else if (trackId && state.cameraTrackId && trackId === state.cameraTrackId) {
        purpose = 'camera';
    } else if (state.screenSharing && !hasLiveVideoTrack(state.screenTrack)) {
        purpose = 'screen';
    } else if (!hasLiveVideoTrack(state.cameraTrack)) {
        purpose = 'camera';
    } else if (state.screenSharing) {
        purpose = 'screen';
    }

    setPeerVideoTrackForPurpose(state, purpose, track);

    const expectedTrackId = purpose === 'screen' ? state.screenTrackId : state.cameraTrackId;
    if (!expectedTrackId) {
        if (purpose === 'screen') {
            state.screenTrackId = trackId;
        } else {
            state.cameraTrackId = trackId;
        }
    }

    track.onended = () => {
        clearEndedPeerVideoTrack(state, purpose, trackId);
        refreshActiveRemoteTile();
    };
}

function updateDynamicRemotePeerTiles(primaryPeerId = 0) {
    const participantsList = document.getElementById('call-participants-list');
    if (!participantsList) {
        return;
    }

    const sortedRemotePeerIds = getSortedRemotePeerIds();
    const participants = [
        {
            peerId: CALL_SELF_FOCUS_PEER_ID,
            username: localUsername || 'You',
            isSelf: true,
            stream: (isScreenSharing && screenStream) ? screenStream : localStream,
            screenSharing: Boolean(isScreenSharing)
        },
        ...sortedRemotePeerIds.map((peerId) => {
            const state = callPeerStates.get(peerId);
            return {
                peerId,
                username: String(state?.username || 'Participant'),
                isSelf: false,
                stream: getPreferredParticipantStream(state),
                screenSharing: Boolean(state?.screenSharing)
            };
        })
    ];

    const renderSignature = participants
        .map((participant) => {
            const liveVideoTrackIds = participant.stream
                && typeof participant.stream.getVideoTracks === 'function'
                ? participant.stream
                    .getVideoTracks()
                    .filter((track) => track.readyState === 'live')
                    .map((track) => String(track.id || ''))
                    .sort()
                    .join(',')
                : '';

            return [
                Number(participant.peerId || 0),
                participant.username,
                participant.isSelf ? '1' : '0',
                participant.screenSharing ? '1' : '0',
                liveVideoTrackIds
            ].join('|');
        })
        .join('||');

    if (callParticipantsRenderSignature === renderSignature) {
        participants.forEach((participant) => {
            const tile = participantsList.querySelector(`.js-call-participant-tile[data-peer-id="${Number(participant.peerId || 0)}"]`);
            if (!tile) {
                return;
            }
            const isSelected = Number(activeRemotePeerId || 0) === participant.peerId;
            const isSpeaking = isParticipantSpeaking(participant.peerId);
            applyParticipantTileVisualState(tile, { isSelected, isSpeaking });
        });
        updateCallParticipantsPanelVisibility();
        return;
    }

    callParticipantsRenderSignature = renderSignature;

    participantsList.replaceChildren();

    participants.forEach((participant) => {
        const tile = document.createElement('div');
        tile.className = 'js-call-participant-tile shrink-0 flex flex-col items-stretch gap-1.5 rounded-xl border border-zinc-700 bg-zinc-900/70 p-2 w-36 md:w-full';
        tile.dataset.peerId = String(participant.peerId);

        const videoContainer = document.createElement('div');
        videoContainer.className = 'js-call-participant-video-container relative rounded-xl overflow-hidden bg-zinc-900 border border-zinc-700 flex items-center justify-center';
        videoContainer.dataset.peerId = String(participant.peerId);
        videoContainer.style.width = '100%';
        videoContainer.style.aspectRatio = '16 / 9';

        const video = document.createElement('video');
        video.autoplay = true;
        video.playsInline = true;
        if (participant.isSelf) {
            video.muted = true;
        }
        video.className = 'hidden absolute inset-0 w-full h-full object-contain';

        const placeholder = document.createElement('div');
        placeholder.className = 'js-call-participant-placeholder absolute inset-0 flex items-center justify-center';
        placeholder.innerHTML = '<i class="fa fa-user text-4xl text-zinc-700"></i>';

        const liveVideoStream = participant.stream
            && typeof participant.stream.getVideoTracks === 'function'
            && participant.stream.getVideoTracks().some((track) => track.readyState === 'live')
            ? participant.stream
            : null;

        if (liveVideoStream) {
            video.srcObject = liveVideoStream;
            video.classList.remove('hidden');
            placeholder.classList.add('hidden');
            video.play().catch(() => {});
        }

        videoContainer.appendChild(video);
        videoContainer.appendChild(placeholder);

        const nameButton = document.createElement('button');
        nameButton.type = 'button';
        nameButton.className = 'js-call-participant-username text-xs text-zinc-300 text-left truncate';
        nameButton.textContent = participant.isSelf ? `${participant.username} (You)` : participant.username;
        const hasLiveVideo = Boolean(liveVideoStream);

        const selectAsPrimary = () => {
            if (Number(activeRemotePeerId || 0) === participant.peerId) {
                return;
            }
            activeRemotePeerId = participant.peerId;
            remoteSpotlighted = false;
            refreshActiveRemoteTile();
        };

        videoContainer.classList.add('cursor-pointer');
        nameButton.classList.add('hover:text-zinc-100', 'hover:underline', 'underline-offset-2');
        videoContainer.onclick = selectAsPrimary;
        nameButton.onclick = selectAsPrimary;

        const nameRow = document.createElement('div');
        nameRow.className = 'flex items-center gap-1.5';
        nameRow.appendChild(nameButton);

        const statusIcon = document.createElement('span');
        statusIcon.className = 'text-[11px] text-zinc-300 shrink-0 flex items-center gap-1';
        const statusIcons = [];

        if (hasLiveVideo) {
            statusIcons.push('<i class="fa fa-video" title="Video calling"></i>');
        }
        if (participant.screenSharing) {
            statusIcons.push('<i class="fa fa-display" title="Screen sharing"></i>');
        }

        if (statusIcons.length > 0) {
            statusIcon.innerHTML = statusIcons.join('');
            nameRow.appendChild(statusIcon);
        }

        const isSelected = Number(activeRemotePeerId || 0) === participant.peerId;
        const isSpeaking = isParticipantSpeaking(participant.peerId);
        applyParticipantTileVisualState(tile, { isSelected, isSpeaking });

        tile.appendChild(videoContainer);
        tile.appendChild(nameRow);
        participantsList.appendChild(tile);
    });

    updateCallParticipantsPanelVisibility();
}

function refreshActiveRemoteTile() {
    const remoteCameraVideo = document.getElementById('remote-camera-video');
    const remoteScreenVideo = document.getElementById('remote-screen-video');
    const remotePlaceholder = document.getElementById('remote-video-placeholder');
    const sortedRemotePeerIds = getSortedRemotePeerIds();

    const isSelfFocused = Number(activeRemotePeerId || 0) === CALL_SELF_FOCUS_PEER_ID;

    if (!isSelfFocused && (!activeRemotePeerId || !callPeerStates.has(activeRemotePeerId))) {
        activeRemotePeerId = sortedRemotePeerIds[0] || 0;
    }

    const state = activeRemotePeerId > 0 ? callPeerStates.get(activeRemotePeerId) : null;
    peerConnection = activeRemotePeerId > 0 ? (callPeerConnections.get(activeRemotePeerId) || null) : null;

    if (isSelfFocused && remoteCameraVideo && remoteScreenVideo) {
        peerUsername = localUsername ? `${localUsername} (You)` : 'You';
        remoteIsScreenSharing = Boolean(isScreenSharing);
        remoteCameraVideo.muted = true;
        remoteScreenVideo.muted = true;

        if (remoteCameraVideo.srcObject !== localStream) {
            remoteCameraVideo.srcObject = localStream;
        }
        if (remoteScreenVideo.srcObject !== screenStream) {
            remoteScreenVideo.srcObject = screenStream;
        }

        const localCameraTrack = localStream?.getVideoTracks?.().find((track) => track.readyState === 'live') || null;
        const localScreenTrack = screenStream?.getVideoTracks?.().find((track) => track.readyState === 'live') || null;

        const hasCameraVideo = hasLiveVideoTrack(localCameraTrack);
        const hasScreenVideo = Boolean(isScreenSharing) && hasLiveVideoTrack(localScreenTrack);
        remoteHasVideo = hasCameraVideo || hasScreenVideo;

        const preferredSelfPrimaryType =
            selfFocusPreferredPrimaryType === 'camera' || selfFocusPreferredPrimaryType === 'screen'
                ? selfFocusPreferredPrimaryType
                : (hasScreenVideo ? 'screen' : 'camera');

        const selfFocusState = {
            cameraTrack: localCameraTrack,
            screenTrack: localScreenTrack,
            preferredPrimaryType: preferredSelfPrimaryType,
            cameraStartedAtMs: 0,
            screenStartedAtMs: 0,
            isSelfFocus: true
        };

        updateRemoteMediaPipLayout(selfFocusState);
        selfFocusPreferredPrimaryType = selfFocusState.preferredPrimaryType;

        if (hasCameraVideo) {
            remoteCameraVideo.play().catch(() => {});
        }
        if (hasScreenVideo) {
            remoteScreenVideo.play().catch(() => {});
        }

        remotePlaceholder?.classList.toggle('hidden', remoteHasVideo);
        updateCallVideoTileLayout();
        updateDynamicRemotePeerTiles(activeRemotePeerId);
        updateRemoteUsernameLabel();
        setTileSpeaking(document.getElementById('remote-video-container'), false);
        updateCallParticipantsPanelVisibility();
        return;
    }

    if (!state || !remoteCameraVideo || !remoteScreenVideo) {
        remoteHasVideo = false;
        remoteIsScreenSharing = false;
        if (remoteCameraVideo) {
            remoteCameraVideo.srcObject = null;
            remoteCameraVideo.classList.add('hidden');
        }
        if (remoteScreenVideo) {
            remoteScreenVideo.srcObject = null;
            remoteScreenVideo.classList.add('hidden');
        }
        remotePlaceholder?.classList.remove('hidden');
        updateCallVideoTileLayout();
        updateDynamicRemotePeerTiles(0);
        peerUsername = '';
        updateRemoteUsernameLabel();
        updateCallParticipantsPanelVisibility();
        return;
    }

    peerUsername = state.username || 'Participant';
    remoteIsScreenSharing = Boolean(state.screenSharing);
    remoteCameraVideo.muted = false;
    remoteScreenVideo.muted = false;

    if (remoteCameraVideo.srcObject !== state.remoteCameraStream) {
        remoteCameraVideo.srcObject = state.remoteCameraStream;
    }
    if (remoteScreenVideo.srcObject !== state.remoteScreenStream) {
        remoteScreenVideo.srcObject = state.remoteScreenStream;
    }

    const hasCameraVideo = hasLiveVideoTrack(state.cameraTrack);
    const hasScreenVideo = hasLiveVideoTrack(state.screenTrack);
    const hasVideo = hasCameraVideo || hasScreenVideo;
    remoteHasVideo = hasVideo;
    updateRemoteMediaPipLayout(state);
    if (hasCameraVideo) {
        remoteCameraVideo.play().catch(() => {});
    }
    if (hasScreenVideo) {
        remoteScreenVideo.play().catch(() => {});
    }
    updateCallVideoTileLayout();
    updateDynamicRemotePeerTiles(activeRemotePeerId);
    updateRemoteUsernameLabel();
    const _activeSpeaking = activeRemotePeerId ? remoteAnalysers.get(activeRemotePeerId)?.speaking : false;
    setTileSpeaking(document.getElementById('remote-video-container'), Boolean(_activeSpeaking));
    updateCallParticipantsPanelVisibility();
}

function removePeerConnection(peerId) {
    const existing = callPeerConnections.get(peerId);
    if (existing) {
        try { existing.close(); } catch (e) {}
    }
    callPeerConnections.delete(peerId);
    callPeerStates.delete(peerId);

    stopRemoteSpeakingDetection(peerId);

    const audioEl = remoteAudioElements.get(peerId);
    if (audioEl?.parentNode) {
        audioEl.parentNode.removeChild(audioEl);
    }
    remoteAudioElements.delete(peerId);

    const extraTile = document.querySelector(`.js-call-participant-tile[data-peer-id="${Number(peerId || 0)}"]`);
    if (extraTile) {
        extraTile.remove();
    }

    if (activeRemotePeerId === peerId) {
        activeRemotePeerId = 0;
    }
    refreshActiveRemoteTile();
}

async function sendSignalToPeer(peerId, type, payload) {
    if (!currentCallId || !peerId) return;
    await postForm('/api/calls/signal', {
        csrf_token: getCsrfToken(),
        call_id: String(currentCallId),
        to_user_id: String(peerId),
        type,
        data: JSON.stringify(payload)
    }).catch(() => {});
}

async function flushPendingIce(peerId) {
    const state = callPeerStates.get(peerId);
    const pc = callPeerConnections.get(peerId);
    if (!state || !pc || !pc.remoteDescription) {
        return;
    }

    const queue = [...state.pendingIce];
    state.pendingIce = [];
    for (const candidate of queue) {
        try {
            await pc.addIceCandidate(new RTCIceCandidate(candidate));
        } catch (e) {}
    }
}

function ensurePeerConnection(peerId, username = '') {
    const numericPeerId = Number(peerId || 0);
    if (!numericPeerId) return null;

    const existing = callPeerConnections.get(numericPeerId);
    if (existing) {
        const existingState = getOrCreatePeerState(numericPeerId);
        if (username) existingState.username = username;
        ensureOutgoingAudioTrackOnPeerConnection(existing);
        return existing;
    }

    const peerState = getOrCreatePeerState(numericPeerId);
    if (username) peerState.username = username;

    const pc = new RTCPeerConnection({
        iceServers: [{ urls: 'stun:stun.l.google.com:19302' }]
    });

    if (localStream) {
        localStream.getTracks().forEach((track) => pc.addTrack(track, localStream));
    }
    ensureOutgoingAudioTrackOnPeerConnection(pc);

    pc.ontrack = (event) => {
        const stream = (event.streams && event.streams[0]) ? event.streams[0] : null;
        if (stream && stream !== peerState.remoteStream && stream.getAudioTracks().length > 0) {
            peerState.remoteStream = stream;
        } else if (event.track && event.track.kind === 'audio' && !peerState.remoteStream.getTracks().includes(event.track)) {
            peerState.remoteStream.addTrack(event.track);
        }

        if (event.track.kind === 'audio') {
            const audioEl = ensureRemoteAudioElement(numericPeerId);
            if (audioEl.srcObject !== peerState.remoteStream) {
                audioEl.srcObject = peerState.remoteStream;
            }
            requestRemoteAudioPlayback(numericPeerId);
            startRemoteSpeakingDetection(numericPeerId);
        }

        if (event.track.kind === 'video') {
            assignIncomingVideoTrack(peerState, event.track);
            if (!activeRemotePeerId) {
                activeRemotePeerId = numericPeerId;
            }
        }
        refreshActiveRemoteTile();
    };

    pc.onicecandidate = async (event) => {
        if (!event.candidate || !currentCallId) return;
        await sendSignalToPeer(numericPeerId, 'ice', event.candidate);
    };

    pc.onnegotiationneeded = async () => {
        if (!currentCallId || !shouldInitiatePeerOffer(numericPeerId)) return;
        if (pc.signalingState !== 'stable' || peerState.makingOffer || peerState.isApplyingRemote) return;
        try {
            peerState.makingOffer = true;
            const offer = await pc.createOffer();
            await pc.setLocalDescription(offer);
            await sendSignalToPeer(numericPeerId, 'offer', offer);
        } catch (e) {
        } finally {
            peerState.makingOffer = false;
        }
    };

    pc.onconnectionstatechange = () => {
        const state = pc.connectionState;
        if (state === 'failed' || state === 'closed') {
            removePeerConnection(numericPeerId);
            return;
        }
        if (state === 'connected') {
            const allConnected = callPeerConnections.size > 0 &&
                Array.from(callPeerConnections.values()).every(p => p.connectionState === 'connected');
            if (allConnected) rampDownCallSignalPolling();
        }
        if (state === 'disconnected') {
            setTimeout(() => {
                if (pc.connectionState === 'disconnected') {
                    removePeerConnection(numericPeerId);
                }
            }, 2000);
        }
    };

    callPeerConnections.set(numericPeerId, pc);
    if (!activeRemotePeerId) {
        activeRemotePeerId = numericPeerId;
    }
    refreshActiveRemoteTile();
    return pc;
}

function syncPeerParticipants(participants) {
    const remoteParticipants = Array.isArray(participants) ? participants : [];
    const activePeerIds = new Set();

    remoteParticipants.forEach((participant) => {
        const peerId = Number(participant?.user_id || 0);
        if (!peerId) return;
        activePeerIds.add(peerId);

        ensurePeerConnection(peerId, String(participant?.username || 'Participant'));
        const state = getOrCreatePeerState(peerId);
        state.username = String(participant?.username || state.username || 'Participant');
        applyPeerMediaMeta(state, {
            screen_sharing: Boolean(participant?.screen_sharing)
        });
    });

    Array.from(callPeerConnections.keys()).forEach((peerId) => {
        if (!activePeerIds.has(peerId)) {
            removePeerConnection(peerId);
        }
    });

    hadCallPeerConnected = hadCallPeerConnected || remoteParticipants.length > 0;
    refreshActiveRemoteTile();
}

async function handleIncomingCallSignal(signal) {
    const fromUserId = Number(signal?.from_user_id || 0);
    const signalType = String(signal?.type || '');
    const payload = parseSignalPayload(signal?.payload);
    if (!fromUserId || !signalType || !payload) {
        return;
    }

    const pc = ensurePeerConnection(fromUserId);
    if (!pc) return;
    const state = getOrCreatePeerState(fromUserId);

    if (signalType === 'offer') {
        try {
            state.isApplyingRemote = true;
            if (pc.signalingState === 'have-local-offer') {
                await pc.setLocalDescription({ type: 'rollback' });
            }
            await pc.setRemoteDescription(new RTCSessionDescription(payload));
            await flushPendingIce(fromUserId);
            const answer = await pc.createAnswer();
            await pc.setLocalDescription(answer);
            await sendSignalToPeer(fromUserId, 'answer', answer);
        } catch (e) {
        } finally {
            state.isApplyingRemote = false;
        }
        return;
    }

    if (signalType === 'answer') {
        try {
            if (pc.signalingState === 'have-local-offer') {
                await pc.setRemoteDescription(new RTCSessionDescription(payload));
                await flushPendingIce(fromUserId);
            }
        } catch (e) {}
        return;
    }

    if (signalType === 'ice') {
        if (pc.remoteDescription) {
            try {
                await pc.addIceCandidate(new RTCIceCandidate(payload));
            } catch (e) {}
        } else {
            state.pendingIce.push(payload);
        }
        return;
    }

    if (signalType === 'meta') {
        applyPeerMediaMeta(state, payload);
        refreshActiveRemoteTile();
    }
}

function rampDownCallSignalPolling() {
    if (!callSignalPollFastMode) return;
    callSignalPollFastMode = false;
    if (callSignalPollFastModeTimeout) {
        clearTimeout(callSignalPollFastModeTimeout);
        callSignalPollFastModeTimeout = null;
    }
    if (callSignalPollInterval) {
        clearInterval(callSignalPollInterval);
        callSignalPollInterval = null;
    }
    if (currentCallId) {
        callSignalPollInterval = setInterval(pollCallSignal, 1200);
    }
}

async function startCallSignaling(options = {}) {
    if (callSignalPollInterval) {
        clearInterval(callSignalPollInterval);
        callSignalPollInterval = null;
    }
    if (callSignalPollFastModeTimeout) {
        clearTimeout(callSignalPollFastModeTimeout);
        callSignalPollFastModeTimeout = null;
    }

    const persistedSession = readPersistedActiveCallSession();
    const persistedCursor = Number(persistedSession?.signal_cursor || 0);
    const persistedCallId = Number(persistedSession?.call_id || 0);
    callSignalCursor = (persistedCursor > 0 && persistedCallId === Number(currentCallId || 0))
        ? persistedCursor
        : 0;

    callSignalPollFastMode = Boolean(options.fast);
    const pollInterval = callSignalPollFastMode ? 300 : 1200;

    await pollCallSignal();
    callSignalPollInterval = setInterval(pollCallSignal, pollInterval);

    if (callSignalPollFastMode) {
        callSignalPollFastModeTimeout = setTimeout(rampDownCallSignalPolling, 15000);
    }
}

async function pollCallSignal() {
    if (!currentCallId) return;
    try {
        const response = await fetch(`/api/calls/signal/${currentCallId}?since_id=${callSignalCursor}`);
        const data = await response.json();
        if (!data || data.error) return;

        if (Number.isFinite(Number(data?.next_since_id))) {
            callSignalCursor = Math.max(callSignalCursor, Number(data.next_since_id || 0));
            try {
                const raw = sessionStorage.getItem(CALL_SESSION_STORAGE_KEY);
                if (raw) {
                    const parsed = JSON.parse(raw);
                    parsed.signal_cursor = callSignalCursor;
                    sessionStorage.setItem(CALL_SESSION_STORAGE_KEY, JSON.stringify(parsed));
                }
            } catch {}
        }

        syncPeerParticipants(data?.participants || []);

        const signals = Array.isArray(data?.signals) ? data.signals : [];
        for (const signal of signals) {
            await handleIncomingCallSignal(signal);
        }
    } catch (e) {}
}

async function acceptCall() {
    // Suppress the incoming ring immediately (before the async join API call returns),
    // so a poll firing during that window can't restart the audio.
    declinedCallId = lastIncomingCallAlertId;
    stopCallRingingAudio();
    clearAcceptedCallNotifications();
    declinedCallId = 0;
    ensureCurrentChatContext(globalCallContext);
    await startVoiceCall();
}

async function joinCall() {
    const joinId = Number(latestChatCallId || lastIncomingCallAlertId || declinedCallId || 0);
    if (!joinId) {
        showToast('No active call to join', 'info');
        return;
    }

    declinedCallId = 0;
    lastIncomingCallAlertId = joinId;
    stopCallRingingAudio();
    clearAcceptedCallNotifications();
    ensureCurrentChatContext(globalCallContext);
    await startVoiceCall();
}

async function declineCall() {
    const declinedId = Number(lastIncomingCallAlertId || currentCallId || 0);
    declinedCallId = declinedId;
    stopCallRingingAudio();
    clearAcceptedCallNotifications();
    let declinedCallEnded = false;

    if (declinedId > 0) {
        const result = await postForm('/api/calls/decline', {
            csrf_token: getCsrfToken(),
            call_id: String(declinedId)
        });

        if (!result.success) {
            showToast(result.error || 'Unable to decline call', 'error');
        } else {
            declinedCallEnded = Number(result.ended || 0) > 0;
        }
    }

    document.getElementById('accept-call-btn')?.classList.add('hidden');
    document.getElementById('decline-call-btn')?.classList.add('hidden');

    if (declinedCallEnded) {
        document.getElementById('join-call-btn')?.classList.add('hidden');
        lastIncomingCallAlertId = 0;
        latestChatCallId = 0;
    } else {
        document.getElementById('join-call-btn')?.classList.remove('hidden');
    }

    refreshGlobalCallState({ force: true }).catch(() => {});
}

// ── Speaking detection ────────────────────────────────────────────────────────

const SPEAKING_THRESHOLD = 8;   // RMS on 0-255 scale
const SPEAKING_ONSET_MS  = 80;  // must exceed threshold for this long before "speaking"
const SPEAKING_DECAY_MS  = 500; // hold "speaking" this long after audio drops

function getOrCreateAudioContext() {
    if (!speakingAudioCtx || speakingAudioCtx.state === 'closed') {
        speakingAudioCtx = new (window.AudioContext || window.webkitAudioContext)();
    }
    if (speakingAudioCtx.state === 'suspended') {
        speakingAudioCtx.resume().catch(() => {});
    }
    return speakingAudioCtx;
}

function setTileSpeaking(container, speaking) {
    if (!container) return;
    container.classList.toggle('border-emerald-400', Boolean(speaking));
}

function isParticipantSpeaking(peerId) {
    if (Number(peerId) === CALL_SELF_FOCUS_PEER_ID) {
        return Boolean(localParticipantSpeaking);
    }

    return Boolean(remoteAnalysers.get(Number(peerId || 0))?.speaking);
}

function applyParticipantTileVisualState(tile, { isSelected = false, isSpeaking = false } = {}) {
    if (!tile) return;

    const selected = Boolean(isSelected);
    const speaking = Boolean(isSpeaking);

    tile.classList.toggle('bg-blue-500/10', selected);
    tile.classList.remove('border-zinc-700', 'border-blue-500', 'border-emerald-400');

    if (speaking) {
        tile.classList.add('border-emerald-400');
        return;
    }

    if (selected) {
        tile.classList.add('border-blue-500');
        return;
    }

    tile.classList.add('border-zinc-700');
}

function makeSpeakingAnalyser(stream, onSpeakingChange) {
    const ctx = getOrCreateAudioContext();
    const source = ctx.createMediaStreamSource(stream);
    const analyser = ctx.createAnalyser();
    analyser.fftSize = 512;
    analyser.smoothingTimeConstant = 0.4;
    source.connect(analyser);

    const data = new Uint8Array(analyser.fftSize);
    let onsetAt = 0;
    let decayAt  = 0;
    let speaking = false;
    let rafId;

    function tick() {
        rafId = requestAnimationFrame(tick);
        analyser.getByteTimeDomainData(data);
        let sum = 0;
        for (let i = 0; i < data.length; i++) {
            const s = (data[i] - 128) / 128;
            sum += s * s;
        }
        const rms = Math.sqrt(sum / data.length) * 255;
        const now = performance.now();
        const active = rms > SPEAKING_THRESHOLD;

        if (active) {
            decayAt = now + SPEAKING_DECAY_MS;
            if (!speaking) {
                if (!onsetAt) onsetAt = now;
                if (now - onsetAt >= SPEAKING_ONSET_MS) {
                    speaking = true;
                    onSpeakingChange(true);
                }
            }
        } else {
            onsetAt = 0;
            if (speaking && now >= decayAt) {
                speaking = false;
                onSpeakingChange(false);
            }
        }
    }

    tick();
    return { stop() { cancelAnimationFrame(rafId); }, get speaking() { return speaking; } };
}

function startLocalSpeakingDetection() {
    stopLocalSpeakingDetection();
    if (!localStream || !localStream.getAudioTracks().length) return;

    const handle = makeSpeakingAnalyser(localStream, (speaking) => {
        const isSpeaking = !isMuted && Boolean(speaking);
        localParticipantSpeaking = isSpeaking;
        setTileSpeaking(document.getElementById('local-media-container'), isSpeaking);
        updateRemoteSpeakingIndicator(CALL_SELF_FOCUS_PEER_ID, isSpeaking);
    });
    localSpeakingRaf = handle;
}

function stopLocalSpeakingDetection() {
    if (localSpeakingRaf) {
        localSpeakingRaf.stop();
        localSpeakingRaf = null;
    }
    localParticipantSpeaking = false;
    setTileSpeaking(document.getElementById('local-media-container'), false);
    updateRemoteSpeakingIndicator(CALL_SELF_FOCUS_PEER_ID, false);
}

function startRemoteSpeakingDetection(peerId) {
    const numericPeerId = Number(peerId || 0);
    if (!numericPeerId) return;
    stopRemoteSpeakingDetection(numericPeerId);

    const state = callPeerStates.get(numericPeerId);
    if (!state || !state.remoteStream || !state.remoteStream.getAudioTracks().length) return;

    const handle = makeSpeakingAnalyser(state.remoteStream, (speaking) => {
        const entry = remoteAnalysers.get(numericPeerId);
        if (entry) entry.speaking = speaking;
        updateRemoteSpeakingIndicator(numericPeerId, speaking);
    });
    remoteAnalysers.set(numericPeerId, { handle, speaking: false });
}

function stopRemoteSpeakingDetection(peerId) {
    const numericPeerId = Number(peerId || 0);
    const entry = remoteAnalysers.get(numericPeerId);
    if (!entry) return;
    entry.handle.stop();
    remoteAnalysers.delete(numericPeerId);
    updateRemoteSpeakingIndicator(numericPeerId, false);
}

function stopAllRemoteSpeakingDetection() {
    remoteAnalysers.forEach((entry, peerId) => {
        entry.handle.stop();
        updateRemoteSpeakingIndicator(peerId, false);
    });
    remoteAnalysers.clear();
}

function updateRemoteSpeakingIndicator(peerId, speaking) {
    const numericPeerId = Number(peerId || 0);
    if (numericPeerId === Number(activeRemotePeerId || 0)) {
        setTileSpeaking(document.getElementById('remote-video-container'), speaking);
    }
    const tile = document.querySelector(`.js-call-participant-tile[data-peer-id="${numericPeerId}"]`);
    if (tile) {
        applyParticipantTileVisualState(tile, {
            isSelected: Number(activeRemotePeerId || 0) === numericPeerId,
            isSpeaking: Boolean(speaking)
        });
    }
}
