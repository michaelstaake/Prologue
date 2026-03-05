// Extracted from app.js for feature-focused organization.

let messageInteractionHandlersBound = false;
let openReactionPickerMessageId = 0;
let lastRenderedChatId = 0;
let lastRenderedMessagesSignature = '';
const expandedSystemEventGroupsByChat = new Map();
let pendingPinReplaceMessageId = 0;
let pinnedBannerLastScrollTop = 0;
let pinnedBannerHiddenByScroll = false;
let messageFormLastScrollTop = 0;

const PINNED_BANNER_MOBILE_BREAKPOINT_QUERY = '(max-width: 767.98px)';
const MESSAGE_FORM_MOBILE_BREAKPOINT_QUERY = '(max-width: 639.98px)';
const PINNED_BANNER_SCROLL_DELTA_PX = 6;
const MESSAGE_FORM_SCROLL_DELTA_PX = 6;

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

function isMessageFormMobileViewport() {
    if (typeof window.matchMedia !== 'function') return false;
    return window.matchMedia(MESSAGE_FORM_MOBILE_BREAKPOINT_QUERY).matches;
}

function setMessageFormScrollVisibility(isVisible) {
    const form = document.getElementById('message-form');
    if (!form) return;

    if (!isMessageFormMobileViewport()) {
        form.style.transform = '';
        form.style.opacity = '';
        form.style.pointerEvents = '';
        form.style.transition = '';
        form.style.maxHeight = '';
        form.style.overflow = '';
        form.style.padding = '';
        form.style.borderTopWidth = '';
        return;
    }

    form.style.transition = 'transform 0.25s ease, opacity 0.25s ease, max-height 0.25s ease, padding 0.25s ease';

    if (isVisible) {
        form.style.transform = '';
        form.style.opacity = '';
        form.style.pointerEvents = '';
        form.style.maxHeight = '';
        form.style.overflow = '';
        form.style.padding = '';
        form.style.borderTopWidth = '';
    } else {
        form.style.transform = 'translateY(100%)';
        form.style.opacity = '0';
        form.style.pointerEvents = 'none';
        form.style.maxHeight = '0';
        form.style.overflow = 'hidden';
        form.style.padding = '0 1.5rem';
        form.style.borderTopWidth = '0';
    }
}

