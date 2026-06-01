<?php
/**
 * Browse Rooms Chip Row
 *
 * Demoted forum navigation for the feed-first homepage. Replaces the
 * 2010-era [bbp-forum-index] directory table with a compact row of room
 * chips. For a low-volume community a directory table advertises emptiness;
 * a chip row keeps browsing available without leading with it.
 *
 * Rooms are pulled DYNAMICALLY from public top-level forums — no hardcoded
 * forum IDs. The consolidated public room set (Music Discussion, Live Shows &
 * Scenes, Artist Corner, The Lab, The Back Bar) are the published, top-level
 * forums; artist sub-forums are children (non-zero parent) and the staff forum
 * is `hidden`, so both are excluded.
 *
 * Note: bbPress registers `hidden` and `private` as forum post statuses, so a
 * forum's visibility lives in its post_status. We restrict the chip query to
 * the bbPress public status only (bbp_get_public_status_id()) and additionally
 * subtract any IDs flagged in the `_bbp_hidden_forums` / `_bbp_private_forums`
 * options as a belt-and-suspenders guard. This filters out any hidden/private
 * forum generically rather than hardcoding the current staff forum ID.
 *
 * Loaded via the extrachill_community_home_after_feed action hook
 * (registered in inc/home/actions.php).
 *
 * @package ExtraChillCommunity
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'extrachill_community_get_room_chips' ) ) {
	/**
	 * Fetch the room forums for the homepage chip row.
	 *
	 * Published, top-level forums ordered by the bbPress menu order so the
	 * room ordering matches the rest of the platform. Filterable for later
	 * phases without touching the template.
	 *
	 * @return WP_Post[]
	 */
	function extrachill_community_get_room_chips() {
		// A forum's visibility lives in its post_status: bbPress registers
		// `hidden` and `private` as forum statuses, so restricting to the public
		// status is what actually excludes the hidden staff forum. (A literal
		// `publish` mostly works but the canonical public id is the safe value.)
		$public_status = function_exists( 'bbp_get_public_status_id' )
			? bbp_get_public_status_id()
			: 'publish';

		// Belt-and-suspenders: also subtract any forum IDs bbPress flags as
		// hidden/private in its options, in case status and option ever diverge.
		$exclude = array();

		if ( function_exists( 'bbp_get_hidden_forum_ids' ) ) {
			$exclude = array_merge( $exclude, bbp_get_hidden_forum_ids() );
		}

		if ( function_exists( 'bbp_get_private_forum_ids' ) ) {
			$exclude = array_merge( $exclude, bbp_get_private_forum_ids() );
		}

		$exclude = array_values( array_unique( array_map( 'absint', $exclude ) ) );

		$query_args = array(
			'post_type'              => bbp_get_forum_post_type(),
			'post_status'            => $public_status,
			'post_parent'            => 0,
			'posts_per_page'         => -1,
			'orderby'                => array(
				'menu_order' => 'ASC',
				'title'      => 'ASC',
			),
			'no_found_rows'          => true,
			'update_post_term_cache' => false,
			'update_post_meta_cache' => false,
		);

		if ( ! empty( $exclude ) ) {
			$query_args['post__not_in'] = $exclude;
		}

		$rooms = get_posts( $query_args );

		/**
		 * Filter the room forums shown as homepage chips.
		 *
		 * @param WP_Post[] $rooms Published top-level forum posts.
		 */
		return apply_filters( 'extrachill_community_room_chips', $rooms );
	}

	/**
	 * Render the Browse rooms chip row.
	 *
	 * Hooked to extrachill_community_home_after_feed.
	 */
	function extrachill_community_render_browse_rooms() {
		if ( ! function_exists( 'bbp_get_forum_post_type' ) ) {
			return;
		}

		$rooms = extrachill_community_get_room_chips();

		if ( empty( $rooms ) ) {
			return;
		}
		?>
		<nav class="community-browse-rooms" aria-labelledby="community-browse-rooms-heading">
			<div class="community-section-header">
				<h2 id="community-browse-rooms-heading"><?php esc_html_e( 'Browse rooms', 'extra-chill-community' ); ?></h2>
			</div>
			<ul class="community-room-chips ec-mobile-full-width-panel">
				<?php foreach ( $rooms as $room ) : ?>
					<li class="community-room-chip">
						<a href="<?php echo esc_url( get_permalink( $room ) ); ?>" class="community-room-chip-link">
							<?php echo esc_html( get_the_title( $room ) ); ?>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		</nav>
		<?php
	}
}
