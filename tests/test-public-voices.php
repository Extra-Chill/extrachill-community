<?php
/** Standalone regression coverage for accountable managed public voices. */

namespace AgentsAPI\AI {
	class WP_Agent_Execution_Principal {
		public $acting_user_id;
		public $effective_agent_id;
		public $capability_ceiling;

		public function __construct( $acting_user_id, $effective_agent_id = '__wordpress_user__', $capability_ceiling = null ) {
			$this->acting_user_id     = $acting_user_id;
			$this->effective_agent_id = $effective_agent_id;
			$this->capability_ceiling = $capability_ceiling;
		}
	}
}

namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	define( 'EXTRACHILL_COMMUNITY_PUBLIC_VOICE_META', '_extrachill_public_voice' );
	define( 'EXTRACHILL_COMMUNITY_AUTOMATED_META', '_extrachill_automated_agent' );

	class WP_Post {
		public function __construct( array $data ) {
			foreach ( $data as $key => $value ) {
				$this->$key = $value;
			}
		}
	}

	class WP_Error {
		private $code;
		private $message;

		public function __construct( $code, $message = '' ) {
			$this->code    = $code;
			$this->message = $message;
		}

		public function get_error_code() {
			return $this->code;
		}

		public function get_error_message() {
			return $this->message;
		}
	}

	class WP_Agent_Access {
		public static function get_current_principal() {
			return $GLOBALS['_voice_principal'];
		}
	}

	$GLOBALS['_voice_actions']      = array();
	$GLOBALS['_voice_blog']         = 1;
	$GLOBALS['_voice_blog_stack']   = array();
	$GLOBALS['_voice_caps']         = array();
	$GLOBALS['_voice_cross_calls']  = array();
	$GLOBALS['_voice_current_user'] = 7;
	$GLOBALS['_voice_errors']       = array();
	$GLOBALS['_voice_meta']         = array();
	$GLOBALS['_voice_principal']    = new AgentsAPI\AI\WP_Agent_Execution_Principal( 7 );
	$GLOBALS['_voice_venue_mode']   = 'active';
	$GLOBALS['_voice_artist_active'] = true;
	$GLOBALS['_voice_posts']        = array();

	function voice_reset() {
		$GLOBALS['_voice_actions']       = array();
		$GLOBALS['_voice_blog']          = 1;
		$GLOBALS['_voice_blog_stack']    = array();
		$GLOBALS['_voice_caps']          = array(
			7 => array(
				'publish_topics' => true,
				'publish_replies' => true,
				'edit_topic' => true,
				'edit_reply' => true,
				'edit_others_topics' => true,
				'edit_others_replies' => true,
			),
			8 => array( 'edit_topic' => true, 'edit_reply' => true ),
		);
		$GLOBALS['_voice_cross_calls']   = array();
		$GLOBALS['_voice_current_user']  = 7;
		$GLOBALS['_voice_errors']        = array();
		$GLOBALS['_voice_meta']          = array();
		$GLOBALS['_voice_principal']     = new AgentsAPI\AI\WP_Agent_Execution_Principal( 7 );
		$GLOBALS['_voice_venue_mode']    = 'active';
		$GLOBALS['_voice_artist_active'] = true;
		$GLOBALS['_voice_posts']         = array(
			10 => new WP_Post( array( 'ID' => 10, 'post_type' => 'forum', 'post_status' => 'publish', 'post_author' => 7, 'post_parent' => 0, 'post_title' => 'Room', 'post_content' => '' ) ),
			20 => new WP_Post( array( 'ID' => 20, 'post_type' => 'topic', 'post_status' => 'publish', 'post_author' => 7, 'post_parent' => 10, 'post_title' => 'Topic', 'post_content' => 'Original', 'post_date_gmt' => '2026-07-28 00:00:00', 'post_modified_gmt' => '2026-07-28 00:00:00' ) ),
			21 => new WP_Post( array( 'ID' => 21, 'post_type' => 'reply', 'post_status' => 'publish', 'post_author' => 7, 'post_parent' => 20, 'post_title' => '', 'post_content' => 'Reply', 'post_date_gmt' => '2026-07-28 00:00:00', 'post_modified_gmt' => '2026-07-28 00:00:00' ) ),
			101 => new WP_Post( array( 'ID' => 101, 'post_type' => 'artist_profile', 'post_status' => 'publish', 'post_author' => 7, 'post_parent' => 0, 'post_title' => 'Human Resources', 'post_content' => '' ) ),
		);
		unset( $GLOBALS['extrachill_community_pending_public_voice'] );
		$_POST = array();
	}

	function voice_assert( $condition, $message ) {
		if ( ! $condition ) {
			fwrite( STDERR, "FAIL: {$message}\n" );
			exit( 1 );
		}
	}

	function add_action( $hook, $callback ) {
		$GLOBALS['_voice_actions'][ $hook ][] = $callback;
	}
	function add_filter( $hook, $callback ) {
		$GLOBALS['_voice_actions'][ $hook ][] = $callback;
	}
	function __( $value ) { return $value; }
	function esc_html__( $value ) { return $value; }
	function esc_attr__( $value ) { return $value; }
	function esc_html_e( $value ) { echo htmlspecialchars( $value, ENT_QUOTES ); }
	function esc_attr_e( $value ) { echo htmlspecialchars( $value, ENT_QUOTES ); }
	function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES ); }
	function esc_attr( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES ); }
	function esc_url( $value ) { return (string) $value; }
	function esc_url_raw( $value ) { return preg_match( '#^https://#', (string) $value ) ? (string) $value : ''; }
	function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
	function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) ); }
	function wp_strip_all_tags( $value ) { return strip_tags( (string) $value ); }
	function wp_unslash( $value ) { return $value; }
	function wp_kses_post( $value ) { return $value; }
	function selected( $left, $right ) { if ( (string) $left === (string) $right ) { echo 'selected="selected"'; } }
	function is_wp_error( $value ) { return $value instanceof WP_Error; }
	function absint( $value ) { return abs( (int) $value ); }
	function get_current_user_id() { return $GLOBALS['_voice_current_user']; }
	function get_current_blog_id() { return $GLOBALS['_voice_blog']; }
	function switch_to_blog( $blog_id ) { $GLOBALS['_voice_blog_stack'][] = $GLOBALS['_voice_blog']; $GLOBALS['_voice_blog'] = (int) $blog_id; }
	function restore_current_blog() { $GLOBALS['_voice_blog'] = array_pop( $GLOBALS['_voice_blog_stack'] ); }
	function ec_get_blog_id( $site = '' ) { return 'artist' === $site ? 4 : 2; }
	function ec_get_artists_for_user( $user_id ) { return 7 === (int) $user_id && $GLOBALS['_voice_artist_active'] ? array( 101 ) : array(); }
	function ec_user_can( $capability, $context = array() ) { return 'manage_artist' === $capability && 7 === (int) ( $context['user_id'] ?? 0 ) && 101 === (int) ( $context['artist_id'] ?? 0 ) && $GLOBALS['_voice_artist_active']; }
	function get_post( $post_id ) { return isset( $GLOBALS['_voice_posts'][ $post_id ] ) ? clone $GLOBALS['_voice_posts'][ $post_id ] : null; }
	function get_post_field( $field, $post_id ) { return $GLOBALS['_voice_posts'][ $post_id ]->$field ?? ''; }
	function get_permalink( $post ) { $id = is_object( $post ) ? $post->ID : $post; return 101 === (int) $id ? 'https://artist.example/human-resources/' : 'https://community.example/post/' . (int) $id; }
	function get_userdata( $user_id ) { return in_array( (int) $user_id, array( 7, 8 ), true ) ? (object) array( 'ID' => (int) $user_id, 'display_name' => 7 === (int) $user_id ? 'Chris' : 'Moderator' ) : false; }
	function get_author_posts_url( $user_id ) { return 'https://community.example/author/' . (int) $user_id; }
	function bbp_get_user_profile_url( $user_id ) { return 'https://community.example/members/' . (int) $user_id; }
	function get_post_meta( $post_id, $key, $single = false ) { return $GLOBALS['_voice_meta'][ $post_id ][ $key ] ?? ''; }
	function update_post_meta( $post_id, $key, $value ) { $GLOBALS['_voice_meta'][ $post_id ][ $key ] = $value; return true; }
	function delete_post_meta( $post_id, $key ) { unset( $GLOBALS['_voice_meta'][ $post_id ][ $key ] ); return true; }

	function ec_cross_site_rest_request() {
		$args = func_get_args();
		$GLOBALS['_voice_cross_calls'][] = $args;
		if ( '/wp-abilities/v1/abilities/extrachill/get-managed-venue-voices/run' === $args[2] ) {
			if ( 'failure' === $GLOBALS['_voice_venue_mode'] ) {
				return new WP_Error( 'events_unavailable', 'Events unavailable.' );
			}
			if ( 'revoked' === $GLOBALS['_voice_venue_mode'] ) {
				return array( 'voices' => array() );
			}
			return array(
				'voices' => array(
					array(
						'reference' => 'venue:55',
						'term_id' => 55,
						'name' => 'The Royal American',
						'slug' => 'the-royal-american',
						'url' => 'https://events.example/venue/the-royal-american/',
						'description' => 'Public description only.',
					),
				),
			);
		}
		if ( '/wp/v2/venue/55' === $args[2] ) {
			return array( 'id' => 55, 'name' => 'The Royal American', 'link' => 'https://events.example/venue/the-royal-american/' );
		}
		return new WP_Error( 'unknown_route', 'Unknown route.' );
	}

	function user_can( $user_id, $capability ) { return ! empty( $GLOBALS['_voice_caps'][ $user_id ][ $capability ] ); }
	function bbp_add_error( $code, $message ) { $GLOBALS['_voice_errors'][ $code ] = $message; }
	function bbp_is_topic_edit() { return false; }
	function bbp_is_reply_edit() { return false; }
	function bbp_get_topic_id() { return 20; }
	function bbp_get_reply_id() { return 21; }
	function bbp_get_forum_post_type() { return 'forum'; }
	function bbp_get_topic_post_type() { return 'topic'; }
	function bbp_get_reply_post_type() { return 'reply'; }
	function bbp_get_public_status_id() { return 'publish'; }
	function bbp_get_topic_forum_id() { return 10; }
	function bbp_get_reply_topic_id() { return 20; }
	function bbp_get_reply_forum_id() { return 10; }
	function bbp_get_reply_to() { return 0; }
	function bbp_get_topic_permalink( $post_id ) { return 'https://community.example/topic/' . (int) $post_id; }
	function bbp_get_reply_url( $post_id ) { return 'https://community.example/reply/' . (int) $post_id; }
	function bbp_insert_topic( $data ) { $GLOBALS['_voice_posts'][30] = new WP_Post( array_merge( array( 'ID' => 30, 'post_modified_gmt' => '2026-07-28 00:00:00' ), $data ) ); return 30; }
	function bbp_insert_reply( $data ) { $GLOBALS['_voice_posts'][40] = new WP_Post( array_merge( array( 'ID' => 40, 'post_title' => '', 'post_modified_gmt' => '2026-07-28 00:00:00' ), $data ) ); return 40; }
	function wp_update_post( $data ) { foreach ( $data as $key => $value ) { if ( 'ID' !== $key ) { $GLOBALS['_voice_posts'][ $data['ID'] ]->$key = $value; } } return $data['ID']; }
	function mysql_to_rfc3339() { return '2026-07-28T00:00:00+00:00'; }
	function do_action( $hook ) { $GLOBALS['_voice_actions'][ $hook ][] = func_get_args(); }
	function extrachill_community_maybe_convert_markdown( $content ) { return $content; }

	voice_reset();
	require __DIR__ . '/../inc/content/public-voices.php';
	require __DIR__ . '/../inc/core/ability-helpers.php';
	require __DIR__ . '/../inc/content/topic-reply-write.php';

	$artists = extrachill_community_get_managed_artist_voices( 7 );
	voice_assert( isset( $artists['artist:101'] ) && 'Human Resources' === $artists['artist:101']['name'], 'Artist discovery must use the canonical managed profile.' );
	voice_assert( 1 === get_current_blog_id() && array() === $GLOBALS['_voice_blog_stack'], 'Artist discovery must restore multisite context.' );

	$venues = extrachill_community_get_managed_venue_voices();
	voice_assert( isset( $venues['venue:55'] ), 'Venue discovery must consume the direct voices envelope.' );
	voice_assert( array( 'events', 'GET', '/wp-abilities/v1/abilities/extrachill/get-managed-venue-voices/run' ) === $GLOBALS['_voice_cross_calls'][0], 'Venue discovery must use the exact zero-input Events ability call.' );

	voice_assert( 'invalid_public_voice' === extrachill_community_authorize_public_voice( 'venue:55<script>', 7 )->get_error_code(), 'Malformed voice references must fail closed.' );
	$GLOBALS['_voice_venue_mode'] = 'revoked';
	voice_assert( 'public_voice_not_managed' === extrachill_community_authorize_public_voice( 'venue:55', 7 )->get_error_code(), 'Revoked venue authority must fail closed.' );
	$GLOBALS['_voice_venue_mode'] = 'failure';
	voice_assert( 'events_unavailable' === extrachill_community_authorize_public_voice( 'venue:55', 7 )->get_error_code(), 'Cross-site venue failures must propagate without granting authority.' );

	voice_reset();
	$topic_result = extrachill_community_ability_create_topic( array( 'forum_id' => 10, 'title' => 'Artist news', 'content' => 'News', 'public_voice' => 'artist:101' ) );
	voice_assert( 7 === $topic_result['author_id'] && 7 === $GLOBALS['_voice_posts'][30]->post_author, 'Ability topic creation must retain the accountable human author.' );
	voice_assert( 'artist:101' === $GLOBALS['_voice_meta'][30][ EXTRACHILL_COMMUNITY_PUBLIC_VOICE_META ], 'Ability topic creation must store only the canonical reference.' );
	voice_assert( 7 === $topic_result['public_voice']['accountable_user_id'], 'Ability output must disclose the accountable human separately.' );

	$reply_result = extrachill_community_ability_create_reply( array( 'topic_id' => 20, 'content' => 'Venue update', 'public_voice' => 'venue:55' ) );
	voice_assert( 7 === $reply_result['author_id'] && 'venue:55' === $GLOBALS['_voice_meta'][40][ EXTRACHILL_COMMUNITY_PUBLIC_VOICE_META ], 'Ability reply creation must support an authorized venue without changing post_author.' );

	$GLOBALS['_voice_venue_mode'] = 'failure';
	$failed_reply = extrachill_community_ability_create_reply( array( 'topic_id' => 20, 'content' => 'No write', 'public_voice' => 'venue:55' ) );
	voice_assert( is_wp_error( $failed_reply ) && 'events_unavailable' === $failed_reply->get_error_code(), 'Ability create must fail before writing when venue authority is unavailable.' );

	voice_reset();
	update_post_meta( 20, EXTRACHILL_COMMUNITY_PUBLIC_VOICE_META, 'venue:55' );
	$GLOBALS['_voice_venue_mode'] = 'revoked';
	extrachill_community_ability_update_topic( array( 'topic_id' => 20, 'content' => 'Safe edit' ) );
	voice_assert( '' === get_post_meta( 20, EXTRACHILL_COMMUNITY_PUBLIC_VOICE_META, true ), 'An author edit must clear a voice whose authority was revoked.' );

	voice_reset();
	update_post_meta( 21, EXTRACHILL_COMMUNITY_PUBLIC_VOICE_META, 'artist:101' );
	$GLOBALS['_voice_current_user'] = 8;
	$GLOBALS['_voice_principal']    = new AgentsAPI\AI\WP_Agent_Execution_Principal( 8 );
	$moderation = extrachill_community_ability_update_reply( array( 'reply_id' => 21, 'content' => 'Moderated' ) );
	voice_assert( ! is_wp_error( $moderation ) && 'artist:101' === get_post_meta( 21, EXTRACHILL_COMMUNITY_PUBLIC_VOICE_META, true ), 'Moderation edits must preserve another human author and unchanged voice.' );
	$spoof = extrachill_community_ability_update_reply( array( 'reply_id' => 21, 'content' => 'Spoof', 'public_voice' => 'venue:55' ) );
	voice_assert( 'public_voice_author_mismatch' === $spoof->get_error_code(), 'Moderators must not adopt a voice for another accountable author.' );

	voice_reset();
	update_post_meta( 20, EXTRACHILL_COMMUNITY_PUBLIC_VOICE_META, 'artist:101' );
	extrachill_community_ability_update_topic( array( 'topic_id' => 20, 'content' => 'Reassigned', 'user_id' => 8 ) );
	voice_assert( 8 === $GLOBALS['_voice_posts'][20]->post_author && '' === get_post_meta( 20, EXTRACHILL_COMMUNITY_PUBLIC_VOICE_META, true ), 'Author reassignment must clear rather than transfer entity authority.' );

	voice_reset();
	$GLOBALS['_voice_principal'] = new AgentsAPI\AI\WP_Agent_Execution_Principal( 7, 'extra-chill-bot' );
	$automated = extrachill_community_ability_create_topic( array( 'forum_id' => 10, 'title' => 'Automated', 'content' => 'Agent-assisted', 'public_voice' => 'artist:101' ) );
	voice_assert( 7 === $automated['author_id'] && 'extra-chill-bot' === get_post_meta( 30, EXTRACHILL_COMMUNITY_AUTOMATED_META, true ), 'Trusted agent execution must preserve bounded disclosure while keeping human ownership.' );
	voice_assert( true === $automated['public_voice']['automated'], 'Ability formatting must expose automated disclosure without agent authority.' );

	ob_start();
	$rendered = extrachill_community_render_post_public_voice( 30 );
	$html     = ob_get_clean();
	voice_assert( $rendered && false !== strpos( $html, 'Human Resources' ) && false !== strpos( $html, 'Posted by' ) && false !== strpos( $html, 'Chris' ) && false !== strpos( $html, 'Automated contribution' ), 'Canonical rendering must include entity, accountable human, and automation disclosure.' );
	voice_assert( 'Chris' === extrachill_community_filter_public_voice_author_name( 'Chris', 30 ), 'Notification and non-render author identity must remain human.' );
	extrachill_community_begin_public_voice_render();
	voice_assert( 'Human Resources' === extrachill_community_filter_public_voice_author_name( 'Chris', 30 ), 'Public render scopes must substitute the canonical identity.' );
	extrachill_community_end_public_voice_render();
	voice_assert( empty( $GLOBALS['extrachill_community_public_voice_render_depth'] ), 'Public render scopes must close without leaking into later hooks.' );

	voice_reset();
	$GLOBALS['_voice_venue_mode'] = 'failure';
	ob_start();
	extrachill_community_render_public_voice_selector( 'topic' );
	$form_html = ob_get_clean();
	voice_assert( false !== strpos( $form_html, 'Publish as' ) && false !== strpos( $form_html, 'Myself' ) && false !== strpos( $form_html, 'Managed venues could not be loaded' ) && false !== strpos( $form_html, 'aria-live="polite"' ), 'Native forms must retain self/artist options and expose an accessible venue error state.' );
	$GLOBALS['_voice_venue_mode'] = 'revoked';
	ob_start();
	extrachill_community_render_public_voice_selector( 'reply' );
	$empty_html = ob_get_clean();
	voice_assert( false !== strpos( $empty_html, 'No managed venue voices are available.' ), 'Native forms must expose the empty venue state.' );

	$_POST = array( 'bbp_public_voice' => 'venue:999' );
	extrachill_community_validate_native_public_voice();
	voice_assert( isset( $GLOBALS['_voice_errors']['bbp_public_voice_not_managed'] ), 'Native forms must reject unmanaged or spoofed voice input.' );

	$ability_source = file_get_contents( __DIR__ . '/../inc/content/topic-reply-abilities.php' );
	$voice_source   = file_get_contents( __DIR__ . '/../inc/content/public-voices.php' );
	voice_assert( 4 === substr_count( $ability_source, "'public_voice' => extrachill_community_public_voice_input_schema()" ), 'Create/update topic and reply abilities must share the bounded voice input.' );
	voice_assert( false !== strpos( $voice_source, "add_filter( 'bbp_get_topic_author_display_name'" ) && false !== strpos( $voice_source, "add_filter( 'bbp_get_reply_author_display_name'" ) && false !== strpos( $voice_source, "add_filter( 'bbp_get_topic_author_link'" ) && false !== strpos( $voice_source, "add_filter( 'bbp_get_reply_author_link'" ), 'Topic lead, reply/activity cards, and freshness must use the same native bbPress identity filters.' );
	voice_assert( false === strpos( $voice_source, '_bbp_anonymous_' ), 'Public voices must never use bbPress anonymous identity metadata.' );

	echo "PASS: Managed public voices preserve human accountability across native forms, abilities, rendering, edits, moderation, and automation.\n";
}