function bindMessageFormScrollBehavior(messagesBox) {
    if (!messagesBox || messagesBox.dataset.formScrollBound === '1') return;

    messagesBox.dataset.formScrollBound = '1';
    messageFormLastScrollTop = Math.max(0, messagesBox.scrollTop || 0);

    messagesBox.addEventListener('scroll', () => {
        if (!isMessageFormMobileViewport()) return;

        const currentScrollTop = Math.max(0, messagesBox.scrollTop || 0);
        const delta = currentScrollTop - messageFormLastScrollTop;
        const isNearBottom = (messagesBox.scrollHeight - messagesBox.clientHeight - currentScrollTop) < 50;

        if (isNearBottom) {
            setMessageFormScrollVisibility(true);
            messageFormLastScrollTop = currentScrollTop;
            return;
        }

        if (Math.abs(delta) < MESSAGE_FORM_SCROLL_DELTA_PX) {
            return;
        }

        if (delta > 0) {
            setMessageFormScrollVisibility(true);
        } else {
            setMessageFormScrollVisibility(false);
        }

        messageFormLastScrollTop = currentScrollTop;
    }, { passive: true });

    window.addEventListener('resize', () => {
        setMessageFormScrollVisibility(true);
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
    const canPinMessages = canCurrentUserPinMessages();
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
            unpinButton.classList.toggle('hidden', !canPinMessages);
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
        unpinButton.classList.toggle('hidden', !canPinMessages);
        unpinButton.disabled = !canPinMessages;
        unpinButton.classList.toggle('opacity-50', !canPinMessages);
        unpinButton.classList.toggle('cursor-not-allowed', !canPinMessages);
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
    bindMessageFormScrollBehavior(messagesBox);

    messagesBox.addEventListener('click', (event) => {
        const systemEventsToggleButton = event.target.closest('.js-system-events-toggle');
        if (systemEventsToggleButton) {
            event.preventDefault();

            const groupKey = String(systemEventsToggleButton.getAttribute('data-system-event-group-key') || '');
            if (!groupKey) {
                return;
            }

            const hiddenCount = Math.max(0, Number(systemEventsToggleButton.getAttribute('data-system-event-hidden-count') || 0));
            const currentlyExpanded = systemEventsToggleButton.getAttribute('aria-expanded') === 'true';
            const nextExpanded = !currentlyExpanded;

            setSystemEventGroupExpanded(Number(currentChat?.id || 0), groupKey, nextExpanded);

            const groupedRows = messagesBox.querySelectorAll(`[data-system-event-group-key="${groupKey}"][data-system-event-hidden="1"]`);
            groupedRows.forEach((row) => {
                if (!(row instanceof HTMLElement)) return;
                row.classList.toggle('hidden', !nextExpanded);
            });

            systemEventsToggleButton.setAttribute('aria-expanded', nextExpanded ? 'true' : 'false');
            const toggleLabel = systemEventsToggleButton.querySelector('.js-system-events-toggle-label');
            if (toggleLabel) {
                toggleLabel.textContent = nextExpanded
                    ? `Hide ${hiddenCount} ${hiddenCount === 1 ? 'event' : 'events'}`
                    : `Show ${hiddenCount} more ${hiddenCount === 1 ? 'event' : 'events'}`;
            }

            return;
        }

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

    const closeBtn = document.getElementById('emoji-drawer-close');

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

    if (closeBtn) {
        closeBtn.addEventListener('click', (event) => {
            event.preventDefault();
            setOpenState(false);
            search.value = '';
            renderGrid(getDefaultEmojiItems());
        });
    }

    search.addEventListener('input', () => {
        renderGrid(findEmojiTypeaheadMatches(search.value));
    });

    grid.addEventListener('click', (event) => {
        const button = event.target.closest('[data-emoji]');
        if (!button) return;

        const emoji = button.getAttribute('data-emoji') || '';
        if (!emoji) return;

        insertTextAtCursor(input, emoji);

        if (isMessageFormMobileViewport()) {
            setOpenState(false);
            search.value = '';
            renderGrid(getDefaultEmojiItems());
        }
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
                <div class="mt-1.5 flex items-center justify-between gap-2">
                    <label class="text-[11px] text-zinc-500" for="pending-attachment-expiry-${attachment.id}">Expiry</label>
                    <select id="pending-attachment-expiry-${attachment.id}" class="bg-zinc-900 border border-zinc-700 rounded px-2 py-1 text-[11px] text-zinc-200" data-attachment-expiry="${attachment.id}">
                        <option value="0" ${Number(attachment.expiry_seconds || 0) === 0 ? 'selected' : ''}>None</option>
                        <option value="3600" ${Number(attachment.expiry_seconds || 0) === 3600 ? 'selected' : ''}>1 hour</option>
                        <option value="86400" ${Number(attachment.expiry_seconds || 0) === 86400 ? 'selected' : ''}>24 hours</option>
                    </select>
                </div>
                <div class="mt-1 text-xs text-zinc-500 flex items-center justify-between gap-2">
                    <span>${escapeHtml(formatFileSize(attachment.file_size))} · ${escapeHtml(formatAttachmentExpiryLabel(attachment.expiry_seconds || 0))}</span>
                    <button type="button" class="text-red-300 hover:text-red-200" data-attachment-delete="${attachment.id}" title="Delete pending attachment" aria-label="Delete pending attachment">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </div>
            </div>
        `;
    }).join('');
}

async function uploadAttachmentFile(file, expirySeconds = 0) {
    if (!currentChat) return;
    if (normalizeChatType(currentChat.type) === 'personal' && currentChat.can_send_messages === false) {
        showToast(getPersonalChatMessageRestrictionToast(currentChat.message_restriction_reason), 'error');
        return;
    }

    const formData = new FormData();
    formData.append('csrf_token', getCsrfToken());
    formData.append('chat_id', String(currentChat.id));
    formData.append('attachment', file);
    formData.append('expiry_seconds', String(Number(expirySeconds || 0)));

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

async function updatePendingAttachmentExpiry(attachmentId, expirySeconds) {
    const id = Number(attachmentId || 0);
    const seconds = Number(expirySeconds || 0);
    if (!Number.isFinite(id) || id <= 0) return;
    if (![0, 3600, 86400].includes(seconds)) return;

    const result = await postForm('/api/attachments/expiry', {
        csrf_token: getCsrfToken(),
        attachment_id: String(id),
        expiry_seconds: String(seconds)
    });

    if (!result.success) {
        showToast(result.error || 'Unable to update attachment expiry', 'error');
        renderPendingAttachmentList();
        return;
    }

    pendingAttachments = pendingAttachments.map((attachment) => {
        if (Number(attachment.id) !== id) return attachment;
        return { ...attachment, expiry_seconds: seconds };
    });
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
            await uploadAttachmentFile(file, 0);
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

    list.addEventListener('change', (event) => {
        const select = event.target.closest('[data-attachment-expiry]');
        if (!select) return;
        const attachmentId = Number(select.getAttribute('data-attachment-expiry') || 0);
        const expirySeconds = Number(select.value || 0);
        updatePendingAttachmentExpiry(attachmentId, expirySeconds).catch(() => {
            showToast('Unable to update attachment expiry', 'error');
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
        const image = document.getElementById('attachment-lightbox-image');
        if (event.target === image) return;
        closeOverlay();
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !overlay.classList.contains('hidden')) {
            closeOverlay();
        }
    });
}


const SIDEBAR_CUSTOM_MAX_ITEMS = 8;
const SIDEBAR_MODE_LOCALSTORAGE_KEY = 'prologue.sidebar.chatMode';
const SIDEBAR_CUSTOM_DEFAULT_LABEL = 'Custom';
const SIDEBAR_CUSTOM_MAX_LABEL_LENGTH = 10;

let sidebarChatsCache = [];
let sidebarCustomChatNumbers = [];
let sidebarCustomLabel = SIDEBAR_CUSTOM_DEFAULT_LABEL;
let sidebarCustomConfigLoaded = false;
let sidebarMode = 'all';
let sidebarModeInitialized = false;
let sidebarIsDraggingCustom = false;
let sidebarCustomContextChatNumber = '';

function navigateWithFallback(url, options = {}) {
    const targetUrl = String(url || '').trim();
    if (!targetUrl) return;

    if (typeof window.navigateApp === 'function') {
        window.navigateApp(targetUrl, options).catch(() => {
            window.location.href = targetUrl;
        });
        return;
    }

    window.location.href = targetUrl;
}

async function reloadCurrentView(options = {}) {
    if (typeof window.reloadAppView === 'function') {
        const success = await window.reloadAppView(options).catch(() => false);
        if (success) return;
    }

    window.location.reload();
}

function normalizeSidebarMode(value) {
    const mode = String(value || '').trim().toLowerCase();
    if (mode === 'pm' || mode === 'group' || mode === 'custom' || mode === 'all') {
        return mode;
    }
    return 'all';
}

function getStoredSidebarMode() {
    try {
        return normalizeSidebarMode(window.localStorage?.getItem(SIDEBAR_MODE_LOCALSTORAGE_KEY));
    } catch {
        return 'all';
    }
}

function setStoredSidebarMode(mode) {
    try {
        window.localStorage?.setItem(SIDEBAR_MODE_LOCALSTORAGE_KEY, normalizeSidebarMode(mode));
    } catch {
    }
}

function getSidebarElements() {
    return {
        modeToggle: document.getElementById('chat-sidebar-mode-toggle'),
        list: document.getElementById('chat-sidebar-list'),
        customControls: document.getElementById('chat-sidebar-custom-controls'),
        customCount: document.getElementById('chat-sidebar-custom-count'),
        customHelp: document.getElementById('chat-sidebar-custom-help'),
        customSearch: document.getElementById('chat-sidebar-custom-search'),
        customTypeahead: document.getElementById('chat-sidebar-custom-typeahead'),
        customMenu: document.getElementById('chat-sidebar-custom-menu')
    };
}

function normalizeSidebarCustomLabel(value) {
    const normalized = String(value ?? '').replace(/\s+/g, ' ').trim();
    if (!normalized) return SIDEBAR_CUSTOM_DEFAULT_LABEL;
    return normalized.slice(0, SIDEBAR_CUSTOM_MAX_LABEL_LENGTH);
}

function getSidebarCustomLabel() {
    return normalizeSidebarCustomLabel(sidebarCustomLabel);
}

function applySidebarCustomLabel() {
    const label = getSidebarCustomLabel();

    const modeLabel = document.getElementById('chat-sidebar-custom-mode-label');
    if (modeLabel) {
        modeLabel.textContent = label;
    }

    const { customHelp } = getSidebarElements();
    if (customHelp) {
        customHelp.textContent = `Add a chat to ${label} by right clicking on it`;
    }
}

function updateSidebarCustomCount() {
    const { customCount } = getSidebarElements();
    if (!customCount) return;
    customCount.textContent = `${getSidebarCustomLabel()} (${sidebarCustomChatNumbers.length}/${SIDEBAR_CUSTOM_MAX_ITEMS})`;
}

function getSidebarUnreadFlags() {
    const hasUnreadIn = (items) => items.some((chat) => Number(chat?.unread_count || 0) > 0);
    const customChatNumbers = new Set(sidebarCustomChatNumbers);

    return {
        pm: hasUnreadIn(sidebarChatsCache.filter((chat) => normalizeChatType(chat?.type) === 'personal')),
        group: hasUnreadIn(sidebarChatsCache.filter((chat) => normalizeChatType(chat?.type) === 'group')),
        custom: hasUnreadIn(sidebarChatsCache.filter((chat) => customChatNumbers.has(String(chat?.chat_number || ''))))
    };
}

function getChatSidebarTitle(chat) {
    const type = normalizeChatType(chat?.type);
    if (type === 'group') {
        return chat?.chat_title || formatNumber(chat?.chat_number || '');
    }
    return chat?.chat_title || `Chat ${formatNumber(chat?.chat_number || '')}`;
}

function hideSidebarCustomTypeahead() {
    const { customTypeahead } = getSidebarElements();
    if (!customTypeahead) return;
    customTypeahead.replaceChildren();
    customTypeahead.classList.add('hidden');
}

function getChatByNumber(chatNumber) {
    const value = String(chatNumber || '');
    return sidebarChatsCache.find((chat) => String(chat.chat_number || '') === value) || null;
}

function sanitizeCustomChatNumbers(chatNumbers, chats) {
    const validChatNumbers = new Set((Array.isArray(chats) ? chats : []).map((chat) => String(chat.chat_number || '')));
    const unique = [];
    const seen = new Set();

    (Array.isArray(chatNumbers) ? chatNumbers : []).forEach((value) => {
        const number = String(value || '').trim();
        if (!number || seen.has(number)) return;
        if (!validChatNumbers.has(number)) return;
        seen.add(number);
        unique.push(number);
    });

    return unique.slice(0, SIDEBAR_CUSTOM_MAX_ITEMS);
}

async function fetchSidebarCustomConfig() {
    if (sidebarCustomConfigLoaded) return;

    try {
        const response = await fetch('/api/chats/sidebar-custom');
        if (!response.ok) {
            throw new Error('Unable to load custom sidebar config');
        }
        const payload = await response.json();
        const incoming = Array.isArray(payload?.chat_numbers) ? payload.chat_numbers : [];
        sidebarCustomChatNumbers = incoming.map((value) => String(value || '').trim()).filter(Boolean).slice(0, SIDEBAR_CUSTOM_MAX_ITEMS);
    } catch {
        sidebarCustomChatNumbers = [];
    }

    try {
        const response = await fetch('/api/chats/sidebar-custom-label');
        if (!response.ok) {
            throw new Error('Unable to load custom sidebar label');
        }
        const payload = await response.json();
        sidebarCustomLabel = normalizeSidebarCustomLabel(payload?.custom_label);
    } catch {
        sidebarCustomLabel = SIDEBAR_CUSTOM_DEFAULT_LABEL;
    }

    sidebarCustomConfigLoaded = true;
    applySidebarCustomLabel();
}

async function persistSidebarCustomConfig() {
    const payload = JSON.stringify(sidebarCustomChatNumbers.slice(0, SIDEBAR_CUSTOM_MAX_ITEMS));
    const result = await postForm('/api/chats/sidebar-custom', {
        csrf_token: getCsrfToken(),
        chat_numbers: payload
    });
    if (!result || result.success !== true) {
        throw new Error(result?.error || 'Unable to save custom sidebar config');
    }
}

async function persistSidebarCustomLabel(nextLabel) {
    const result = await postForm('/api/chats/sidebar-custom-label', {
        csrf_token: getCsrfToken(),
        label: String(nextLabel ?? '')
    });
    if (!result || result.success !== true) {
        throw new Error(result?.error || 'Unable to save custom sidebar label');
    }
    sidebarCustomLabel = normalizeSidebarCustomLabel(result?.custom_label);
    applySidebarCustomLabel();
}

async function syncSidebarCustomChatNumbers(chats) {
    const sanitized = sanitizeCustomChatNumbers(sidebarCustomChatNumbers, chats);
    const changed = sanitized.length !== sidebarCustomChatNumbers.length
        || sanitized.some((value, index) => value !== sidebarCustomChatNumbers[index]);

    if (!changed) return;
    sidebarCustomChatNumbers = sanitized;

    if (!sidebarCustomConfigLoaded) return;

    try {
        await persistSidebarCustomConfig();
    } catch {
    }
}

function buildSidebarChatNode(chat, options = {}) {
    const { customMode = false } = options;
    const type = normalizeChatType(chat.type);
    const unreadCount = Math.max(0, Number(chat.unread_count || 0));
    const hasPersonalStatus = type === 'personal' && Boolean(chat.effective_status_label);
    const showFavoriteStar = type === 'personal' && (Number(chat.is_favorite) === 1 || chat.is_favorite === true);
    const sidebarTitle = getChatSidebarTitle(chat);
    const rawMessage = decodeStoredMentionsToPlainText(chat.last_message || '');
    const secondaryLine = (() => {
        if (!rawMessage) return 'No messages yet';
        const senderId = Number(chat.last_message_user_id || 0);
        const prefix = senderId && senderId === Number(currentUserId || 0)
            ? 'You'
            : (chat.last_message_sender_username || '');
        return prefix ? `${prefix}: ${rawMessage}` : rawMessage;
    })();

    const item = document.createElement('a');
    item.href = `/c/${formatNumber(chat.chat_number)}`;
    item.className = 'block py-2 px-3 rounded-xl hover:bg-zinc-800 mb-1';
    item.dataset.sidebarChatNumber = String(chat.chat_number || '');
    item.dataset.sidebarChatType = type;

    if (window.location.pathname === item.getAttribute('href')) {
        item.classList.add('bg-zinc-800/80');
    }

    if (customMode) {
        item.draggable = true;
        item.classList.add('cursor-move');
        item.dataset.sidebarCustomItem = '1';
    }

    const primaryLine = document.createElement('div');
    primaryLine.className = 'font-medium flex items-center gap-2 min-w-0';

    const title = document.createElement('span');
    title.className = 'truncate';
    title.textContent = String(sidebarTitle);
    primaryLine.appendChild(title);

    const typeBadge = document.createElement('span');
    typeBadge.className = type === 'group'
        ? 'shrink-0 px-1.5 py-0.5 rounded-md text-[10px] uppercase tracking-wide border border-sky-500/30 bg-sky-500/10 text-sky-200'
        : 'shrink-0 px-1.5 py-0.5 rounded-md text-[10px] uppercase tracking-wide border border-violet-500/30 bg-violet-500/10 text-violet-200';
    typeBadge.textContent = type === 'group' ? 'Group' : 'PM';
    primaryLine.appendChild(typeBadge);

    if (showFavoriteStar) {
        const star = document.createElement('i');
        star.className = 'fa-solid fa-star text-amber-400 text-[11px]';
        star.title = 'Favorite';
        primaryLine.appendChild(star);
    }

    if (hasPersonalStatus) {
        const statusDot = document.createElement('span');
        statusDot.className = `inline-block w-2 h-2 rounded-full ${String(chat.effective_status_dot_class || 'bg-zinc-500')}`;
        statusDot.title = String(chat.effective_status_label || 'Offline');
        primaryLine.appendChild(statusDot);
    }

    if (customMode) {
        const dragIcon = document.createElement('i');
        dragIcon.className = 'fa-solid fa-grip-vertical text-zinc-500 text-[10px]';
        dragIcon.title = 'Drag to reorder';
        primaryLine.appendChild(dragIcon);
    }

    if (unreadCount > 0) {
        const unreadBadge = document.createElement('span');
        unreadBadge.className = 'ml-auto min-w-[1.25rem] h-5 px-1 rounded-full bg-emerald-600 text-white text-xs inline-flex items-center justify-center';
        unreadBadge.textContent = String(formatCountBadgeValue(unreadCount));
        primaryLine.appendChild(unreadBadge);
    }

    const secondary = document.createElement('div');
    secondary.className = 'text-xs text-zinc-400 truncate';
    secondary.textContent = String(secondaryLine);

    item.appendChild(primaryLine);
    item.appendChild(secondary);
    return item;
}

function getVisibleSidebarChats() {
    if (sidebarMode === 'pm') {
        return sidebarChatsCache.filter((chat) => normalizeChatType(chat.type) === 'personal');
    }

    if (sidebarMode === 'group') {
        return sidebarChatsCache.filter((chat) => normalizeChatType(chat.type) === 'group');
    }

    if (sidebarMode === 'custom') {
        const chatByNumber = new Map(sidebarChatsCache.map((chat) => [String(chat.chat_number || ''), chat]));
        return sidebarCustomChatNumbers
            .map((chatNumber) => chatByNumber.get(chatNumber))
            .filter(Boolean);
    }

    return sidebarChatsCache;
}

function updateSidebarModeButtons() {
    const { modeToggle, customControls } = getSidebarElements();
    if (!modeToggle) return;
    const unreadFlags = getSidebarUnreadFlags();

    const buttons = modeToggle.querySelectorAll('[data-chat-sidebar-mode]');
    buttons.forEach((button) => {
        if (!(button instanceof HTMLElement)) return;
        const mode = normalizeSidebarMode(button.dataset.chatSidebarMode);
        const isActive = mode === sidebarMode;
        const shouldHighlightUnread = mode !== 'all' && unreadFlags[mode] === true;

        button.classList.remove('bg-emerald-600', 'bg-emerald-600/25', 'text-emerald-100', 'text-emerald-200');
        button.classList.toggle('bg-zinc-700', isActive);
        button.classList.toggle('text-zinc-100', isActive);
        button.classList.toggle('text-zinc-400', !isActive);
        button.classList.toggle('hover:text-zinc-200', !isActive);

        if (shouldHighlightUnread) {
            if (isActive) {
                button.classList.remove('bg-zinc-700', 'text-zinc-100');
                button.classList.add('bg-emerald-600', 'text-emerald-100');
            } else {
                button.classList.remove('text-zinc-400');
                button.classList.add('bg-emerald-600', 'text-emerald-100');
            }
        }
    });

    if (customControls) {
        customControls.classList.toggle('hidden', sidebarMode !== 'custom');
    }
}

function clearSidebarDragIndicators() {
    const { list } = getSidebarElements();
    if (!list) return;
    list.querySelectorAll('[data-sidebar-custom-item]').forEach((node) => {
        node.classList.remove('ring-1', 'ring-emerald-500/60');
    });
}

function renderSidebarList() {
    const { list } = getSidebarElements();
    if (!list) return;
    updateSidebarCustomCount();
    updateSidebarModeButtons();

    const chats = getVisibleSidebarChats();
    const nodes = chats.map((chat) => buildSidebarChatNode(chat, { customMode: sidebarMode === 'custom' }));

    if (nodes.length > 0) {
        list.replaceChildren(...nodes);
        return;
    }

    const empty = document.createElement('div');
    empty.className = 'text-zinc-500 text-sm px-3 py-1';
    empty.textContent = sidebarMode === 'custom'
        ? `${getSidebarCustomLabel()} list is empty`
        : 'No chats yet';
    list.replaceChildren(empty);
}

function bindSidebarCustomRenameModal() {
    const modal = document.getElementById('sidebar-custom-rename-modal');
    const form = document.getElementById('sidebar-custom-rename-form');
    const input = document.getElementById('sidebar-custom-rename-input');
    const cancel = document.getElementById('sidebar-custom-rename-cancel');
    const submit = document.getElementById('sidebar-custom-rename-submit');
    if (!modal || !form || !input || !cancel || !submit) return;
    if (modal.dataset.bound === '1') return;
    modal.dataset.bound = '1';

    const setOpenState = (isOpen) => {
        modal.classList.toggle('hidden', !isOpen);
        modal.setAttribute('aria-hidden', isOpen ? 'false' : 'true');

        if (isOpen) {
            const current = getSidebarCustomLabel();
            input.value = current === SIDEBAR_CUSTOM_DEFAULT_LABEL ? '' : current;
            setTimeout(() => {
                input.focus();
                input.select();
            }, 0);
            return;
        }

        submit.disabled = false;
        submit.textContent = 'Save';
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

        const submitted = String(input.value || '').trim();
        const isReset = submitted === '';

        submit.disabled = true;
        submit.textContent = 'Saving...';

        try {
            await persistSidebarCustomLabel(submitted);
            updateSidebarCustomCount();
            if (sidebarMode === 'custom') {
                renderSidebarList();
            }
            closeModal();
            showToast(isReset ? 'Category reset to Custom' : 'Category renamed', 'success');
        } catch {
            showToast('Unable to rename category', 'error');
        } finally {
            submit.disabled = false;
            submit.textContent = 'Save';
        }
    });

    window.openSidebarCustomRenameModal = () => setOpenState(true);
}

function renderSidebarCustomTypeahead() {
    const { customSearch, customTypeahead } = getSidebarElements();
    if (!customSearch || !customTypeahead || sidebarMode !== 'custom') {
        hideSidebarCustomTypeahead();
        return;
    }

    const query = String(customSearch.value || '').trim().toLowerCase();
    if (!query) {
        hideSidebarCustomTypeahead();
        return;
    }

    if (sidebarCustomChatNumbers.length >= SIDEBAR_CUSTOM_MAX_ITEMS) {
        hideSidebarCustomTypeahead();
        return;
    }

    const selected = new Set(sidebarCustomChatNumbers);
    const matches = sidebarChatsCache.filter((chat) => {
        const chatNumber = String(chat.chat_number || '');
        if (selected.has(chatNumber)) return false;
        const title = String(getChatSidebarTitle(chat)).toLowerCase();
        const formatted = String(formatNumber(chatNumber)).toLowerCase();
        return title.includes(query) || formatted.includes(query) || chatNumber.includes(query);
    }).slice(0, 6);

    if (matches.length === 0) {
        hideSidebarCustomTypeahead();
        return;
    }

    const buttons = matches.map((chat) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'w-full text-left px-3 py-2 hover:bg-zinc-800 text-sm flex items-center justify-between gap-2';
        button.dataset.sidebarCustomTypeahead = String(chat.chat_number || '');

        const title = document.createElement('span');
        title.className = 'truncate';
        title.textContent = String(getChatSidebarTitle(chat));

        const badge = document.createElement('span');
        badge.className = 'text-[10px] uppercase tracking-wide text-zinc-400 shrink-0';
        badge.textContent = normalizeChatType(chat.type) === 'group' ? 'Group' : 'PM';

        button.appendChild(title);
        button.appendChild(badge);
        return button;
    });

    customTypeahead.replaceChildren(...buttons);
    customTypeahead.classList.remove('hidden');
}

async function addChatToCustomSidebar(chatNumber) {
    const number = String(chatNumber || '').trim();
    if (!number) return;
    if (sidebarCustomChatNumbers.includes(number)) return;

    if (sidebarCustomChatNumbers.length >= SIDEBAR_CUSTOM_MAX_ITEMS) {
        showToast('Custom list can have up to 8 chats', 'error');
        return;
    }

    if (!getChatByNumber(number)) {
        showToast('Chat unavailable', 'error');
        return;
    }

    sidebarCustomChatNumbers.push(number);

    try {
        await persistSidebarCustomConfig();
        renderSidebarList();
    } catch {
        sidebarCustomChatNumbers = sidebarCustomChatNumbers.filter((value) => value !== number);
        showToast('Unable to update custom list', 'error');
    }
}

async function removeChatFromCustomSidebar(chatNumber) {
    const number = String(chatNumber || '').trim();
    if (!number) return;

    const next = sidebarCustomChatNumbers.filter((value) => value !== number);
    if (next.length === sidebarCustomChatNumbers.length) return;

    const previous = [...sidebarCustomChatNumbers];
    sidebarCustomChatNumbers = next;

    try {
        await persistSidebarCustomConfig();
        renderSidebarList();
    } catch {
        sidebarCustomChatNumbers = previous;
        showToast('Unable to update custom list', 'error');
    }
}

async function reorderCustomSidebarChats(draggedChatNumber, targetChatNumber) {
    const dragged = String(draggedChatNumber || '').trim();
    const target = String(targetChatNumber || '').trim();
    if (!dragged || !target || dragged === target) return;

    const current = [...sidebarCustomChatNumbers];
    const fromIndex = current.indexOf(dragged);
    const toIndex = current.indexOf(target);
    if (fromIndex < 0 || toIndex < 0) return;

    current.splice(fromIndex, 1);
    current.splice(toIndex, 0, dragged);

    if (current.join(',') === sidebarCustomChatNumbers.join(',')) return;
    const previous = [...sidebarCustomChatNumbers];
    sidebarCustomChatNumbers = current;

    try {
        await persistSidebarCustomConfig();
        renderSidebarList();
    } catch {
        sidebarCustomChatNumbers = previous;
        renderSidebarList();
        showToast('Unable to save custom order', 'error');
    }
}

async function copyTextFallback(text) {
    const value = String(text || '');
    if (!value) return;
    if (typeof window.copyTextToClipboard === 'function') {
        await window.copyTextToClipboard(value);
        return;
    }

    if (navigator.clipboard && window.isSecureContext) {
        await navigator.clipboard.writeText(value);
        return;
    }

    const tempInput = document.createElement('textarea');
    tempInput.value = value;
    tempInput.setAttribute('readonly', '');
    tempInput.style.position = 'absolute';
    tempInput.style.left = '-9999px';
    document.body.appendChild(tempInput);
    tempInput.select();
    document.execCommand('copy');
    tempInput.remove();
}

function hideSidebarCustomContextMenu() {
    const { customMenu } = getSidebarElements();
    if (!customMenu) return;
    customMenu.classList.add('hidden');
    sidebarCustomContextChatNumber = '';
}

function setSidebarContextMenuButtonVisibility(action, visible) {
    const { customMenu } = getSidebarElements();
    if (!customMenu) return;

    const button = customMenu.querySelector(`[data-custom-menu-action="${action}"]`);
    if (!(button instanceof HTMLButtonElement)) return;

    button.classList.toggle('hidden', !visible);
}

function showSidebarCustomContextMenu(event, chatNumber) {
    const { customMenu } = getSidebarElements();
    if (!customMenu) return;

    const chat = getChatByNumber(chatNumber);
    const isGroup = normalizeChatType(chat?.type) === 'group';
    const number = String(chatNumber || '').trim();
    const inCustomList = sidebarCustomChatNumbers.includes(number);
    const canAddToCustom = !inCustomList && sidebarCustomChatNumbers.length < SIDEBAR_CUSTOM_MAX_ITEMS;

    setSidebarContextMenuButtonVisibility('open', Boolean(chat));
    setSidebarContextMenuButtonVisibility('add', canAddToCustom);
    setSidebarContextMenuButtonVisibility('copy-link', isGroup);
    setSidebarContextMenuButtonVisibility('copy-number', isGroup);
    setSidebarContextMenuButtonVisibility('remove', inCustomList);

    customMenu.style.left = `${event.clientX}px`;
    customMenu.style.top = `${event.clientY}px`;
    customMenu.classList.remove('hidden');
    sidebarCustomContextChatNumber = String(chatNumber || '');

    const rect = customMenu.getBoundingClientRect();
    const maxLeft = window.innerWidth - rect.width - 8;
    const maxTop = window.innerHeight - rect.height - 8;
    customMenu.style.left = `${Math.max(8, Math.min(event.clientX, maxLeft))}px`;
    customMenu.style.top = `${Math.max(8, Math.min(event.clientY, maxTop))}px`;
}

function bindSidebarCustomMenu() {
    const { customMenu } = getSidebarElements();
    if (!customMenu || customMenu.dataset.bound === '1') return;
    customMenu.dataset.bound = '1';

    customMenu.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-custom-menu-action]');
        if (!(button instanceof HTMLButtonElement)) return;

        const action = String(button.dataset.customMenuAction || '');
        const chatNumber = String(sidebarCustomContextChatNumber || '');
        const chat = getChatByNumber(chatNumber);
        if (!chatNumber || !chat) {
            hideSidebarCustomContextMenu();
            return;
        }

        if ((action === 'copy-link' || action === 'copy-number') && normalizeChatType(chat.type) !== 'group') {
            hideSidebarCustomContextMenu();
            return;
        }

        try {
            if (action === 'open') {
                window.open(`/c/${formatNumber(chatNumber)}`, '_blank', 'noopener');
            } else if (action === 'add') {
                await addChatToCustomSidebar(chatNumber);
            } else if (action === 'copy-link') {
                await copyTextFallback(`${window.location.origin}/c/${formatNumber(chatNumber)}`);
                showToast('Group link copied', 'success');
            } else if (action === 'copy-number') {
                await copyTextFallback(formatNumber(chatNumber));
                showToast('Group number copied', 'success');
            } else if (action === 'remove') {
                await removeChatFromCustomSidebar(chatNumber);
            }
        } catch {
            showToast('Unable to complete action', 'error');
        }

        hideSidebarCustomContextMenu();
    });

    document.addEventListener('click', (event) => {
        if (customMenu.classList.contains('hidden')) return;
        if (customMenu.contains(event.target)) return;
        hideSidebarCustomContextMenu();
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        if (customMenu.classList.contains('hidden')) return;
        hideSidebarCustomContextMenu();
    });
}

function bindSidebarControls() {
    const { modeToggle, customSearch, customTypeahead, list } = getSidebarElements();
    if (!modeToggle || !list) return;
    if (modeToggle.dataset.bound === '1') return;
    modeToggle.dataset.bound = '1';

    modeToggle.addEventListener('click', (event) => {
        const button = event.target.closest('[data-chat-sidebar-mode]');
        if (!(button instanceof HTMLButtonElement)) return;
        const nextMode = normalizeSidebarMode(button.dataset.chatSidebarMode);
        if (nextMode === sidebarMode) return;

        sidebarMode = nextMode;
        setStoredSidebarMode(nextMode);
        updateSidebarModeButtons();
        hideSidebarCustomTypeahead();
        hideSidebarCustomContextMenu();
        renderSidebarList();

        if (customSearch && sidebarMode === 'custom') {
            customSearch.focus();
        }
    });

    modeToggle.addEventListener('contextmenu', (event) => {
        const target = event.target.closest('[data-chat-sidebar-mode="custom"]');
        if (!(target instanceof HTMLButtonElement)) return;

        event.preventDefault();
        hideSidebarCustomContextMenu();
        hideSidebarCustomTypeahead();
        if (typeof window.openSidebarCustomRenameModal === 'function') {
            window.openSidebarCustomRenameModal();
        }
    });

    if (customSearch) {
        customSearch.addEventListener('input', () => {
            renderSidebarCustomTypeahead();
        });

        customSearch.addEventListener('focus', () => {
            renderSidebarCustomTypeahead();
        });

        customSearch.addEventListener('keydown', async (event) => {
            if (event.key !== 'Enter') return;
            if (!customTypeahead || customTypeahead.classList.contains('hidden')) return;
            const options = customTypeahead.querySelectorAll('[data-sidebar-custom-typeahead]');
            if (options.length !== 1) return;
            event.preventDefault();
            const chatNumber = String(options[0].getAttribute('data-sidebar-custom-typeahead') || '').trim();
            if (!chatNumber) return;
            await addChatToCustomSidebar(chatNumber);
            customSearch.value = '';
            hideSidebarCustomTypeahead();
        });
    }

    if (customTypeahead) {
        customTypeahead.addEventListener('click', async (event) => {
            const button = event.target.closest('[data-sidebar-custom-typeahead]');
            if (!(button instanceof HTMLElement)) return;
            const chatNumber = String(button.dataset.sidebarCustomTypeahead || '').trim();
            if (!chatNumber) return;
            await addChatToCustomSidebar(chatNumber);
            if (customSearch) {
                customSearch.value = '';
            }
            hideSidebarCustomTypeahead();
        });
    }

    list.addEventListener('dragstart', (event) => {
        if (sidebarMode !== 'custom') return;
        const target = event.target.closest('[data-sidebar-custom-item]');
        if (!(target instanceof HTMLElement)) return;
        const chatNumber = String(target.dataset.sidebarChatNumber || '');
        if (!chatNumber) return;

        sidebarIsDraggingCustom = true;
        target.classList.add('opacity-60');
        event.dataTransfer?.setData('text/plain', chatNumber);
        if (event.dataTransfer) {
            event.dataTransfer.effectAllowed = 'move';
        }
    });

    list.addEventListener('dragover', (event) => {
        if (sidebarMode !== 'custom') return;
        event.preventDefault();

        const target = event.target.closest('[data-sidebar-custom-item]');
        if (!(target instanceof HTMLElement)) return;
        clearSidebarDragIndicators();
        target.classList.add('ring-1', 'ring-emerald-500/60');
    });

    list.addEventListener('dragleave', (event) => {
        const target = event.target.closest?.('[data-sidebar-custom-item]');
        if (!(target instanceof HTMLElement)) return;
        target.classList.remove('ring-1', 'ring-emerald-500/60');
    });

    list.addEventListener('drop', async (event) => {
        if (sidebarMode !== 'custom') return;
        event.preventDefault();

        const droppedOn = event.target.closest('[data-sidebar-custom-item]');
        const draggedChatNumber = String(event.dataTransfer?.getData('text/plain') || '').trim();
        const targetChatNumber = droppedOn instanceof HTMLElement
            ? String(droppedOn.dataset.sidebarChatNumber || '').trim()
            : '';

        clearSidebarDragIndicators();
        if (draggedChatNumber && targetChatNumber && draggedChatNumber !== targetChatNumber) {
            await reorderCustomSidebarChats(draggedChatNumber, targetChatNumber);
        }
    });

    list.addEventListener('dragend', (event) => {
        sidebarIsDraggingCustom = false;
        clearSidebarDragIndicators();
        const target = event.target.closest?.('[data-sidebar-custom-item]');
        if (target instanceof HTMLElement) {
            target.classList.remove('opacity-60');
        }
    });

    list.addEventListener('contextmenu', (event) => {
        const target = event.target.closest('[data-sidebar-chat-number]');
        if (!(target instanceof HTMLElement)) return;
        const chatNumber = String(target.dataset.sidebarChatNumber || '').trim();
        if (!chatNumber) return;

        event.preventDefault();
        showSidebarCustomContextMenu(event, chatNumber);
    });

    document.addEventListener('click', (event) => {
        if (!customSearch || !customTypeahead) return;
        if (customTypeahead.classList.contains('hidden')) return;
        if (!customSearch.contains(event.target) && !customTypeahead.contains(event.target)) {
            hideSidebarCustomTypeahead();
        }
    });

    bindSidebarCustomMenu();
    bindSidebarCustomRenameModal();
}


async function loadSidebarChats() {
    const { list } = getSidebarElements();
    if (!list) return;

    if (!sidebarCustomConfigLoaded) {
        await fetchSidebarCustomConfig();
    }

    if (!sidebarModeInitialized) {
        sidebarMode = getStoredSidebarMode();
        sidebarModeInitialized = true;
    }

    const res = await fetch('/api/chats');
    const data = await res.json();
    const chats = Array.isArray(data.chats) ? data.chats : [];

    sidebarChatsCache = chats;
    await syncSidebarCustomChatNumbers(chats);

    bindSidebarControls();
    updateSidebarModeButtons();

    if (!sidebarIsDraggingCustom) {
        renderSidebarList();
    }

    renderSidebarCustomTypeahead();
}

async function createGroupChat() {
    const result = await postForm('/api/chats/group/create', {
        csrf_token: getCsrfToken()
    });

    if (result.success && result.chat_number) {
        navigateWithFallback(`/c/${formatNumber(result.chat_number)}?prompt_group_name=1`, { source: 'create-group', updateHistory: true });
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
        await reloadCurrentView({ source: 'add-group-member' });
        return;
    }

    showToast(result.error || 'Unable to add member', 'error');
}

function bindAddUserModal() {
    const modal = document.getElementById('add-user-modal');
    const form = document.getElementById('add-user-form');
    const input = document.getElementById('add-user-input');
    const typeahead = document.getElementById('add-user-typeahead');
    const cancel = document.getElementById('add-user-cancel');
    const submit = document.getElementById('add-user-submit');
    if (!modal || !form || !input || !cancel || !submit) return;

    let friendsCache = null;

    async function loadFriends() {
        if (friendsCache) return friendsCache;
        try {
            const res = await fetch('/api/friends');
            const data = await res.json();
            friendsCache = Array.isArray(data.friends) ? data.friends : [];
        } catch {
            friendsCache = [];
        }
        return friendsCache;
    }

    function getCurrentMemberUsernames() {
        const chips = document.querySelectorAll('[data-group-member-username]');
        const names = new Set();
        chips.forEach((el) => {
            const name = String(el.dataset.groupMemberUsername || '').trim().toLowerCase();
            if (name) names.add(name);
        });
        return names;
    }

    function renderTypeahead(query) {
        if (!typeahead) return;
        const normalizedQuery = String(query || '').trim().toLowerCase();
        if (!normalizedQuery || !friendsCache) {
            typeahead.replaceChildren();
            typeahead.classList.add('hidden');
            return;
        }

        const currentMembers = getCurrentMemberUsernames();
        const matches = friendsCache
            .filter((f) => {
                const username = String(f.username || '').toLowerCase();
                return username.includes(normalizedQuery) && !currentMembers.has(username);
            })
            .slice(0, 4);

        if (matches.length === 0 || matches.length > 3) {
            typeahead.replaceChildren();
            typeahead.classList.add('hidden');
            return;
        }

        const buttons = matches.map((friend) => {
            const username = String(friend?.username || '');
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'w-full text-left px-4 py-2.5 hover:bg-zinc-800 text-sm';
            button.dataset.addUserTypeahead = username;
            button.textContent = username;
            return button;
        });

        typeahead.replaceChildren(...buttons);
        typeahead.classList.remove('hidden');
    }

    function hideTypeahead() {
        if (typeahead) {
            typeahead.replaceChildren();
            typeahead.classList.add('hidden');
        }
    }

    const setOpenState = (isOpen) => {
        modal.classList.toggle('hidden', !isOpen);

        if (isOpen) {
            input.value = '';
            hideTypeahead();
            friendsCache = null;
            loadFriends();
            setTimeout(() => {
                input.focus();
            }, 0);
            return;
        }

        submit.disabled = false;
        submit.textContent = 'Add user';
        hideTypeahead();
    };

    const closeModal = () => {
        setOpenState(false);
    };

    input.addEventListener('input', () => {
        renderTypeahead(input.value);
    });

    input.addEventListener('focus', () => {
        if (input.value.trim()) {
            renderTypeahead(input.value);
        }
    });

    input.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter') return;
        if (!typeahead || typeahead.classList.contains('hidden')) return;
        const buttons = typeahead.querySelectorAll('[data-add-user-typeahead]');
        if (buttons.length !== 1) return;
        event.preventDefault();
        const username = String(buttons[0].dataset.addUserTypeahead || '').trim();
        input.value = username;
        hideTypeahead();
    });

    if (typeahead) {
        typeahead.addEventListener('click', (event) => {
            const btn = event.target.closest('[data-add-user-typeahead]');
            if (!(btn instanceof HTMLElement)) return;
            const username = String(btn.dataset.addUserTypeahead || '').trim();
            input.value = username;
            hideTypeahead();
        });
    }

    cancel.addEventListener('click', (event) => {
        event.preventDefault();
        closeModal();
    });

    modal.addEventListener('click', (event) => {
        if (event.target !== modal) return;
        closeModal();
    });

    document.addEventListener('click', (event) => {
        if (!typeahead || typeahead.classList.contains('hidden')) return;
        if (!input.contains(event.target) && !typeahead.contains(event.target)) {
            hideTypeahead();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        if (modal.classList.contains('hidden')) return;
        if (typeahead && !typeahead.classList.contains('hidden')) {
            hideTypeahead();
            return;
        }
        closeModal();
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (submit.disabled) return;

        submit.disabled = true;
        submit.textContent = 'Adding...';
        hideTypeahead();

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

async function promoteGroupModerator(userId, username) {
    if (!currentChat) return;
    const result = await postForm('/api/chats/group/promote-moderator', {
        csrf_token: getCsrfToken(),
        chat_id: String(currentChat.id),
        user_id: String(userId)
    });

    if (result.success) {
        showToast(`${String(username || 'User')} is now a moderator`, 'success');
        await reloadCurrentView({ source: 'promote-group-moderator' });
        return;
    }

    showToast(result.error || 'Unable to promote moderator', 'error');
}

async function demoteGroupModerator(userId, username) {
    if (!currentChat) return;
    const result = await postForm('/api/chats/group/demote-moderator', {
        csrf_token: getCsrfToken(),
        chat_id: String(currentChat.id),
        user_id: String(userId)
    });

    if (result.success) {
        showToast(`${String(username || 'User')} is no longer a moderator`, 'success');
        await reloadCurrentView({ source: 'demote-group-moderator' });
        return;
    }

    showToast(result.error || 'Unable to demote moderator', 'error');
}

async function muteGroupMember(userId, username) {
    if (!currentChat) return;
    const result = await postForm('/api/chats/group/mute-member', {
        csrf_token: getCsrfToken(),
        chat_id: String(currentChat.id),
        user_id: String(userId)
    });

    if (result.success) {
        showToast(`${String(username || 'User')} was muted`, 'success');
        await reloadCurrentView({ source: 'mute-group-member' });
        return;
    }

    showToast(result.error || 'Unable to mute member', 'error');
}

async function unmuteGroupMember(userId, username) {
    if (!currentChat) return;
    const result = await postForm('/api/chats/group/unmute-member', {
        csrf_token: getCsrfToken(),
        chat_id: String(currentChat.id),
        user_id: String(userId)
    });

    if (result.success) {
        showToast(`${String(username || 'User')} was unmuted`, 'success');
        await reloadCurrentView({ source: 'unmute-group-member' });
        return;
    }

    showToast(result.error || 'Unable to unmute member', 'error');
}

async function requestCurrentGroupJoin() {
    if (!currentChat) return;

    const result = await postForm('/api/chats/group/request-join', {
        csrf_token: getCsrfToken(),
        chat_id: String(currentChat.id)
    });

    if (result.success) {
        showToast('Join request sent', 'success');
        await reloadCurrentView({ source: 'group-request-join' });
        return;
    }

    showToast(result.error || 'Unable to request access', 'error');
}

async function cancelCurrentGroupJoinRequest() {
    if (!currentChat) return;

    const result = await postForm('/api/chats/group/cancel-request', {
        csrf_token: getCsrfToken(),
        chat_id: String(currentChat.id)
    });

    if (result.success) {
        showToast('Join request cancelled', 'success');
        await reloadCurrentView({ source: 'group-cancel-request' });
        return;
    }

    showToast(result.error || 'Unable to cancel request', 'error');
}

async function approveGroupJoinRequest(userId) {
    if (!currentChat) return;

    const result = await postForm('/api/chats/group/approve-request', {
        csrf_token: getCsrfToken(),
        chat_id: String(currentChat.id),
        user_id: String(userId)
    });

    if (result.success) {
        showToast('Join request approved', 'success');
        await reloadCurrentView({ source: 'group-approve-request' });
        return;
    }

    showToast(result.error || 'Unable to approve request', 'error');
}

async function denyGroupJoinRequest(userId) {
    if (!currentChat) return;

    const result = await postForm('/api/chats/group/deny-request', {
        csrf_token: getCsrfToken(),
        chat_id: String(currentChat.id),
        user_id: String(userId)
    });

    if (result.success) {
        showToast('Join request denied', 'success');
        await reloadCurrentView({ source: 'group-deny-request' });
        return;
    }

    showToast(result.error || 'Unable to deny request', 'error');
}

window.promoteGroupModerator = promoteGroupModerator;
window.demoteGroupModerator = demoteGroupModerator;
window.muteGroupMember = muteGroupMember;
window.unmuteGroupMember = unmuteGroupMember;
window.approveGroupJoinRequest = approveGroupJoinRequest;
window.denyGroupJoinRequest = denyGroupJoinRequest;

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
    const safeCurrentUserId = Number(currentUserId || 0);
    return memberNodes
        .map((node) => {
            const userId = Number(node.getAttribute('data-group-member-user-id') || 0);
            const username = String(node.getAttribute('data-group-member-username') || '').trim();
            if (!Number.isFinite(userId) || userId <= 0 || !username) {
                return null;
            }
            if (safeCurrentUserId > 0 && userId === safeCurrentUserId) {
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
        navigateWithFallback(nextLocation, { source: 'leave-group', updateHistory: true });
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
        navigateWithFallback(String(result.redirect || '/'), { source: 'delete-group', updateHistory: true });
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
        await reloadCurrentView({ source: 'take-group-ownership' });
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
        await reloadCurrentView({ source: 'rename-chat' });
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
    if (currentChat && data.group_edit_window !== undefined) {
        currentChat.group_edit_window = String(data.group_edit_window || 'never');
    }
    if (currentChat && data.group_delete_window !== undefined) {
        currentChat.group_delete_window = String(data.group_delete_window || 'never');
    }
    const hasCanSendMessage = Object.prototype.hasOwnProperty.call(data, 'can_send_message');
    const nextRestrictionReason = Object.prototype.hasOwnProperty.call(data, 'can_send_message_reason')
        ? String(data.can_send_message_reason || '')
        : String(currentChat?.message_restriction_reason || '');
    if (currentChat && hasCanSendMessage) {
        currentChat.can_send_messages = Boolean(data.can_send_message);
        currentChat.message_restriction_reason = nextRestrictionReason;
    }
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

    if (hasCanSendMessage) {
        setChatComposerEnabled(Boolean(data.can_send_message), nextRestrictionReason);
    }

    if (Object.prototype.hasOwnProperty.call(data, 'can_start_call')) {
        setChatCallEnabled(Boolean(data.can_start_call));
    }

    if (Object.prototype.hasOwnProperty.call(data, 'user_in_active_call')) {
        setChatUserInActiveCall(Boolean(data.user_in_active_call));
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

        const editedAt = String(msg?.edited_at || '');

        return [
            messageId,
            createdAt,
            quotedMessageId,
            content,
            mentionMapFingerprint,
            attachmentFingerprint,
            reactionFingerprint,
            editedAt
        ].join('|');
    }).join('\n');
}

function getSystemEventExpandedGroupSet(chatId) {
    const safeChatId = Number(chatId || 0);
    if (!Number.isFinite(safeChatId) || safeChatId <= 0) {
        return new Set();
    }

    if (!expandedSystemEventGroupsByChat.has(safeChatId)) {
        expandedSystemEventGroupsByChat.set(safeChatId, new Set());
    }

    return expandedSystemEventGroupsByChat.get(safeChatId);
}

function isSystemEventGroupExpanded(chatId, groupKey) {
    if (!groupKey) return false;
    return getSystemEventExpandedGroupSet(chatId).has(String(groupKey));
}

function setSystemEventGroupExpanded(chatId, groupKey, expanded) {
    const safeGroupKey = String(groupKey || '');
    if (!safeGroupKey) return;

    const expandedSet = getSystemEventExpandedGroupSet(chatId);
    if (expanded) {
        expandedSet.add(safeGroupKey);
    } else {
        expandedSet.delete(safeGroupKey);
    }
}

function pruneSystemEventExpandedGroups(chatId, visibleGroupKeys) {
    const expandedSet = getSystemEventExpandedGroupSet(chatId);
    if (expandedSet.size === 0) return;

    const visibleKeys = visibleGroupKeys instanceof Set ? visibleGroupKeys : new Set();
    Array.from(expandedSet).forEach((key) => {
        if (!visibleKeys.has(key)) {
            expandedSet.delete(key);
        }
    });
}

function buildSystemEventGroupKey(chatId, runMessages) {
    const safeChatId = Number(chatId || 0);
    const firstMessage = runMessages[0] || {};
    const lastMessage = runMessages[runMessages.length - 1] || {};
    const firstId = Number(firstMessage?.id || 0);
    const lastId = Number(lastMessage?.id || 0);
    const firstTimestamp = String(firstMessage?.created_at || '');
    const lastTimestamp = String(lastMessage?.created_at || '');
    const rawFingerprint = `${safeChatId}|${firstId}|${lastId}|${runMessages.length}|${firstTimestamp}|${lastTimestamp}`;
    const fingerprintHash = Array.from(rawFingerprint).reduce((value, char) => ((value * 31) + char.charCodeAt(0)) >>> 0, 7);
    return `${safeChatId}-${firstId}-${lastId}-${runMessages.length}-${fingerprintHash.toString(16)}`;
}

function buildRenderableMessageEntries(messages, chatId) {
    const entries = [];
    const visibleSystemGroupKeys = new Set();

    if (!Array.isArray(messages) || messages.length === 0) {
        pruneSystemEventExpandedGroups(chatId, visibleSystemGroupKeys);
        return entries;
    }

    let index = 0;
    while (index < messages.length) {
        const message = messages[index];
        if (!(message?.is_system_event)) {
            entries.push({
                type: 'user',
                message,
                originalIndex: index
            });
            index += 1;
            continue;
        }

        const runStart = index;
        while (index < messages.length && (messages[index]?.is_system_event)) {
            index += 1;
        }

        const runMessages = messages.slice(runStart, index);
        if (runMessages.length <= 2) {
            runMessages.forEach((runMessage, offset) => {
                entries.push({
                    type: 'system',
                    message: runMessage,
                    originalIndex: runStart + offset,
                    groupKey: '',
                    isCollapsibleMiddle: false
                });
            });
            continue;
        }

        const groupKey = buildSystemEventGroupKey(chatId, runMessages);
        const hiddenCount = Math.max(0, runMessages.length - 2);
        const isExpanded = isSystemEventGroupExpanded(chatId, groupKey);
        visibleSystemGroupKeys.add(groupKey);

        entries.push({
            type: 'system',
            message: runMessages[0],
            originalIndex: runStart,
            groupKey,
            isCollapsibleMiddle: false
        });

        entries.push({
            type: 'system-toggle',
            groupKey,
            hiddenCount,
            expanded: isExpanded
        });

        for (let middleIndex = 1; middleIndex < runMessages.length - 1; middleIndex += 1) {
            entries.push({
                type: 'system',
                message: runMessages[middleIndex],
                originalIndex: runStart + middleIndex,
                groupKey,
                isCollapsibleMiddle: true,
                hiddenByDefault: !isExpanded
            });
        }

        entries.push({
            type: 'system',
            message: runMessages[runMessages.length - 1],
            originalIndex: index - 1,
            groupKey,
            isCollapsibleMiddle: false
        });
    }

    pruneSystemEventExpandedGroups(chatId, visibleSystemGroupKeys);
    return entries;
}

function getPersonalChatMessageRestrictionText(reason) {
    if (String(reason || '') === 'group_muted') {
        return 'You are muted in this group.';
    }

    if (String(reason || '') === 'group_members_only') {
        return 'Join this group to send messages.';
    }

    if (String(reason || '') === 'group_read_only_public') {
        return 'This is a public read-only view. Join the group to participate.';
    }

    if (String(reason || '') === 'banned_user') {
        return "You can't send messages to a banned user.";
    }

    return 'Messaging is disabled in this private chat until you add each other as friends again.';
}

function getPersonalChatMessageRestrictionToast(reason) {
    if (String(reason || '') === 'group_muted') {
        return 'You are muted in this group';
    }

    if (String(reason || '') === 'group_members_only') {
        return 'Join this group to send messages';
    }

    if (String(reason || '') === 'group_read_only_public') {
        return 'This public group view is read-only';
    }

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
        box.replaceChildren();
        return;
    }

    const subject = names.length === 1
        ? `${names[0]} is typing`
        : `${names.join(', ')} are typing`;

    box.classList.remove('hidden');
    const subjectNode = document.createTextNode(subject);
    const dots = document.createElement('span');
    dots.className = 'typing-dots';
    dots.setAttribute('aria-hidden', 'true');

    for (let i = 0; i < 3; i += 1) {
        const dot = document.createElement('span');
        dot.textContent = '.';
        dots.appendChild(dot);
    }

    box.replaceChildren(subjectNode, dots);
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
    const activeChatId = Number(currentChat?.id || 0);
    const renderEntries = buildRenderableMessageEntries(messages, activeChatId);

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

    const quotedMessageIds = new Set();
    messages.forEach((m) => {
        const qid = Number(m?.quoted_message_id || 0);
        if (qid > 0) quotedMessageIds.add(qid);
    });
    messages.forEach((m) => {
        if (!m?.is_system_event && m?.is_quoted === undefined) {
            m.is_quoted = quotedMessageIds.has(Number(m?.id || 0));
        }
    });

    let previousUserId = null;
    let previousWasSystemEvent = false;
    const chunks = [];

    renderEntries.forEach((entry) => {
        if (entry?.type === 'system-toggle') {
            const groupKey = String(entry.groupKey || '');
            const hiddenCount = Math.max(0, Number(entry.hiddenCount || 0));
            const isExpanded = Boolean(entry.expanded);
            const toggleLabel = isExpanded
                ? `Hide ${hiddenCount} ${hiddenCount === 1 ? 'event' : 'events'}`
                : `Show ${hiddenCount} more ${hiddenCount === 1 ? 'event' : 'events'}`;

            chunks.push(`
                <div class="flex gap-3 mt-1">
                    <div class="w-10 shrink-0"><div class="w-10 h-1"></div></div>
                    <div class="min-w-0 flex-1">
                        <button
                            type="button"
                            class="js-system-events-toggle text-xs text-zinc-500 hover:text-zinc-300"
                            data-system-event-group-key="${escapeHtml(groupKey)}"
                            data-system-event-hidden-count="${hiddenCount}"
                            aria-expanded="${isExpanded ? 'true' : 'false'}"
                        >
                            <span class="js-system-events-toggle-label">${escapeHtml(toggleLabel)}</span>
                        </button>
                    </div>
                </div>
            `);

            previousWasSystemEvent = true;
            previousUserId = null;
            return;
        }

        const msg = entry?.message;
        if (entry?.type === 'system' && msg?.is_system_event) {
            const isNewPrologueCluster = !previousWasSystemEvent;
            const systemEventType = String(msg?.event_type || '');
            const isCallSystemEvent = systemEventType.startsWith('call_');
            const sysFullTimestamp = String(msg.created_at || '');
            const sysCompactTimestamp = formatCompactMessageTimestamp(sysFullTimestamp);
            const groupKey = String(entry?.groupKey || '');
            const isCollapsibleMiddle = Boolean(entry?.isCollapsibleMiddle);
            const isHiddenByDefault = Boolean(entry?.hiddenByDefault);
            const middleAttrs = isCollapsibleMiddle
                ? ` data-system-event-group-key="${escapeHtml(groupKey)}" data-system-event-hidden="1"`
                : '';

            chunks.push(`
                <div class="flex gap-3 ${isNewPrologueCluster ? 'mt-4' : 'mt-1'}${isHiddenByDefault ? ' hidden' : ''}"${middleAttrs}>
                    <div class="w-10 shrink-0">
                        ${isNewPrologueCluster ? '<div class="w-10 h-10 rounded-full border border-zinc-700 bg-zinc-900 text-zinc-400 flex items-center justify-center font-semibold mt-0.5"><i class="fa-solid fa-comments text-xs" aria-hidden="true"></i></div>' : '<div class="w-10 h-10"></div>'}
                    </div>
                    <div class="min-w-0 flex-1">
                        ${isNewPrologueCluster ? `<div class="flex items-center gap-2 mb-0.5"><span class="text-sm font-semibold leading-5 text-zinc-400">Prologue</span></div>` : ''}
                        <div class="text-zinc-400 text-[15px] leading-6">${renderPlainTextWithEmoji(String(msg.content || ''))}</div>
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

        const index = Number(entry?.originalIndex || 0);

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
        const editedAt = String(msg?.edited_at || '');
        const isQuoted = !!(msg?.is_quoted);
        const hasAttachments = Array.isArray(msg?.attachments) && msg.attachments.length > 0;
        const canDeleteAttachments = canDeleteAttachmentFromMessage(msg);
        const isGroupReadOnlyViewer = normalizeChatType(currentChat?.type) === 'group' && !Boolean(currentChat?.is_group_member);
        const canReplyToMessage = !isGroupReadOnlyViewer && !isCurrentUserGroupMuted();
        const canPinMessage = !isGroupReadOnlyViewer && canCurrentUserPinMessages();

        let editButton = '';
        let deleteButton = '';
        if (canEditMessage(msg, isQuoted)) {
            editButton = `<button type="button" class="text-zinc-400 hover:text-zinc-300 js-edit-link" title="Edit" aria-label="Edit" data-edit-message-id="${Number(msg.id)}"><i class="fa-solid fa-pen" aria-hidden="true"></i></button>`;
        }
        if (canDeleteMessage(msg)) {
            deleteButton = `<button type="button" class="text-zinc-400 hover:text-zinc-300 js-delete-link" title="Delete" aria-label="Delete" data-delete-message-id="${Number(msg.id)}"><i class="fa-solid fa-trash" aria-hidden="true"></i></button>`;
        }

        const interactionControlsMarkup = isGroupReadOnlyViewer
            ? ''
            : `<div class="flex items-center gap-3 md:opacity-0 md:group-hover:opacity-100 md:pointer-events-none md:group-hover:pointer-events-auto md:transition-opacity md:duration-150 md:ease-out">
                                ${canReplyToMessage ? `<button type="button" class="text-zinc-400 hover:text-zinc-300 js-quote-link" title="Quote" aria-label="Quote" data-quote-message-id="${Number(msg.id)}" data-quote-username="${escapeHtml(String(msg.username || ''))}" data-quote-user-number="${escapeHtml(String(msg.user_number || ''))}" data-quote-content="${escapeHtml(String(msg.content || ''))}" data-quote-mention-map="${mentionMapJson}"><i class="fa-solid fa-reply" aria-hidden="true"></i></button>` : ''}
                                ${editButton}
                                ${deleteButton}
                                ${canPinMessage ? `<button type="button" class="text-zinc-400 hover:text-zinc-300 js-pin-link" title="Pin" aria-label="Pin" data-pin-message-id="${Number(msg.id)}"><i class="fa-solid fa-thumbtack" aria-hidden="true"></i></button>` : ''}
                                <button type="button" class="text-zinc-400 hover:text-zinc-300 js-react-link" title="React" aria-label="React" data-react-message-id="${Number(msg.id)}"><i class="fa-solid fa-thumbs-up" aria-hidden="true"></i></button>
                            </div>`;

        chunks.push(`
            <div class="flex gap-3 ${isNewGroup ? 'mt-4' : 'mt-1'} group" data-message-id="${Number(msg.id)}" data-message-user-id="${messageUserId}" data-message-created-at="${escapeHtml(fullTimestamp)}" data-message-is-quoted="${isQuoted ? '1' : '0'}" data-message-has-attachments="${hasAttachments ? '1' : '0'}" data-message-edited-at="${escapeHtml(editedAt)}">
                <div class="w-10 shrink-0">
                    ${isNewGroup ? renderAvatarMarkup(msg, 'w-10 h-10 mt-0.5', 'text-sm') : '<div class="w-10 h-10"></div>'}
                </div>
                <div class="min-w-0 flex-1">
                    ${isNewGroup ? `<div class="flex items-center gap-2 mb-0.5">${profileUrl ? `<a href="${escapeHtml(profileUrl)}" class="text-sm font-semibold leading-5 inline-block prologue-accent hover:text-emerald-300 hover:underline underline-offset-2">${escapeHtml(msg.username)}</a>` : `<div class="text-sm font-semibold leading-5 inline-block prologue-accent">${escapeHtml(msg.username)}</div>`}${showStatus ? `<span class="inline-block w-1.5 h-1.5 rounded-full ${escapeHtml(statusDotClass)}" title="${escapeHtml(statusLabel)}"></span>` : ''}</div>` : ''}
                    ${renderQuotedMessageBlock(msg)}
                    <div class="text-zinc-200 text-[17px] leading-6 js-message-content" data-raw-content="${escapeHtml(msg.content)}" data-mention-map="${mentionMapJson}">${renderMessageContent(msg.content, mentionMap)}</div>
                    ${renderMessageAttachments(msg.attachments, canDeleteAttachments)}
                    <div class="relative mt-0.5">
                        ${renderReactionPickerMarkup(msg.id)}
                        <div class="text-xs flex items-center gap-3">
                            <span class="text-zinc-500" data-utc="${escapeHtml(fullTimestamp)}" title="${escapeHtml(fullTimestamp)}">${escapeHtml(compactTimestamp)}</span>
                            ${editedAt ? '<span class="text-zinc-500 italic">(edited)</span>' : ''}
                            ${interactionControlsMarkup}
                            ${isGroupReadOnlyViewer ? '' : reactionBadgesMarkup}
                        </div>
                    </div>
                </div>
            </div>
        `);

        previousUserId = messageUserId;
        previousWasSystemEvent = false;
    });

    const messagesMarkup = chunks.join('');
    const fragment = document.createRange().createContextualFragment(messagesMarkup);
    box.replaceChildren(fragment);
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

function isCurrentUserGroupMuted() {
    if (!currentChat || normalizeChatType(currentChat.type) !== 'group') {
        return false;
    }

    return String(currentChat.message_restriction_reason || '') === 'group_muted';
}

function canCurrentUserPinMessages() {
    if (!currentChat) {
        return false;
    }

    if (normalizeChatType(currentChat.type) !== 'group') {
        return true;
    }

    const ownerUserId = Number(currentChat.owner_user_id || 0);
    const me = Number(currentUserId || 0);
    if (ownerUserId > 0 && ownerUserId === me) {
        return true;
    }

    return currentChat.is_group_moderator === true;
}

function isWithinWindow(windowValue, createdAt) {
    if (windowValue === 'never') return false;
    if (windowValue === 'forever') return true;
    const seconds = parseInt(windowValue, 10);
    if (isNaN(seconds) || seconds <= 0) return false;
    const messageTime = new Date(createdAt + (createdAt.includes('Z') || createdAt.includes('+') ? '' : 'Z')).getTime();
    if (isNaN(messageTime)) return false;
    return (Date.now() - messageTime) <= seconds * 1000;
}

function canEditMessage(msg, isQuoted) {
    if (!currentChat) return false;
    if (isQuoted) return false;
    const isOwn = Number(msg.user_id) === Number(currentUserId);
    const isAdmin = !!(currentChat.is_admin);
    if (!isOwn && !isAdmin) return false;
    const isPersonal = normalizeChatType(currentChat.type) === 'personal';
    if (isPersonal || isAdmin) return true;
    return isWithinWindow(currentChat.group_edit_window || 'never', String(msg.created_at || ''));
}

function canDeleteMessage(msg) {
    if (!currentChat) return false;
    if (msg?.is_quoted) return false;
    const isOwn = Number(msg.user_id) === Number(currentUserId);
    const isAdmin = !!(currentChat.is_admin);
    if (!isOwn && !isAdmin) return false;
    const isPersonal = normalizeChatType(currentChat.type) === 'personal';
    if (isPersonal || isAdmin) return true;
    return isWithinWindow(currentChat.group_delete_window || 'never', String(msg.created_at || ''));
}

function canDeleteAttachmentFromMessage(msg) {
    if (!currentChat) return false;
    const isOwn = Number(msg.user_id) === Number(currentUserId);
    const isAdmin = !!(currentChat.is_admin);
    if (!isOwn && !isAdmin) return false;
    const isPersonal = normalizeChatType(currentChat.type) === 'personal';
    if (isPersonal || isAdmin) return true;
    return isWithinWindow(currentChat.group_delete_window || 'never', String(msg.created_at || ''));
}

function bindMessageEditAndDelete() {
    const messagesContainer = document.getElementById('messages');
    if (!messagesContainer) return;

    messagesContainer.addEventListener('click', (event) => {
        const editBtn = event.target.closest('.js-edit-link');
        if (editBtn) {
            event.preventDefault();
            const messageId = Number(editBtn.dataset.editMessageId || 0);
            if (messageId > 0) startEditMessage(messageId);
            return;
        }

        const deleteBtn = event.target.closest('.js-delete-link');
        if (deleteBtn) {
            event.preventDefault();
            const messageId = Number(deleteBtn.dataset.deleteMessageId || 0);
            if (messageId > 0) promptDeleteMessage(messageId);
            return;
        }

        const attachmentDeleteBtn = event.target.closest('.js-attachment-delete');
        if (attachmentDeleteBtn) {
            event.preventDefault();
            const attachmentId = Number(attachmentDeleteBtn.dataset.attachmentId || 0);
            if (attachmentId > 0) deleteSubmittedAttachment(attachmentId);
            return;
        }

        const saveBtn = event.target.closest('.js-edit-save');
        if (saveBtn) {
            event.preventDefault();
            const messageId = Number(saveBtn.dataset.editMessageId || 0);
            if (messageId > 0) submitEditMessage(messageId);
            return;
        }

        const cancelBtn = event.target.closest('.js-edit-cancel');
        if (cancelBtn) {
            event.preventDefault();
            const messageId = Number(cancelBtn.dataset.editMessageId || 0);
            if (messageId > 0) cancelEditMessage(messageId);
            return;
        }
    });
}

async function deleteSubmittedAttachment(attachmentId) {
    try {
        const result = await postForm('/api/attachments/delete', {
            csrf_token: getCsrfToken(),
            attachment_id: attachmentId
        });

        if (result.error) {
            showToast(result.error, 'error');
            return;
        }

        await pollMessages({ scrollMode: 'preserve', forceRender: true });
    } catch (error) {
        showToast('Failed to delete attachment', 'error');
    }
}

function startEditMessage(messageId) {
    const messageDiv = document.querySelector(`[data-message-id="${messageId}"]`);
    if (!messageDiv) return;
    const contentDiv = messageDiv.querySelector('.js-message-content');
    if (!contentDiv) return;

    const rawContent = contentDiv.dataset.rawContent || '';
    contentDiv.dataset.originalContent = contentDiv.innerHTML;
    contentDiv.innerHTML = `
        <div class="flex flex-col gap-2">
            <input type="text" class="js-edit-input w-full bg-zinc-800 border border-zinc-700 rounded-xl px-4 py-2 text-zinc-100 text-[15px]" value="${escapeHtml(rawContent)}" maxlength="16384">
            <div class="flex gap-2">
                <button type="button" class="js-edit-save text-xs px-3 py-1 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white" data-edit-message-id="${messageId}">Save</button>
                <button type="button" class="js-edit-cancel text-xs px-3 py-1 rounded-lg bg-zinc-700 hover:bg-zinc-600 text-zinc-200" data-edit-message-id="${messageId}">Cancel</button>
            </div>
        </div>
    `;
    const input = contentDiv.querySelector('.js-edit-input');
    if (input) {
        input.focus();
        input.setSelectionRange(input.value.length, input.value.length);
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') { e.preventDefault(); submitEditMessage(messageId); }
            if (e.key === 'Escape') { e.preventDefault(); cancelEditMessage(messageId); }
        });
    }
}

async function submitEditMessage(messageId) {
    const messageDiv = document.querySelector(`[data-message-id="${messageId}"]`);
    if (!messageDiv) return;
    const input = messageDiv.querySelector('.js-edit-input');
    if (!input) return;
    const newContent = input.value.trim();
    if (!newContent) {
        showToast('Message cannot be empty', 'error');
        return;
    }

    try {
        const result = await postForm('/api/messages/edit', {
            csrf_token: getCsrfToken(),
            message_id: messageId,
            content: newContent
        });
        if (result.error) {
            showToast(result.error, 'error');
            cancelEditMessage(messageId);
            return;
        }
        await pollMessages({ scrollMode: 'preserve', forceRender: true });
    } catch (e) {
        showToast('Failed to edit message', 'error');
        cancelEditMessage(messageId);
    }
}

function cancelEditMessage(messageId) {
    const messageDiv = document.querySelector(`[data-message-id="${messageId}"]`);
    if (!messageDiv) return;
    const contentDiv = messageDiv.querySelector('.js-message-content');
    if (!contentDiv || !contentDiv.dataset.originalContent) return;
    contentDiv.innerHTML = contentDiv.dataset.originalContent;
    delete contentDiv.dataset.originalContent;
}

function promptDeleteMessage(messageId) {
    const modal = document.getElementById('delete-message-modal');
    const idInput = document.getElementById('delete-message-id');
    if (!modal || !idInput) return;
    idInput.value = messageId;
    modal.classList.remove('hidden');
    modal.setAttribute('aria-hidden', 'false');
}

function bindDeleteMessageModal() {
    const modal = document.getElementById('delete-message-modal');
    if (!modal) return;
    const form = document.getElementById('delete-message-form');
    const cancelBtn = document.getElementById('delete-message-cancel');
    const idInput = document.getElementById('delete-message-id');

    const closeModal = () => {
        modal.classList.add('hidden');
        modal.setAttribute('aria-hidden', 'true');
        if (idInput) idInput.value = '';
    };

    if (cancelBtn) cancelBtn.addEventListener('click', closeModal);
    modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !modal.classList.contains('hidden')) closeModal();
    });

    if (form) {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const messageId = Number(idInput?.value || 0);
            if (messageId <= 0) return;
            closeModal();
            try {
                const result = await postForm('/api/messages/delete', {
                    csrf_token: getCsrfToken(),
                    message_id: messageId
                });
                if (result.error) {
                    showToast(result.error, 'error');
                    return;
                }
                await pollMessages({ scrollMode: 'preserve', forceRender: true });
            } catch (err) {
                showToast('Failed to delete message', 'error');
            }
        });
    }
}

