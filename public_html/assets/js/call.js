// Extracted from app.js for feature-focused organization.

// ── Speaking detection state ──────────────────────────────────────────────────
let speakingAudioCtx = null;
let localSpeakingRaf = null;
const remoteAnalysers = new Map();

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
        return;
    }

    if (state === 'joinable') {
        label.textContent = 'Call in progress';
        bar.classList.add('bg-emerald-500/20', 'border-emerald-500/50', 'text-emerald-200');
        return;
    }

    if (state === 'muted') {
        label.textContent = 'Call muted';
        bar.classList.add('bg-amber-500/20', 'border-amber-500/50', 'text-amber-200');
        return;
    }

    label.textContent = 'On call';
    bar.classList.add('bg-emerald-500/20', 'border-emerald-500/50', 'text-emerald-200');
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
    const currentUserJoined = Number(call?.current_user_joined || 0) > 0
        || (Number.isFinite(safeCurrentCallId) && safeCurrentCallId > 0 && safeCurrentCallId === callId);
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

    if (peerConnection) {
        peerConnection.close();
        peerConnection = null;
    }
    const remoteVideo = document.getElementById('remote-video');
    if (remoteVideo) remoteVideo.srcObject = null;
    document.querySelectorAll('.js-remote-peer-tile').forEach((tile) => tile.remove());
    isCallOfferer = false;
    appliedPeerIceCandidatesCount = 0;
    peerAnswerApplied = false;
    peerOfferApplied = false;
    lastAppliedOfferSdp = null;
    lastAppliedAnswerSdp = null;
    initialSignalingComplete = false;
    setChatCallStatusBar(null);
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

    // Hide overlay and restore layout
    const overlay = document.getElementById('call-overlay');
    if (overlay) {
        overlay.classList.add('hidden');
        overlay.style.cssText = 'inset:0';
    }
    callOverlayMode = 'full';
    const appLayout = document.getElementById('app-layout');
    if (appLayout) { appLayout.style.marginTop = ''; appLayout.style.height = ''; }

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

async function refreshChatCallStatusBar(options = {}) {
    if (!currentChat) return;
    if (chatCallStatusInFlight && !options.force) return;

    chatCallStatusInFlight = true;
    try {
        const response = await fetch(`/api/calls/active/${currentChat.id}`);
        const payload = await response.json();
        const activeCall = payload?.call || null;
        const callState = getChatCallState(payload?.call || null);
        latestChatCallId = Number(callState?.callId || 0);

        const safeCurrentCallId = Number(currentCallId || 0);
        const safeActiveCallId = Number(activeCall?.id || 0);
        const participantCount = Math.max(0, Number(activeCall?.participant_count || 0));
        const currentUserJoined = Number(activeCall?.current_user_joined || 0) > 0;

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
            await postForm('/api/calls/end', {
                csrf_token: getCsrfToken(),
                call_id: String(safeCurrentCallId)
            }).catch(() => {});
            await cleanupLocalCallSession({ restorePresence: true });
            return;
        }

        setChatCallStatusBar(callState.state, callState.incomingAlert);
        syncCallRingingState(callState);
    } catch {
        if (!currentCallId) {
            setChatCallStatusBar(null);
            stopCallRingingAudio();
        }
    } finally {
        chatCallStatusInFlight = false;
    }
}


