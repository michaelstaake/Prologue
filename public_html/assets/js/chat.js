// Extracted from app.js for feature-focused organization.

let messageInteractionHandlersBound = false;
let openReactionPickerMessageId = 0;
let lastRenderedChatId = 0;
let lastRenderedMessagesSignature = '';
let pendingPinReplaceMessageId = 0;
let pinnedBannerLastScrollTop = 0;
let pinnedBannerHiddenByScroll = false;

const PINNED_BANNER_MOBILE_BREAKPOINT_QUERY = '(max-width: 767.98px)';
const PINNED_BANNER_SCROLL_DELTA_PX = 6;

function isPinnedBannerMobileViewport() {
    if (typeof window.matchMedia !== 'function') return false;
    return window.matchMedia(PINNED_BANNER_MOBILE_BREAKPOINT_QUERY).matches;
}

function setPinnedBannerScrollVisibility(isVisible) {
    const banner = document.getElementById('pinned-message-banner');
    if (!banner) return;

    const hasPinnedMessage = Number(banner.dataset.pinnedMessageId || 0) > 0;
    if (!hasPinnedMessage || !isPinnedBannerMobileViewport()) {
        banner.classList.remove('-translate-y-full', 'opacity-0', 'pointer-events-none', 'max-h-0', 'py-0', 'border-b-0', 'overflow-hidden');
        if (!banner.classList.contains('py-3')) {
            banner.classList.add('py-3');
        }
        if (!banner.classList.contains('border-b')) {
            banner.classList.add('border-b');
        }
        if (!hasPinnedMessage) {
            pinnedBannerHiddenByScroll = false;
        }
        return;
    }

    pinnedBannerHiddenByScroll = !isVisible;
    banner.classList.toggle('-translate-y-full', !isVisible);
    banner.classList.toggle('opacity-0', !isVisible);
    banner.classList.toggle('pointer-events-none', !isVisible);
    banner.classList.toggle('max-h-0', !isVisible);
    banner.classList.toggle('py-0', !isVisible);
    banner.classList.toggle('border-b-0', !isVisible);
    banner.classList.toggle('overflow-hidden', !isVisible);

    banner.classList.toggle('py-3', isVisible);
    banner.classList.toggle('border-b', isVisible);
}

function bindPinnedBannerScrollBehavior(messagesBox) {
    if (!messagesBox || messagesBox.dataset.pinnedScrollBound === '1') return;

    messagesBox.dataset.pinnedScrollBound = '1';
    pinnedBannerLastScrollTop = Math.max(0, messagesBox.scrollTop || 0);

    messagesBox.addEventListener('scroll', () => {
        const currentScrollTop = Math.max(0, messagesBox.scrollTop || 0);
        const delta = currentScrollTop - pinnedBannerLastScrollTop;

        if (Math.abs(delta) < PINNED_BANNER_SCROLL_DELTA_PX) {
            return;
        }

        if (delta < 0) {
            setPinnedBannerScrollVisibility(false);
        } else {
            setPinnedBannerScrollVisibility(true);
        }

        pinnedBannerLastScrollTop = currentScrollTop;
    }, { passive: true });

    window.addEventListener('resize', () => {
        setPinnedBannerScrollVisibility(true);
    });
}

const MESSAGE_REACTION_OPTIONS = [
    { code: '1F44D', label: 'Like' },
    { code: '1F44E', label: 'Dislike' },
    { code: '2665', label: 'Love' },
    { code: '1F923', label: 'Laugh' },
    { code: '1F622', label: 'Cry' },
    { code: '1F436', label: 'Pup' },
    { code: '1F4A9', label: 'Poop' }
];

const MESSAGE_REACTION_LABEL_BY_CODE = new Map(MESSAGE_REACTION_OPTIONS.map((entry) => [entry.code, entry.label]));

function normalizeEmojiKey(value) {
    return String(value || '').replace(/\.svg$/i, '').trim().toUpperCase();
}

function emojiKeyToChar(key) {
    const tokens = normalizeEmojiKey(key).split('-').filter(Boolean);
    if (tokens.length === 0) return '';

    try {
        return tokens.map((token) => {
            const codePoint = Number.parseInt(token, 16);
            if (!Number.isFinite(codePoint)) {
                throw new Error('Invalid code point');
            }
            return String.fromCodePoint(codePoint);
        }).join('');
    } catch {
        return '';
    }
}

function splitEmojiKeywordList(value) {
    return String(value || '')
        .split(',')
        .map((keyword) => keyword.trim().toLowerCase())
        .filter(Boolean);
}

function tokenizeEmojiText(value) {
    return String(value || '')
        .toLowerCase()
        .replace(/[\-_]/g, ' ')
        .split(/[^\p{L}\p{N}]+/u)
        .map((token) => token.trim())
        .filter(Boolean);
}

function normalizeOpenMojiMetadataByKey() {
    if (openMojiMetadataByKey) return openMojiMetadataByKey;

    const source = window.OPENMOJI_METADATA;
    if (!source || typeof source !== 'object') {
        openMojiMetadataByKey = {};
        return openMojiMetadataByKey;
    }

    const normalized = {};
    Object.entries(source).forEach(([key, value]) => {
        normalized[normalizeEmojiKey(key)] = value;
    });
    openMojiMetadataByKey = normalized;
    return openMojiMetadataByKey;
}

function getEmojiKeywordsForKey(key) {
    const normalizedKey = normalizeEmojiKey(key);
    const metadataByKey = normalizeOpenMojiMetadataByKey();
    const metadata = metadataByKey[normalizedKey] || metadataByKey[normalizedKey.replace(/(?:-)?FE0F/gi, '')] || null;

    const keywords = new Set();

    if (metadata) {
        tokenizeEmojiText(metadata.annotation).forEach((token) => keywords.add(token));
        splitEmojiKeywordList(metadata.tags).forEach((token) => keywords.add(token));
        splitEmojiKeywordList(metadata.openmoji_tags).forEach((token) => keywords.add(token));
        tokenizeEmojiText(metadata.group).forEach((token) => keywords.add(token));
        tokenizeEmojiText(metadata.subgroups).forEach((token) => keywords.add(token));
    }

    for (const rule of EMOJI_KEYWORD_RULES) {
        if (rule.pattern.test(normalizedKey)) {
            for (const keyword of rule.keywords) {
                keywords.add(keyword);
            }
        }
    }

    if (normalizedKey.includes('-200D-')) {
        keywords.add('zwj');
        keywords.add('sequence');
    }

    if (normalizedKey.includes('-1F3F')) {
        keywords.add('skin tone');
    }

    return Array.from(keywords).map((keyword) => String(keyword).trim().toLowerCase()).filter(Boolean);
}

function initializeOpenMojiCatalog() {
    if (openMojiCatalog.length > 0) return;

    const files = Array.isArray(window.OPENMOJI_FILES) ? window.OPENMOJI_FILES : [];
    const metadataByKey = normalizeOpenMojiMetadataByKey();

    openMojiByKey = new Map();
    openMojiCatalog = files.map((fileName) => {
        const key = normalizeEmojiKey(fileName);
        const character = emojiKeyToChar(key);
        const keywords = getEmojiKeywordsForKey(key);
        const metadata = metadataByKey[key] || metadataByKey[key.replace(/(?:-)?FE0F/gi, '')] || {};
        const annotation = String(metadata.annotation || '').toLowerCase();
        const item = {
            key,
            keyLower: key.toLowerCase(),
            fileName,
            character,
            url: `/emojis/${fileName}`,
            keywords,
            annotation,
            searchText: [key.toLowerCase(), character, annotation, ...keywords].join(' ')
        };

        openMojiByKey.set(key, item);
        return item;
    }).filter((item) => item.key);
}

function getGraphemeClusters(text) {
    const value = String(text ?? '');

    if (typeof Intl !== 'undefined' && typeof Intl.Segmenter === 'function') {
        const segmenter = new Intl.Segmenter(undefined, { granularity: 'grapheme' });
        return Array.from(segmenter.segment(value), (segment) => segment.segment);
    }

    return Array.from(value);
}

function buildEmojiCandidateKeys(grapheme) {
    const codePoints = Array.from(grapheme).map((char) => char.codePointAt(0).toString(16).toUpperCase());
    if (codePoints.length === 0) return [];

    const exact = codePoints.join('-');
    const withoutVariation = codePoints.filter((token) => token !== 'FE0F');
    const withoutVariationKey = withoutVariation.join('-');

    const candidates = [exact];
    if (withoutVariation.length > 0 && withoutVariationKey !== exact) {
        candidates.push(withoutVariationKey);
    }

    return candidates;
}

function findOpenMojiForGrapheme(grapheme) {
    initializeOpenMojiCatalog();
    if (openMojiByKey.size === 0) return null;

    const candidates = buildEmojiCandidateKeys(grapheme);
    for (const candidate of candidates) {
        const match = openMojiByKey.get(candidate);
        if (match) return match;
    }

    return null;
}

function renderTextWithEmojiOnly(content) {
    const graphemes = getGraphemeClusters(String(content ?? ''));
    let html = '';

    for (const grapheme of graphemes) {
        if (grapheme === '\n') {
            html += '<br>';
            continue;
        }

        const emojiMatch = findOpenMojiForGrapheme(grapheme);
        if (emojiMatch?.url) {
            html += `<img src="${escapeHtml(emojiMatch.url)}" alt="${escapeHtml(grapheme)}" class="inline-block w-7 h-7 align-[-0.2em] mx-[1px]" loading="lazy" decoding="async">`;
            continue;
        }

        html += escapeHtml(grapheme);
    }

    return html;
}

function renderPlainTextWithEmoji(content) {
    const source = String(content ?? '');
    const linkPattern = /(https?:\/\/[^\s<]+|\/c\/\d{4}-\d{4}-\d{4}-\d{4}\/delete)/gi;
    let html = '';
    let cursor = 0;
    let match = linkPattern.exec(source);

    while (match) {
        const tokenStart = match.index;
        if (tokenStart > cursor) {
            html += renderTextWithEmojiOnly(source.slice(cursor, tokenStart));
        }

        const matchedUrl = String(match[0] || '');
        if (/^\/c\/\d{4}-\d{4}-\d{4}-\d{4}\/delete$/i.test(matchedUrl)) {
            html += `<a href="${escapeHtml(matchedUrl)}" class="text-red-400 hover:text-red-300 hover:underline underline-offset-2">Delete chat</a>`;
        } else {
            html += `<a href="${escapeHtml(matchedUrl)}" target="_blank" rel="noopener noreferrer" class="prologue-accent hover:text-emerald-300 hover:underline underline-offset-2">${escapeHtml(matchedUrl)}</a>`;
        }

        cursor = linkPattern.lastIndex;
        match = linkPattern.exec(source);
    }

    if (cursor < source.length) {
        html += renderTextWithEmojiOnly(source.slice(cursor));
    }

    return html;
}

