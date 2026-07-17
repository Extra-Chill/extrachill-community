<?php
/**
 * Artist profile discussion section.
 *
 * Community registers its own section through Artist Platform's public profile
 * section registry. Artist Platform remains unaware of Community.
 *
 * @package ExtraChillCommunity
 * @subpackage Core
 */

defined( 'ABSPATH' ) || exit;

/** Maximum recent discussions rendered on an artist profile. */
const EXTRACHILL_COMMUNITY_ARTIST_TOPIC_LIMIT = 4;

/**
 * Register the Community-owned artist discussion section.
 *
 * @param array[] $sections Registered profile sections.
 * @return array[]
 */
function extrachill_community_register_artist_discussions_section( $sections ) {
	$sections[] = array(
		'id'       => 'discussions',
		'label'    => __( 'Discussions', 'extra-chill-community' ),
		'priority' => 50,
		'as_tab'   => false,
		'visible'  => 'extrachill_community_artist_discussions_visible',
		'render'   => 'extrachill_community_render_artist_discussions_section',
	);

	return $sections;
}
add_filter( 'ec_artist_profile_sections', 'extrachill_community_register_artist_discussions_section', 10, 3 );

/**
 * Whether the current request is an Artist Platform profile surface.
 *
 * @return bool
 */
function extrachill_community_is_artist_profile_discussions_surface() {
	return function_exists( 'is_singular' ) && is_singular( 'artist_profile' );
}

/**
 * Load Community's existing topic-card styles on canonical artist profiles.
 *
 * @return void
 */
function extrachill_community_enqueue_artist_discussions_styles() {
	if ( ! extrachill_community_is_artist_profile_discussions_surface() ) {
		return;
	}

	wp_enqueue_style(
		'extrachill-bbpress',
		EXTRACHILL_COMMUNITY_PLUGIN_URL . '/inc/assets/css/bbpress.css',
		array(),
		filemtime( EXTRACHILL_COMMUNITY_PLUGIN_DIR . '/inc/assets/css/bbpress.css' )
	);
	wp_enqueue_style(
		'topics-loop',
		EXTRACHILL_COMMUNITY_PLUGIN_URL . '/inc/assets/css/topics-loop.css',
		array( 'extrachill-bbpress' ),
		filemtime( EXTRACHILL_COMMUNITY_PLUGIN_DIR . '/inc/assets/css/topics-loop.css' )
	);
}
add_action( 'wp_enqueue_scripts', 'extrachill_community_enqueue_artist_discussions_styles', 20 );

/**
 * Resolve a bound main-blog artist term to its shared slug.
 *
 * @param int $artist_term_id Bound main-blog artist term ID.
 * @return string
 */
function extrachill_community_resolve_bound_artist_slug( $artist_term_id ) {
	$artist_term_id = (int) $artist_term_id;
	if ( $artist_term_id <= 0 || ! function_exists( 'ec_get_blog_id' ) ) {
		return '';
	}

	$main_blog_id = (int) ec_get_blog_id( 'main' );
	if ( $main_blog_id <= 0 ) {
		return '';
	}

	$slug = '';
	switch_to_blog( $main_blog_id );
	try {
		if ( ! taxonomy_exists( 'artist' ) ) {
			return '';
		}

		$term = get_term( $artist_term_id, 'artist' );
		if ( $term instanceof WP_Term ) {
			$slug = (string) $term->slug;
		}
	} finally {
		restore_current_blog();
	}

	return $slug;
}

/**
 * Gather the canonical Community destination and recent artist topics.
 *
 * The main-blog term ID cannot be queried directly against another site's
 * term tables. Its slug is the shared join key, so Community resolves the
 * local term by slug before deriving its real archive URL and topic query.
 * Results are memoized because the registry calls visibility before render.
 *
 * @param int $artist_id      Artist profile post ID.
 * @param int $artist_term_id Bound main-blog artist term ID.
 * @return array{blog_id:int,url:string,name:string,topic_ids:int[]}
 */
