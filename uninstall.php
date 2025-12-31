<?php

/**
 * Fired when the plugin is uninstalled.
 *
 * @package Edel_Chat_Pro
 */

// If uninstall not called from WordPress, then exit.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

$options = get_option('edel_chat_options');

// Only delete data if the option is checked
if (!empty($options['delete_on_uninstall']) && $options['delete_on_uninstall'] == 1) {
    global $wpdb;

    // 1. Drop Custom Tables
    $table_msg = $wpdb->prefix . 'edel_chat_messages';
    $table_room = $wpdb->prefix . 'edel_chat_rooms';
    $table_react = $wpdb->prefix . 'edel_chat_reactions';

    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
    $wpdb->query("DROP TABLE IF EXISTS $table_msg");
    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
    $wpdb->query("DROP TABLE IF EXISTS $table_room");
    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
    $wpdb->query("DROP TABLE IF EXISTS $table_react");

    // 2. Delete Options
    delete_option('edel_chat_options');
    // Also delete any transients if used
    // Note: Transients are usually cleaned up automatically or can be explicitly deleted if key patterns are known.

    // 3. Delete Post Meta (Room Admin Name)
    // Deletes metadata with key '_edel_chat_admin_name' from all posts
    delete_post_meta_by_key('_edel_chat_admin_name');

    // 4. Delete User Meta (Avatar URL)
    // This requires iterating users or a direct DB query, but delete_metadata works if we don't specify object_id (though it's generally for specific object).
    // Better to use $wpdb to delete all meta with this key for performance and completeness.
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery
    $wpdb->delete($wpdb->usermeta, array('meta_key' => 'edel_custom_avatar_url'), array('%s'));

    // 5. Delete Uploaded Files (Avatars and Images)
    $upload_dir = wp_upload_dir();
    $edel_upload_dir = $upload_dir['basedir'] . '/edel-chat-pro';
    $edel_avatar_dir = $upload_dir['basedir'] . '/edel-avatars';

    // Use WP_Filesystem API for file deletion
    global $wp_filesystem;
    if (empty($wp_filesystem)) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        WP_Filesystem();
    }

    if ($wp_filesystem->is_dir($edel_upload_dir)) {
        // recursive delete
        $wp_filesystem->delete($edel_upload_dir, true);
    }

    if ($wp_filesystem->is_dir($edel_avatar_dir)) {
        // recursive delete
        $wp_filesystem->delete($edel_avatar_dir, true);
    }
}
