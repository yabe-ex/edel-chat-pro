<?php

class EdelChatProFront {

    // 最終ログイン日時を記録
    function record_user_login($user_login, $user) {
        update_user_meta($user->ID, 'edel_last_login', current_time('mysql'));
    }

    function render_chat_shortcode($atts) {
        $version = (defined('EDEL_CHAT_PRO_DEVELOP') && EDEL_CHAT_PRO_DEVELOP) ? time() : EDEL_CHAT_PRO_VERSION;
        $a = shortcode_atts(array('id' => (string)get_the_ID()), $atts);
        $room_id = sanitize_text_field($a['id']);
        wp_enqueue_style(EDEL_CHAT_PRO_SLUG . '-front', EDEL_CHAT_PRO_URL . '/css/front.css', array(), $version);
        $options = get_option('edel_chat_options');
        $bg_color = !empty($options['bg_color']) ? $options['bg_color'] : '#7296cc';
        $me_color = !empty($options['me_color']) ? $options['me_color'] : '#8de055';
        $other_color = !empty($options['other_color']) ? $options['other_color'] : '#ffffff';
        $height = !empty($options['window_height']) ? $options['window_height'] : '500px';

        // 設定からマイページURLを取得
        $profile_url = isset($options['profile_page_url']) ? $options['profile_page_url'] : '';

        $admin_label = isset($options['admin_label']) ? $options['admin_label'] : 'ADMIN';

        $polling_interval = !empty($options['polling_interval']) ? intval($options['polling_interval']) : 3;
        if ($polling_interval < 1) $polling_interval = 3;

        $custom_css = ":root { --ec-bg-color: {$bg_color}; --ec-me-color: {$me_color}; --ec-other-color: {$other_color}; --ec-height: {$height}; }";
        wp_add_inline_style(EDEL_CHAT_PRO_SLUG . '-front', $custom_css);
        wp_enqueue_script(EDEL_CHAT_PRO_SLUG . '-front', EDEL_CHAT_PRO_URL . '/js/front.js', array('jquery'), $version, true);
        $currentUser = array();
        if (is_user_logged_in()) {
            $u = wp_get_current_user();
            $currentUser['id'] = $u->ID;
            $currentUser['name'] = $u->display_name;
            $currentUser['avatar'] = get_avatar_url($u->ID);
            $currentUser['isAdmin'] = current_user_can('manage_options');
        }
        $roomAdminName = get_post_meta(get_the_ID(), '_edel_chat_admin_name', true);
        wp_localize_script(EDEL_CHAT_PRO_SLUG . '-front', 'edelChat', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce(EDEL_CHAT_PRO_SLUG),
            'roomId'  => $room_id,
            'currentUser' => $currentUser,
            'roomAdminName' => $roomAdminName,
            'adminLabel' => $admin_label,
            'soundUrl' => '',
            'pollingInterval' => $polling_interval,
            'profileUrl' => $profile_url,
            'sendLabel' => __('Send', 'edel-chat-pro'),
            'deleteConfirm' => __('Are you sure you want to delete?', 'edel-chat-pro')
        ));
        ob_start();
?>
        <div id="edel-chat-app" data-room-id="<?php echo esc_attr($room_id); ?>">
            <div class="edel-chat-header-tools">
                <button id="edel-chat-sound-toggle" title="<?php esc_attr_e('Toggle Sound', 'edel-chat-pro'); ?>">🔔 ON</button>
            </div>
            <div class="edel-chat-area" id="edel-chat-messages">
                <div id="edel-chat-loader">
                    <div class="edel-spinner"></div>
                </div>
            </div>
            <div id="edel-chat-stamp-picker">
                <div class="edel-stamp-grid" id="edel-stamp-list"></div>
            </div>

            <div id="edel-chat-reply-preview" style="display:none;">
                <div class="edel-reply-info">
                    <span id="edel-reply-to-name"></span> <?php esc_html_e('Reply to', 'edel-chat-pro'); ?>
                    <span id="edel-reply-close">×</span>
                </div>
                <div id="edel-reply-text-preview"></div>
            </div>