function bindGroupJoinRequestButtons() {
    const requestButton = document.getElementById('request-group-join');
    const cancelButton = document.getElementById('cancel-group-join-request');

    if (requestButton) {
        requestButton.addEventListener('click', async (event) => {
            event.preventDefault();
            await requestCurrentGroupJoin();
        });
    }

    if (cancelButton) {
        cancelButton.addEventListener('click', async (event) => {
            event.preventDefault();
            await cancelCurrentGroupJoinRequest();
        });
    }
}

function closeGroupMemberActionMenus(exceptMenuId = '') {
    const menus = document.querySelectorAll('[data-group-member-menu]');
    const toggles = document.querySelectorAll('[data-group-member-menu-toggle]');

    menus.forEach((menu) => {
        if (!(menu instanceof HTMLElement)) return;
        const shouldKeepOpen = exceptMenuId && menu.id === exceptMenuId;
        menu.classList.toggle('hidden', !shouldKeepOpen);
    });

    toggles.forEach((toggle) => {
        if (!(toggle instanceof HTMLElement)) return;
        const menuId = String(toggle.dataset.menuId || '');
        const isExpanded = Boolean(exceptMenuId && menuId === exceptMenuId);
        toggle.setAttribute('aria-expanded', isExpanded ? 'true' : 'false');
    });
}

