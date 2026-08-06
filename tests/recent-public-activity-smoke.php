<?php
/** Focused coverage for the bounded recent public activity owner contract. */

define( 'ABSPATH', __DIR__ . '/' );

class WP_Post {
	public function __construct( array $data ) {
		foreach ( $data as $key => $value ) {
			$this->$key = $value;
		}
	}
}

class WP_Query {
	public $posts;

	public function __construct( $args ) {
		$GLOBALS['_recent_activity_query_args'] = $args;
		$this->posts                            = $GLOBALS['_recent_activity_query_posts'];
	}
}

$GLOBALS['_recent_activity_ability']     = null;
$GLOBALS['_recent_activity_query_args']  = array();
$GLOBALS['_recent_activity_query_posts'] = array();
$GLOBALS['_recent_activity_posts']       = array();
$GLOBALS['_recent_activity_terms']       = array();

function add_action() {}
function __( $text ) { return $text; }
function __return_true() { return true; }
function wp_register_ability( $name, $args ) {
	if ( 'extrachill/community-recent-public-activity' === $name ) {
		$GLOBALS['_recent_activity_ability'] = $args;
	}
}
function bbp_get_topic_post_type() { return 'topic'; }
function bbp_get_reply_post_type() { return 'reply'; }
function bbp_get_forum_post_type() { return 'forum'; }
function bbp_get_public_status_id() { return 'publish'; }
function bbp_get_closed_status_id() { return 'closed'; }
function bbp_get_reply_topic_id( $id ) { return (int) ( $GLOBALS['_recent_activity_posts'][ $id ]->topic_id ?? 0 ); }
function bbp_get_topic_forum_id( $id ) { return (int) ( $GLOBALS['_recent_activity_posts'][ $id ]->post_parent ?? 0 ); }
function bbp_get_reply_url( $id ) { return 'https://community.example.test/reply/' . (int) $id; }
function bbp_get_topic_permalink( $id ) { return 'https://community.example.test/topic/' . (int) $id; }
function bbp_get_forum_permalink( $id ) { return 'https://community.example.test/forum/' . (int) $id; }
function get_post( $id ) { return $GLOBALS['_recent_activity_posts'][ $id ] ?? null; }
function get_the_title( $post ) { return $post->post_title; }
function get_userdata( $id ) { return $id ? (object) array( 'ID' => $id, 'display_name' => 'User ' . $id ) : false; }
function extrachill_get_user_profile_url( $id ) { return 'https://community.example.test/members/' . (int) $id; }
function get_post_meta() { return ''; }
function get_the_terms( $id ) { return $GLOBALS['_recent_activity_terms'][ $id ] ?? array(); }
function get_term_link( $term ) { return 'https://community.example.test/artist/' . $term->slug; }
function is_wp_error() { return false; }
function mysql2date( $format, $date ) { return gmdate( $format, strtotime( $date . ' UTC' ) ); }

function recent_activity_post( $id, $type, $status, $parent = 10, $author = 7 ) {
	return new WP_Post(
		array(
			'ID'            => $id,
			'post_type'     => $type,
			'post_status'   => $status,
			'post_parent'   => $parent,
			'post_author'   => $author,
			'post_title'    => ucfirst( $type ) . ' ' . $id,
			'post_name'     => $type . '-' . $id,
			'post_date'     => '2026-08-01 12:00:00',
			'post_date_gmt' => '2026-08-01 12:00:00',
		)
	);
}

function recent_activity_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

require dirname( __DIR__ ) . '/inc/content/recent-public-activity.php';
extrachill_community_register_recent_public_activity_ability();

$ability = $GLOBALS['_recent_activity_ability'];
recent_activity_assert( true === $ability['meta']['show_in_rest'], 'Projection must use the public core ability runner.' );
recent_activity_assert( true === $ability['meta']['annotations']['readonly'], 'Projection must remain read-only.' );
recent_activity_assert( 20 === $ability['input_schema']['properties']['limit']['maximum'], 'Input must declare the strict limit.' );
recent_activity_assert( 20 === $ability['output_schema']['properties']['items']['maxItems'], 'Output must declare the strict limit.' );
recent_activity_assert( false === $ability['output_schema']['additionalProperties'], 'Top-level output must be exact.' );

$forum  = recent_activity_post( 10, 'forum', 'publish', 0 );
$public = recent_activity_post( 20, 'topic', 'publish' );
$closed = recent_activity_post( 21, 'topic', 'closed' );
$reply  = recent_activity_post( 30, 'reply', 'publish' );
$reply->topic_id = 20;
$private = recent_activity_post( 22, 'topic', 'private' );
$draft   = recent_activity_post( 23, 'topic', 'draft' );
$trash   = recent_activity_post( 24, 'topic', 'trash' );
$orphan  = recent_activity_post( 31, 'reply', 'publish' );
$orphan->topic_id = 999;

$GLOBALS['_recent_activity_posts'] = array(
	10 => $forum,
	20 => $public,
	21 => $closed,
	22 => $private,
	23 => $draft,
	24 => $trash,
	30 => $reply,
	31 => $orphan,
);
$GLOBALS['_recent_activity_terms'][20] = array( (object) array( 'name' => 'Test Artist', 'slug' => 'test-artist' ) );
$GLOBALS['_recent_activity_query_posts'] = array( $public, $closed, $reply, $private, $draft, $trash, $orphan );

$result = extrachill_community_ability_recent_public_activity( array( 'limit' => 999 ) );
recent_activity_assert( 3 === count( $result['items'] ), 'Only public, closed-public, and publicly parented reply activity may escape.' );
recent_activity_assert( 'discussion' === $result['items'][1]['activity_type'], 'Closed discussions remain public without exposing their storage status.' );
recent_activity_assert( 'reply' === $result['items'][2]['activity_type'], 'Public replies remain distinguishable without exposing post types.' );
recent_activity_assert( 20 === $GLOBALS['_recent_activity_query_args']['posts_per_page'], 'Runtime must clamp callers to the strict maximum.' );
recent_activity_assert( array( 'schema_version', 'items' ) === array_keys( $result ), 'Projection must expose only version and items at the top level.' );
recent_activity_assert( false === strpos( serialize( $result ), 'post_status' ) && false === strpos( serialize( $result ), 'topic_id' ), 'Projection must not leak storage fields.' );
recent_activity_assert( 'test-artist' === $result['items'][0]['relationships']['artists'][0]['slug'], 'Artist relationships must be portable owner data.' );

$GLOBALS['_recent_activity_query_posts'] = array( $private, $draft, $trash, $orphan );
$empty = extrachill_community_ability_recent_public_activity( array( 'artist_slug' => 'test-artist' ) );
recent_activity_assert( array() === $empty['items'], 'An all-hidden query must return a versioned empty result.' );
recent_activity_assert( 'test-artist' === $GLOBALS['_recent_activity_query_args']['tax_query'][0]['terms'], 'Artist scoping must stay inside the owner query.' );
recent_activity_assert( 'topic' === $GLOBALS['_recent_activity_query_args']['post_type'], 'Artist scoping must query relationship-owning discussions only.' );

echo "PASS: Recent public activity is bounded, exact, and visibility-safe.\n";
