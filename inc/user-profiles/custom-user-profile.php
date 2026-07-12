<?php
/**
 * Custom User Profile Display
 *
 * Extends bbPress user profiles with cross-site data aggregation from main site.
 * Uses the canonical blog ID provider to access extrachill.com (main site) post counts and comments.
 *
 * Cross-Site Data:
 * - User post count from main site (displayed as "articles")
 * - User comments from main site blog
 * - Links to author archive: extrachill.com/author/{slug}/
 *
 * Integration: Always uses try/finally pattern with restore_current_blog() for safety.
 *
 * @package ExtraChillCommunity
 */

/**
 * Get the displayed user's main-site published post count.
 *
 * @param int $user_id User ID.
 * @return int
 */
function extrachill_get_main_site_post_count( $user_id ) {
	$main_blog_id = function_exists( 'ec_get_blog_id' ) ? ec_get_blog_id( 'main' ) : null;
	if ( ! $main_blog_id ) {
		return 0;
	}

	switch_to_blog( $main_blog_id );
	try {
		$post_count = (int) count_user_posts( $user_id, 'post', true );
	} finally {
		restore_current_blog();
	}

	return $post_count;
}

/**
 * Render the profile hero meta line: join date + linked activity counts.
 *
 * Replaces the former "Community Activity" profile card. Its unique data
 * (Joined date, the only links to the topics/replies sub-pages, the
 * cross-site articles + blog-comments counts) now lives compactly in the
 * header: "Joined March 2019 · 12 threads · 148 replies · 37 articles".
 * Zero-count items are simply omitted — no noise.
 */
function extrachill_display_profile_meta_line() {
	$user_id = bbp_get_displayed_user_id();
	if ( ! $user_id ) {
		return;
	}

	$parts = array();

	$join_date = bbp_get_displayed_user_field( 'user_registered' );
	if ( ! empty( $join_date ) ) {
		$parts[] = sprintf(
			/* translators: %s: localized month + year the user registered. */
			esc_html__( 'Joined %s', 'extra-chill-community' ),
			esc_html( date_i18n( 'F Y', strtotime( $join_date ) ) )
		);
	}

	$topic_count = (int) bbp_get_user_topic_count_raw( $user_id );
	if ( $topic_count > 0 ) {
		$parts[] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( bbp_get_user_topics_created_url( $user_id ) ),
			esc_html( sprintf( /* translators: %s: formatted number of threads. */ _n( '%s thread', '%s threads', $topic_count, 'extra-chill-community' ), number_format_i18n( $topic_count ) ) )
		);
	}

	$reply_count = (int) bbp_get_user_reply_count_raw( $user_id );
	if ( $reply_count > 0 ) {
		$parts[] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( bbp_get_user_replies_created_url( $user_id ) ),
			esc_html( sprintf( /* translators: %s: formatted number of replies. */ _n( '%s reply', '%s replies', $reply_count, 'extra-chill-community' ), number_format_i18n( $reply_count ) ) )
		);
	}

	$post_count = extrachill_get_main_site_post_count( $user_id );
	if ( $post_count > 0 && function_exists( 'ec_get_user_author_archive_url' ) ) {
		$author_url = ec_get_user_author_archive_url( $user_id );
		if ( $author_url ) {
			$parts[] = sprintf(
				'<a href="%s">%s</a>',
				esc_url( $author_url ),
				esc_html( sprintf( /* translators: %s: formatted number of articles. */ _n( '%s article', '%s articles', $post_count, 'extra-chill-community' ), number_format_i18n( $post_count ) ) )
			);
		}
	}

	$comment_count = function_exists( 'get_user_main_site_comment_count' ) ? (int) get_user_main_site_comment_count( $user_id ) : 0;
	if ( $comment_count > 0 ) {
		$comments_url = ec_get_site_url( 'community' ) . '/blog-comments?user_id=' . $user_id;
		$parts[]      = sprintf(
			'<a href="%s">%s</a>',
			esc_url( $comments_url ),
			esc_html( sprintf( /* translators: %s: formatted number of blog comments. */ _n( '%s blog comment', '%s blog comments', $comment_count, 'extra-chill-community' ), number_format_i18n( $comment_count ) ) )
		);
	}

	if ( empty( $parts ) ) {
		return;
	}

	echo '<p class="bbp-user-meta-line">' . implode( '<span class="bbp-user-meta-sep" aria-hidden="true"> · </span>', $parts ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Each part is escaped at build time above.
}

