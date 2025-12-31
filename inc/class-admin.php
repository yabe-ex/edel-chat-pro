<?php

class EdelChatProAdmin {

    function create_menu() {
        add_menu_page(EDEL_CHAT_PRO_NAME, EDEL_CHAT_PRO_NAME, 'manage_options', 'edel-chat-pro', array($this, 'show_log_page'), 'dashicons-format-chat', 20);
        add_submenu_page('edel-chat-pro', __('Chat Rooms', 'edel-chat-pro'), __('Chat Rooms', 'edel-chat-pro'), 'manage_options', 'edel-chat-pro', array($this, 'show_log_page'));
        add_submenu_page('edel-chat-pro', __('Settings', 'edel-chat-pro'), __('Settings', 'edel-chat-pro'), 'manage_options', 'edel-chat-pro-settings', array($this, 'show_setting_page'));

        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_init', array($this, 'handle_csv_download'));

        add_action('add_meta_boxes', array($this, 'add_room_meta_box'));
        add_action('save_post', array($this, 'save_room_meta_box'));
    }

    public function add_plugin_links($links) {
        $url = admin_url('admin.php?page=edel-chat-pro-settings');
        $settings_link = '<a href="' . esc_url($url) . '" style="font-weight:bold;">' . esc_html__('Settings', 'edel-chat-pro') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    function handle_csv_download() {
        if (isset($_POST['action']) && $_POST['action'] === 'download_csv') {
            if (!current_user_can('manage_options')) return;

            $room_id = isset($_POST['room_id']) ? sanitize_text_field(wp_unslash($_POST['room_id'])) : '';
            check_admin_referer('edel_download_csv_' . $room_id);

            global $wpdb;
            $table_name = EDEL_CHAT_TABLE;

            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Table name is a constant.
            $logs = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM " . $table_name . " WHERE room_id = %s AND is_deleted = 0 ORDER BY created_at ASC",
                $room_id
            ));

            $filename = 'chat_log_' . $room_id . '_' . gmdate('YmdHis') . '.csv';

            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');

            $csv_lines = array();

            // CSV Header
            $csv_lines[] = $this->array_to_csv(array('ID', 'Date', 'IP Address', 'Nickname', 'User Token', 'Type', 'Message'));

            foreach ($logs as $row) {
                $content = $row->message;
                if ($row->message_type === 'image') {
                    $content = '[Image] ' . $row->message;
                } elseif ($row->message_type === 'stamp') {
                    $content = '[Stamp]';
                }

                $csv_lines[] = $this->array_to_csv(array(
                    $row->id,
                    $row->created_at,
                    !empty($row->ip_address) ? $row->ip_address : '',
                    $row->nickname,
                    $row->user_token,
                    $row->message_type,
                    $content
                ));
            }

            echo "\xEF\xBB\xBF"; // BOM
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV data.
            echo implode('', $csv_lines);
            exit();
        }
    }

    private function array_to_csv($fields, $delimiter = ',', $enclosure = '"', $escape_char = '\\') {
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations.fopen_fopen
        $buffer = fopen('php://memory', 'r+');
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations.fputcsv_fputcsv
        fputcsv($buffer, $fields, $delimiter, $enclosure, $escape_char);
        rewind($buffer);
        $csv = stream_get_contents($buffer);
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations.fclose_fclose
        fclose($buffer);
        return $csv;
    }

    function add_room_meta_box() {
        $screens = array('post', 'page');
        foreach ($screens as $screen) {
            add_meta_box('edel_chat_room_settings', __('Edel Chat Pro Room Settings', 'edel-chat-pro'), array($this, 'render_room_meta_box'), $screen, 'side');
        }
    }

    function render_room_meta_box($post) {
        $admin_name = get_post_meta($post->ID, '_edel_chat_admin_name', true);
        wp_nonce_field('edel_chat_room_meta_save', 'edel_chat_room_meta_nonce');
        echo '<p><label>' . esc_html__('Admin Name for this page:', 'edel-chat-pro') . '</label><input type="text" name="edel_chat_admin_name" value="' . esc_attr($admin_name) . '" style="width:100%;"><small>' . esc_html__('Name used when admin posts.', 'edel-chat-pro') . '</small></p>';
    }

    function save_room_meta_box($post_id) {
        if (!isset($_POST['edel_chat_room_meta_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['edel_chat_room_meta_nonce'])), 'edel_chat_room_meta_save')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (isset($_POST['edel_chat_admin_name'])) update_post_meta($post_id, '_edel_chat_admin_name', sanitize_text_field(wp_unslash($_POST['edel_chat_admin_name'])));
    }

    function register_settings() {
        register_setting('edel_chat_option_group', 'edel_chat_options', array('sanitize_callback' => array($this, 'sanitize_options')));

        add_settings_section('edel_chat_design_section', '', null, 'edel-chat-pro-settings');

        add_settings_field('bg_color', __('Background Color', 'edel-chat-pro'), array($this, 'render_color_picker'), 'edel-chat-pro-settings', 'edel_chat_design_section', array('field' => 'bg_color', 'default' => '#7296cc'));
        add_settings_field('me_color', __('My Bubble Color', 'edel-chat-pro'), array($this, 'render_color_picker'), 'edel-chat-pro-settings', 'edel_chat_design_section', array('field' => 'me_color', 'default' => '#8de055'));
        add_settings_field('other_color', __('Other Bubble Color', 'edel-chat-pro'), array($this, 'render_color_picker'), 'edel-chat-pro-settings', 'edel_chat_design_section', array('field' => 'other_color', 'default' => '#ffffff'));
        add_settings_field('window_height', __('Chat Window Height', 'edel-chat-pro'), array($this, 'render_text_field'), 'edel-chat-pro-settings', 'edel_chat_design_section', array('field' => 'window_height', 'default' => '500px'));

        add_settings_field('admin_label', __('Admin Badge Text', 'edel-chat-pro'), array($this, 'render_text_field_small'), 'edel-chat-pro-settings', 'edel_chat_design_section', array('field' => 'admin_label', 'default' => 'ADMIN', 'desc' => __('Example: ADMIN, STAFF', 'edel-chat-pro')));

        add_settings_field('polling_interval', __('Update Interval (sec)', 'edel-chat-pro'), array($this, 'render_number_field'), 'edel-chat-pro-settings', 'edel_chat_design_section', array('field' => 'polling_interval', 'default' => '3', 'desc' => __('Interval for auto-fetching messages. Increase this if server load is high.', 'edel-chat-pro')));

        add_settings_field('spam_limit', __('Spam Limit (sec)', 'edel-chat-pro'), array($this, 'render_number_field'), 'edel-chat-pro-settings', 'edel_chat_design_section', array('field' => 'spam_limit', 'default' => '2', 'desc' => __('Wait time between posts (0 to disable).', 'edel-chat-pro')));

        add_settings_field('banned_ips', __('Banned IP List', 'edel-chat-pro'), array($this, 'render_textarea_field'), 'edel-chat-pro-settings', 'edel_chat_design_section', array('field' => 'banned_ips', 'description' => __('Enter one IP address per line. Posts from these IPs will be rejected.', 'edel-chat-pro')));

        add_settings_field('ng_words', __('NG Words', 'edel-chat-pro'), array($this, 'render_textarea_field'), 'edel-chat-pro-settings', 'edel_chat_design_section', array('field' => 'ng_words', 'description' => __('Comma separated (e.g. word1,word2).', 'edel-chat-pro')));

        // New setting for uninstallation
        add_settings_section('edel_chat_uninstall_section', __('Uninstallation', 'edel-chat-pro'), null, 'edel-chat-pro-settings');
        add_settings_field('delete_on_uninstall', __('Delete Data on Uninstall', 'edel-chat-pro'), array($this, 'render_checkbox_field'), 'edel-chat-pro-settings', 'edel_chat_uninstall_section', array('field' => 'delete_on_uninstall', 'description' => __('If checked, all chat logs, settings, and uploaded files will be permanently deleted when the plugin is deleted.', 'edel-chat-pro')));
    }

    function sanitize_options($input) {
        $new_input = array();
        if (isset($input['bg_color'])) $new_input['bg_color'] = sanitize_hex_color($input['bg_color']);
        if (isset($input['me_color'])) $new_input['me_color'] = sanitize_hex_color($input['me_color']);
        if (isset($input['other_color'])) $new_input['other_color'] = sanitize_hex_color($input['other_color']);
        if (isset($input['window_height'])) $new_input['window_height'] = sanitize_text_field($input['window_height']);
        if (isset($input['admin_label'])) $new_input['admin_label'] = sanitize_text_field($input['admin_label']);
        if (isset($input['polling_interval'])) $new_input['polling_interval'] = absint($input['polling_interval']);
        if (isset($input['spam_limit'])) $new_input['spam_limit'] = absint($input['spam_limit']);
        if (isset($input['banned_ips'])) $new_input['banned_ips'] = sanitize_textarea_field($input['banned_ips']);
        if (isset($input['ng_words'])) $new_input['ng_words'] = sanitize_textarea_field($input['ng_words']);
        // Sanitize uninstall checkbox
        $new_input['delete_on_uninstall'] = isset($input['delete_on_uninstall']) ? 1 : 0;
        return $new_input;
    }

    function render_color_picker($args) {
        $options = get_option('edel_chat_options');
        $val = isset($options[$args['field']]) ? $options[$args['field']] : $args['default'];
        echo '<input type="text" name="edel_chat_options[' . esc_attr($args['field']) . ']" value="' . esc_attr($val) . '" class="edel-color-field" data-default-color="' . esc_attr($args['default']) . '" />';
    }

    function render_text_field($args) {
        $options = get_option('edel_chat_options');
        $val = isset($options[$args['field']]) ? $options[$args['field']] : $args['default'];
        echo '<input type="text" name="edel_chat_options[' . esc_attr($args['field']) . ']" value="' . esc_attr($val) . '" class="regular-text" />';
        echo '<p class="description">' . esc_html__('Example: 500px, 80vh', 'edel-chat-pro') . '</p>';
    }

    function render_text_field_small($args) {
        $options = get_option('edel_chat_options');
        $val = isset($options[$args['field']]) ? $options[$args['field']] : $args['default'];
        echo '<input type="text" name="edel_chat_options[' . esc_attr($args['field']) . ']" value="' . esc_attr($val) . '" class="regular-text" style="width:200px;" />';
        if (isset($args['desc'])) echo '<p class="description">' . esc_html($args['desc']) . '</p>';
    }

    function render_number_field($args) {
        $options = get_option('edel_chat_options');
        $val = isset($options[$args['field']]) ? $options[$args['field']] : $args['default'];
        echo '<input type="number" name="edel_chat_options[' . esc_attr($args['field']) . ']" value="' . esc_attr($val) . '" class="small-text" min="0" step="1" />';
        if (isset($args['desc'])) echo '<p class="description">' . esc_html($args['desc']) . '</p>';
    }

    function render_textarea_field($args) {
        $options = get_option('edel_chat_options');
        $val = isset($options[$args['field']]) ? $options[$args['field']] : '';
        echo '<textarea name="edel_chat_options[' . esc_attr($args['field']) . ']" rows="5" cols="50" class="large-text code">' . esc_textarea($val) . '</textarea>';
        if (isset($args['description'])) echo '<p class="description">' . esc_html($args['description']) . '</p>';
    }

    function render_checkbox_field($args) {
        $options = get_option('edel_chat_options');
        $val = isset($options[$args['field']]) ? $options[$args['field']] : 0;
        echo '<input type="checkbox" name="edel_chat_options[' . esc_attr($args['field']) . ']" value="1" ' . checked(1, $val, false) . ' />';
        if (isset($args['description'])) echo '<p class="description">' . esc_html($args['description']) . '</p>';
    }

    function admin_enqueue($hook) {
        if (strpos($hook, 'edel-chat-pro') === false) return;
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('edel-chat-admin-js', EDEL_CHAT_PRO_URL . '/js/admin.js', array('jquery', 'wp-color-picker'), EDEL_CHAT_PRO_VERSION, true);
    }

    function show_setting_page() {
?>
        <div class="wrap">
            <h1><?php echo esc_html(EDEL_CHAT_PRO_NAME); ?> <?php esc_html_e('Settings', 'edel-chat-pro'); ?></h1>
            <form method="post" action="options.php">
                <?php settings_fields('edel_chat_option_group');
                do_settings_sections('edel-chat-pro-settings');
                submit_button(); ?>
            </form>
        </div>
<?php
    }

    function show_log_page() {
        global $wpdb;
        $msg_table = EDEL_CHAT_TABLE;
        $room_table = EDEL_CHAT_ROOMS_TABLE;

        if (isset($_POST['action']) && $_POST['action'] === 'update_room_name') {
            $rid = isset($_POST['room_id']) ? sanitize_text_field(wp_unslash($_POST['room_id'])) : '';
            check_admin_referer('edel_update_room_name');
            $rname = isset($_POST['room_name']) ? sanitize_text_field(wp_unslash($_POST['room_name'])) : '';

            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Table name is a constant.
            $exists = $wpdb->get_var($wpdb->prepare("SELECT room_id FROM " . $room_table . " WHERE room_id = %s", $rid));
            if ($exists) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                $wpdb->update($room_table, array('room_name' => $rname), array('room_id' => $rid), array('%s'), array('%s'));
            } else {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                $wpdb->insert($room_table, array('room_id' => $rid, 'room_name' => $rname), array('%s', '%s'));
            }
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Room name updated.', 'edel-chat-pro') . '</p></div>';
        }

        if (isset($_POST['action']) && $_POST['action'] === 'delete_log') {
            $log_id = isset($_POST['log_id']) ? intval($_POST['log_id']) : 0;
            check_admin_referer('delete_chat_log_' . $log_id);
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->update($msg_table, array('is_deleted' => 1), array('id' => $log_id), array('%d'), array('%d'));
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Log deleted.', 'edel-chat-pro') . '</p></div>';
        }

        if (isset($_POST['action']) && $_POST['action'] === 'empty_room') {
            $target_rid = isset($_POST['room_id']) ? sanitize_text_field(wp_unslash($_POST['room_id'])) : '';
            check_admin_referer('edel_empty_room_' . $target_rid);
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->update($msg_table, array('is_deleted' => 1), array('room_id' => $target_rid), array('%d'), array('%s'));
            /* translators: %s: room id */
            echo '<div class="notice notice-success is-dismissible"><p>' . sprintf(esc_html__('All messages in room "%s" have been deleted.', 'edel-chat-pro'), esc_html($target_rid)) . '</p></div>';
        }

        $view = isset($_GET['view']) ? sanitize_text_field(wp_unslash($_GET['view'])) : 'list';
        $target_room = isset($_GET['room_id']) ? sanitize_text_field(wp_unslash($_GET['room_id'])) : '';

        // Detail View
        if ($view === 'room' && !empty($target_room)) {
            // Caching this query as it can be heavy
            $cache_key = 'edel_chat_logs_' . md5($target_room);
            $logs = wp_cache_get($cache_key, 'edel_chat_logs');
            if (false === $logs) {
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Table name is a constant.
                $logs = $wpdb->get_results($wpdb->prepare("SELECT * FROM " . $msg_table . " WHERE room_id = %s AND is_deleted = 0 ORDER BY created_at DESC LIMIT 200", $target_room));
                wp_cache_set($cache_key, $logs, 'edel_chat_logs', 30);
            }

            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Table name is a constant.
            $room_name = $wpdb->get_var($wpdb->prepare("SELECT room_name FROM " . $room_table . " WHERE room_id = %s", $target_room));
            if (!$room_name) $room_name = "(" . __('No Name', 'edel-chat-pro') . ")";

            echo '<div class="wrap"><h1>' . esc_html__('Chat Details', 'edel-chat-pro') . ': ' . esc_html($room_name) . ' (' . esc_html($target_room) . ')</h1>';
            echo '<a href="' . esc_url(admin_url('admin.php?page=edel-chat-pro')) . '" class="button">← ' . esc_html__('Back to List', 'edel-chat-pro') . '</a>';
            echo '<table class="wp-list-table widefat fixed striped table-view-list" style="margin-top:20px;"><thead><tr><th width="150">' . esc_html__('Date', 'edel-chat-pro') . '</th><th width="120">' . esc_html__('IP Address', 'edel-chat-pro') . '</th><th width="150">' . esc_html__('Nickname', 'edel-chat-pro') . '</th><th>' . esc_html__('Message', 'edel-chat-pro') . '</th><th width="80">' . esc_html__('Action', 'edel-chat-pro') . '</th></tr></thead><tbody>';

            if ($logs) {
                foreach ($logs as $row) {
                    echo '<tr>';
                    echo '<td>' . esc_html($row->created_at) . '</td>';
                    echo '<td>' . (!empty($row->ip_address) ? esc_html($row->ip_address) : '-') . '</td>';
                    echo '<td>' . esc_html($row->nickname) . '</td>';
                    echo '<td>';
                    if ($row->message_type === 'image') echo '<a href="' . esc_url($row->message) . '" target="_blank">[' . esc_html__('Image', 'edel-chat-pro') . ']</a>';
                    else echo nl2br(esc_html($row->message));
                    echo '</td><td><form method="post"><input type="hidden" name="action" value="delete_log"><input type="hidden" name="log_id" value="' . esc_attr($row->id) . '">';
                    wp_nonce_field('delete_chat_log_' . $row->id);
                    echo '<button type="submit" class="button button-small button-link-delete" onclick="return confirm(\'' . esc_js(__('Are you sure?', 'edel-chat-pro')) . '\');">' . esc_html__('Delete', 'edel-chat-pro') . '</button></form></td></tr>';
                }
            } else {
                echo '<tr><td colspan="5">' . esc_html__('No logs found.', 'edel-chat-pro') . '</td></tr>';
            }
            echo '</tbody></table></div>';

            // List View
        } else {
            // Using aliases for tables
            $cache_key = 'edel_chat_room_list';
            $rooms = wp_cache_get($cache_key, 'edel_chat_rooms');
            if (false === $rooms) {
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Table names are constants.
                $sql = "SELECT m.room_id, COUNT(m.id) as msg_count, MAX(m.created_at) as last_active, r.room_name FROM " . $msg_table . " m LEFT JOIN " . $room_table . " r ON m.room_id = r.room_id WHERE m.is_deleted = 0 GROUP BY m.room_id ORDER BY last_active DESC";
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                $rooms = $wpdb->get_results($sql);
                wp_cache_set($cache_key, $rooms, 'edel_chat_rooms', 60);
            }

            echo '<div class="wrap"><h1>' . esc_html__('Chat Rooms', 'edel-chat-pro') . '</h1>';
            echo '<table class="wp-list-table widefat fixed striped table-view-list" style="table-layout: fixed;">';
            echo '<thead><tr>';
            echo '<th style="width:10%;">' . esc_html__('Room ID', 'edel-chat-pro') . '</th>';
            echo '<th style="width:30%;">' . esc_html__('Room Name', 'edel-chat-pro') . '</th>';
            echo '<th style="width:10%;">' . esc_html__('Messages', 'edel-chat-pro') . '</th>';
            echo '<th style="width:15%;">' . esc_html__('Last Active', 'edel-chat-pro') . '</th>';
            echo '<th style="width:35%;">' . esc_html__('Action', 'edel-chat-pro') . '</th>';
            echo '</tr></thead><tbody>';

            if ($rooms) {
                foreach ($rooms as $room) {
                    $detail_url = admin_url('admin.php?page=edel-chat-pro&view=room&room_id=' . urlencode($room->room_id));
                    echo '<tr>';
                    echo '<td style="word-wrap:break-word;"><a href="' . esc_url($detail_url) . '"><strong>' . esc_html($room->room_id) . '</strong></a></td>';

                    echo '<td>';
                    echo '<form method="post" style="display:flex; gap:5px; align-items:center;">';
                    echo '<input type="hidden" name="action" value="update_room_name">';
                    echo '<input type="hidden" name="room_id" value="' . esc_attr($room->room_id) . '">';
                    wp_nonce_field('edel_update_room_name');
                    echo '<input type="text" name="room_name" value="' . esc_attr($room->room_name) . '" placeholder="' . esc_attr__('Set Name', 'edel-chat-pro') . '" style="width:100%; max-width:200px;">';
                    echo '<button type="submit" class="button button-small">' . esc_html__('Save', 'edel-chat-pro') . '</button>';
                    echo '</form>';
                    echo '</td>';

                    echo '<td>' . esc_html($room->msg_count) . '</td>';
                    echo '<td>' . esc_html($room->last_active) . '</td>';

                    echo '<td><div style="display:flex; gap:5px; flex-wrap:wrap;">';
                    echo '<a href="' . esc_url($detail_url) . '" class="button button-primary">' . esc_html__('View Logs', 'edel-chat-pro') . '</a>';

                    echo '<form method="post" style="margin:0;">';
                    echo '<input type="hidden" name="action" value="download_csv">';
                    echo '<input type="hidden" name="room_id" value="' . esc_attr($room->room_id) . '">';
                    wp_nonce_field('edel_download_csv_' . $room->room_id);
                    echo '<button type="submit" class="button button-secondary">' . esc_html__('CSV DL', 'edel-chat-pro') . '</button>';
                    echo '</form>';

                    echo '<form method="post" onsubmit="return confirm(\'' . esc_js(__('Are you sure you want to delete all logs in this room?', 'edel-chat-pro')) . '\');" style="margin:0;">';
                    echo '<input type="hidden" name="action" value="empty_room">';
                    echo '<input type="hidden" name="room_id" value="' . esc_attr($room->room_id) . '">';
                    wp_nonce_field('edel_empty_room_' . $room->room_id);
                    echo '<button type="submit" class="button button-link-delete" style="color:#a00;">' . esc_html__('Delete All', 'edel-chat-pro') . '</button>';
                    echo '</form>';
                    echo '</div></td>';

                    echo '</tr>';
                }
            } else {
                echo '<tr><td colspan="5">' . esc_html__('No rooms found.', 'edel-chat-pro') . '</td></tr>';
            }
            echo '</tbody></table></div>';
        }
    }
}
