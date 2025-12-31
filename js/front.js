jQuery(document).ready(function ($) {
    const roomId = edelChat.roomId;
    const currentUser = edelChat.currentUser;
    const soundUri =
        'data:audio/wav;base64,UklGRn4AAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YV4AAACAgICAgICAgICAgICAgICAf3hxeHCAgHB4cICAgICAgIBweHB4cICAgICAgIBwcHhweHCAgICAgICAgHB4cHhwgICAgICAgICAcHhweHCAgICAgICAgICAgIA=';
    const audio = new Audio();
    audio.src = soundUri;
    let isSoundOn = true;

    let lastMessageId = 0;
    let firstMessageId = 0;
    let userToken = '';
    let isLoadingOld = false;
    let lastRenderedDate = '';
    let isInitialLoad = true;
    let lastUpdateCheck = '2000-01-01 00:00:00';
    let replyingToId = null;
    let blockedUsers = JSON.parse(localStorage.getItem('edel_blocked_users_' + roomId) || '[]');

    const stamps = [
        { id: 'ok', color: '#8de055', text: 'OK!', icon: '✓' },
        { id: 'ng', color: '#ff4d4d', text: 'NO', icon: '✕' },
        { id: 'thx', color: '#ff85c0', text: 'Thanks', icon: '♥' },
        { id: 'sorry', color: '#5cdbd3', text: 'Sorry', icon: '💧' },
        { id: 'hello', color: '#ffc069', text: 'Hello', icon: '☀' },
        { id: 'good', color: '#ffd666', text: 'Good!', icon: '👍' },
        { id: 'wait', color: '#d9d9d9', text: 'Wait..', icon: '⏳' },
        { id: 'help', color: '#ff7875', text: 'Help!', icon: '🆘' },
        { id: 'lol', color: '#fff566', text: 'LOL', icon: '😆' },
        { id: 'cry', color: '#69c0ff', text: 'Cry', icon: '😭' },
        { id: 'angry', color: '#ff4d4f', text: 'Angry', icon: '💢' },
        { id: 'clap', color: '#ffc53d', text: 'Clap', icon: '👏' },
        { id: 'star', color: '#fff000', text: 'Star', icon: '⭐' },
        { id: 'heart', color: '#ffadd2', text: 'Love', icon: '💕' },
        { id: 'fire', color: '#ff4d4f', text: 'Fire', icon: '🔥' },
        { id: 'check', color: '#95de64', text: 'Check', icon: '✅' },
        { id: 'done', color: '#597ef7', text: 'Done', icon: '🏁' },
        { id: 'idea', color: '#fff566', text: 'Idea', icon: '💡' },
        { id: 'hatena', color: '#85a5ff', text: '?', icon: '❓' },
        { id: 'bikkuri', color: '#ffc53d', text: '!', icon: '❗' },
        { id: 'zzz', color: '#b37feb', text: 'Zzz', icon: '💤' },
        { id: 'fight', color: '#ff85c0', text: 'Fight', icon: '💪' },
        { id: 'bow', color: '#5cdbd3', text: 'Bow', icon: '🙇' },
        { id: 'bye', color: '#ff9c6e', text: 'Bye', icon: '👋' }
    ];
    function createStampSvg(stamp) {
        return `<svg viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg"><rect x="5" y="5" width="90" height="90" rx="20" ry="20" fill="${stamp.color}" stroke="none" /><text x="50" y="50" font-family="sans-serif" font-weight="bold" font-size="24" text-anchor="middle" fill="#fff" dy="-5">${stamp.text}</text><text x="50" y="80" font-size="30" text-anchor="middle" dy="0">${stamp.icon}</text></svg>`;
    }

    const reactionEmojis = ['👍', '❤️', '😂', '😮', '😢', '🙏'];

    if (currentUser && currentUser.id) {
        userToken = 'wp_' + currentUser.id;
        $('#edel-chat-name-area').hide();
        $('#edel-chat-user-info').css('display', 'flex');
        $('#edel-chat-user-avatar').attr('src', currentUser.avatar);
        const uName = currentUser.isAdmin && edelChat.roomAdminName ? edelChat.roomAdminName : currentUser.name;
        $('#edel-chat-user-name').text(uName);
        $('#edel-chat-name').val(uName);
    } else {
        userToken = localStorage.getItem('edel_chat_token');
        if (!userToken || userToken.startsWith('wp_')) {
            userToken = 'user_' + Math.random().toString(36).substr(2, 9) + '_' + Date.now();
            localStorage.setItem('edel_chat_token', userToken);
        }
        const savedName = localStorage.getItem('edel_chat_name');
        if (savedName) $('#edel-chat-name').val(savedName);
    }

    const $stampList = $('#edel-stamp-list');
    stamps.forEach((s) => {
        $stampList.append($(`<div class="edel-stamp-item" data-id="${s.id}">${createStampSvg(s)}</div>`));
    });

    fetchMessages('new');

    const activeInterval = edelChat.pollingInterval && edelChat.pollingInterval > 0 ? edelChat.pollingInterval * 1000 : 3000;
    const backgroundInterval = 60000;
    let pollTimer = null;

    function startPolling(interval) {
        if (pollTimer) clearInterval(pollTimer);
        pollTimer = setInterval(function () {
            fetchMessages('new');
        }, interval);
    }

    startPolling(activeInterval);

    document.addEventListener('visibilitychange', function () {
        if (document.hidden) {
            startPolling(backgroundInterval);
        } else {
            startPolling(activeInterval);
            fetchMessages('new');
        }
    });

    // Events
    $('#edel-chat-stamp-btn').on('click', function (e) {
        e.stopPropagation();
        $('#edel-chat-stamp-picker').fadeToggle(100);
    });
    $(document).on('click', function (e) {
        if (!$(e.target).closest('#edel-chat-stamp-picker, #edel-chat-stamp-btn').length) $('#edel-chat-stamp-picker').fadeOut(100);
    });
    $(document).on('click', '.edel-stamp-item', function () {
        const id = $(this).data('id');
        const stampData = stamps.find((s) => s.id === id);
        if (stampData) {
            sendMessage(createStampSvg(stampData), 'stamp');
            $('#edel-chat-stamp-picker').fadeOut(100);
        }
    });
    $('#edel-chat-sound-toggle').on('click', function () {
        isSoundOn = !isSoundOn;
        $(this).text(isSoundOn ? '🔔 ON' : '🔕 OFF');
        if (isSoundOn) audio.play().catch((e) => {});
    });
    $('#edel-chat-messages').on('scroll', function () {
        if ($(this).scrollTop() === 0 && !isLoadingOld && firstMessageId > 0) {
            isLoadingOld = true;
            $('#edel-chat-loader').show();
            fetchMessages('old');
        }
    });
    $('#edel-chat-send-btn').on('click', function () {
        const text = $('#edel-chat-text').val();
        if (!text.trim()) return;
        sendMessage(text, 'text');
    });
    $('#edel-chat-text').on('keypress', function (e) {
        if (e.which === 13 && !e.shiftKey) {
            e.preventDefault();
            const text = $('#edel-chat-text').val();
            if (!text.trim()) return;
            sendMessage(text, 'text');
        }
    });

    // --- Image Handling ---

    let pendingImageData = null;

    function handleImageUpload(file) {
        if (!file || !file.type.startsWith('image/')) return;

        const $imgBtn = $('#edel-chat-image-btn');
        $imgBtn.prop('disabled', true).css('opacity', 0.5);

        $('#edel-chat-image-input').val('');

        const reader = new FileReader();
        reader.onload = function (event) {
            const img = new Image();
            img.onload = function () {
                const canvas = document.createElement('canvas');
                const ctx = canvas.getContext('2d');
                const MAX = 800;
                let w = img.width;
                let h = img.height;
                if (w > h) {
                    if (w > MAX) {
                        h *= MAX / w;
                        w = MAX;
                    }
                } else {
                    if (h > MAX) {
                        w *= MAX / h;
                        h = MAX;
                    }
                }
                canvas.width = w;
                canvas.height = h;
                ctx.drawImage(img, 0, 0, w, h);

                pendingImageData = canvas.toDataURL('image/jpeg', 0.7);

                $('#edel-confirm-preview').attr('src', pendingImageData);
                $('#edel-image-confirm-modal').fadeIn(200).css('display', 'flex');
            };
            img.src = event.target.result;
        };
        reader.readAsDataURL(file);
    }

    $('#edel-confirm-cancel').on('click', function () {
        $('#edel-image-confirm-modal').fadeOut(200);
        pendingImageData = null;
        const $imgBtn = $('#edel-chat-image-btn');
        $imgBtn.prop('disabled', false).css('opacity', 1);
    });

    $('#edel-confirm-send').on('click', function () {
        if (pendingImageData) {
            const $imgBtn = $('#edel-chat-image-btn');
            sendMessage(pendingImageData, 'image', $imgBtn);
            $('#edel-image-confirm-modal').fadeOut(200);
            pendingImageData = null;
        }
    });

    $('#edel-chat-image-btn').on('click', function () {
        $('#edel-chat-image-input').click();
    });
    $('#edel-chat-image-input').on('change', function (e) {
        const file = e.target.files[0];
        handleImageUpload(file);
    });

    const $dropArea = $('#edel-chat-app');

    $dropArea.on('dragover', function (e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).css('border', '2px dashed #0084ff');
    });

    $dropArea.on('dragleave', function (e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).css('border', '1px solid #ccc');
    });

    $dropArea.on('drop', function (e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).css('border', '1px solid #ccc');

        const files = e.originalEvent.dataTransfer.files;
        if (files.length > 0) {
            handleImageUpload(files[0]);
        }
    });

    $('#edel-chat-text').on('paste', function (e) {
        const items = (e.originalEvent.clipboardData || e.clipboardData).items;
        for (let i = 0; i < items.length; i++) {
            if (items[i].type.indexOf('image') !== -1) {
                const file = items[i].getAsFile();
                handleImageUpload(file);
                e.preventDefault();
                break;
            }
        }
    });

    // --- Image Handling End ---

    $(document).on('click', '.edel-chat-reply-btn', function () {
        const msgId = $(this).data('id');
        const name = $(this).data('name');
        const text = $(this).data('text');
        replyingToId = msgId;
        $('#edel-reply-to-name').text(name);
        $('#edel-reply-text-preview').text(text.length > 30 ? text.substring(0, 30) + '...' : text);
        $('#edel-chat-reply-preview').slideDown(100);
        $('#edel-chat-text').focus();
    });
    $('#edel-reply-close').on('click', function () {
        replyingToId = null;
        $('#edel-chat-reply-preview').slideUp(100);
    });

    $(document).on('click', '.edel-chat-react-btn', function (e) {
        e.stopPropagation();
        const msgId = $(this).data('id');
        $('.edel-reaction-picker').remove();
        const $picker = $('<div class="edel-reaction-picker"></div>');
        reactionEmojis.forEach((emoji) => {
            $picker.append(`<span class="edel-react-emoji" data-id="${msgId}" data-emoji="${emoji}">${emoji}</span>`);
        });
        $(this).parent().append($picker);
    });
    $(document).on('click', '.edel-react-emoji', function (e) {
        e.stopPropagation();
        const msgId = $(this).data('id');
        const emoji = $(this).data('emoji');
        sendReaction(msgId, emoji);
        $('.edel-reaction-picker').remove();
    });
    $(document).on('click', function (e) {
        if (!$(e.target).closest('.edel-reaction-picker, .edel-chat-react-btn').length) {
            $('.edel-reaction-picker').remove();
        }
    });

    $(document).on('click', '.edel-chat-block-btn', function () {
        const token = $(this).data('token');
        const name = $(this).data('name');
        if (confirm(`Are you sure you want to block ${name}?`)) {
            // Translated
            blockedUsers.push(token);
            localStorage.setItem('edel_blocked_users_' + roomId, JSON.stringify(blockedUsers));
            $(`.edel-chat-row[data-token="${token}"]`).fadeOut();
        }
    });

    function sendReaction(msgId, emoji) {
        $.ajax({
            url: edelChat.ajaxurl,
            type: 'POST',
            data: { action: 'edel_chat_reaction', nonce: edelChat.nonce, msg_id: msgId, reaction: emoji, user_token: userToken },
            success: function (res) {
                if (res.success) {
                    updateReactions(msgId, res.data.counts, res.data.my_reactions);
                }
            }
        });
    }

    function updateReactions(msgId, counts, myReactions) {
        const $container = $(`#edel-msg-${msgId} .edel-chat-reactions`);
        $container.empty();
        if (counts && counts.length > 0) {
            counts.forEach((c) => {
                const isMine = myReactions.includes(c.reaction) ? 'my-react' : '';
                $container.append(`<span class="edel-reaction-pill ${isMine}">${c.reaction} <span class="count">${c.cnt}</span></span>`);
            });
        }
    }

    function sendMessage(content, type, $loadingBtn = null) {
        let name = $('#edel-chat-name').val();
        if (!name.trim()) name = 'Anonymous'; // Translated
        if (!currentUser) localStorage.setItem('edel_chat_name', name);
        const $sendBtn = $('#edel-chat-send-btn');
        if (type === 'text') $sendBtn.prop('disabled', true).text('...');
        $.ajax({
            url: edelChat.ajaxurl,
            type: 'POST',
            data: {
                action: 'edel_chat_send',
                nonce: edelChat.nonce,
                room_id: roomId,
                message: content,
                msg_type: type,
                nickname: name,
                user_token: userToken,
                reply_to: replyingToId
            },
            success: function (res) {
                if (res.success) {
                    if (type === 'text') $('#edel-chat-text').val('').css('height', '36px');
                    if (replyingToId) {
                        replyingToId = null;
                        $('#edel-chat-reply-preview').slideUp(100);
                    }
                    fetchMessages('new', true);
                } else {
                    alert(res.data);
                }
            },
            complete: function () {
                if (type === 'text') $sendBtn.prop('disabled', false).text(edelChat.sendLabel);
                if ($loadingBtn) $loadingBtn.prop('disabled', false).css('opacity', 1);
            }
        });
    }

    function fetchMessages(mode, isSelfPost = false) {
        const data = {
            action: 'edel_chat_fetch',
            nonce: edelChat.nonce,
            room_id: roomId,
            mode: mode,
            last_update_check: lastUpdateCheck,
            user_token: userToken
        };
        if (mode === 'new') data.last_id = lastMessageId;
        else data.first_id = firstMessageId;
        $.ajax({
            url: edelChat.ajaxurl,
            type: 'POST',
            data: data,
            success: function (res) {
                if (!res.success) return;
                const msgs = res.data.messages;
                const deletedIds = res.data.deleted_ids;
                if (res.data.server_time) lastUpdateCheck = res.data.server_time;
                if (mode === 'new' && deletedIds && deletedIds.length > 0) {
                    deletedIds.forEach((delId) => {
                        $('#edel-msg-' + delId).fadeOut(500, function () {
                            $(this).remove();
                        });
                    });
                }
                let shouldScroll = false;
                let playSound = false;
                if (msgs.length > 0) {
                    if (mode === 'new') {
                        msgs.forEach((msg) => {
                            if (blockedUsers.includes(msg.user_token)) return;
                            if (lastRenderedDate !== msg.date_ymd) {
                                appendDateDivider(msg.date_disp, 'append');
                                lastRenderedDate = msg.date_ymd;
                            }
                            if ($('#edel-msg-' + msg.id).length > 0) {
                                if (msg.is_edited) updateMessageContent(msg);
                                updateReactions(msg.id, msg.reactions, msg.my_reactions);
                            } else {
                                appendMessage(msg, 'append');
                                if (msg.id > lastMessageId) {
                                    lastMessageId = msg.id;
                                    if (!isInitialLoad && msg.user_token !== userToken && !isSelfPost) playSound = true;
                                }
                            }
                            if (firstMessageId === 0 || msg.id < firstMessageId) firstMessageId = msg.id;
                        });
                        if (isInitialLoad) isInitialLoad = false;
                        shouldScroll = true;
                    } else {
                        const $area = $('#edel-chat-messages');
                        const oldHeight = $area[0].scrollHeight;
                        msgs.forEach((msg, index) => {
                            if (blockedUsers.includes(msg.user_token)) return;
                            appendMessage(msg, 'prepend');
                            let showDate = false;
                            if (index === 0) showDate = true;
                            else if (msg.date_ymd !== msgs[index - 1].date_ymd) showDate = true;
                            if (showDate) appendDateDivider(msg.date_disp, 'prepend');
                            if (msg.id < firstMessageId) firstMessageId = msg.id;
                        });
                        const newHeight = $area[0].scrollHeight;
                        $area.scrollTop(newHeight - oldHeight);
                        isLoadingOld = false;
                        $('#edel-chat-loader').hide();
                    }
                    if (firstMessageId === 0 && msgs.length > 0) firstMessageId = msgs[0].id;
                } else {
                    if (isInitialLoad && mode === 'new') isInitialLoad = false;
                    if (mode === 'old') {
                        isLoadingOld = false;
                        $('#edel-chat-loader').hide();
                    }
                }
                if (mode === 'new' && playSound && isSoundOn) audio.play().catch((e) => {});
                if (shouldScroll) scrollToBottom();
            }
        });
    }

    function appendDateDivider(dateText, direction) {
        const html = `<div class="edel-chat-date-divider"><span>${dateText}</span></div>`;
        if (direction === 'append') $('#edel-chat-messages').append(html);
        else $('#edel-chat-loader').after(html);
    }

    function appendMessage(msg, direction) {
        if ($('#edel-msg-' + msg.id).length > 0) return;
        const isMe = msg.user_token === userToken;
        const typeClass = isMe ? 'is-me' : 'is-other';

        let avatarHtml = '';
        if (!isMe) {
            if (msg.avatar) avatarHtml = `<img src="${msg.avatar}" class="edel-chat-avatar-img">`;
            else avatarHtml = `<div class="edel-chat-avatar-default">${msg.nickname.charAt(0)}</div>`;
        }

        let badgeHtml = '';
        if (msg.is_admin && edelChat.adminLabel && edelChat.adminLabel.trim() !== '') {
            badgeHtml = `<span class="edel-admin-badge">${edelChat.adminLabel}</span>`;
        }

        let bubbleClass = '';
        let contentHtml = createContentHtml(msg);
        let rawText = msg.msg_type === 'text' ? msg.raw_message : '';

        let replyHtml = '';
        if (msg.reply) {
            replyHtml = `<div class="edel-chat-reply-quote"><div class="edel-quote-name">↩ ${msg.reply.name}</div><div class="edel-quote-text">${msg.reply.text}</div></div>`;
        }

        let actionBtns = '';

        actionBtns += `<div class="edel-action-btn edel-chat-react-btn" data-id="${msg.id}" title="Reaction">☺</div>`; // Translated

        if (isMe) {
            if (msg.msg_type === 'text')
                actionBtns += `<div class="edel-action-btn edel-chat-edit-btn" data-id="${msg.id}" data-raw="${msg.raw_message}" title="Edit">✎</div>`; // Translated
            actionBtns += `<div class="edel-action-btn edel-chat-delete" data-id="${msg.id}" title="Delete">×</div>`; // Translated
        } else {
            actionBtns += `<div class="edel-action-btn edel-chat-reply-btn" data-id="${msg.id}" data-name="${msg.nickname}" data-text="${rawText}" title="Reply">↩</div>`; // Translated
            actionBtns += `<div class="edel-action-btn edel-chat-block-btn" data-token="${msg.user_token}" data-name="${msg.nickname}" title="Block">🚫</div>`; // Translated
        }

        let reactionPills = '';
        if (msg.reactions && msg.reactions.length > 0) {
            msg.reactions.forEach((c) => {
                const isMine = msg.my_reactions && msg.my_reactions.includes(c.reaction) ? 'my-react' : '';
                reactionPills += `<span class="edel-reaction-pill ${isMine}">${c.reaction} <span class="count">${c.cnt}</span></span>`;
            });
        }

        let html = `<div class="edel-chat-row ${typeClass}" id="edel-msg-${msg.id}" data-token="${msg.user_token}">`;

        html += `<div class="edel-chat-actions-bar">${actionBtns}</div>`;

        if (!isMe) html += `<div class="edel-chat-avatar-col">${avatarHtml}</div>`;
        html += `<div class="edel-chat-content-col">`;
        if (!isMe) html += `<div class="edel-chat-nickname">${msg.nickname} ${badgeHtml}</div>`;
        if (msg.msg_type === 'stamp') bubbleClass = 'is-stamp';

        html += `<div class="edel-chat-bubble ${bubbleClass}" id="edel-bubble-${msg.id}">`;
        html += replyHtml;
        html += contentHtml;
        html += `</div>`;

        html += `<div class="edel-chat-reactions">${reactionPills}</div>`;

        html += `</div>`;
        html += `</div>`;

        if (direction === 'append') $('#edel-chat-messages').append(html);
        else $('#edel-chat-loader').after(html);
    }

    function createContentHtml(msg) {
        let html = '';
        if (msg.msg_type === 'image') {
            html = `<img src="${msg.message}" class="edel-chat-image" alt="Image">`; // Translated
        } else if (msg.msg_type === 'stamp') {
            html = `<div class="edel-chat-stamp">${msg.message}</div>`;
        } else {
            html = `<div class="edel-chat-text">${msg.message}</div>`;
            if (msg.ogp) {
                html += `<a href="${msg.ogp.url}" target="_blank" class="edel-ogp-card">`;
                if (msg.ogp.image) html += `<div class="edel-ogp-img" style="background-image:url('${msg.ogp.image}')"></div>`;
                html += `<div class="edel-ogp-content"><div class="edel-ogp-title">${msg.ogp.title}</div>`;
                if (msg.ogp.desc) html += `<div class="edel-ogp-desc">${msg.ogp.desc}</div>`;
                html += `</div></a>`;
            }
        }
        html += `<div class="edel-chat-meta">`;
        html += `<span>${msg.time}</span>`;
        if (msg.is_edited) html += `<span class="edel-edited-mark"> (Edited)</span>`; // Translated
        html += `</div>`;
        return html;
    }
    function updateMessageContent(msg) {
        const $bubble = $('#edel-bubble-' + msg.id);
        let replyHtml = '';
        if (msg.reply) {
            replyHtml = `<div class="edel-chat-reply-quote"><div class="edel-quote-name">↩ ${msg.reply.name}</div><div class="edel-quote-text">${msg.reply.text}</div></div>`;
        }
        $bubble.html(replyHtml + createContentHtml(msg));
        $bubble.closest('.edel-chat-row').find('.edel-chat-edit-btn').data('raw', msg.raw_message);
    }
    function scrollToBottom() {
        const area = $('#edel-chat-messages');
        area.scrollTop(area[0].scrollHeight);
    }
    $(document).on('click', '.edel-chat-delete', function () {
        if (!confirm(edelChat.deleteConfirm)) return;
        const msgId = $(this).data('id');
        const $row = $('#edel-msg-' + msgId);
        $.ajax({
            url: edelChat.ajaxurl,
            type: 'POST',
            data: { action: 'edel_chat_delete', nonce: edelChat.nonce, msg_id: msgId, user_token: userToken },
            success: function (res) {
                if (res.success)
                    $row.fadeOut(300, function () {
                        $(this).remove();
                    });
            }
        });
    });
    $(document).on('click', '.edel-chat-image', function () {
        $('#edel-lightbox-img').attr('src', $(this).attr('src'));
        $('#edel-lightbox').fadeIn(200).css('display', 'flex');
    });
    $('#edel-lightbox, #edel-lightbox-close').on('click', function (e) {
        if (e.target.id === 'edel-lightbox' || e.target.id === 'edel-lightbox-close') $('#edel-lightbox').fadeOut(200);
    });
    $(document).on('click', '.edel-chat-edit-btn', function () {
        const id = $(this).data('id');
        const rawText = $(this).data('raw');
        const $bubble = $('#edel-bubble-' + id);
        if ($bubble.find('.edel-edit-form').length > 0) return;
        const currentHtml = $bubble.html();
        const formHtml = `<div class="edel-edit-form"><textarea class="edel-edit-input">${rawText}</textarea><div class="edel-edit-buttons"><button class="edel-edit-cancel">Cancel</button><button class="edel-edit-save" data-id="${id}">Save</button></div></div>`; // Translated
        $bubble.data('original', currentHtml);
        $bubble.html(formHtml);
    });
    $(document).on('click', '.edel-edit-cancel', function () {
        const $bubble = $(this).closest('.edel-chat-bubble');
        $bubble.html($bubble.data('original'));
    });
    $(document).on('click', '.edel-edit-save', function () {
        const id = $(this).data('id');
        const $bubble = $(this).closest('.edel-chat-bubble');
        const newText = $bubble.find('.edel-edit-input').val();
        if (!newText.trim()) return;
        $.ajax({
            url: edelChat.ajaxurl,
            type: 'POST',
            data: { action: 'edel_chat_edit', nonce: edelChat.nonce, msg_id: id, message: newText, user_token: userToken },
            success: function (res) {
                if (res.success) {
                    updateMessageContent(res.data);
                } else {
                    alert('Error: ' + res.data); // Translated
                }
            }
        });
    });
});