function normalizeMentionMap(mentionMap) {
    if (!mentionMap || typeof mentionMap !== 'object' || Array.isArray(mentionMap)) {
        return {};
    }

    const normalized = {};
    Object.entries(mentionMap).forEach(([userNumber, username]) => {
        const safeUserNumber = String(userNumber || '').replace(/\D/g, '').slice(0, 16);
        const safeUsername = String(username || '').toLowerCase();
        if (!safeUserNumber || !/^[a-z][a-z0-9]{3,31}$/i.test(safeUsername)) {
            return;
        }
        normalized[safeUserNumber] = safeUsername;
    });

    return normalized;
}

function decodeStoredMentionsToPlainText(content) {
    return String(content ?? '').replace(/@\[(\d{16})\|([a-z][a-z0-9]{3,31})\]/gi, '@$2');
}

function renderMessageContent(content, mentionMap = null) {
    const normalizedMentionMap = normalizeMentionMap(mentionMap);
    const source = String(content ?? '');
    const mentionPattern = /@\[(\d{16})\|([a-z][a-z0-9]{3,31})\]/gi;
    let html = '';
    let cursor = 0;
    let match = mentionPattern.exec(source);

    while (match) {
        const tokenStart = match.index;
        if (tokenStart > cursor) {
            html += renderPlainTextWithEmoji(source.slice(cursor, tokenStart));
        }

        const userNumber = String(match[1] || '').replace(/\D/g, '').slice(0, 16);
        const fallbackUsername = String(match[2] || '').toLowerCase();
        const displayUsername = String(normalizedMentionMap[userNumber] || fallbackUsername).toLowerCase();
        const profileUrl = getProfileUrlByUserNumber(userNumber);

        if (profileUrl) {
            html += `<a href="${escapeHtml(profileUrl)}" class="prologue-accent hover:text-emerald-300 hover:underline underline-offset-2">@${escapeHtml(displayUsername)}</a>`;
        } else {
            html += `@${escapeHtml(displayUsername)}`;
        }

        cursor = mentionPattern.lastIndex;
        match = mentionPattern.exec(source);
    }

    if (cursor < source.length) {
        html += renderPlainTextWithEmoji(source.slice(cursor));
    }

    return html;
}

function normalizeReactionCode(value) {
    const normalized = String(value || '').toUpperCase().replace(/[^0-9A-F]/g, '');
    if (!normalized) return '';
    return MESSAGE_REACTION_LABEL_BY_CODE.has(normalized) ? normalized : '';
}

function getReactionImageUrlByCode(reactionCode) {
    initializeOpenMojiCatalog();

    const code = normalizeReactionCode(reactionCode);
    if (!code) return '';

    const keyCandidates = [code, `${code}-FE0F`, code.replace(/(?:-)?FE0F/gi, '')]
        .map((candidate) => normalizeEmojiKey(candidate))
        .filter(Boolean);

    for (const key of keyCandidates) {
        const item = openMojiByKey.get(key);
        if (item?.url) {
            return item.url;
        }
    }

    return '';
}

function reactionCodeToChar(reactionCode) {
    const code = normalizeReactionCode(reactionCode);
    if (!code) return '';
    return emojiKeyToChar(code);
}

function renderReactionEmojiMarkup(reactionCode, sizeClass = 'w-4 h-4') {
    const code = normalizeReactionCode(reactionCode);
    if (!code) return '';

    const charValue = reactionCodeToChar(code);
    const imageUrl = getReactionImageUrlByCode(code);
    const fallbackLabel = MESSAGE_REACTION_LABEL_BY_CODE.get(code) || 'Reaction';

    if (imageUrl) {
        return `<img src="${escapeHtml(imageUrl)}" alt="${escapeHtml(charValue || fallbackLabel)}" class="${escapeHtml(sizeClass)}" loading="lazy" decoding="async">`;
    }

    return `<span>${escapeHtml(charValue || fallbackLabel)}</span>`;
}

function decodeStoredMentionsWithMap(content, mentionMap = null) {
    const normalizedMentionMap = normalizeMentionMap(mentionMap);
    return String(content ?? '').replace(/@\[(\d{16})\|([a-z][a-z0-9]{3,31})\]/gi, (_full, userNumber, fallbackUsername) => {
        const normalizedUserNumber = String(userNumber || '').replace(/\D/g, '').slice(0, 16);
        const resolvedUsername = String(normalizedMentionMap[normalizedUserNumber] || fallbackUsername || '').toLowerCase();
        return `@${resolvedUsername}`;
    });
}

function getQuotedPreviewText(content, mentionMap = null) {
    const decoded = decodeStoredMentionsWithMap(content, mentionMap);
    return decoded.replace(/\s+/g, ' ').trim();
}

function renderQuotedMessageBlock(msg) {
    const quotedContentRaw = String(msg?.quoted_content || '');
    if (!quotedContentRaw.trim()) {
        return '';
    }

    const quotedMessageId = Number(msg?.quoted_message_id || 0);
    const hasQuotedMessageTarget = Number.isFinite(quotedMessageId) && quotedMessageId > 0;
    const quotedMentionMap = normalizeMentionMap(msg?.quote_mention_map || {});
    const quotedUserNumber = String(msg?.quoted_user_number || '').replace(/\D/g, '').slice(0, 16);
    const quotedUsername = String(msg?.quoted_username || 'Unknown user').trim() || 'Unknown user';
    const quotedProfileUrl = quotedUserNumber ? getProfileUrlByUserNumber(quotedUserNumber) : '';

    const blockAttributes = hasQuotedMessageTarget
        ? ` class="js-quoted-message-link mt-1.5 mb-2 p-2 rounded-lg border border-zinc-700 bg-zinc-900/70 text-sm hover:bg-zinc-800/70 transition-colors cursor-pointer" data-quoted-message-id="${quotedMessageId}" title="Go to quoted message"`
        : ' class="mt-1.5 mb-2 p-2 rounded-lg border border-zinc-700 bg-zinc-900/70 text-sm"';

    return `
        <div${blockAttributes}>
            <div class="text-zinc-400 text-xs mb-1">${quotedProfileUrl ? `<a href="${escapeHtml(quotedProfileUrl)}" class="hover:underline underline-offset-2">${escapeHtml(quotedUsername)}</a>` : escapeHtml(quotedUsername)}</div>
            <div class="text-zinc-300 leading-5 line-clamp-3">${renderMessageContent(quotedContentRaw, quotedMentionMap)}</div>
        </div>
    `;
}

function renderReactionBadgesMarkup(messageId, reactions) {
    if (!Array.isArray(reactions) || reactions.length === 0) {
        return '';
    }

    const badges = reactions
        .map((reaction) => {
            const reactionCode = normalizeReactionCode(reaction?.reaction_code || '');
            if (!reactionCode) return '';

            const reactionCount = Math.max(0, Number(reaction?.count || 0));
            if (reactionCount <= 0) return '';

            const users = Array.isArray(reaction?.users) ? reaction.users.map((user) => String(user || '').trim()).filter(Boolean) : [];
            const reactionLabel = MESSAGE_REACTION_LABEL_BY_CODE.get(reactionCode) || 'Reaction';
            const tooltip = `${reactionLabel}: ${users.length ? users.join(', ') : 'No users'}`;
            const reactedByCurrentUser = reaction?.reacted_by_current_user === true || Number(reaction?.reacted_by_current_user || 0) === 1;
            const badgeClass = reactedByCurrentUser
                ? 'bg-zinc-700 border-zinc-500 text-zinc-100'
                : 'bg-zinc-800 border-zinc-700 text-zinc-300 hover:bg-zinc-700';

            return `
                <button type="button" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full border text-[12px] js-reaction-badge ${badgeClass}" data-reaction-message-id="${Number(messageId)}" data-reaction-code="${escapeHtml(reactionCode)}" title="${escapeHtml(tooltip)}">
                    ${renderReactionEmojiMarkup(reactionCode, 'w-6 h-6')}
                    <span>${escapeHtml(String(reactionCount))}</span>
                </button>
            `;
        })
        .filter(Boolean)
        .join('');

    if (!badges) return '';
    return `<div class="flex items-center gap-1.5">${badges}</div>`;
}

function renderReactionPickerMarkup(messageId) {
    const options = MESSAGE_REACTION_OPTIONS.map((option) => `
        <button type="button" class="w-10 h-10 rounded-full hover:bg-zinc-800 flex items-center justify-center js-reaction-option" data-reaction-message-id="${Number(messageId)}" data-reaction-code="${escapeHtml(option.code)}" title="${escapeHtml(option.label)}">
            ${renderReactionEmojiMarkup(option.code, 'w-7 h-7')}
        </button>
    `).join('');

    return `
        <div class="hidden js-reaction-picker absolute left-0 bottom-full mb-1.5 z-30" data-reaction-picker-for="${Number(messageId)}">
            <div class="inline-flex items-center gap-1.5 bg-zinc-900 border border-zinc-700 rounded-full px-2 py-1">
                ${options}
            </div>
        </div>
    `;
}

function closeAllReactionPickers(resetState = true) {
    const pickers = document.querySelectorAll('.js-reaction-picker');
    pickers.forEach((picker) => picker.classList.add('hidden'));
    if (resetState) {
        openReactionPickerMessageId = 0;
    }
}

function toggleReactionPicker(messageId) {
    const safeMessageId = Number(messageId || 0);
    if (!Number.isFinite(safeMessageId) || safeMessageId <= 0) return;

    const picker = document.querySelector(`.js-reaction-picker[data-reaction-picker-for="${safeMessageId}"]`);
    if (!picker) return;

    const willOpen = picker.classList.contains('hidden');
    closeAllReactionPickers(false);
    if (willOpen) {
        picker.classList.remove('hidden');
        openReactionPickerMessageId = safeMessageId;
    } else {
        openReactionPickerMessageId = 0;
    }
}

function setQuoteDraft(quoteData) {
    const hiddenInput = document.getElementById('quoted-message-id');
    const preview = document.getElementById('quote-preview');
    const previewUsername = document.getElementById('quote-preview-username');
    const previewContent = document.getElementById('quote-preview-content');
    if (!hiddenInput || !preview || !previewUsername || !previewContent) return;

    const messageId = Number(quoteData?.messageId || 0);
    if (!Number.isFinite(messageId) || messageId <= 0) {
        hiddenInput.value = '';
        preview.classList.add('hidden');
        previewUsername.textContent = '';
        previewContent.innerHTML = '';
        return;
    }

    const username = String(quoteData?.username || 'Unknown user').trim() || 'Unknown user';
    const content = String(quoteData?.content || '');
    const mentionMap = normalizeMentionMap(quoteData?.mentionMap || {});

    hiddenInput.value = String(messageId);
    previewUsername.textContent = username;
    previewContent.innerHTML = renderMessageContent(getQuotedPreviewText(content, mentionMap), mentionMap);
    preview.classList.remove('hidden');
}

