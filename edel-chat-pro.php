<?php

/**
 * Plugin Name:       Edel Chat Pro
 * Description:       ショートコードで設置できるLINE風グループチャット (Pro機能搭載版)
 * Version:           1.2.3
 * Author:            Edel Hearts
 * Author URI:        https://edel-hearts.com
 * License:           GPLv2 or later
 * Text Domain:       edel-chat-pro
 * Domain Path:       /languages
 * Requires at least: 5.7
 * Requires PHP:      7.2
 */

if (!defined('ABSPATH')) exit();

define('EDEL_CHAT_PRO_NAME', 'Edel Chat Pro');
define('EDEL_CHAT_PRO_URL', plugins_url('', __FILE__));
define('EDEL_CHAT_PRO_PATH', dirname(__FILE__));
define('EDEL_CHAT_PRO_SLUG', 'edel-chat-pro');
define('EDEL_CHAT_PRO_VERSION', '1.2.3');
define('EDEL_CHAT_PRO_DEVELOP', true);

global $wpdb;
define('EDEL_CHAT_TABLE', $wpdb->prefix . 'edel_chat_messages');
define('EDEL_CHAT_ROOMS_TABLE', $wpdb->prefix . 'edel_chat_rooms');
define('EDEL_CHAT_REACTIONS_TABLE', $wpdb->prefix . 'edel_chat_reactions');

class EdelChatPro {
    public function init() {
        require_once EDEL_CHAT_PRO_PATH . '/inc/class-admin.php';
        $admin = new EdelChatProAdmin();
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($admin, 'add_plugin_links'));
        add_action('admin_menu', array($admin, 'create_menu'));
        add_action('admin_enqueue_scripts', array($admin, 'admin_enqueue'));
        add_action('add_meta_boxes', array($admin, 'add_room_meta_box'));
        add_action('save_post', array($admin, 'save_room_meta_box'));

        require_once EDEL_CHAT_PRO_PATH . '/inc/class-front.php';
        $front = new EdelChatProFront();

        add_shortcode('edel_chat', array($front, 'render_chat_shortcode'));
        add_shortcode('edel_mypage', array($front, 'edel_render_mypage'));

        add_action('wp_ajax_edel_chat_send', array($front, 'ajax_send_message'));
        add_action('wp_ajax_nopriv_edel_chat_send', array($front, 'ajax_send_message'));

        add_action('wp_ajax_edel_chat_fetch', array($front, 'ajax_fetch_messages'));
        add_action('wp_ajax_nopriv_edel_chat_fetch', array($front, 'ajax_fetch_messages'));

        add_action('wp_ajax_edel_chat_delete', array($front, 'ajax_delete_message'));
        add_action('wp_ajax_nopriv_edel_chat_delete', array($front, 'ajax_delete_message'));

        add_action('wp_ajax_edel_chat_edit', array($front, 'ajax_edit_message'));
        add_action('wp_ajax_nopriv_edel_chat_edit', array($front, 'ajax_edit_message'));

        add_action('wp_ajax_edel_chat_reaction', array($front, 'ajax_send_reaction'));
        add_action('wp_ajax_nopriv_edel_chat_reaction', array($front, 'ajax_send_reaction'));

        add_filter('get_avatar_url', array($front, 'edel_custom_avatar_url_filter'), 10, 3);
        add_filter('get_avatar', array($front, 'edel_custom_avatar_html_filter'), 10, 5);
        add_action('wp_login', array($front, 'record_user_login'), 10, 2);
    }

    public static function activate() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // 1. メッセージテーブル
        $table_name = EDEL_CHAT_TABLE;
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            room_id varchar(100) NOT NULL,
            user_token varchar(100) NOT NULL,
            nickname varchar(100) NOT NULL,
            message text NOT NULL,
            message_type varchar(20) DEFAULT 'text' NOT NULL,
            meta_data longtext DEFAULT NULL,
            reply_to bigint(20) DEFAULT NULL,
            ip_address varchar(100) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            updated_at datetime DEFAULT NULL,
            is_deleted tinyint(1) DEFAULT 0 NOT NULL,
            PRIMARY KEY  (id),
            KEY room_id (room_id)
        ) $charset_collate;";
        dbDelta($sql);

        // 2. ルーム管理テーブル
        $rooms_table = EDEL_CHAT_ROOMS_TABLE;
        $sql_rooms = "CREATE TABLE $rooms_table (
            room_id varchar(100) NOT NULL,
            room_name varchar(255) NOT NULL,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (room_id)
        ) $charset_collate;";
        dbDelta($sql_rooms);

        // 3. リアクションテーブル
        $reactions_table = EDEL_CHAT_REACTIONS_TABLE;
        $sql_reactions = "CREATE TABLE $reactions_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            message_id bigint(20) NOT NULL,
            user_token varchar(100) NOT NULL,
            reaction varchar(50) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            KEY message_id (message_id),
            UNIQUE KEY user_reaction (message_id, user_token, reaction)
        ) $charset_collate;";
        dbDelta($sql_reactions);

        $upload_dir = wp_upload_dir();
        $chat_upload_dir = $upload_dir['basedir'] . '/edel-chat-pro';
        if (!file_exists($chat_upload_dir)) {
            wp_mkdir_p($chat_upload_dir);
        }
    }
}

register_activation_hook(__FILE__, array('EdelChatPro', 'activate'));

$edel_chat_pro_instance = new EdelChatPro();
$edel_chat_pro_instance->init();