function extrachill_community_get_artist_discussions( $artist_id, $artist_term_id ) {
	static $memo = array();

	$artist_id      = (int) $artist_id;
	$artist_term_id = (int) $artist_term_id;
	$key            = $artist_id . ':' . $artist_term_id;
	$empty          = array(
		'blog_id'   => 0,
		'url'       => '',
		'name'      => '',
		'topic_ids' => array(),
	);

	if ( isset( $memo[ $key ] ) ) {
		return $memo[ $key ];
	}
	$memo[ $key ] = $empty;

	if (
		$artist_id <= 0 ||
		'artist_profile' !== get_post_type( $artist_id ) ||
		! function_exists( 'ec_get_blog_id' ) ||
		! function_exists( 'bbp_get_topic_post_type' ) ||
		! function_exists( 'bbp_get_public_status_id' )
	) {
		return $empty;
	}

	$slug              = extrachill_community_resolve_bound_artist_slug( $artist_term_id );
	$community_blog_id = (int) ec_get_blog_id( 'community' );
	if ( '' === $slug || $community_blog_id <= 0 ) {
		return $empty;
	}

	switch_to_blog( $community_blog_id );
	try {
		if ( ! taxonomy_exists( 'artist' ) ) {
			return $empty;
		}

		$term = get_term_by( 'slug', $slug, 'artist' );
		if ( ! ( $term instanceof WP_Term ) ) {
			return $empty;
		}

		$url = get_term_link( $term );
		if ( is_wp_error( $url ) || '' === (string) $url ) {
			return $empty;
		}

		$query = new WP_Query(
			array(
				'post_type'              => bbp_get_topic_post_type(),
				'post_status'            => bbp_get_public_status_id(),
				'posts_per_page'         => EXTRACHILL_COMMUNITY_ARTIST_TOPIC_LIMIT,
				'orderby'                => 'meta_value',
				'meta_key'               => '_bbp_last_active_time', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_type'              => 'DATETIME',
				'order'                  => 'DESC',
				'ignore_sticky_posts'    => true,
				'no_found_rows'          => true,
				'update_post_term_cache' => true,
				'update_post_meta_cache' => true,
				'tax_query'              => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					array(
						'taxonomy' => 'artist',
						'field'    => 'term_id',
						'terms'    => (int) $term->term_id,
					),
				),
			)
		);

		$memo[ $key ] = array(
			'blog_id'   => $community_blog_id,
			'url'       => (string) $url,
			'name'      => (string) $term->name,
			'topic_ids' => array_map( 'intval', wp_list_pluck( $query->posts, 'ID' ) ),
		);
	} finally {
		restore_current_blog();
	}

	return $memo[ $key ];
}

/**
 * Hide the section when its profile, dependencies, or real destination fail.
 *
 * @param int $artist_id      Artist profile post ID.
 * @param int $artist_term_id Bound main-blog artist term ID.
 * @return bool
 */
function extrachill_community_artist_discussions_visible( $artist_id, $artist_term_id ) {
	$data = extrachill_community_get_artist_discussions( $artist_id, $artist_term_id );

	return '' !== $data['url'];
}

/**
 * Render the Community discussion destination and recent topic cards.
 *
 * @param int $artist_id      Artist profile post ID.
 * @param int $artist_term_id Bound main-blog artist term ID.
 * @return void
 */
function extrachill_community_render_artist_discussions_section( $artist_id, $artist_term_id ) {
	$data = extrachill_community_get_artist_discussions( $artist_id, $artist_term_id );
	if ( '' === $data['url'] ) {
		return;
	}

	echo '<section class="artist-discussions-section">';
	echo '<h2 class="section-title">' . esc_html__( 'Discussions', 'extra-chill-community' ) . '</h2>';

	if ( ! empty( $data['topic_ids'] ) ) {
		switch_to_blog( $data['blog_id'] );
		try {
			if ( function_exists( 'extrachill_is_activity_feed_card' ) ) {
				extrachill_is_activity_feed_card( true );
			}

			echo '<div class="artist-forum-topics ec-mobile-full-width-panel"><div class="bbp-body">';
			foreach ( $data['topic_ids'] as $topic_id ) {
				$topic = get_post( $topic_id );
				if ( ! ( $topic instanceof WP_Post ) || bbp_get_topic_post_type() !== $topic->post_type ) {
					continue;
				}

				require EXTRACHILL_COMMUNITY_PLUGIN_DIR . 'bbpress/loop-single-topic-card.php';
			}
			echo '</div></div>';
		} finally {
			if ( function_exists( 'extrachill_is_activity_feed_card' ) ) {
				extrachill_is_activity_feed_card( false );
			}
			restore_current_blog();
		}
	} else {
		echo '<p class="artist-discussions-empty">' . esc_html__( 'No discussions yet. Start the conversation in the Community.', 'extra-chill-community' ) . '</p>';
	}

	printf(
		'<div class="artist-discussions-view-all"><a href="%1$s" class="button-3 button-small">%2$s</a></div>',
		esc_url( $data['url'] ),
		esc_html__( 'View artist discussions', 'extra-chill-community' )
	);
	echo '</section>';
}