            <div class="edel-chat-controls">
                <div class="edel-chat-name-row" id="edel-chat-name-area">
                    <label><?php esc_html_e('Name:', 'edel-chat-pro'); ?></label><input type="text" id="edel-chat-name" placeholder="<?php esc_attr_e('Anonymous', 'edel-chat-pro'); ?>" maxlength="10">
                </div>
                <div id="edel-chat-user-info" style="display:none; margin-bottom:5px; font-size:12px; color:#666; align-items:center;">
                    <img id="edel-chat-user-avatar" src="" style="width:20px; height:20px; border-radius:50%; margin-right:5px;">
                    <span id="edel-chat-user-name"></span>
                </div>
                <div class="edel-chat-input-row">
                    <input type="file" id="edel-chat-image-input" accept="image/jpeg,image/png" style="display:none;">
                    <button id="edel-chat-image-btn" type="button" title="<?php esc_attr_e('Send Image', 'edel-chat-pro'); ?>"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#555" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                            <circle cx="8.5" cy="8.5" r="1.5"></circle>
                            <polyline points="21 15 16 10 5 21"></polyline>
                        </svg></button>
                    <button id="edel-chat-stamp-btn" type="button" title="<?php esc_attr_e('Stamps', 'edel-chat-pro'); ?>"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#555" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"></circle>
                            <path d="M8 14s1.5 2 4 2 4-2 4-2"></path>
                            <line x1="9" y1="9" x2="9.01" y2="9"></line>
                            <line x1="15" y1="9" x2="15.01" y2="9"></line>
                        </svg></button>
                    <textarea id="edel-chat-text" placeholder="<?php esc_attr_e('Enter message', 'edel-chat-pro'); ?>" rows="1"></textarea>
                    <button id="edel-chat-send-btn"><?php esc_html_e('Send', 'edel-chat-pro'); ?></button>
                </div>
            </div>
        </div>

        <div id="edel-lightbox"><span id="edel-lightbox-close">&times;</span><img id="edel-lightbox-img" src=""></div>

