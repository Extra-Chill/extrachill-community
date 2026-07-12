<?php
/**
 * Profile Activity Feed
 *
 * Renders a user-scoped activity feed (the displayed user's topics + replies)
 * on their bbPress profile page. This is a distinct surface from the global
 * /recent page: it owns its own renderer and a query filtered to the displayed
 * user via extrachill_get_user_activity_query(), so the two surfaces can no
 * longer affect one another.
 *
 * Historically the global /recent template carried a dead `bbp_is_single_user()`
 * branch that was never reachable (the template is only assigned to the /recent
 * page), so profiles had no activity feed at all. This restores a correct,
 * user-scoped feed.
 *
 * @package ExtraChillCommunity
 */

// Prevent direct access.
if ( ! defined('ABSPATH') ) {
	exit;
}

/**
 * Render the displayed user's activity feed below their profile.
 *
 * Hooked to bbp_template_after_user_profile so it renders as the very last
 * element on the profile page — after the header, the About / Community
 * Activity / Artists card grid, the heatmap, and concert history. The feed is the
 * long-tail detail view; all glanceable profile info stays above it. The
 * bbp_is_single_user_profile() guard keeps it on the main profile tab only,
 * not on the topics/replies/edit sub-tabs which already have their own
 * bbPress loops. Full-width via the existing .user-profile-activity-feed
 * CSS rule.
 *
 * @return void
 */
function extrachill_render_profile_activity_feed() {
	if ( ! function_exists('bbp_is_single_user_profile') || ! bbp_is_single_user_profile() ) {
		return;
	}

	if ( ! function_exists('extrachill_get_user_activity_query') ) {
		return;
	}

	$user_id = (int) bbp_get_displayed_user_id();
	if ( $user_id <= 0 ) {
		return;
	}

	$display_name = bbp_get_displayed_user_field('display_name');

	$feed = extrachill_get_user_activity_query( $user_id, 15 );

	echo '<div class="bbp-user-profile-card user-profile-activity-feed">';
	printf(
		'<h3>%s</h3>',
		/* translators: %s: displayed user's name. */
		esc_html( sprintf( __( "%s's Recent Activity", 'extra-chill-community' ), $display_name ) )
	);

	$empty_notice = sprintf(
		/* translators: %s: displayed user's name. */
		__( '%s has no recent activity yet.', 'extra-chill-community' ),
		$display_name
	);

	extrachill_render_recent_feed( $feed, $empty_notice );

	echo '</div>';
}
// Fires after the entire profile body (bbpress/user-profile.php). Priority 99
// keeps the feed the final element on the page, after the heatmap (1),
// Concert History (5), and Music Fan Details (20) which share this hook.
add_action( 'bbp_template_after_user_profile', 'extrachill_render_profile_activity_feed', 99 );