async function startVoiceCall() {
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
    isCallOfferer = !start.joined_existing;
    hadCallPeerConnected = false;
    if (start.joined_existing) {
        stopCallRingingAudio();
        setChatCallStatusBar(isMuted ? 'muted' : 'active');
    } else {
        setChatCallStatusBar('ringing');
        syncCallRingingState({ state: 'ringing', callId: Number(currentCallId || 0), ringingDirection: 'outgoing', incomingAlert: false });
    }
    localStream = await navigator.mediaDevices.getUserMedia({ audio: true, video: false });
    startLocalSpeakingDetection();
    const localVideo = document.getElementById('local-video');
    if (localVideo) localVideo.srcObject = localStream;

    const screenshareWrap = document.getElementById('screenshare-btn-wrap');
    if (screenshareWrap) {
        screenshareWrap.classList.toggle('hidden', isMobileDevice());
    }
    updateVideoButton();
    updateScreenShareButton();
    updateLocalPipLayout();

    setCallOverlayMode('full');
    applySidebarStatus({
        effective_status: 'busy',
        effective_status_label: 'Busy',
        effective_status_text_class: 'text-amber-400',
        effective_status_dot_class: 'bg-amber-500'
    });
    await startCallSignaling();
    refreshChatCallStatusBar({ force: true });
    showToast('Call started', 'success');
}