function clearQuoteDraft() {
    setQuoteDraft(null);
}

async function reactToMessage(messageId, reactionCode) {
    const safeMessageId = Number(messageId || 0);
    const safeReactionCode = normalizeReactionCode(reactionCode);
    if (!Number.isFinite(safeMessageId) || safeMessageId <= 0 || !safeReactionCode) {
        return;
    }

    const result = await postForm('/api/messages/react', {
        csrf_token: getCsrfToken(),
        message_id: String(safeMessageId),
        reaction_code: safeReactionCode
    });

    if (!result.success) {
        showToast(result.error || 'Unable to react to message', 'error');
        return;
    }

    closeAllReactionPickers();
    await pollMessages({ scrollMode: 'preserve' });
}

function normalizePinnedMessage(pinnedMessage) {
    if (!pinnedMessage || typeof pinnedMessage !== 'object') {
        return null;
    }

    const id = Number(pinnedMessage.id || 0);
    if (!Number.isFinite(id) || id <= 0) {
        return null;
    }

    return {
        id,
        chat_id: Number(pinnedMessage.chat_id || 0),
        user_id: Number(pinnedMessage.user_id || 0),
        username: String(pinnedMessage.username || 'Unknown user').trim() || 'Unknown user',
        user_number: String(pinnedMessage.user_number || ''),
        created_at: String(pinnedMessage.created_at || ''),
        content: String(pinnedMessage.content || ''),
        mention_map: normalizeMentionMap(pinnedMessage.mention_map || {})
    };
}

function renderPinnedMessageBanner(pinnedMessage) {
    const banner = document.getElementById('pinned-message-banner');
    const usernameNode = document.getElementById('pinned-message-username');
    const timeNode = document.getElementById('pinned-message-time');
    const contentNode = document.getElementById('pinned-message-content');
    const goToButton = document.getElementById('pinned-message-goto');
    const unpinButton = document.getElementById('pinned-message-unpin');
    if (!banner || !usernameNode || !timeNode || !contentNode) return;

    const safePinnedMessage = normalizePinnedMessage(pinnedMessage);
    if (!safePinnedMessage) {
        banner.classList.add('hidden');
        banner.classList.remove('-translate-y-full', 'opacity-0', 'pointer-events-none');
        banner.dataset.pinnedMessageId = '0';
        pinnedBannerHiddenByScroll = false;
        usernameNode.textContent = '';
        delete timeNode.dataset.utc;
        timeNode.removeAttribute('title');
        timeNode.textContent = '';
        contentNode.innerHTML = '';
        contentNode.dataset.rawContent = '';
        contentNode.dataset.mentionMap = '{}';
        if (unpinButton) {
            unpinButton.disabled = true;
            unpinButton.classList.add('opacity-50', 'cursor-not-allowed');
        }
        if (goToButton) {
            goToButton.disabled = true;
            goToButton.classList.add('opacity-50', 'cursor-not-allowed');
        }
        return;
    }

    banner.classList.remove('hidden');
    banner.dataset.pinnedMessageId = String(safePinnedMessage.id);
    setPinnedBannerScrollVisibility(!pinnedBannerHiddenByScroll);
    usernameNode.textContent = safePinnedMessage.username;
    timeNode.dataset.utc = safePinnedMessage.created_at;
    timeNode.title = safePinnedMessage.created_at;
    timeNode.textContent = formatCompactMessageTimestamp(safePinnedMessage.created_at);
    contentNode.dataset.rawContent = safePinnedMessage.content;
    contentNode.dataset.mentionMap = JSON.stringify(safePinnedMessage.mention_map || {});
    contentNode.innerHTML = renderMessageContent(safePinnedMessage.content, safePinnedMessage.mention_map || {});
    if (typeof window.refreshUtcTimestamps === 'function') {
        window.refreshUtcTimestamps(timeNode);
    }
    if (unpinButton) {
        unpinButton.disabled = false;
        unpinButton.classList.remove('opacity-50', 'cursor-not-allowed');
    }
    if (goToButton) {
        goToButton.disabled = false;
        goToButton.classList.remove('opacity-50', 'cursor-not-allowed');
    }
}

async function unpinCurrentChatMessage() {
    if (!currentChat) {
        return;
    }

    const result = await postForm('/api/messages/unpin', {
        csrf_token: getCsrfToken(),
        chat_id: String(currentChat.id)
    });

    if (!result.success) {
        showToast(result.error || 'Unable to unpin message', 'error');
        return;
    }

    currentChat.pinned_message = null;
    renderPinnedMessageBanner(null);
    showToast('Pinned message removed', 'success');
}

function bindPinnedMessageBannerActions() {
    const goToButton = document.getElementById('pinned-message-goto');
    const unpinButton = document.getElementById('pinned-message-unpin');
    if (!goToButton && !unpinButton) {
        return;
    }

    if (goToButton && goToButton.dataset.bound !== '1') {
        goToButton.dataset.bound = '1';
        goToButton.addEventListener('click', (event) => {
            event.preventDefault();
            if (goToButton.disabled) {
                return;
            }

            const box = document.getElementById('messages');
            const pinnedMessageId = Number(currentChat?.pinned_message?.id || 0);
            if (!box || !Number.isFinite(pinnedMessageId) || pinnedMessageId <= 0) {
                showToast('Pinned message not available', 'error');
                return;
            }

            const focused = focusMessageById(pinnedMessageId);
            if (!focused) {
                showToast('Pinned message is not in the loaded history', 'error');
                return;
            }
        });
    }

    if (!unpinButton || unpinButton.dataset.bound === '1') {
        return;
    }
    unpinButton.dataset.bound = '1';

    unpinButton.addEventListener('click', (event) => {
        event.preventDefault();
        if (unpinButton.disabled) {
            return;
        }

        unpinButton.disabled = true;
        unpinCurrentChatMessage()
            .catch(() => {
                showToast('Unable to unpin message', 'error');
            })
            .finally(() => {
                const hasPinnedMessage = Number(currentChat?.pinned_message?.id || 0) > 0;
                unpinButton.disabled = !hasPinnedMessage;
                unpinButton.classList.toggle('opacity-50', !hasPinnedMessage);
                unpinButton.classList.toggle('cursor-not-allowed', !hasPinnedMessage);
                if (goToButton) {
                    goToButton.disabled = !hasPinnedMessage;
                    goToButton.classList.toggle('opacity-50', !hasPinnedMessage);
                    goToButton.classList.toggle('cursor-not-allowed', !hasPinnedMessage);
                }
            });
    });
}

function bindPinReplaceModal() {
    const modal = document.getElementById('pin-replace-modal');
    const form = document.getElementById('pin-replace-form');
    const cancel = document.getElementById('pin-replace-cancel');
    const submit = document.getElementById('pin-replace-submit');
    if (!modal || !form || !cancel || !submit) return;

    if (modal.dataset.bound === '1') {
        return;
    }
    modal.dataset.bound = '1';

    const setOpenState = (isOpen) => {
        modal.classList.toggle('hidden', !isOpen);
        if (!isOpen) {
            pendingPinReplaceMessageId = 0;
            submit.disabled = false;
            submit.textContent = 'Replace';
        }
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

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (submit.disabled) return;

        const messageId = Number(pendingPinReplaceMessageId || 0);
        if (!Number.isFinite(messageId) || messageId <= 0) {
            closeModal();
            return;
        }

        submit.disabled = true;
        submit.textContent = 'Replacing...';

        try {
            await pinMessageInCurrentChat(messageId, true);
        } finally {
            closeModal();
        }
    });

    window.openPinReplaceModal = (messageId) => {
        pendingPinReplaceMessageId = Number(messageId || 0);
        if (!Number.isFinite(pendingPinReplaceMessageId) || pendingPinReplaceMessageId <= 0) {
            pendingPinReplaceMessageId = 0;
            return;
        }

        setOpenState(true);
    };
}

async function pinMessageInCurrentChat(messageId, forceReplace = false) {
    const safeMessageId = Number(messageId || 0);
    if (!currentChat || !Number.isFinite(safeMessageId) || safeMessageId <= 0) {
        return;
    }

    const result = await postForm('/api/messages/pin', {
        csrf_token: getCsrfToken(),
        chat_id: String(currentChat.id),
        message_id: String(safeMessageId),
        force_replace: forceReplace ? '1' : '0'
    });

    if (result.success) {
        const pinnedMessage = normalizePinnedMessage(result.pinned_message);
        currentChat.pinned_message = pinnedMessage;
        renderPinnedMessageBanner(pinnedMessage);
        showToast(result.replaced ? 'Pinned message replaced' : 'Message pinned', 'success');
        return;
    }

    if (result.requires_confirm) {
        if (typeof window.openPinReplaceModal === 'function') {
            window.openPinReplaceModal(safeMessageId);
        }
        return;
    }

    showToast(result.error || 'Unable to pin message', 'error');
}