        <div id="edel-image-confirm-modal" style="display:none;">
            <div class="edel-confirm-content">
                <p><?php esc_html_e('Do you want to send this image?', 'edel-chat-pro'); ?></p>
                <img id="edel-confirm-preview" src="">
                <div class="edel-confirm-actions">
                    <button id="edel-confirm-cancel"><?php esc_html_e('Cancel', 'edel-chat-pro'); ?></button>
                    <button id="edel-confirm-send"><?php esc_html_e('Send', 'edel-chat-pro'); ?></button>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    function ajax_send_message() {
        check_ajax_referer(EDEL_CHAT_PRO_SLUG, 'nonce');
        global $wpdb;
        $table_name = EDEL_CHAT_TABLE;
        $userToken = isset($_POST['user_token']) ? sanitize_text_field(wp_unslash($_POST['user_token'])) : '';
        if (is_user_logged_in()) $userToken = 'wp_' . get_current_user_id();

        $ip_address = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
        $options = get_option('edel_chat_options');

        if (!empty($options['banned_ips'])) {
            $banned_list = array_map('trim', explode("\n", $options['banned_ips']));
            if (in_array($ip_address, $banned_list)) {
                wp_send_json_error(__('Access denied.', 'edel-chat-pro'));
                return;
            }
        }

        $limit = isset($options['spam_limit']) && $options['spam_limit'] !== '' ? intval($options['spam_limit']) : 2;
        $last_send_key = 'edel_chat_last_send_' . md5($userToken);
        $last_send_time = get_transient($last_send_key);
        if ($limit > 0 && $last_send_time && (time() - $last_send_time) < $limit) {
            /* translators: %d: seconds */
            wp_send_json_error(sprintf(__('Please wait %d seconds before sending another message.', 'edel-chat-pro'), $limit));
            return;
        }
        set_transient($last_send_key, time(), 60);

        $nickname = isset($_POST['nickname']) ? sanitize_text_field(wp_unslash($_POST['nickname'])) : '';
        $roomId = isset($_POST['room_id']) ? sanitize_text_field(wp_unslash($_POST['room_id'])) : '';

        // Unslash before sanitization is crucial.
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized later based on type (image vs text).
        $rawMessage = isset($_POST['message']) ? wp_unslash($_POST['message']) : '';

        $msgType = isset($_POST['msg_type']) ? sanitize_text_field(wp_unslash($_POST['msg_type'])) : 'text';
        $replyTo = isset($_POST['reply_to']) ? intval($_POST['reply_to']) : null;
        if ($replyTo === 0) $replyTo = null;

        if (is_user_logged_in() && current_user_can('manage_options')) {
            if (is_numeric($roomId)) {
                $roomAdminName = get_post_meta($roomId, '_edel_chat_admin_name', true);
                if (!empty($roomAdminName)) $nickname = $roomAdminName;
            }
        }
        if (empty($nickname)) $nickname = __('Anonymous', 'edel-chat-pro');
        if (empty($rawMessage)) wp_send_json_error(__('Content is empty.', 'edel-chat-pro'));

        $saveMessage = '';
        $saveType = 'text';
        $metaData = null;

        if ($msgType === 'image') {
            if (preg_match('/^data:image\/(\w+);base64,/', $rawMessage, $type)) {
                $data = substr($rawMessage, strpos($rawMessage, ',') + 1);
                $type = strtolower($type[1]);
                if (!in_array($type, ['jpg', 'jpeg', 'gif', 'png'])) {
                    wp_send_json_error(__('Invalid image format.', 'edel-chat-pro'));
                    return;
                }
                $data = base64_decode($data);
                $upload_dir = wp_upload_dir();
                $save_dir = $upload_dir['basedir'] . '/edel-chat-pro';

                global $wp_filesystem;
                if (empty($wp_filesystem)) {
                    require_once ABSPATH . 'wp-admin/includes/file.php';
                    WP_Filesystem();
                }

                if (!$wp_filesystem->is_dir($save_dir)) $wp_filesystem->mkdir($save_dir);
                $fileName = time() . '_' . wp_generate_password(8, false) . '.' . $type;
                $wp_filesystem->put_contents($save_dir . '/' . $fileName, $data);

                $saveMessage = $upload_dir['baseurl'] . '/edel-chat-pro/' . $fileName;
                $saveType = 'image';
            } else {
                wp_send_json_error(__('Image error.', 'edel-chat-pro'));
                return;
            }
        } else if ($msgType === 'stamp') {
            $saveMessage = sanitize_textarea_field($rawMessage);
            $saveType = 'stamp';
        } else {
            // Text message
            if (!empty($options['ng_words'])) {
                $ng_words = array_map('trim', explode(',', $options['ng_words']));
                foreach ($ng_words as $word) {
                    if (!empty($word) && mb_strpos($rawMessage, $word) !== false) {
                        wp_send_json_error(__('Message contains prohibited words.', 'edel-chat-pro'));
                        return;
                    }
                }
            }
            $saveMessage = sanitize_textarea_field($rawMessage);
            if (preg_match('/https?:\/\/[^\s]+/', $saveMessage, $matches)) {
                $url = $matches[0];
                $ogp = $this->fetch_ogp($url);
                if ($ogp) {
                    $metaData = json_encode(array('ogp' => $ogp), JSON_UNESCAPED_UNICODE);
                }
            }
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $result = $wpdb->insert($table_name, array(
            'room_id' => $roomId,
            'user_token' => $userToken,
            'nickname' => $nickname,
            'message' => $saveMessage,
            'message_type' => $saveType,
            'meta_data' => $metaData,
            'reply_to' => $replyTo,
            'ip_address' => $ip_address,
            'created_at' => current_time('mysql')
        ), array('%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s'));

        if ($result) wp_send_json_success();
        else wp_send_json_error(__('Save failed.', 'edel-chat-pro'));
    }

    function ajax_send_reaction() {
        check_ajax_referer(EDEL_CHAT_PRO_SLUG, 'nonce');
        global $wpdb;
        $table_name = EDEL_CHAT_REACTIONS_TABLE;

        $msgId = isset($_POST['msg_id']) ? intval($_POST['msg_id']) : 0;
        $reaction = isset($_POST['reaction']) ? sanitize_text_field(wp_unslash($_POST['reaction'])) : '';
        $userToken = isset($_POST['user_token']) ? sanitize_text_field(wp_unslash($_POST['user_token'])) : '';
        if (is_user_logged_in()) $userToken = 'wp_' . get_current_user_id();

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Table name is a constant.
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM " . $table_name . " WHERE message_id = %d AND user_token = %s AND reaction = %s",
            $msgId,
            $userToken,
            $reaction
        ));