function toggleMute() {
    if (!localStream) return;
    isMuted = !isMuted;
    localStream.getAudioTracks().forEach(track => { track.enabled = !isMuted; });
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

async function replaceOutgoingVideoTrackAcrossPeers(track, stream) {
    const tasks = [];
    forEachCallPeerConnection((pc) => {
        const sender = pc.getSenders().find((candidate) => candidate.track && candidate.track.kind === 'video');
        if (sender) {
            tasks.push(sender.replaceTrack(track));
            return;
        }
        if (track && stream) {
            pc.addTrack(track, stream);
        }
    });
    if (tasks.length > 0) {
        await Promise.allSettled(tasks);
    }
}

function removeOutgoingVideoTracksAcrossPeers() {
    forEachCallPeerConnection((pc) => {
        pc.getSenders().filter((sender) => sender.track && sender.track.kind === 'video').forEach((sender) => {
            pc.removeTrack(sender);
        });
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

            if (!isScreenSharing) {
                await replaceOutgoingVideoTrackAcrossPeers(videoTrack, localStream);
            }
            // If screen sharing is active, keep sending screen to peer; camera only shows locally (PiP)
        } catch (e) {
            showToast('Could not enable camera', 'error');
            return;
        }
    } else {
        localStream.getVideoTracks().forEach(track => { track.stop(); localStream.removeTrack(track); });
        isVideoEnabled = false;

        if (!isScreenSharing) {
            removeOutgoingVideoTracksAcrossPeers();
        }
    }

    updateVideoButton();
    updateLocalPipLayout();
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
    callOverlayMode = mode;
    const overlay = document.getElementById('call-overlay');
    const appLayout = document.getElementById('app-layout');
    if (!overlay) return;

    if (mode === 'hidden') {
        overlay.classList.add('hidden');
        overlay.style.cssText = 'inset:0';
        if (appLayout) { appLayout.style.marginTop = ''; appLayout.style.height = ''; }
    } else if (mode === 'half') {
        overlay.classList.remove('hidden');
        overlay.style.cssText = 'top:0;left:0;right:0;bottom:auto;height:50vh';
        if (appLayout) { appLayout.style.marginTop = '50vh'; appLayout.style.height = '50vh'; }
    } else { // 'full'
        overlay.classList.remove('hidden');
        overlay.style.cssText = 'inset:0';
        if (appLayout) { appLayout.style.marginTop = ''; appLayout.style.height = ''; }
    }

    updateCallOverlayModeButtons();
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
    // Clickable only when remote is screen sharing
    const clickable = remoteIsScreenSharing;
    btn.disabled = !clickable;
    btn.style.cursor = clickable ? 'pointer' : 'default';
    if (clickable) {
        btn.classList.add('hover:text-white', 'hover:underline', 'underline-offset-2');
        btn.title = 'Spotlight their screen share';
    } else {
        btn.classList.remove('hover:text-white', 'hover:underline', 'underline-offset-2');
        btn.title = '';
    }
}

function spotlightRemoteUser() {
    if (!remoteIsScreenSharing) return;
    remoteSpotlighted = !remoteSpotlighted;
    updateCallVideoTileLayout();
}

function updateCallVideoTileLayout() {
    const remoteTile = document.getElementById('remote-user-tile');
    const localTile = document.getElementById('local-user-tile');
    const callVideos = document.getElementById('call-videos');
    const remoteContainer = document.getElementById('remote-video-container');
    if (!remoteTile || !localTile || !callVideos) return;

    if (remoteSpotlighted) {
        // Remote fills the area; local becomes a small absolute-positioned corner tile
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

        localTile.style.position = 'absolute';
        localTile.style.bottom = '16px';
        localTile.style.right = '16px';
        localTile.style.zIndex = '1';
        localTile.style.margin = '0';

        const localContainer = document.getElementById('local-media-container');
        if (localContainer) {
            localContainer.style.width = '180px';
            localContainer.style.height = '112px';
        }
    } else {
        // Restore normal flex layout
        callVideos.style.position = '';
        callVideos.style.display = '';

        remoteTile.style.width = '';
        remoteTile.style.height = '';
        remoteTile.style.display = '';
        remoteTile.style.flexDirection = '';
        remoteTile.style.alignItems = '';
        remoteTile.style.gap = '';

        if (remoteContainer) {
            remoteContainer.style.flex = '';
            remoteContainer.style.width = '320px';
            remoteContainer.style.height = '180px';
            remoteContainer.style.borderRadius = '';
        }

        localTile.style.position = '';
        localTile.style.bottom = '';
        localTile.style.right = '';
        localTile.style.zIndex = '';
        localTile.style.margin = '';

        updateLocalPipLayout();
    }
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

    await replaceOutgoingVideoTrackAcrossPeers(screenTrack, screenStream);

    const screenVideo = document.getElementById('screen-share-video');
    if (screenVideo) {
        screenVideo.srcObject = screenStream;
    }

    isScreenSharing = true;
    updateScreenShareButton();
    updateLocalPipLayout();
    sendMetaSignal({ screen_sharing: true });

    screenTrack.onended = () => { stopScreenShare(true); };
}

async function stopScreenShare(restoreCamera = true) {
    if (screenStream) {
        screenStream.getTracks().forEach(track => track.stop());
        screenStream = null;
    }
    isScreenSharing = false;

    const screenVideo = document.getElementById('screen-share-video');
    if (screenVideo) {
        screenVideo.srcObject = null;
    }

    if (restoreCamera && isVideoEnabled && localStream) {
        try {
            const cameraStream = await navigator.mediaDevices.getUserMedia({ video: true });
            const cameraTrack = cameraStream.getVideoTracks()[0];
            if (cameraTrack) {
                await replaceOutgoingVideoTrackAcrossPeers(cameraTrack, localStream);
                localStream.getVideoTracks().forEach(t => { t.stop(); localStream.removeTrack(t); });
                localStream.addTrack(cameraTrack);
                const localVideo = document.getElementById('local-video');
                if (localVideo) localVideo.srcObject = localStream;
            }
        } catch (e) {}
    } else if (!restoreCamera || !isVideoEnabled) {
        removeOutgoingVideoTracksAcrossPeers();
    }

    updateScreenShareButton();
    updateLocalPipLayout();
    sendMetaSignal({ screen_sharing: false });
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
    return me > 0 && other > 0 && me < other;
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
    audioElement.dataset.peerId = String(peerId);
    audioElement.className = 'hidden';
    document.body.appendChild(audioElement);
    remoteAudioElements.set(peerId, audioElement);
    return audioElement;
}

function getOrCreatePeerState(peerId) {
    if (!callPeerStates.has(peerId)) {
        callPeerStates.set(peerId, {
            remoteStream: new MediaStream(),
            pendingIce: [],
            username: '',
            screenSharing: false,
            makingOffer: false,
            isApplyingRemote: false,
        });
    }
    return callPeerStates.get(peerId);
}

function updateDynamicRemotePeerTiles(primaryPeerId = 0) {
    const callVideos = document.getElementById('call-videos');
    const localUserTile = document.getElementById('local-user-tile');
    if (!callVideos || !localUserTile) {
        return;
    }

    const peerIds = Array.from(callPeerStates.keys()).filter((peerId) => Number(peerId) !== Number(primaryPeerId));
    const desiredPeerIds = new Set(peerIds.map((peerId) => Number(peerId)));

    callVideos.querySelectorAll('.js-remote-peer-tile').forEach((tile) => {
        const tilePeerId = Number(tile.dataset.peerId || 0);
        if (!desiredPeerIds.has(tilePeerId)) {
            tile.remove();
        }
    });

    peerIds.forEach((peerId) => {
        const numericPeerId = Number(peerId || 0);
        if (!numericPeerId) return;

        const state = callPeerStates.get(numericPeerId);
        if (!state) return;

        let tile = callVideos.querySelector(`.js-remote-peer-tile[data-peer-id="${numericPeerId}"]`);
        if (!tile) {
            tile = document.createElement('div');
            tile.className = 'js-remote-peer-tile flex flex-col items-center gap-2';
            tile.dataset.peerId = String(numericPeerId);
            tile.innerHTML = `
                <div class="relative rounded-2xl overflow-hidden bg-zinc-900 border border-zinc-700 flex items-center justify-center" style="width:320px;height:180px">
                    <video autoplay playsinline class="hidden absolute inset-0 w-full h-full object-contain"></video>
                    <div class="js-remote-peer-placeholder absolute inset-0 flex items-center justify-center"><i class="fa fa-user text-5xl text-zinc-700"></i></div>
                </div>
                <span class="js-remote-peer-username text-xs text-zinc-300"></span>
            `;
            callVideos.insertBefore(tile, localUserTile);
        }

        const label = tile.querySelector('.js-remote-peer-username');
        if (label) {
            label.textContent = state.username || 'Participant';
        }

        const video = tile.querySelector('video');
        const placeholder = tile.querySelector('.js-remote-peer-placeholder');
        if (video) {
            if (video.srcObject !== state.remoteStream) {
                video.srcObject = state.remoteStream;
            }

            const hasVideo = state.remoteStream.getVideoTracks().some((track) => track.readyState === 'live');
            video.classList.toggle('hidden', !hasVideo);
            placeholder?.classList.toggle('hidden', hasVideo);
            video.play().catch(() => {});
        }
    });
}

function refreshActiveRemoteTile() {
    const remoteVideo = document.getElementById('remote-video');
    const remotePlaceholder = document.getElementById('remote-video-placeholder');

    if (!activeRemotePeerId || !callPeerStates.has(activeRemotePeerId)) {
        const candidate = Array.from(callPeerStates.keys())[0] || 0;
        activeRemotePeerId = candidate;
    }

    const state = activeRemotePeerId ? callPeerStates.get(activeRemotePeerId) : null;
    peerConnection = activeRemotePeerId ? (callPeerConnections.get(activeRemotePeerId) || null) : null;

    if (!state || !remoteVideo) {
        remoteHasVideo = false;
        remoteIsScreenSharing = false;
        if (remoteVideo) {
            remoteVideo.srcObject = null;
            remoteVideo.classList.add('hidden');
        }
        remotePlaceholder?.classList.remove('hidden');
        updateDynamicRemotePeerTiles(0);
        peerUsername = '';
        updateRemoteUsernameLabel();
        return;
    }

    peerUsername = state.username || 'Participant';
    remoteIsScreenSharing = Boolean(state.screenSharing);

    if (remoteVideo.srcObject !== state.remoteStream) {
        remoteVideo.srcObject = state.remoteStream;
    }

    const hasVideo = state.remoteStream.getVideoTracks().some((track) => track.readyState === 'live');
    remoteHasVideo = hasVideo;
    remoteVideo.classList.toggle('hidden', !hasVideo);
    remotePlaceholder?.classList.toggle('hidden', hasVideo);
    remoteVideo.play().catch(() => {});
    updateDynamicRemotePeerTiles(activeRemotePeerId);
    updateRemoteUsernameLabel();
    const _activeSpeaking = activeRemotePeerId ? remoteAnalysers.get(activeRemotePeerId)?.speaking : false;
    setTileSpeaking(document.getElementById('remote-video-container'), Boolean(_activeSpeaking));
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

    const extraTile = document.querySelector(`.js-remote-peer-tile[data-peer-id="${Number(peerId || 0)}"]`);
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

    pc.ontrack = (event) => {
        const stream = (event.streams && event.streams[0]) ? event.streams[0] : peerState.remoteStream;
        if (stream !== peerState.remoteStream) {
            peerState.remoteStream = stream;
        } else if (event.track && !stream.getTracks().includes(event.track)) {
            stream.addTrack(event.track);
        }

        if (event.track.kind === 'audio') {
            const audioEl = ensureRemoteAudioElement(numericPeerId);
            if (audioEl.srcObject !== stream) {
                audioEl.srcObject = stream;
            }
            audioEl.play().catch(() => {});
            startRemoteSpeakingDetection(numericPeerId);
        }

        if (event.track.kind === 'video' && !activeRemotePeerId) {
            activeRemotePeerId = numericPeerId;
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
        if (state === 'disconnected') {
            setTimeout(() => {
                if (pc.connectionState === 'disconnected') {
                    removePeerConnection(numericPeerId);
                }
            }, 5000);
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
        state.screenSharing = Boolean(participant?.screen_sharing);
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
        if (Object.prototype.hasOwnProperty.call(payload, 'screen_sharing')) {
            state.screenSharing = Boolean(payload.screen_sharing);
            refreshActiveRemoteTile();
        }
    }
}

async function startCallSignaling() {
    if (callSignalPollInterval) {
        clearInterval(callSignalPollInterval);
        callSignalPollInterval = null;
    }

    callSignalCursor = 0;
    await pollCallSignal();
    callSignalPollInterval = setInterval(pollCallSignal, 1200);
}

async function pollCallSignal() {
    if (!currentCallId) return;
    try {
        const response = await fetch(`/api/calls/signal/${currentCallId}?since_id=${callSignalCursor}`);
        const data = await response.json();
        if (!data || data.error) return;

        if (Number.isFinite(Number(data?.next_since_id))) {
            callSignalCursor = Math.max(callSignalCursor, Number(data.next_since_id || 0));
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
    await startVoiceCall();
}

async function declineCall() {
    const declinedId = Number(lastIncomingCallAlertId || currentCallId || 0);
    declinedCallId = declinedId;
    stopCallRingingAudio();
    clearAcceptedCallNotifications();

    if (declinedId > 0) {
        const result = await postForm('/api/calls/decline', {
            csrf_token: getCsrfToken(),
            call_id: String(declinedId)
        });

        if (!result.success) {
            showToast(result.error || 'Unable to decline call', 'error');
        }
    }

    document.getElementById('accept-call-btn')?.classList.add('hidden');
    document.getElementById('decline-call-btn')?.classList.add('hidden');
    document.getElementById('join-call-btn')?.classList.remove('hidden');
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
    container.style.borderColor = speaking ? '#34d399' : '';
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
        if (isMuted) return;
        setTileSpeaking(document.getElementById('local-media-container'), speaking);
    });
    localSpeakingRaf = handle;
}

function stopLocalSpeakingDetection() {
    if (localSpeakingRaf) {
        localSpeakingRaf.stop();
        localSpeakingRaf = null;
    }
    setTileSpeaking(document.getElementById('local-media-container'), false);
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
    const tile = document.querySelector(`.js-remote-peer-tile[data-peer-id="${numericPeerId}"]`);
    if (tile) {
        setTileSpeaking(tile.querySelector('div'), speaking);
    }
}