function bindMessageQuotesAndReactions() {
    if (messageInteractionHandlersBound) return;

    const messagesBox = document.getElementById('messages');
    if (!messagesBox) return;

    messageInteractionHandlersBound = true;
    bindPinReplaceModal();
    bindPinnedMessageBannerActions();

    const initialPinnedMessage = normalizePinnedMessage(window.INITIAL_PINNED_MESSAGE || null);
    if (currentChat) {
        currentChat.pinned_message = initialPinnedMessage;
    }
    renderPinnedMessageBanner(initialPinnedMessage);
    bindPinnedBannerScrollBehavior(messagesBox);

    messagesBox.addEventListener('click', (event) => {
        const quotedMessageLink = event.target.closest('.js-quoted-message-link');
        if (quotedMessageLink) {
            if (event.target.closest('a')) {
                return;
            }
            event.preventDefault();
            const quotedMessageId = Number(quotedMessageLink.getAttribute('data-quoted-message-id') || 0);
            const focused = focusMessageById(quotedMessageId);
            if (!focused) {
                showToast('Quoted message is not in the loaded history', 'error');
            }
            closeAllReactionPickers();
            return;
        }

        const quoteButton = event.target.closest('.js-quote-link');
        if (quoteButton) {
            event.preventDefault();
            const messageId = Number(quoteButton.getAttribute('data-quote-message-id') || 0);
            const username = String(quoteButton.getAttribute('data-quote-username') || '');
            const content = String(quoteButton.getAttribute('data-quote-content') || '');

            let mentionMap = {};
            try {
                mentionMap = JSON.parse(quoteButton.getAttribute('data-quote-mention-map') || '{}');
            } catch {
                mentionMap = {};
            }

            setQuoteDraft({ messageId, username, content, mentionMap });

            const input = document.getElementById('message-input');
            if (input) {
                input.focus();
            }
            closeAllReactionPickers();
            return;
        }

        const reactLink = event.target.closest('.js-react-link');
        if (reactLink) {
            event.preventDefault();
            const messageId = Number(reactLink.getAttribute('data-react-message-id') || 0);
            toggleReactionPicker(messageId);
            return;
        }

        const pinLink = event.target.closest('.js-pin-link');
        if (pinLink) {
            event.preventDefault();
            const messageId = Number(pinLink.getAttribute('data-pin-message-id') || 0);
            const activePinnedMessageId = Number(currentChat?.pinned_message?.id || 0);

            if (activePinnedMessageId > 0 && activePinnedMessageId !== messageId) {
                if (typeof window.openPinReplaceModal === 'function') {
                    window.openPinReplaceModal(messageId);
                }
                return;
            }

            if (activePinnedMessageId === messageId) {
                showToast('That message is already pinned', 'success');
                return;
            }

            pinMessageInCurrentChat(messageId).catch(() => {
                showToast('Unable to pin message', 'error');
            });
            return;
        }

        const reactionOption = event.target.closest('.js-reaction-option');
        if (reactionOption) {
            event.preventDefault();
            const messageId = Number(reactionOption.getAttribute('data-reaction-message-id') || 0);
            const reactionCode = String(reactionOption.getAttribute('data-reaction-code') || '');
            reactToMessage(messageId, reactionCode).catch(() => {
                showToast('Unable to react to message', 'error');
            });
            return;
        }

        const reactionBadge = event.target.closest('.js-reaction-badge');
        if (reactionBadge) {
            event.preventDefault();
            const messageId = Number(reactionBadge.getAttribute('data-reaction-message-id') || 0);
            const reactionCode = String(reactionBadge.getAttribute('data-reaction-code') || '');
            reactToMessage(messageId, reactionCode).catch(() => {
                showToast('Unable to remove reaction', 'error');
            });
            return;
        }

        if (!event.target.closest('.js-reaction-picker')) {
            closeAllReactionPickers();
        }
    });

    const cancelQuoteButton = document.getElementById('quote-preview-cancel');
    if (cancelQuoteButton) {
        cancelQuoteButton.addEventListener('click', (event) => {
            event.preventDefault();
            clearQuoteDraft();
        });
    }

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeAllReactionPickers();
        }
    });
}

function applyEmojiRenderingToExistingMessages() {
    const messageNodes = document.querySelectorAll('#messages .js-message-content');
    if (!messageNodes.length) return;

    messageNodes.forEach((messageNode) => {
        const rawContent = messageNode.dataset.rawContent ?? messageNode.textContent ?? '';
        let mentionMap = {};
        try {
            mentionMap = JSON.parse(messageNode.dataset.mentionMap || '{}');
        } catch {
            mentionMap = {};
        }
        messageNode.innerHTML = renderMessageContent(rawContent, mentionMap);
    });
}

function getDefaultEmojiItems() {
    initializeOpenMojiCatalog();

    const defaults = DEFAULT_EMOJI_KEYS
        .map((key) => openMojiByKey.get(normalizeEmojiKey(key)))
        .filter(Boolean);

    if (defaults.length > 0) return defaults;

    return openMojiCatalog.filter((item) => item.character).slice(0, 80);
}

function getEmojiSearchScore(item, normalizedQuery, queryTokens, rawQuery) {
    let score = 0;
    const annotation = String(item.annotation || '');
    const keywords = Array.isArray(item.keywords) ? item.keywords : [];

    if (item.character && item.character === rawQuery) {
        score += 2000;
    }

    if (annotation === normalizedQuery) {
        score += 1000;
    } else if (annotation.startsWith(normalizedQuery)) {
        score += 700;
    } else if (annotation.includes(normalizedQuery)) {
        score += 450;
    }

    if (item.keyLower === normalizedQuery) {
        score += 900;
    } else if (item.keyLower.startsWith(normalizedQuery)) {
        score += 550;
    } else if (item.keyLower.includes(normalizedQuery)) {
        score += 250;
    }

    for (const keyword of keywords) {
        if (keyword === normalizedQuery) {
            score += 850;
            continue;
        }
        if (keyword.startsWith(normalizedQuery)) {
            score += 500;
            continue;
        }
        if (keyword.includes(normalizedQuery)) {
            score += 180;
        }
    }

    for (const token of queryTokens) {
        if (!token) continue;

        if (annotation === token) {
            score += 120;
        } else if (annotation.startsWith(token)) {
            score += 80;
        } else if (annotation.includes(token)) {
            score += 40;
        }

        if (item.keyLower === token) {
            score += 100;
        } else if (item.keyLower.startsWith(token)) {
            score += 70;
        } else if (item.keyLower.includes(token)) {
            score += 30;
        }

        for (const keyword of keywords) {
            if (keyword === token) {
                score += 90;
            } else if (keyword.startsWith(token)) {
                score += 60;
            } else if (keyword.includes(token)) {
                score += 25;
            }
        }
    }

    if (queryTokens.length > 1) {
        const joined = queryTokens.join(' ');
        if (annotation.includes(joined)) {
            score += 150;
        }
        if (keywords.some((keyword) => keyword.includes(joined))) {
            score += 130;
        }
    }

    return score;
}

function findEmojiTypeaheadMatches(query) {
    initializeOpenMojiCatalog();
    const normalizedQuery = String(query || '').trim().toLowerCase();
    if (!normalizedQuery) return getDefaultEmojiItems();
    const queryTokens = normalizedQuery.split(/\s+/).filter(Boolean);
    const rawQuery = String(query || '').trim();

    const rankedMatches = [];

    for (const item of openMojiCatalog) {
        const matchesAllTokens = queryTokens.every((token) => item.searchText.includes(token));
        const matchesChar = item.character.includes(query);
        if (!matchesAllTokens && !matchesChar) continue;

        const score = getEmojiSearchScore(item, normalizedQuery, queryTokens, rawQuery);
        rankedMatches.push({ item, score });

        if (rankedMatches.length >= 260) {
            break;
        }
    }

    rankedMatches.sort((a, b) => {
        if (b.score !== a.score) return b.score - a.score;
        return a.item.key.localeCompare(b.item.key);
    });

    return rankedMatches.map((entry) => entry.item).slice(0, 120);
}

function insertTextAtCursor(input, text) {
    if (!input) return;

    const start = Number(input.selectionStart ?? input.value.length);
    const end = Number(input.selectionEnd ?? input.value.length);

    if (typeof input.setRangeText === 'function') {
        input.setRangeText(text, start, end, 'end');
    } else {
        input.value = `${input.value.slice(0, start)}${text}${input.value.slice(end)}`;
        input.selectionStart = input.selectionEnd = start + text.length;
    }

    input.focus();
    input.dispatchEvent(new Event('input', { bubbles: true }));
}

function bindEmojiDrawer() {
    const toggle = document.getElementById('emoji-toggle');
    const drawer = document.getElementById('emoji-drawer');
    const search = document.getElementById('emoji-search');
    const grid = document.getElementById('emoji-grid');
    const input = document.getElementById('message-input');
    const attachmentsDrawer = document.getElementById('attachments-drawer');
    const attachmentsToggle = document.getElementById('attachments-toggle');

    if (!toggle || !drawer || !search || !grid || !input) return;

    const previewImg = document.getElementById('emoji-preview-img');
    const previewLabel = document.getElementById('emoji-preview-label');

    const renderGrid = (items) => {
        const safeItems = items.filter((item) => item.character);
        grid.innerHTML = safeItems.map((item) => `
            <button type="button" class="w-12 h-12 rounded-lg bg-zinc-800 hover:bg-zinc-700 border border-zinc-700 flex items-center justify-center" data-emoji="${escapeHtml(item.character)}" data-url="${escapeHtml(item.url)}" data-annotation="${escapeHtml(item.annotation || item.key)}" title="${escapeHtml(item.key)}">
                <img src="${escapeHtml(item.url)}" alt="${escapeHtml(item.character)}" class="w-9 h-9" loading="lazy" decoding="async">
            </button>
        `).join('') || '<div class="col-span-full text-xs text-zinc-400 py-4 text-center">No emoji found</div>';
    };

    const setOpenState = (isOpen) => {
        drawer.classList.toggle('hidden', !isOpen);
        toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        if (isOpen) {
            search.focus();
        }
    };

    renderGrid(getDefaultEmojiItems());

    toggle.addEventListener('click', (event) => {
        event.preventDefault();
        const willOpen = drawer.classList.contains('hidden');
        setOpenState(willOpen);
        if (willOpen && attachmentsDrawer && attachmentsToggle) {
            attachmentsDrawer.classList.add('hidden');
            attachmentsToggle.setAttribute('aria-expanded', 'false');
        }
        if (!willOpen) {
            search.value = '';
            renderGrid(getDefaultEmojiItems());
        }
    });

    search.addEventListener('input', () => {
        renderGrid(findEmojiTypeaheadMatches(search.value));
    });

    grid.addEventListener('click', (event) => {
        const button = event.target.closest('[data-emoji]');
        if (!button) return;

        const emoji = button.getAttribute('data-emoji') || '';
        if (!emoji) return;

        insertTextAtCursor(input, emoji);
    });

    grid.addEventListener('mouseover', (event) => {
        const button = event.target.closest('[data-emoji]');
        if (!button) return;
        const url = button.getAttribute('data-url') || '';
        const annotation = button.getAttribute('data-annotation') || '';
        if (previewImg && url) {
            previewImg.src = url;
            previewImg.alt = annotation;
            previewImg.classList.remove('hidden');
        }
        if (previewLabel) {
            previewLabel.textContent = annotation;
            previewLabel.classList.remove('hidden');
        }
    });

    grid.addEventListener('mouseleave', () => {
        if (previewImg) {
            previewImg.classList.add('hidden');
            previewImg.src = '';
        }
        if (previewLabel) {
            previewLabel.classList.add('hidden');
            previewLabel.textContent = '';
        }
    });

    document.addEventListener('click', (event) => {
        if (drawer.classList.contains('hidden')) return;
        if (drawer.contains(event.target) || toggle.contains(event.target)) return;
        setOpenState(false);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        if (drawer.classList.contains('hidden')) return;
        setOpenState(false);
    });
}