        if ($exists) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->delete($table_name, array('id' => $exists), array('%d'));
            $action = 'removed';
        } else {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->insert($table_name, array(
                'message_id' => $msgId,
                'user_token' => $userToken,
                'reaction' => $reaction
            ), array('%d', '%s', '%s'));
            $action = 'added';
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Table name is a constant.
        $counts = $wpdb->get_results($wpdb->prepare(
            "SELECT reaction, COUNT(*) as cnt FROM " . $table_name . " WHERE message_id = %d GROUP BY reaction",
            $msgId
        ));

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Table name is a constant.
        $myReactions = $wpdb->get_col($wpdb->prepare(
            "SELECT reaction FROM " . $table_name . " WHERE message_id = %d AND user_token = %s",
            $msgId,
            $userToken
        ));

        wp_send_json_success(array('action' => $action, 'counts' => $counts, 'my_reactions' => $myReactions));
    }

    function ajax_fetch_messages() {
        check_ajax_referer(EDEL_CHAT_PRO_SLUG, 'nonce');
        global $wpdb;
        $table_name = EDEL_CHAT_TABLE;
        $reaction_table = EDEL_CHAT_REACTIONS_TABLE;

        $roomId = isset($_POST['room_id']) ? sanitize_text_field(wp_unslash($_POST['room_id'])) : '';
        $mode = isset($_POST['mode']) ? sanitize_text_field(wp_unslash($_POST['mode'])) : 'new';
        $userToken = isset($_POST['user_token']) ? sanitize_text_field(wp_unslash($_POST['user_token'])) : '';
        if (is_user_logged_in()) $userToken = 'wp_' . get_current_user_id();

        $messages = array();
        $deletedIds = array();

        if ($mode === 'old') {
            $firstId = isset($_POST['first_id']) ? intval($_POST['first_id']) : 0;
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Table name is a constant.
            $sql = $wpdb->prepare("SELECT * FROM " . $table_name . " WHERE room_id = %s AND id < %d AND is_deleted = 0 ORDER BY id DESC LIMIT 20", $roomId, $firstId);
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $results = $wpdb->get_results($sql);
            $results = array_reverse($results);
        } else {
            $lastId = isset($_POST['last_id']) ? intval($_POST['last_id']) : 0;
            $lastUpdateCheck = isset($_POST['last_update_check']) ? sanitize_text_field(wp_unslash($_POST['last_update_check'])) : gmdate('Y-m-d H:i:s', strtotime('-10 seconds'));

            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Table name is a constant.
            $sql = $wpdb->prepare("SELECT * FROM " . $table_name . " WHERE room_id = %s AND is_deleted = 0 AND (id > %d OR updated_at > %s) ORDER BY created_at ASC LIMIT 50", $roomId, $lastId, $lastUpdateCheck);
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $results = $wpdb->get_results($sql);

            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Table name is a constant.
            $delSql = $wpdb->prepare("SELECT id FROM " . $table_name . " WHERE room_id = %s AND is_deleted = 1", $roomId);
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $delResults = $wpdb->get_results($delSql);
            foreach ($delResults as $d) {
                $deletedIds[] = (int)$d->id;
            }
        }

        foreach ($results as $row) {
            $msgContent = ($row->message_type === 'image') ? esc_url($row->message) : nl2br(esc_html($row->message));
            if ($row->message_type === 'stamp') $msgContent = wp_kses_post($row->message); // Allow SVG

            $avatar = '';
            $userSlug = '';
            $isAdmin = false;
            if (strpos($row->user_token, 'wp_') === 0) {
                $uid = intval(substr($row->user_token, 3));
                $avatar = get_avatar_url($uid);

                // ユーザー情報と公開設定を取得
                $user_info = get_userdata($uid);
                $is_public = get_user_meta($uid, 'edel_is_profile_public', true);

                // 公開設定がONの場合のみスラッグを渡す
                if ($user_info && !empty($is_public)) {
                    $userSlug = $user_info->user_nicename;
                }

                if (user_can($uid, 'manage_options')) {
                    $isAdmin = true;
                }
            }

            $ogp = null;
            if (!empty($row->meta_data)) {
                $meta = json_decode($row->meta_data, true);
                if (isset($meta['ogp'])) $ogp = $meta['ogp'];
            }

            $replyData = null;
            if (!empty($row->reply_to)) {
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Table name is a constant.
                $replyRow = $wpdb->get_row($wpdb->prepare("SELECT nickname, message, message_type FROM " . $table_name . " WHERE id = %d", $row->reply_to));
                if ($replyRow) {
                    $rMsg = ($replyRow->message_type === 'image') ? '[' . __('Image', 'edel-chat-pro') . ']' : (($replyRow->message_type === 'stamp') ? '[' . __('Stamp', 'edel-chat-pro') . ']' : mb_strimwidth(wp_strip_all_tags($replyRow->message), 0, 30, '...'));
                    $replyData = array(
                        'id' => $row->reply_to,
                        'name' => $replyRow->nickname,
                        'text' => $rMsg
                    );
                }
            }

            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Table name is a constant.
            $reactCounts = $wpdb->get_results($wpdb->prepare(
                "SELECT reaction, COUNT(*) as cnt FROM " . $reaction_table . " WHERE message_id = %d GROUP BY reaction",
                $row->id
            ));
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Table name is a constant.
            $myReacts = $wpdb->get_col($wpdb->prepare(
                "SELECT reaction FROM " . $table_name . " WHERE message_id = %d AND user_token = %s",
                $row->id,
                $userToken
            ));

            $timestamp = strtotime($row->created_at);
            $messages[] = array(
                'id' => (int)$row->id,
                'message' => $msgContent,
                'raw_message' => $row->message,
                'msg_type' => $row->message_type,
                'nickname' => esc_html($row->nickname),
                'user_token' => esc_html($row->user_token),
                'user_slug' => $userSlug,
                'time' => gmdate('H:i', $timestamp + (get_option('gmt_offset') * 3600)), // Adjust to site timezone for display
                'date_ymd' => gmdate('Y-m-d', $timestamp + (get_option('gmt_offset') * 3600)),
                'date_disp' => gmdate('M j, Y', $timestamp + (get_option('gmt_offset') * 3600)),
                'avatar' => $avatar,
                'is_admin' => $isAdmin,
                'ogp' => $ogp,
                'is_edited' => !empty($row->updated_at),
                'reply' => $replyData,
                'reactions' => $reactCounts,
                'my_reactions' => $myReacts
            );
        }

        wp_send_json_success(array('messages' => $messages, 'deleted_ids' => $deletedIds, 'server_time' => current_time('mysql')));
    }

    function fetch_ogp($url) {
        $response = wp_remote_get($url, array('timeout' => 3));
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) return null;
        $body = wp_remote_retrieve_body($response);
        $ogp = array('url' => $url);
        if (preg_match('/<meta property="og:title" content="([^"]+)"/i', $body, $m)) $ogp['title'] = $m[1];
        elseif (preg_match('/<title>(.*?)<\/title>/i', $body, $m)) $ogp['title'] = $m[1];
        if (preg_match('/<meta property="og:image" content="([^"]+)"/i', $body, $m)) $ogp['image'] = $m[1];
        if (preg_match('/<meta property="og:description" content="([^"]+)"/i', $body, $m)) $ogp['desc'] = mb_strimwidth($m[1], 0, 100, '...');
        elseif (preg_match('/<meta name="description" content="([^"]+)"/i', $body, $m)) $ogp['desc'] = mb_strimwidth($m[1], 0, 100, '...');
        if (empty($ogp['title'])) return null;
        return $ogp;
    }

    function ajax_edit_message() {
        check_ajax_referer(EDEL_CHAT_PRO_SLUG, 'nonce');
        global $wpdb;
        $table_name = EDEL_CHAT_TABLE;
        $msgId = isset($_POST['msg_id']) ? intval($_POST['msg_id']) : 0;
        $newMessage = isset($_POST['message']) ? sanitize_textarea_field(wp_unslash($_POST['message'])) : '';
        $userToken = isset($_POST['user_token']) ? sanitize_text_field(wp_unslash($_POST['user_token'])) : '';
        if (is_user_logged_in()) $userToken = 'wp_' . get_current_user_id();
        if (empty($newMessage)) wp_send_json_error(__('Message is empty.', 'edel-chat-pro'));

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Table name is a constant.
        $exists = $wpdb->get_row($wpdb->prepare("SELECT id FROM " . $table_name . " WHERE id = %d AND user_token = %s AND is_deleted = 0", $msgId, $userToken));
        if (!$exists) wp_send_json_error(__('Permission denied.', 'edel-chat-pro'));

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $result = $wpdb->update($table_name, array('message' => $newMessage, 'updated_at' => current_time('mysql')), array('id' => $msgId), array('%s', '%s'), array('%d'));
        if ($result !== false) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Table name is a constant.
            $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM " . $table_name . " WHERE id = %d", $msgId));
            $msgContent = nl2br(esc_html($row->message));
            $ogp = null;
            if (!empty($row->meta_data)) {
                $meta = json_decode($row->meta_data, true);
                if (isset($meta['ogp'])) $ogp = $meta['ogp'];
            }
            $timestamp = strtotime($row->created_at);
            $responseData = array('id' => (int)$row->id, 'message' => $msgContent, 'raw_message' => $row->message, 'msg_type' => $row->message_type, 'ogp' => $ogp, 'time' => gmdate('H:i', $timestamp + (get_option('gmt_offset') * 3600)), 'is_edited' => true);
            wp_send_json_success($responseData);
        } else {
            wp_send_json_error(__('Update failed.', 'edel-chat-pro'));
        }
    }

    function ajax_delete_message() {
        check_ajax_referer(EDEL_CHAT_PRO_SLUG, 'nonce');
        global $wpdb;
        $table_name = EDEL_CHAT_TABLE;
        $msgId = isset($_POST['msg_id']) ? intval($_POST['msg_id']) : 0;
        $userToken = isset($_POST['user_token']) ? sanitize_text_field(wp_unslash($_POST['user_token'])) : '';
        if (is_user_logged_in()) $userToken = 'wp_' . get_current_user_id();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $result = $wpdb->update($table_name, array('is_deleted' => 1), array('id' => $msgId, 'user_token' => $userToken), array('%d'), array('%d', '%s'));
        if ($result !== false) wp_send_json_success();
        else wp_send_json_error(__('Deletion failed.', 'edel-chat-pro'));
    }

    function edel_render_mypage() {
        $version = (defined('EDEL_CHAT_PRO_DEVELOP') && EDEL_CHAT_PRO_DEVELOP) ? time() : EDEL_CHAT_PRO_VERSION;
        wp_enqueue_style(EDEL_CHAT_PRO_SLUG . '-mypage', EDEL_CHAT_PRO_URL . '/css/mypage.css', array(), $version);

        // 1. 他ユーザーのプロフィール閲覧モード
        if (isset($_GET['edel_user'])) {
            $slug = sanitize_text_field($_GET['edel_user']);
            $user = get_user_by('slug', $slug);

            if (!$user) {
                return '<div class="edel-mypage-container"><p>' . esc_html__('User not found.', 'edel-chat-pro') . '</p></div>';
            }

            $is_public = get_user_meta($user->ID, 'edel_is_profile_public', true);
            if (!$is_public) {
                return '<div class="edel-mypage-container"><p>' . esc_html__('This profile is private.', 'edel-chat-pro') . '</p></div>';
            }

            $nickname = get_user_meta($user->ID, 'nickname', true) ?: $user->display_name;
            $description = get_user_meta($user->ID, 'description', true);
            $last_login = get_user_meta($user->ID, 'edel_last_login', true);
            $last_login_disp = $last_login ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($last_login)) : __('Unknown', 'edel-chat-pro');

            // ★変更点1: 画像URLを直接取得
            $custom_avatar_url = get_user_meta($user->ID, 'edel_custom_avatar_url', true);

            ob_start();
        ?>
            <div class="edel-mypage-container">
                <section class="edel-mypage-section">
                    <div class="edel-profile-header" style="text-align:center; margin-bottom:20px;">
                        <div class="edel-profile-avatar" style="margin-bottom:10px;">
                            <?php
                            // ★変更点2: 画像がある場合はimgタグを直接出力、なければ標準関数を使用
                            if ($custom_avatar_url) {
                                echo '<img src="' . esc_url($custom_avatar_url) . '" class="edel-mypage-avatar-img">';
                            } else {
                                echo get_avatar($user->ID, 120);
                            }
                            ?>
                        </div>
                        <h2 style="margin:0; font-size:24px;"><?php echo esc_html($nickname); ?></h2>
                        <p style="color:#888; font-size:12px; margin-top:5px;">
                            <?php esc_html_e('Last Login:', 'edel-chat-pro'); ?> <?php echo esc_html($last_login_disp); ?>
                        </p>
                    </div>

                    <?php if ($description): ?>
                        <div class="edel-profile-bio" style="background:#f9f9f9; padding:15px; border-radius:8px;">
                            <h3 style="font-size:16px; margin-top:0; border-bottom:1px solid #eee; padding-bottom:5px;"><?php esc_html_e('Bio', 'edel-chat-pro'); ?></h3>
                            <p style="white-space:pre-wrap;"><?php echo nl2br(esc_html($description)); ?></p>
                        </div>
                    <?php endif; ?>

                    <div style="margin-top:20px; text-align:center;">
                        <a href="javascript:history.back()" class="edel-btn-secondary" style="padding:10px 20px; background:#eee; border-radius:30px; text-decoration:none; color:#333; font-weight:bold;"><?php esc_html_e('Go Back', 'edel-chat-pro'); ?></a>
                    </div>
                </section>
            </div>
        <?php
            return ob_get_clean();
        }

        // 2. 自分のプロフィール編集モード
        if (!is_user_logged_in()) {
            $login_url = wp_login_url(get_permalink());
            return '<div class="edel-mypage-guest"><p>' . esc_html__('Login required.', 'edel-chat-pro') . '</p><a href="' . esc_url($login_url) . '" class="edel-btn-primary">' . esc_html__('Login', 'edel-chat-pro') . '</a></div>';
        }

        $current_user = wp_get_current_user();
        $user_id = $current_user->ID;
        $msg = '';

        if (isset($_POST['edel_save_profile']) && check_admin_referer('edel_profile_nonce_action', 'edel_profile_nonce_field')) {

            if (isset($_POST['edel_nickname'])) {
                $new_nick = sanitize_text_field(wp_unslash($_POST['edel_nickname']));
                update_user_meta($user_id, 'nickname', $new_nick);
                wp_update_user(['ID' => $user_id, 'display_name' => $new_nick]);
            }
            if (isset($_POST['edel_description'])) {
                update_user_meta($user_id, 'description', sanitize_textarea_field(wp_unslash($_POST['edel_description'])));
            }

            $is_public_val = isset($_POST['edel_is_profile_public']) ? 1 : 0;
            update_user_meta($user_id, 'edel_is_profile_public', $is_public_val);

            if (!empty($_FILES['edel_avatar_file']['name'])) {
                $upload_result = $this->edel_handle_avatar_upload($_FILES['edel_avatar_file'], $user_id);
                if (is_wp_error($upload_result)) {
                    $msg = '<div class="edel-msg-error">' . esc_html($upload_result->get_error_message()) . '</div>';
                } else {
                    update_user_meta($user_id, 'edel_custom_avatar_url', $upload_result);
                    $msg = '<div class="edel-msg-success">' . esc_html__('Profile and image updated.', 'edel-chat-pro') . '</div>';
                }
            } else {
                $msg = '<div class="edel-msg-success">' . esc_html__('Profile updated.', 'edel-chat-pro') . '</div>';
            }
        }

        $nickname = get_user_meta($user_id, 'nickname', true) ?: $current_user->display_name;
        $description = get_user_meta($user_id, 'description', true);
        $current_avatar_url = get_user_meta($user_id, 'edel_custom_avatar_url', true);
        $is_public = get_user_meta($user_id, 'edel_is_profile_public', true);
        $last_login = get_user_meta($user_id, 'edel_last_login', true);
        $last_login_disp = $last_login ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($last_login)) : '-';

        ob_start();
        ?>
        <div class="edel-mypage-container">
            <?php echo wp_kses_post($msg); ?>

            <section class="edel-mypage-section">
                <h2 class="edel-section-title"><?php esc_html_e('Profile Settings', 'edel-chat-pro'); ?></h2>

                <form id="edel-profile-form" method="post" enctype="multipart/form-data">
                    <?php wp_nonce_field('edel_profile_nonce_action', 'edel_profile_nonce_field'); ?>

                    <div class="edel-form-group icon-group">
                        <label><?php esc_html_e('Icon', 'edel-chat-pro'); ?></label>
                        <div class="edel-icon-preview">
                            <div id="edel-avatar-wrapper">
                                <?php
                                // ★変更点3: ここも自前でimgタグを出力
                                if ($current_avatar_url) {
                                    echo '<img src="' . esc_url($current_avatar_url) . '" class="edel-mypage-avatar-img">';
                                } else {
                                    echo get_avatar($user_id, 80);
                                }
                                ?>
                            </div>
                            <div class="edel-file-input-wrapper">
                                <input type="file" name="edel_avatar_file" id="edel_avatar_file" accept="image/jpeg,image/png,image/gif">
                                <p class="edel-note"><?php esc_html_e('* jpg, png, gif (Max 2MB)', 'edel-chat-pro'); ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="edel-form-group edel-public-setting">
                        <label class="edel-checkbox-label">
                            <input type="checkbox" name="edel_is_profile_public" value="1" <?php checked(1, $is_public); ?>>
                            <?php esc_html_e('Publish my profile', 'edel-chat-pro'); ?>
                        </label>
                        <p class="edel-public-note"><?php esc_html_e('If checked, other users can view your profile from the chat.', 'edel-chat-pro'); ?></p>
                    </div>

                    <div class="edel-form-group">
                        <label><?php esc_html_e('Display Name', 'edel-chat-pro'); ?></label>
                        <input type="text" name="edel_nickname" value="<?php echo esc_attr($nickname); ?>" class="edel-input-text">
                    </div>

                    <div class="edel-form-group">
                        <label><?php esc_html_e('Bio', 'edel-chat-pro'); ?></label>
                        <textarea name="edel_description" rows="4" class="edel-textarea"><?php echo esc_textarea($description); ?></textarea>
                    </div>

                    <div class="edel-form-group">
                        <label><?php esc_html_e('Last Login', 'edel-chat-pro'); ?></label>
                        <p class="edel-last-login-text"><?php echo esc_html($last_login_disp); ?></p>
                    </div>

                    <div class="edel-form-actions">
                        <button type="submit" name="edel_save_profile" class="edel-btn-primary"><?php esc_html_e('Save', 'edel-chat-pro'); ?></button>
                    </div>
                </form>
            </section>
        </div>

        <style>
            .edel-msg-success {
                background: #d4edda;
                color: #155724;
                padding: 15px;
                border-radius: 6px;
                margin-bottom: 25px;
                border: 1px solid #c3e6cb;
                text-align: center;
            }

            .edel-msg-error {
                background: #f8d7da;
                color: #721c24;
                padding: 15px;
                border-radius: 6px;
                margin-bottom: 25px;
                border: 1px solid #f5c6cb;
                text-align: center;
            }
        </style>
