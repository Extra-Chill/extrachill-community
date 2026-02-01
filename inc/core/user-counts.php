<?php
/**
 * User Count Helpers
 *
 * Provides accurate user reply/topic counts by querying the database directly,
 * bypassing potentially stale bbPress user meta.
 *
 * @package ExtraChillCommunity
 * @since 1.x.x
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get accurate reply count for a user by querying posts directly.
 *
 * bbPress stores reply counts in user meta (_bbp_reply_count) but this can
 * become stale. This function queries the database for the actual count.
 *
 * @param int $user_id User ID. Defaults to displayed user.
 * @return int Reply count.
 */
function extrachill_get_user_reply_count_accurate($user_id = 0) {
    if (empty($user_id)) {
        $user_id = bbp_get_displayed_user_id();
    }
    
    if (empty($user_id)) {
        return 0;
    }
    
    // Check transient first
    $cache_key = 'ec_user_reply_count_' . $user_id;
    $count = get_transient($cache_key);
    
    if (false === $count) {
        global $wpdb;
        
        $reply_post_type = bbp_get_reply_post_type();
        
        $count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} 
             WHERE post_author = %d 
             AND post_type = %s 
             AND post_status = %s",
            $user_id,
            $reply_post_type,
            bbp_get_public_status_id()
        ));
        
        // Cache for 1 hour
        set_transient($cache_key, $count, HOUR_IN_SECONDS);
    }
    
    return (int) $count;
}

/**
 * Get accurate topic count for a user by querying posts directly.
 *
 * @param int $user_id User ID. Defaults to displayed user.
 * @return int Topic count.
 */
function extrachill_get_user_topic_count_accurate($user_id = 0) {
    if (empty($user_id)) {
        $user_id = bbp_get_displayed_user_id();
    }
    
    if (empty($user_id)) {
        return 0;
    }
    
    // Check transient first
    $cache_key = 'ec_user_topic_count_' . $user_id;
    $count = get_transient($cache_key);
    
    if (false === $count) {
        global $wpdb;
        
        $topic_post_type = bbp_get_topic_post_type();
        
        $count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} 
             WHERE post_author = %d 
             AND post_type = %s 
             AND post_status = %s",
            $user_id,
            $topic_post_type,
            bbp_get_public_status_id()
        ));
        
        // Cache for 1 hour
        set_transient($cache_key, $count, HOUR_IN_SECONDS);
    }
    
    return (int) $count;
}

/**
 * Clear accurate count transients when forum activity occurs.
 *
 * @param array $user_ids List of user IDs.
 */
function extrachill_clear_accurate_count_cache($user_ids) {
    if (empty($user_ids)) {
        return;
    }
    
    foreach ($user_ids as $user_id) {
        delete_transient('ec_user_reply_count_' . $user_id);
        delete_transient('ec_user_topic_count_' . $user_id);
    }
}

// Hook into existing cache invalidation
add_action('extrachill_clear_user_points_cache', 'extrachill_clear_accurate_count_cache', 10, 1);