/**
 * Display the legacy "Music Fan Details" card.
 *
 * Renders the free-text favorite_artists / top_concerts / top_venues usermeta.
 * This is the pre-My-Shows manual record; it is now demoted below the real
 * Concert History card (see inc/user-profiles/concert-history.php). When the
 * profile owner has legacy top_concerts text, a nudge invites them to convert
 * it into structured tracked shows via My Shows.
 *
 * Rendered on `bbp_template_after_user_profile` (priority 20) so it appears
 * below the profile card grid, under the Concert History card (priority 5).
 * The original `bbp_init` hook fired during init and the echoed markup was
 * discarded, so this card never actually rendered — hooking a template action
 * fixes that.
 */
function display_music_fan_details() {
	$user_id = bbp_get_displayed_user_id();

	// Music Fan Section variables
	$favorite_artists = get_user_meta( $user_id, 'favorite_artists', true );
	$top_concerts     = get_user_meta( $user_id, 'top_concerts', true );
	$top_venues       = get_user_meta( $user_id, 'top_venues', true );

	if ( ! ( $favorite_artists || $top_concerts || $top_venues ) ) {
		return;
	}

	$is_own = ( (int) get_current_user_id() === (int) $user_id );
	?>
	<div class="bbp-user-profile-card ec-music-fan-details-card">
		<h3><?php esc_html_e( 'Music Fan Details', 'extra-chill-community' ); ?></h3>
		<?php if ( $favorite_artists ) : ?>
			<p><strong><?php esc_html_e( 'Favorite Artists:', 'extra-chill-community' ); ?></strong> <?php echo nl2br( esc_html( $favorite_artists ) ); ?></p>
		<?php endif; ?>

		<?php if ( $top_concerts ) : ?>
			<p><strong><?php esc_html_e( 'Top Concerts:', 'extra-chill-community' ); ?></strong> <?php echo nl2br( esc_html( $top_concerts ) ); ?></p>
		<?php endif; ?>

		<?php if ( $top_venues ) : ?>
			<p><strong><?php esc_html_e( 'Top Venues:', 'extra-chill-community' ); ?></strong> <?php echo nl2br( esc_html( $top_venues ) ); ?></p>
		<?php endif; ?>

		<?php
		// These are legacy free-text notes from before My Shows existed.
		// We deliberately do NOT promise to "convert" them: the events
		// catalog is a forward-looking calendar with almost no historic /
		// out-of-market shows, so searching for these past memories mostly
		// finds nothing. Instead, invite the owner to start tracking shows
		// going forward via My Shows — the path that actually works today.
		if ( $is_own && function_exists( 'ec_community_my_shows_url' ) ) :
			$my_shows_url = ec_community_my_shows_url( (int) $user_id );
			if ( $my_shows_url ) :
				?>
				<p class="ec-music-fan-nudge">
					<em><?php esc_html_e( 'These are your personal notes.', 'extra-chill-community' ); ?></em>
					<a href="<?php echo esc_url( $my_shows_url ); ?>"><?php esc_html_e( 'Start tracking shows you attend →', 'extra-chill-community' ); ?></a>
				</p>
				<?php
			endif;
		endif;
		?>
	</div>
	<?php
}

// Render below the profile card grid, after the Concert History card
// (priority 5). Priority 20 keeps it last before the activity feed (99).
add_action( 'bbp_template_after_user_profile', 'display_music_fan_details', 20 );

// Load the function after bbPress is fully loaded
add_action( 'after_setup_theme', 'override_bbp_user_role_after_bbp_load' );

function override_bbp_user_role_after_bbp_load() {
	// Hook into bbPress filter after it's available
	add_filter( 'bbp_get_user_display_role', 'override_bbp_user_forum_role', 10, 2 );
}

function override_bbp_user_forum_role( $role, $user_id ) {
	$custom_title = get_user_meta( $user_id, 'ec_custom_title', true );
	return ! empty( $custom_title ) ? $custom_title : 'Extra Chillian';
}