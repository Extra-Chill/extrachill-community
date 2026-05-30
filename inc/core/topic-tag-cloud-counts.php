<?php
/**
 * Topic Tag Cloud Counts
 *
 * Appends the topic count to each tag in the bbPress topic tag cloud, so tags
 * render as "Tag Name (N)" instead of just "Tag Name". This covers every
 * bbPress topic-tag cloud surface (the Topic Tags page template, the
 * [bbp-topic-tags] shortcode, and the Topic Tags widget), all of which call
 * core wp_tag_cloud() with 'taxonomy' => topic-tag and no count.
 *
 * wp_tag_cloud() has no pre-args filter, so we scope by detecting the topic-tag
 * taxonomy in tag_cloud_sort (which receives $args) and then inject the count
 * span in wp_generate_tag_cloud_data using each term's real count.
 *
 * @package ExtraChillCommunity
 */

defined( 'ABSPATH' ) || exit;

/**
 * Request-scoped flag: true while a bbPress topic-tag cloud is rendering.
 *
 * @var bool
 */
$GLOBALS['extrachill_community_topic_tag_cloud_active'] = false;

/**
 * Detect when wp_tag_cloud() is rendering the bbPress topic-tag taxonomy.
 *
 * Runs inside wp_generate_tag_cloud() before the cloud data is assembled.
 * Sets a flag so wp_generate_tag_cloud_data can scope its count injection to
 * the topic-tag cloud only, leaving any other tag cloud untouched.
 *
 * @param array $tags Tags being rendered.
 * @param array $args Tag cloud arguments (includes 'taxonomy').
 * @return array Unmodified tags.
 */
function extrachill_community_flag_topic_tag_cloud( $tags, $args ) {
	$taxonomy = isset( $args['taxonomy'] ) ? $args['taxonomy'] : '';

	if ( function_exists( 'bbp_get_topic_tag_tax_id' ) && bbp_get_topic_tag_tax_id() === $taxonomy ) {
		$GLOBALS['extrachill_community_topic_tag_cloud_active'] = true;
	}

	return $tags;
}
add_filter( 'tag_cloud_sort', 'extrachill_community_flag_topic_tag_cloud', 10, 2 );

/**
 * Append the topic count to each tag in the topic-tag cloud.
 *
 * Only fires for the bbPress topic-tag cloud (gated by the flag set in
 * extrachill_community_flag_topic_tag_cloud). Rebuilds the per-term
 * 'show_count' span — which core leaves empty unless the show_count arg is set
 * — so each tag renders as "Tag Name (N)" with the existing
 * .tag-link-count markup and styling.
 *
 * @param array[] $tags_data Per-term data arrays for the tag cloud.
 * @return array[] Tag data with counts appended.
 */
function extrachill_community_topic_tag_cloud_counts( $tags_data ) {
	if ( empty( $GLOBALS['extrachill_community_topic_tag_cloud_active'] ) ) {
		return $tags_data;
	}

	// Reset the flag so it cannot leak into a later, unrelated cloud render.
	$GLOBALS['extrachill_community_topic_tag_cloud_active'] = false;

	foreach ( $tags_data as $key => $tag_data ) {
		if ( ! isset( $tag_data['real_count'] ) ) {
			continue;
		}

		$count = (int) $tag_data['real_count'];

		$tags_data[ $key ]['show_count'] = '<span class="tag-link-count"> (' . $count . ')</span>';

		// Mirror core's accessible label so the count is announced consistently.
		$tags_data[ $key ]['aria_label'] = sprintf(
			' aria-label="%1$s (%2$s)"',
			esc_attr( $tag_data['name'] ),
			esc_attr( (string) $count )
		);
	}

	return $tags_data;
}
add_filter( 'wp_generate_tag_cloud_data', 'extrachill_community_topic_tag_cloud_counts' );