function renderPendingAttachmentList() {
    const empty = document.getElementById('attachments-empty');
    const list = document.getElementById('attachments-list');
    if (!empty || !list) return;

    const safeAttachments = pendingAttachments
        .map(normalizeAttachment)
        .filter(Boolean);

    if (safeAttachments.length === 0) {
        empty.classList.remove('hidden');
        list.classList.add('hidden');
        list.innerHTML = '';
        return;
    }

    empty.classList.add('hidden');
    list.classList.remove('hidden');
    list.innerHTML = safeAttachments.map((attachment) => {
        const category = getAttachmentCategory(attachment.file_extension);
        const preview = category === 'image'
            ? `<button type="button" class="js-lightbox-trigger block w-full" data-image-url="${escapeHtml(attachment.url)}" data-image-title="${escapeHtml(attachment.original_name)}">
                   <img src="${escapeHtml(attachment.url)}" alt="${escapeHtml(attachment.original_name)}" class="w-full h-24 object-cover rounded-lg border border-zinc-700" loading="lazy" decoding="async">
               </button>`
            : `<div class="w-full h-24 rounded-lg border border-zinc-700 bg-zinc-800 flex flex-col items-center justify-center gap-1.5">
                   <i class="fa-solid fa-file text-2xl text-zinc-400"></i>
                   <span class="text-xs font-mono font-semibold text-zinc-300 uppercase">.${escapeHtml(attachment.file_extension)}</span>
               </div>`;

        return `
            <div class="bg-zinc-800/70 border border-zinc-700 rounded-xl p-2">
                ${preview}
                <div class="mt-2 text-xs text-zinc-400 truncate" title="${escapeHtml(attachment.original_name)}">${escapeHtml(attachment.original_name)}</div>
                <div class="mt-1 text-xs text-zinc-500 flex items-center justify-between gap-2">
                    <span>${escapeHtml(formatFileSize(attachment.file_size))}</span>
                    <button type="button" class="text-red-300 hover:text-red-200" data-attachment-delete="${attachment.id}">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </div>
            </div>
        `;
    }).join('');
}

async function uploadAttachmentFile(file) {
    if (!currentChat) return;
    if (normalizeChatType(currentChat.type) === 'personal' && currentChat.can_send_messages === false) {
        showToast(getPersonalChatMessageRestrictionToast(currentChat.message_restriction_reason), 'error');
        return;
    }

    const formData = new FormData();
    formData.append('csrf_token', getCsrfToken());
    formData.append('chat_id', String(currentChat.id));
    formData.append('attachment', file);

    const response = await fetch('/api/attachments/upload', {
        method: 'POST',
        body: formData
    });

    const text = await response.text();
    let payload = { success: response.ok };
    try {
        payload = JSON.parse(text);
    } catch {
        payload = { success: response.ok, error: 'attachment_upload_failed' };
    }

    if (!payload.success || !payload.attachment) {
        const error = String(payload.error || 'attachment_upload_failed');
        const label = error === 'attachment_invalid_name'
            ? 'Attachment name must have one dot and a valid extension'
            : error === 'attachment_invalid_type'
                ? 'File type not allowed or unsupported'
                : error === 'attachment_too_large'
                    ? 'Attachment exceeds the maximum file size'
                    : 'Attachment upload failed';
        showToast(label, 'error');
        return;
    }

    const normalized = normalizeAttachment(payload.attachment);
    if (!normalized) {
        showToast('Attachment upload failed', 'error');
        return;
    }

    pendingAttachments.push(normalized);
    renderPendingAttachmentList();
}

async function deletePendingAttachment(attachmentId) {
    const id = Number(attachmentId || 0);
    if (!Number.isFinite(id) || id <= 0) return;

    const result = await postForm('/api/attachments/delete', {
        csrf_token: getCsrfToken(),
        attachment_id: String(id)
    });

    if (!result.success) {
        showToast(result.error || 'Unable to delete attachment', 'error');
        return;
    }

    pendingAttachments = pendingAttachments.filter((attachment) => Number(attachment.id) !== id);
    renderPendingAttachmentList();
}

function bindAttachmentsDrawer() {
    const toggle = document.getElementById('attachments-toggle');
    const drawer = document.getElementById('attachments-drawer');
    const input = document.getElementById('attachments-file-input');
    const selectButton = document.getElementById('attachments-select-button');
    const list = document.getElementById('attachments-list');
    const emojiDrawer = document.getElementById('emoji-drawer');
    const emojiToggle = document.getElementById('emoji-toggle');

    if (!toggle || !drawer || !input || !selectButton || !list) return;

    const setOpenState = (isOpen) => {
        drawer.classList.toggle('hidden', !isOpen);
        toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    };

    renderPendingAttachmentList();

    toggle.addEventListener('click', (event) => {
        event.preventDefault();
        const willOpen = drawer.classList.contains('hidden');
        setOpenState(willOpen);
        if (willOpen && emojiDrawer && emojiToggle) {
            emojiDrawer.classList.add('hidden');
            emojiToggle.setAttribute('aria-expanded', 'false');
        }
    });

    selectButton.addEventListener('click', (event) => {
        event.preventDefault();
        input.click();
    });

    input.addEventListener('change', async () => {
        const files = Array.from(input.files || []);
        if (files.length === 0) return;

        for (const file of files) {
            await uploadAttachmentFile(file);
        }

        input.value = '';
    });

    list.addEventListener('click', (event) => {
        const deleteButton = event.target.closest('[data-attachment-delete]');
        if (!deleteButton) return;
        event.preventDefault();
        deletePendingAttachment(deleteButton.getAttribute('data-attachment-delete')).catch(() => {
            showToast('Unable to delete attachment', 'error');
        });
    });

    document.addEventListener('click', (event) => {
        if (drawer.classList.contains('hidden')) return;
        if (drawer.contains(event.target) || toggle.contains(event.target)) return;
        setOpenState(false);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        if (drawer.classList.contains('hidden')) return;
        setOpenState(false);
    });
}

function openAttachmentLightbox(url, title = 'Attachment') {
    const overlay = document.getElementById('attachment-lightbox');
    const image = document.getElementById('attachment-lightbox-image');
    if (!overlay || !image || !url) return;

    image.src = url;
    image.alt = title;
    overlay.classList.remove('hidden');
}

function bindAttachmentLightbox() {
    const overlay = document.getElementById('attachment-lightbox');
    const close = document.getElementById('attachment-lightbox-close');
    if (!overlay || !close) return;

    const closeOverlay = () => {
        overlay.classList.add('hidden');
    };

    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('.js-lightbox-trigger');
        if (!trigger) return;

        const imageUrl = String(trigger.getAttribute('data-image-url') || '');
        const imageTitle = String(trigger.getAttribute('data-image-title') || 'Attachment');
        if (!imageUrl) return;

        event.preventDefault();
        openAttachmentLightbox(imageUrl, imageTitle);
    });

    close.addEventListener('click', closeOverlay);
    overlay.addEventListener('click', (event) => {
        if (event.target !== overlay) return;
        closeOverlay();
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !overlay.classList.contains('hidden')) {
            closeOverlay();
        }
    });
}


async function loadSidebarChats() {
    const privateList = document.getElementById('private-chat-list');
    const groupList = document.getElementById('group-chat-list');
    if (!privateList || !groupList) return;

    const res = await fetch('/api/chats');
    const data = await res.json();
    const chats = data.chats || [];

    const privateChats = chats.filter((chat) => normalizeChatType(chat.type) === 'personal');
    const groupChats = chats.filter((chat) => normalizeChatType(chat.type) === 'group');

    const renderSidebarChatItems = (items) => items.map(chat => {
        const type = normalizeChatType(chat.type);
        const sidebarTitle = type === 'group'
            ? (chat.chat_title || formatNumber(chat.chat_number))
            : (chat.chat_title || `Chat ${formatNumber(chat.chat_number)}`);
        const chatNumberLabel = String(chat.chat_number_formatted || formatNumber(chat.chat_number));
        const hasCustomTitle = type === 'group' && (Number(chat.has_custom_title) === 1 || chat.has_custom_title === true);
        const hasPersonalStatus = type === 'personal' && Boolean(chat.effective_status_label);
        const showFavoriteStar = type === 'personal' && (Number(chat.is_favorite) === 1 || chat.is_favorite === true);
        const unreadCount = Math.max(0, Number(chat.unread_count || 0));
        const rawMessage = decodeStoredMentionsToPlainText(chat.last_message || '');
        const secondaryLine = (() => {
            if (!rawMessage) return 'No messages yet';
            const senderId = Number(chat.last_message_user_id || 0);
            const prefix = senderId && senderId === Number(currentUserId || 0)
                ? 'You'
                : (chat.last_message_sender_username || '');
            return prefix ? `${prefix}: ${rawMessage}` : rawMessage;
        })();
        const unreadBadge = unreadCount > 0
            ? `<span class="ml-auto min-w-[1.25rem] h-5 px-1 rounded-full bg-emerald-600 text-white text-xs inline-flex items-center justify-center">${escapeHtml(formatCountBadgeValue(unreadCount))}</span>`
            : '';

        return `
            <a href="/c/${formatNumber(chat.chat_number)}" class="block py-2 px-3 rounded-xl hover:bg-zinc-800 mb-1">
                <div class="font-medium flex items-center gap-2 min-w-0">
                    <span class="truncate">${escapeHtml(sidebarTitle)}</span>
                    ${showFavoriteStar ? '<i class="fa-solid fa-star text-amber-400 text-[11px]" title="Favorite"></i>' : ''}
                    ${hasPersonalStatus ? `<span class="inline-block w-2 h-2 rounded-full ${escapeHtml(chat.effective_status_dot_class || 'bg-zinc-500')}" title="${escapeHtml(chat.effective_status_label || 'Offline')}"></span>` : ''}
                    ${unreadBadge}
                </div>
                <div class="text-xs text-zinc-400 truncate">${escapeHtml(secondaryLine)}</div>
            </a>
        `;
    }).join('');

    privateList.innerHTML = renderSidebarChatItems(privateChats) || '<div class="text-zinc-500 text-sm px-3 py-1">No private chats yet</div>';
    groupList.innerHTML = renderSidebarChatItems(groupChats) || '<div class="text-zinc-500 text-sm px-3 py-1">No group chats yet</div>';

}

async function createGroupChat() {
    const result = await postForm('/api/chats/group/create', {
        csrf_token: getCsrfToken()
    });

    if (result.success && result.chat_number) {
        window.location.href = `/c/${formatNumber(result.chat_number)}?prompt_group_name=1`;
        return;
    }

    showToast(result.error || 'Unable to create group chat', 'error');
}

async function addGroupMemberByUsername(event, usernameOverride = null) {
    if (event && typeof event.preventDefault === 'function') {
        event.preventDefault();
    }

    if (!currentChat) return;
    if (normalizeChatType(currentChat.type) !== 'group') {
        showToast('Only group chats can add or remove members', 'error');
        return;
    }

    const usernameInput = document.getElementById('group-add-username');
    const username = String(usernameOverride ?? (usernameInput?.value || '')).trim();
    if (!username) {
        showToast('Enter a username', 'error');
        return;
    }

    const result = await postForm('/api/chats/group/add-member', {
        csrf_token: getCsrfToken(),
        chat_id: String(currentChat.id),
        username
    });

    if (result.success) {
        showToast('User added to group', 'success');
        if (usernameInput) {
            usernameInput.value = '';
        }
        window.location.reload();
        return;
    }

    showToast(result.error || 'Unable to add member', 'error');
}