function bindGroupMemberActionMenus() {
    if (document.body.dataset.groupMemberActionMenusBound === '1') return;
    document.body.dataset.groupMemberActionMenusBound = '1';

    document.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof Element)) return;

        const toggle = target.closest('[data-group-member-menu-toggle]');
        if (toggle instanceof HTMLElement) {
            event.preventDefault();
            event.stopPropagation();

            const menuId = String(toggle.dataset.menuId || '');
            if (!menuId) {
                closeGroupMemberActionMenus();
                return;
            }

            const menu = document.getElementById(menuId);
            if (!menu) {
                closeGroupMemberActionMenus();
                return;
            }

            const shouldOpen = menu.classList.contains('hidden');
            closeGroupMemberActionMenus(shouldOpen ? menuId : '');
            return;
        }

        const clickedAction = target.closest('[data-group-member-action]');
        if (clickedAction) {
            closeGroupMemberActionMenus();
            return;
        }

        if (target.closest('[data-group-member-menu]')) {
            return;
        }

        closeGroupMemberActionMenus();
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        closeGroupMemberActionMenus();
    });
}

function bindMessageSettingsModal() {
    const modal = document.getElementById('message-settings-modal');
    if (!modal) return;
    const form = document.getElementById('message-settings-form');
    const cancelBtn = document.getElementById('message-settings-cancel');
    const editSelect = document.getElementById('message-settings-edit-window');
    const deleteSelect = document.getElementById('message-settings-delete-window');
    const visibilitySelect = document.getElementById('group-settings-visibility');

    const closeModal = () => {
        modal.classList.add('hidden');
        modal.setAttribute('aria-hidden', 'true');
    };

    if (cancelBtn) cancelBtn.addEventListener('click', closeModal);
    modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !modal.classList.contains('hidden')) closeModal();
    });

    window.openMessageSettingsModal = () => {
        if (!currentChat) return;
        if (editSelect) editSelect.value = currentChat.group_edit_window || 'never';
        if (deleteSelect) deleteSelect.value = currentChat.group_delete_window || 'never';
        if (visibilitySelect) visibilitySelect.value = currentChat.group_visibility || 'none';
        modal.classList.remove('hidden');
        modal.setAttribute('aria-hidden', 'false');
    };

    if (form) {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            if (!currentChat) return;
            const editWindow = editSelect?.value || 'never';
            const deleteWindow = deleteSelect?.value || 'never';
            const nonMemberVisibility = visibilitySelect?.value || 'none';
            closeModal();
            try {
                const result = await postForm('/api/chats/group/message-settings', {
                    csrf_token: getCsrfToken(),
                    chat_id: currentChat.id,
                    edit_window: editWindow,
                    delete_window: deleteWindow,
                    non_member_visibility: nonMemberVisibility
                });
                if (result.error) {
                    showToast(result.error, 'error');
                    return;
                }
                currentChat.group_edit_window = editWindow;
                currentChat.group_delete_window = deleteWindow;
                currentChat.group_visibility = nonMemberVisibility;
                showToast('Group settings saved');
                await pollMessages({ scrollMode: 'preserve', forceRender: true });
            } catch (err) {
                showToast('Failed to save settings', 'error');
            }
        });
    }
}