<?php
        return ob_get_clean();
    }

    function edel_handle_avatar_upload($file, $user_id) {
        $file_type = wp_check_filetype($file['name']);
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($file_type['type'], $allowed_types)) {
            return new WP_Error('invalid_type', __('Invalid file type.', 'edel-chat-pro'));
        }

        if ($file['size'] > 2 * 1024 * 1024) {
            return new WP_Error('file_too_large', __('File size too large (Max 2MB).', 'edel-chat-pro'));
        }

        $upload_dir = wp_upload_dir();
        $edel_dirname = 'edel-avatars';
        $target_dir = $upload_dir['basedir'] . '/' . $edel_dirname;

        // Use WP_Filesystem for file operations
        global $wp_filesystem;
        if (empty($wp_filesystem)) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }

        if (!$wp_filesystem->is_dir($target_dir)) {
            $wp_filesystem->mkdir($target_dir);
        }

        $filename = 'avatar-' . $user_id . '-' . time() . '.' . $file_type['ext'];
        $target_file = $target_dir . '/' . $filename;

        // Try to use WP_Filesystem put_contents for upload handling if possible, or fallback to move_uploaded_file logic wrapped.
        $file_content = $wp_filesystem->get_contents($file['tmp_name']);
        if ($wp_filesystem->put_contents($target_file, $file_content)) {
            $old_url = get_user_meta($user_id, 'edel_custom_avatar_url', true);
            if ($old_url) {
                $old_path = str_replace($upload_dir['baseurl'] . '/' . $edel_dirname . '/', $target_dir . '/', $old_url);
                if ($wp_filesystem->exists($old_path)) {
                    $wp_filesystem->delete($old_path);
                }
            }
            return $upload_dir['baseurl'] . '/' . $edel_dirname . '/' . $filename;
        } else {
            return new WP_Error('upload_failed', __('Upload failed. Check directory permissions.', 'edel-chat-pro'));
        }
    }

    function edel_custom_avatar_filter($avatar, $id_or_email, $size, $default, $alt) {
        $user = false;

        if (is_numeric($id_or_email)) {
            $user = get_user_by('id', (int)$id_or_email);
        } elseif (is_object($id_or_email) && !empty($id_or_email->user_id)) {
            $user = get_user_by('id', (int)$id_or_email->user_id);
        } elseif (is_string($id_or_email)) {
            $user = get_user_by('email', $id_or_email);
        }

        if ($user) {
            $custom_avatar_url = get_user_meta($user->ID, 'edel_custom_avatar_url', true);

            if ($custom_avatar_url) {
                return '<img alt="' . esc_attr($alt) . '" src="' . esc_url($custom_avatar_url) . '" class="avatar avatar-' . esc_attr($size) . ' photo" height="' . esc_attr($size) . '" width="' . esc_attr($size) . '" loading="lazy" decoding="async" style="object-fit:cover;" />';
            }
        }
        return $avatar;
    }

    function edel_custom_avatar_url_filter($url, $id_or_email, $args) {
        $user_id = $this->edel_get_user_id_from_avatar_arg($id_or_email);

        if ($user_id) {
            $custom_avatar_url = get_user_meta($user_id, 'edel_custom_avatar_url', true);

            if ($custom_avatar_url) {
                return $custom_avatar_url;
            }
        }
        return $url;
    }

    function edel_custom_avatar_html_filter($avatar, $id_or_email, $size, $default, $alt) {
        $user_id = $this->edel_get_user_id_from_avatar_arg($id_or_email);

        if ($user_id) {
            $custom_avatar_url = get_user_meta($user_id, 'edel_custom_avatar_url', true);

            if ($custom_avatar_url) {
                $class = "avatar avatar-{$size} photo";
                return "<img alt='" . esc_attr($alt) . "' src='" . esc_url($custom_avatar_url) . "' class='{$class}' height='{$size}' width='{$size}' loading='lazy' decoding='async' style='object-fit:cover;' />";
            }
        }
        return $avatar;
    }

    function edel_get_user_id_from_avatar_arg($id_or_email) {
        if (is_numeric($id_or_email)) {
            return (int)$id_or_email;
        } elseif (is_string($id_or_email) && is_email($id_or_email)) {
            $user = get_user_by('email', $id_or_email);
            return $user ? $user->ID : 0;
        } elseif (is_object($id_or_email)) {
            if (!empty($id_or_email->user_id)) {
                return (int)$id_or_email->user_id;
            }
            if (!empty($id_or_email->ID)) {
                return (int)$id_or_email->ID;
            }
        }
        return 0;
    }
}