function bindAddUserModal() {
    const modal = document.getElementById('add-user-modal');
    const form = document.getElementById('add-user-form');
    const input = document.getElementById('add-user-input');
    const cancel = document.getElementById('add-user-cancel');
    const submit = document.getElementById('add-user-submit');
    if (!modal || !form || !input || !cancel || !submit) return;

    const setOpenState = (isOpen) => {
        modal.classList.toggle('hidden', !isOpen);

        if (isOpen) {
            input.value = '';
            setTimeout(() => {
                input.focus();
            }, 0);
            return;
        }

        submit.disabled = false;
        submit.textContent = 'Add user';
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

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (submit.disabled) return;

        submit.disabled = true;
        submit.textContent = 'Adding...';

        try {
            await addGroupMemberByUsername(null, input.value);
        } finally {
            submit.disabled = false;
            submit.textContent = 'Add user';
        }
    });

    window.openAddUserModal = () => setOpenState(true);
}

async function removeGroupMember(userId, username) {
    if (!currentChat) return;
    if (normalizeChatType(currentChat.type) !== 'group') {
        showToast('Only group chats can add or remove members', 'error');
        return;
    }

    const ownerUserId = Number(currentChat.owner_user_id || 0);
    if (ownerUserId > 0 && Number(userId) === ownerUserId) {
        showToast('Group owner cannot be removed', 'error');
        return;
    }

    const result = await postForm('/api/chats/group/remove-member', {
        csrf_token: getCsrfToken(),
        chat_id: String(currentChat.id),
        user_id: String(userId)
    });

    if (result.success) {
        showToast('User removed from group', 'success');
        const memberChip = document.querySelector(`[data-group-member-user-id="${Number(userId)}"]`);
        if (memberChip) {
            memberChip.remove();
        }
        return;
    }

    showToast(result.error || 'Unable to remove member', 'error');
}

async function leaveCurrentGroup() {
    if (!currentChat) return;
    if (normalizeChatType(currentChat.type) !== 'group') {
        showToast('Only group chats can be left', 'error');
        return;
    }

    if (typeof window.openLeaveGroupModal === 'function') {
        window.openLeaveGroupModal();
    }
}

function getEligibleNewOwners() {
    const memberNodes = Array.from(document.querySelectorAll('[data-group-member-user-id]'));
    return memberNodes
        .map((node) => {
            const userId = Number(node.getAttribute('data-group-member-user-id') || 0);
            const username = String(node.getAttribute('data-group-member-username') || '').trim();
            if (!Number.isFinite(userId) || userId <= 0 || !username) {
                return null;
            }
            return { userId, username };
        })
        .filter(Boolean);
}

async function submitLeaveGroup(newOwnerUserId = 0) {
    if (!currentChat) return;
    if (normalizeChatType(currentChat.type) !== 'group') {
        showToast('Only group chats can be left', 'error');
        return;
    }

    const currentOwnerId = Number(currentChat.owner_user_id || 0);
    const isCurrentUserOwner = currentOwnerId > 0 && currentOwnerId === Number(currentUserId || 0);
    const payload = {
        csrf_token: getCsrfToken(),
        chat_id: String(currentChat.id)
    };

    if (isCurrentUserOwner) {
        const ownerOptions = getEligibleNewOwners();

        if (ownerOptions.length === 0) {
            showToast('You cannot leave a group you own with no other members. Delete the group instead.', 'error');
            return;
        }

        const ownerId = Number(newOwnerUserId || 0);
        if (!ownerOptions.some((option) => option.userId === ownerId)) {
            showToast('Choose a current group member to transfer ownership before leaving.', 'error');
            return;
        }

        payload.new_owner_user_id = String(ownerId);
    }

    const result = await postForm('/api/chats/group/leave', payload);

    if (result.success) {
        const nextLocation = String(result.redirect || '/');
        window.location.href = nextLocation;
        return;
    }

    showToast(result.error || 'Unable to leave group', 'error');
}

async function deleteCurrentGroup() {
    if (!currentChat) return;
    if (normalizeChatType(currentChat.type) !== 'group') {
        showToast('Only group chats can be deleted', 'error');
        return;
    }

    if (typeof window.openDeleteGroupModal === 'function') {
        window.openDeleteGroupModal();
    }
}

async function submitDeleteGroup() {
    if (!currentChat) return;
    if (normalizeChatType(currentChat.type) !== 'group') {
        showToast('Only group chats can be deleted', 'error');
        return;
    }

    const result = await postForm('/api/chats/group/delete', {
        csrf_token: getCsrfToken(),
        chat_id: String(currentChat.id)
    });

    if (result.success) {
        window.location.href = String(result.redirect || '/');
        return;
    }

    showToast(result.error || 'Unable to delete group', 'error');
}

async function takeCurrentGroupOwnership() {
    if (!currentChat) return;
    if (normalizeChatType(currentChat.type) !== 'group') {
        showToast('Only group chats can change ownership', 'error');
        return;
    }

    const ownerUserId = Number(currentChat.owner_user_id || 0);
    if (ownerUserId > 0 && ownerUserId === Number(currentUserId || 0)) {
        showToast('You already own this group', 'error');
        return;
    }

    if (typeof window.openTakeOwnershipModal === 'function') {
        window.openTakeOwnershipModal();
        return;
    }

    await submitTakeCurrentGroupOwnership();
}

async function submitTakeCurrentGroupOwnership() {
    if (!currentChat) return;
    if (normalizeChatType(currentChat.type) !== 'group') {
        showToast('Only group chats can change ownership', 'error');
        return;
    }

    const ownerUserId = Number(currentChat.owner_user_id || 0);
    if (ownerUserId > 0 && ownerUserId === Number(currentUserId || 0)) {
        showToast('You already own this group', 'error');
        return;
    }

    const result = await postForm('/api/chats/group/take-ownership', {
        csrf_token: getCsrfToken(),
        chat_id: String(currentChat.id)
    });

    if (result.success) {
        showToast('You now own this group', 'success');
        window.location.reload();
        return;
    }

    showToast(result.error || 'Unable to take ownership', 'error');
}

window.takeCurrentGroupOwnership = takeCurrentGroupOwnership;

function bindTakeOwnershipModal() {
    const modal = document.getElementById('take-ownership-modal');
    const form = document.getElementById('take-ownership-form');
    const cancel = document.getElementById('take-ownership-cancel');
    const submit = document.getElementById('take-ownership-submit');
    if (!modal || !form || !cancel || !submit) return;

    const setOpenState = (isOpen) => {
        modal.classList.toggle('hidden', !isOpen);
        if (!isOpen) {
            submit.disabled = false;
            submit.textContent = 'Take Ownership';
        }
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

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (submit.disabled) return;

        submit.disabled = true;
        submit.textContent = 'Taking...';

        try {
            await submitTakeCurrentGroupOwnership();
        } finally {
            submit.disabled = false;
            submit.textContent = 'Take Ownership';
            closeModal();
        }
    });

    window.openTakeOwnershipModal = () => setOpenState(true);
}

function bindLeaveGroupModal() {
    const modal = document.getElementById('leave-group-modal');
    const form = document.getElementById('leave-group-form');
    const cancel = document.getElementById('leave-group-cancel');
    const submit = document.getElementById('leave-group-submit');
    const ownerTransferBox = document.getElementById('leave-group-owner-transfer');
    const ownerSelect = document.getElementById('leave-group-new-owner');
    const description = document.getElementById('leave-group-modal-description');

    if (!modal || !form || !cancel || !submit || !ownerTransferBox || !ownerSelect || !description) return;

    const isOwner = () => {
        const ownerId = Number(currentChat?.owner_user_id || 0);
        const me = Number(currentUserId || 0);
        return ownerId > 0 && ownerId === me;
    };

    const setOpenState = (isOpen) => {
        modal.classList.toggle('hidden', !isOpen);

        if (!isOpen) {
            submit.disabled = false;
            submit.textContent = 'Leave Group';
            return;
        }

        const ownerFlow = isOwner();
        ownerTransferBox.classList.toggle('hidden', !ownerFlow);

        if (ownerFlow) {
            const options = getEligibleNewOwners();
            ownerSelect.innerHTML = '<option value="">Select member</option>'
                + options.map((option) => `<option value="${option.userId}">${escapeHtml(option.username)}</option>`).join('');

            description.textContent = options.length > 0
                ? 'Choose a new owner, then leave the group.'
                : 'You cannot leave this group because there are no other members to transfer ownership to.';
            submit.disabled = options.length === 0;
            submit.textContent = options.length > 0 ? 'Transfer & Leave' : 'Leave Group';
            return;
        }

        ownerSelect.innerHTML = '<option value="">Select member</option>';
        description.textContent = 'Are you sure you want to leave this group?';
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

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (submit.disabled) return;

        submit.disabled = true;
        submit.textContent = 'Leaving...';

        try {
            const selectedOwnerUserId = Number(ownerSelect.value || 0);
            await submitLeaveGroup(selectedOwnerUserId);
        } finally {
            submit.disabled = false;
            submit.textContent = 'Leave Group';
        }
    });

    window.openLeaveGroupModal = () => setOpenState(true);
}

function bindDeleteGroupModal() {
    const modal = document.getElementById('delete-group-modal');
    const form = document.getElementById('delete-group-form');
    const cancel = document.getElementById('delete-group-cancel');
    const submit = document.getElementById('delete-group-submit');
    if (!modal || !form || !cancel || !submit) return;

    const setOpenState = (isOpen) => {
        modal.classList.toggle('hidden', !isOpen);
        if (!isOpen) {
            submit.disabled = false;
            submit.textContent = 'Delete Group';
        }
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

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (submit.disabled) return;

        submit.disabled = true;
        submit.textContent = 'Deleting...';

        try {
            await submitDeleteGroup();
        } finally {
            submit.disabled = false;
            submit.textContent = 'Delete Group';
        }
    });

    window.openDeleteGroupModal = () => setOpenState(true);
}

async function renameCurrentChat() {
    if (!currentChat) return;
    if (normalizeChatType(currentChat.type) !== 'group') {
        showToast('Only group chats can be renamed', 'error');
        return;
    }

    const input = document.getElementById('rename-chat-input');
    const modal = document.getElementById('rename-chat-modal');
    if (!input || !modal) return;

    const safeTitle = String(input.value || '').trim();

    const result = await postForm('/api/chats/rename', {
        csrf_token: getCsrfToken(),
        chat_id: String(currentChat.id),
        title: safeTitle
    });

    if (result.success) {
        showToast(result.reset ? 'Chat name reset to chat number' : 'Chat renamed', 'success');
        window.location.reload();
        return;
    }

    showToast(result.error || 'Unable to rename chat', 'error');
}

function bindRenameChatModal() {
    const modal = document.getElementById('rename-chat-modal');
    const form = document.getElementById('rename-chat-form');
    const input = document.getElementById('rename-chat-input');
    const cancel = document.getElementById('rename-chat-cancel');
    const submit = document.getElementById('rename-chat-submit');
    if (!modal || !form || !input || !cancel || !submit) return;

    const defaultCancelLabel = 'Cancel';

    const setOpenState = (isOpen, options = {}) => {
        modal.classList.toggle('hidden', !isOpen);

        if (isOpen) {
            const preferGroupNumber = options && options.preferGroupNumber === true;
            const currentTitle = String(document.getElementById('chat-title')?.textContent || '').trim();
            input.value = currentTitle;
            cancel.textContent = preferGroupNumber ? 'Use Group Number' : defaultCancelLabel;
            modal.dataset.preferGroupNumberPrompt = preferGroupNumber ? '1' : '0';
            setTimeout(() => {
                input.focus();
                input.select();
            }, 0);
            return;
        }

        submit.disabled = false;
        submit.textContent = 'Save';
        cancel.textContent = defaultCancelLabel;
        modal.dataset.preferGroupNumberPrompt = '0';
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

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (submit.disabled) return;

        const isInitialGroupNumberPrompt = modal.dataset.preferGroupNumberPrompt === '1';
        const safeTitle = String(input.value || '').trim();
        const groupNumberLabel = currentChat ? String(formatNumber(currentChat.chat_number)) : '';
        if (isInitialGroupNumberPrompt && groupNumberLabel !== '' && safeTitle === groupNumberLabel) {
            closeModal();
            return;
        }

        submit.disabled = true;
        submit.textContent = 'Saving...';

        try {
            await renameCurrentChat();
        } finally {
            submit.disabled = false;
            submit.textContent = 'Save';
        }
    });

    window.openRenameChatModal = (options = {}) => setOpenState(true, options);
}

async function pollMessages(options = {}) {
    if (!currentChat) return;
    const res = await fetch(`/api/messages/${currentChat.id}`);
    const data = await res.json();
    const messages = data.messages || [];
    const typingUsers = data.typing_users || [];
    const pinnedMessage = normalizePinnedMessage(data.pinned_message);
    const forceRender = options.forceRender === true;
    const activeChatId = Number(currentChat.id || 0);
    const nextMessagesSignature = buildMessagesSignature(messages);
    const hasNewMessageState = (
        forceRender
        || activeChatId !== lastRenderedChatId
        || nextMessagesSignature !== lastRenderedMessagesSignature
    );

    if (hasNewMessageState) {
        renderMessages(messages, { scrollMode: options.scrollMode || 'preserve' });
        lastRenderedChatId = activeChatId;
        lastRenderedMessagesSignature = nextMessagesSignature;
    }

    updatePersonalChatHeaderStatusFromMessages(messages);
    renderTypingIndicator(typingUsers);
    if (currentChat) {
        currentChat.pinned_message = pinnedMessage;
    }
    renderPinnedMessageBanner(pinnedMessage);
    refreshChatCallStatusBar();

    const restrictionReason = Object.prototype.hasOwnProperty.call(data, 'can_send_message_reason')
        ? String(data.can_send_message_reason || '')
        : String(currentChat?.message_restriction_reason || '');

    if (Object.prototype.hasOwnProperty.call(data, 'can_send_message')) {
        setChatComposerEnabled(Boolean(data.can_send_message), restrictionReason);
    }

    if (Object.prototype.hasOwnProperty.call(data, 'can_start_call')) {
        setChatCallEnabled(Boolean(data.can_start_call));
    }
}

function buildMessagesSignature(messages) {
    if (!Array.isArray(messages) || messages.length === 0) {
        return 'empty';
    }

    return messages.map((msg) => {
        if (msg?.is_system_event) {
            return `sys|${Number(msg.id || 0)}|${String(msg.created_at || '')}|${String(msg.content || '')}`;
        }

        const messageId = Number(msg?.id || 0);
        const content = String(msg?.content || '');
        const createdAt = String(msg?.created_at || '');
        const quotedMessageId = Number(msg?.quoted_message_id || 0);
        const mentionMap = normalizeMentionMap(msg?.mention_map || {});
        const mentionMapKeys = Object.keys(mentionMap).sort();
        const mentionMapFingerprint = mentionMapKeys.map((key) => `${key}:${mentionMap[key]}`).join(',');

        const attachmentFingerprint = Array.isArray(msg?.attachments)
            ? msg.attachments
                .map((attachment) => normalizeAttachment(attachment))
                .filter(Boolean)
                .map((attachment) => `${attachment.id}:${attachment.file_size}`)
                .join(',')
            : '';

        const reactionFingerprint = Array.isArray(msg?.reactions)
            ? msg.reactions
                .map((reaction) => {
                    const code = normalizeReactionCode(reaction?.reaction_code || '');
                    const count = Number(reaction?.count || 0);
                    const reacted = Number(reaction?.reacted_by_current_user || 0);
                    return `${code}:${count}:${reacted}`;
                })
                .filter(Boolean)
                .sort()
                .join(',')
            : '';

        return [
            messageId,
            createdAt,
            quotedMessageId,
            content,
            mentionMapFingerprint,
            attachmentFingerprint,
            reactionFingerprint
        ].join('|');
    }).join('\n');
}

function getPersonalChatMessageRestrictionText(reason) {
    if (String(reason || '') === 'banned_user') {
        return "You can't send messages to a banned user.";
    }

    return 'Messaging is disabled in this private chat until you add each other as friends again.';
}

function getPersonalChatMessageRestrictionToast(reason) {
    if (String(reason || '') === 'banned_user') {
        return "You can't send messages to a banned user";
    }

    return 'You can only message friends in personal chats';
}


function setChatComposerEnabled(enabled, restrictionReason = '') {
    const isEnabled = Boolean(enabled);
    const safeRestrictionReason = String(restrictionReason || '');

    if (currentChat) {
        currentChat.can_send_messages = isEnabled;
        currentChat.message_restriction_reason = safeRestrictionReason;
    }

    const form = document.getElementById('message-form');
    const controls = document.getElementById('message-composer-controls');
    const input = document.getElementById('message-input');
    const sendButton = form?.querySelector('button[type="submit"]');
    const attachmentsToggle = document.getElementById('attachments-toggle');
    const emojiToggle = document.getElementById('emoji-toggle');
    const notice = document.getElementById('message-disabled-notice');
    const attachmentsDrawer = document.getElementById('attachments-drawer');
    const emojiDrawer = document.getElementById('emoji-drawer');

    if (controls) {
        controls.classList.toggle('hidden', !isEnabled);
    }
    if (input) {
        input.disabled = !isEnabled;
        input.required = isEnabled;
        input.placeholder = 'Message...';
    }
    if (sendButton) {
        sendButton.disabled = !isEnabled;
    }
    if (attachmentsToggle) {
        attachmentsToggle.disabled = !isEnabled;
    }
    if (emojiToggle) {
        emojiToggle.disabled = !isEnabled;
    }
    if (notice) {
        notice.textContent = getPersonalChatMessageRestrictionText(safeRestrictionReason);
        notice.classList.toggle('hidden', isEnabled);
    }
    if (!isEnabled) {
        attachmentsDrawer?.classList.add('hidden');
        attachmentsToggle?.setAttribute('aria-expanded', 'false');
        emojiDrawer?.classList.add('hidden');
        emojiToggle?.setAttribute('aria-expanded', 'false');
    }
}

function applyPersonalChatHeaderStatus(statusDotClass, statusLabel) {
    const dot = document.getElementById('chat-title-status-dot');
    if (!dot) return;

    dot.classList.remove(...CHAT_STATUS_DOT_CLASSES);
    dot.classList.add(String(statusDotClass || 'bg-zinc-500'));
    dot.setAttribute('title', String(statusLabel || 'Offline'));
}

function updatePersonalChatHeaderStatusFromMessages(messages) {
    if (normalizeChatType(currentChat?.type) !== 'personal') return;

    const personalUserId = Number(currentChat?.personal_user_id || 0);
    if (!Number.isFinite(personalUserId) || personalUserId <= 0 || !Array.isArray(messages)) {
        refreshPersonalChatHeaderStatusFallback();
        return;
    }

    for (let index = messages.length - 1; index >= 0; index -= 1) {
        const message = messages[index];
        if (Number(message?.user_id) !== personalUserId) {
            continue;
        }

        applyPersonalChatHeaderStatus(message?.effective_status_dot_class, message?.effective_status_label);
        return;
    }

    refreshPersonalChatHeaderStatusFallback();
}

async function refreshPersonalChatHeaderStatusFallback() {
    if (normalizeChatType(currentChat?.type) !== 'personal') return;
    if (personalStatusFallbackInFlight) return;

    const now = Date.now();
    if (now - lastPersonalStatusFallbackAt < CHAT_STATUS_FALLBACK_POLL_MS) {
        return;
    }

    personalStatusFallbackInFlight = true;
    lastPersonalStatusFallbackAt = now;

    try {
        const response = await fetch('/api/chats');
        const payload = await response.json();
        const chats = Array.isArray(payload?.chats) ? payload.chats : [];
        const currentChatNumber = String(currentChat?.chat_number || '').replace(/\D/g, '');

        if (!currentChatNumber) {
            return;
        }

        const chat = chats.find((item) => {
            const type = normalizeChatType(item?.type);
            const chatNumber = String(item?.chat_number || '').replace(/\D/g, '');
            return type === 'personal' && chatNumber === currentChatNumber;
        });

        if (!chat) {
            return;
        }

        applyPersonalChatHeaderStatus(chat.effective_status_dot_class, chat.effective_status_label);
    } catch {
    } finally {
        personalStatusFallbackInFlight = false;
    }
}

function getMessageScrollAnchor(messageId) {
    const box = document.getElementById('messages');
    if (!box) return null;

    const safeMessageId = Number(messageId);
    if (!Number.isFinite(safeMessageId) || safeMessageId <= 0) return null;

    const target = box.querySelector(`[data-message-id="${safeMessageId}"]`);
    if (!target) return null;

    return Math.max(0, target.offsetTop - box.offsetTop - 16);
}

function focusMessageById(messageId) {
    const box = document.getElementById('messages');
    if (!box) return false;

    const safeMessageId = Number(messageId || 0);
    if (!Number.isFinite(safeMessageId) || safeMessageId <= 0) return false;

    const anchor = getMessageScrollAnchor(safeMessageId);
    if (anchor === null) return false;

    box.scrollTop = anchor;
    const target = box.querySelector(`[data-message-id="${safeMessageId}"]`);
    if (!target) return false;

    target.style.backgroundColor = 'rgba(52, 211, 153, 0.08)';
    target.style.borderRadius = '0.5rem';
    target.style.transition = 'background-color 1.5s ease';
    setTimeout(() => { target.style.backgroundColor = ''; }, 2500);
    return true;
}

function applyInitialChatScrollPosition() {
    const chatView = document.getElementById('chat-view');
    const box = document.getElementById('messages');
    if (!chatView || !box) return;

    const urlParams = new URLSearchParams(window.location.search);
    const targetMsgId = Number(urlParams.get('msg') || 0);
    if (targetMsgId > 0) {
        if (focusMessageById(targetMsgId)) {
            return;
        }
    }

    const firstUnseenMessageId = Number(chatView.dataset.firstUnseenMessageId || 0);
    const anchor = getMessageScrollAnchor(firstUnseenMessageId);

    if (anchor !== null) {
        box.scrollTop = anchor;
        return;
    }

    box.scrollTop = box.scrollHeight;
}

function renderTypingIndicator(typingUsers) {
    const box = document.getElementById('typing-indicator');
    if (!box) return;

    const names = typingUsers
        .map(user => String(user?.username || '').trim())
        .filter(Boolean)
        .slice(0, 3);

    if (names.length === 0) {
        box.classList.add('hidden');
        box.innerHTML = '';
        return;
    }

    const subject = names.length === 1
        ? `${escapeHtml(names[0])} is typing`
        : `${escapeHtml(names.join(', '))} are typing`;

    box.classList.remove('hidden');
    box.innerHTML = `${subject}<span class="typing-dots" aria-hidden="true"><span>.</span><span>.</span><span>.</span></span>`;
}


async function sendTypingStatus(isTyping) {
    if (!currentChat) return;

    await postForm('/api/chats/typing', {
        csrf_token: getCsrfToken(),
        chat_id: String(currentChat.id),
        is_typing: isTyping ? '1' : '0'
    });
}

function clearTypingStopTimer() {
    if (typingStopTimeoutId) {
        clearTimeout(typingStopTimeoutId);
        typingStopTimeoutId = null;
    }
}

function scheduleTypingStop() {
    clearTypingStopTimer();
    typingStopTimeoutId = setTimeout(() => {
        if (!typingActive) return;
        typingActive = false;
        sendTypingStatus(false).catch(() => {});
    }, 2500);
}

function handleMessageInputTyping() {
    const input = document.getElementById('message-input');
    const hasText = Boolean(input?.value?.trim());

    if (!hasText) {
        if (typingActive) {
            typingActive = false;
            sendTypingStatus(false).catch(() => {});
        }
        clearTypingStopTimer();
        return;
    }

    const now = Date.now();
    if (!typingActive || now - lastTypingPingAt >= 2000) {
        typingActive = true;
        lastTypingPingAt = now;
        sendTypingStatus(true).catch(() => {});
    }

    scheduleTypingStop();
}

function renderMessages(messages, options = {}) {
    const box = document.getElementById('messages');
    if (!box) return;

    const scrollMode = String(options.scrollMode || 'preserve');
    const previousScrollHeight = box.scrollHeight;
    const previousScrollTop = box.scrollTop;
    const previousViewportHeight = box.clientHeight;
    const distanceFromBottom = Math.max(0, previousScrollHeight - (previousScrollTop + previousViewportHeight));
    const wasNearBottom = distanceFromBottom <= MESSAGE_SCROLL_BOTTOM_THRESHOLD_PX;

    const clusterStarts = [];
    let prevClusterUserId = null;
    messages.forEach((msg) => {
        if (msg.is_system_event) {
            clusterStarts.push(false);
            prevClusterUserId = null;
            return;
        }
        const userId = Number(msg.user_id);
        clusterStarts.push(prevClusterUserId === null || prevClusterUserId !== userId);
        prevClusterUserId = userId;
    });

    const latestClusterIndexByUser = new Map();
    for (let index = messages.length - 1; index >= 0; index -= 1) {
        if (messages[index]?.is_system_event) continue;
        if (!clusterStarts[index]) continue;

        const clusterUserId = Number(messages[index]?.user_id);
        if (!latestClusterIndexByUser.has(clusterUserId)) {
            latestClusterIndexByUser.set(clusterUserId, index);
        }
    }

    let previousUserId = null;
    let previousWasSystemEvent = false;
    const chunks = [];

    messages.forEach((msg, index) => {
        if (msg.is_system_event) {
            const isNewPrologueCluster = !previousWasSystemEvent;
            const sysFullTimestamp = String(msg.created_at || '');
            const sysCompactTimestamp = formatCompactMessageTimestamp(sysFullTimestamp);
            chunks.push(`
                <div class="flex gap-3 ${isNewPrologueCluster ? 'mt-4' : 'mt-1'}">
                    <div class="w-10 shrink-0">
                        ${isNewPrologueCluster ? '<div class="w-10 h-10 rounded-full border border-zinc-700 flex items-center justify-center font-semibold mt-0.5 bg-emerald-700 text-emerald-100">P</div>' : '<div class="w-10 h-10"></div>'}
                    </div>
                    <div class="min-w-0 flex-1">
                        ${isNewPrologueCluster ? '<div class="flex items-center gap-2 mb-0.5"><span class="text-sm font-semibold leading-5 prologue-accent">Prologue</span></div>' : ''}
                        <div class="text-zinc-200 text-[17px] leading-6">${renderPlainTextWithEmoji(String(msg.content || ''))}</div>
                        <div class="relative mt-0.5">
                            <div class="text-xs flex items-center gap-3">
                                <span class="text-zinc-500" data-utc="${escapeHtml(sysFullTimestamp)}" title="${escapeHtml(sysFullTimestamp)}">${escapeHtml(sysCompactTimestamp)}</span>
                            </div>
                        </div>
                    </div>
                </div>
            `);
            previousWasSystemEvent = true;
            previousUserId = null;
            return;
        }

        const messageUserId = Number(msg.user_id);
        const isNewGroup = previousUserId === null || messageUserId !== previousUserId;
        const showStatus = isNewGroup
            && normalizeChatType(currentChat?.type) === 'group'
            && messageUserId !== Number(currentUserId)
            && latestClusterIndexByUser.get(messageUserId) === index;
        const fullTimestamp = String(msg.created_at || '');
        const compactTimestamp = formatCompactMessageTimestamp(fullTimestamp);
        const profileUrl = getProfileUrlForUser(msg);
        const statusLabel = String(msg?.effective_status_label || 'Offline');
        const statusDotClass = String(msg?.effective_status_dot_class || 'bg-zinc-500');
        const mentionMap = normalizeMentionMap(msg?.mention_map || {});
        const mentionMapJson = escapeHtml(JSON.stringify(mentionMap));
        const reactionBadgesMarkup = renderReactionBadgesMarkup(msg.id, Array.isArray(msg?.reactions) ? msg.reactions : []);

        chunks.push(`
            <div class="flex gap-3 ${isNewGroup ? 'mt-4' : 'mt-1'} group" data-message-id="${Number(msg.id)}">
                <div class="w-10 shrink-0">
                    ${isNewGroup ? renderAvatarMarkup(msg, 'w-10 h-10 mt-0.5', 'text-sm') : '<div class="w-10 h-10"></div>'}
                </div>
                <div class="min-w-0 flex-1">
                    ${isNewGroup ? `<div class="flex items-center gap-2 mb-0.5">${profileUrl ? `<a href="${escapeHtml(profileUrl)}" class="text-sm font-semibold leading-5 inline-block prologue-accent hover:text-emerald-300 hover:underline underline-offset-2">${escapeHtml(msg.username)}</a>` : `<div class="text-sm font-semibold leading-5 inline-block prologue-accent">${escapeHtml(msg.username)}</div>`}${showStatus ? `<span class="inline-block w-1.5 h-1.5 rounded-full ${escapeHtml(statusDotClass)}" title="${escapeHtml(statusLabel)}"></span>` : ''}</div>` : ''}
                    ${renderQuotedMessageBlock(msg)}
                    <div class="text-zinc-200 text-[17px] leading-6 js-message-content" data-raw-content="${escapeHtml(msg.content)}" data-mention-map="${mentionMapJson}">${renderMessageContent(msg.content, mentionMap)}</div>
                    ${renderMessageAttachments(msg.attachments)}
                    <div class="relative mt-0.5">
                        ${renderReactionPickerMarkup(msg.id)}
                        <div class="text-xs flex items-center gap-3">
                            <span class="text-zinc-500" data-utc="${escapeHtml(fullTimestamp)}" title="${escapeHtml(fullTimestamp)}">${escapeHtml(compactTimestamp)}</span>
                            <div class="flex items-center gap-3 md:opacity-0 md:group-hover:opacity-100 md:pointer-events-none md:group-hover:pointer-events-auto md:transition-opacity md:duration-150 md:ease-out">
                                <button type="button" class="text-zinc-400 hover:text-zinc-300 js-quote-link" title="Quote" aria-label="Quote" data-quote-message-id="${Number(msg.id)}" data-quote-username="${escapeHtml(String(msg.username || ''))}" data-quote-user-number="${escapeHtml(String(msg.user_number || ''))}" data-quote-content="${escapeHtml(String(msg.content || ''))}" data-quote-mention-map="${mentionMapJson}"><i class="fa-solid fa-reply" aria-hidden="true"></i></button>
                                <button type="button" class="text-zinc-400 hover:text-zinc-300 js-pin-link" title="Pin" aria-label="Pin" data-pin-message-id="${Number(msg.id)}"><i class="fa-solid fa-thumbtack" aria-hidden="true"></i></button>
                                <button type="button" class="text-zinc-400 hover:text-zinc-300 js-react-link" title="React" aria-label="React" data-react-message-id="${Number(msg.id)}"><i class="fa-solid fa-thumbs-up" aria-hidden="true"></i></button>
                            </div>
                            ${reactionBadgesMarkup}
                        </div>
                    </div>
                </div>
            </div>
        `);

        previousUserId = messageUserId;
        previousWasSystemEvent = false;
    });

    box.innerHTML = chunks.join('');
    if (typeof window.refreshUtcTimestamps === 'function') {
        window.refreshUtcTimestamps(box);
    }

    if (openReactionPickerMessageId > 0) {
        const picker = box.querySelector(`.js-reaction-picker[data-reaction-picker-for="${openReactionPickerMessageId}"]`);
        if (picker) {
            picker.classList.remove('hidden');
        } else {
            openReactionPickerMessageId = 0;
        }
    }

    if (scrollMode === 'bottom') {
        box.scrollTop = box.scrollHeight;
        return;
    }

    if (scrollMode === 'preserve' && !wasNearBottom) {
        const nextScrollTop = Math.max(0, box.scrollHeight - previousViewportHeight - distanceFromBottom);
        box.scrollTop = nextScrollTop;
        return;
    }

    box.scrollTop = box.scrollHeight;
}
