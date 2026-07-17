<?php
/**
 * Focused tests for the Community-owned artist profile discussion section.
 *
 * Run: php tests/test-artist-profile-discussions.php
 */

define( 'ABSPATH', __DIR__ );

class WP_Term {
	public $term_id;
	public $slug;
	public $name;

	public function __construct( $term_id, $slug, $name ) {
		$this->term_id = $term_id;
		$this->slug    = $slug;
		$this->name    = $name;
	}
}

class WP_Post {
	public $ID;
	public $post_type = 'topic';

	public function __construct( $id ) {
		$this->ID = $id;
	}
}

class WP_Query {
	public $posts;

	public function __construct( $args ) {
		$GLOBALS['_test_query_args'][] = $args;
		$this->posts                   = array_map( static fn( $id ) => new WP_Post( $id ), $GLOBALS['_test_topic_ids'] );
	}
}

$GLOBALS['_test_blog_id']    = 4;
$GLOBALS['_test_query_args'] = array();
$GLOBALS['_test_topic_ids']  = array( 91, 82, 73, 64 );
$GLOBALS['_test_filters']    = array();

function add_filter( $hook, $callback, $priority, $accepted_args ) {
	$GLOBALS['_test_filters'][] = compact( 'hook', 'callback', 'priority', 'accepted_args' );
}

function add_action() {}

function __( $text ) {
	return $text;
}

function esc_html__( $text ) {
	return htmlspecialchars( $text, ENT_QUOTES );
}

function esc_url( $url ) {
	return htmlspecialchars( $url, ENT_QUOTES );
}

function is_singular( $post_type ) {
	return 'artist_profile' === $post_type;
}

function get_post_type( $post_id ) {
	return 700 === $post_id ? 'artist_profile' : 'post';
}

function ec_get_blog_id( $key ) {
	return array( 'main' => 1, 'community' => 2 )[ $key ] ?? 0;
}

function switch_to_blog( $blog_id ) {
	$GLOBALS['_test_blog_stack'][] = $GLOBALS['_test_blog_id'];
	$GLOBALS['_test_blog_id']      = (int) $blog_id;
}

function restore_current_blog() {
	$GLOBALS['_test_blog_id'] = array_pop( $GLOBALS['_test_blog_stack'] );
}

function taxonomy_exists( $taxonomy ) {
	return 'artist' === $taxonomy;
}

function get_term( $term_id, $taxonomy ) {
	if ( 1 !== $GLOBALS['_test_blog_id'] || 'artist' !== $taxonomy ) {
		return false;
	}

	if ( 55 === $term_id ) {
		return new WP_Term( 55, 'kid-lake', 'Kid Lake' );
	}

	return 56 === $term_id ? new WP_Term( 56, 'quiet-artist', 'Quiet Artist' ) : false;
}

function get_term_by( $field, $slug, $taxonomy ) {
	if ( 2 !== $GLOBALS['_test_blog_id'] || 'slug' !== $field || 'artist' !== $taxonomy ) {
		return false;
	}

	if ( 'kid-lake' === $slug ) {
		return new WP_Term( 155, 'kid-lake', 'Kid Lake' );
	}

	return 'quiet-artist' === $slug ? new WP_Term( 156, 'quiet-artist', 'Quiet Artist' ) : false;
}

function get_term_link( $term ) {
	return 'https://community.extrachill.com/artist/' . $term->slug . '/?ref="unsafe';
}

function is_wp_error() {
	return false;
}

function bbp_get_topic_post_type() {
	return 'topic';
}

function bbp_get_public_status_id() {
	return 'publish';
}

function wp_list_pluck( $posts, $field ) {
	return array_map( static fn( $post ) => $post->{$field}, $posts );
}

require dirname( __DIR__ ) . '/inc/core/artist-profile-discussions.php';

$failures = 0;

function check( $label, $condition ) {
	global $failures;
	if ( $condition ) {
		echo "PASS: $label\n";
		return;
	}

	echo "FAIL: $label\n";
	++$failures;
}

check( 'Community registers through the profile section seam with all arguments', array( 'hook' => 'ec_artist_profile_sections', 'callback' => 'extrachill_community_register_artist_discussions_section', 'priority' => 10, 'accepted_args' => 3 ) === $GLOBALS['_test_filters'][0] );

$sections = extrachill_community_register_artist_discussions_section( array(), 700, 55 );
check( 'section is Community-owned and ordered after existing profile sections', 'discussions' === $sections[0]['id'] && 50 === $sections[0]['priority'] );

$data = extrachill_community_get_artist_discussions( 700, 55 );
check( 'bound main term resolves to the Community term and canonical archive', 2 === $data['blog_id'] && false !== strpos( $data['url'], '/artist/kid-lake/' ) );
check( 'recent topic output is bounded to four IDs', array( 91, 82, 73, 64 ) === $data['topic_ids'] && 4 === $GLOBALS['_test_query_args'][0]['posts_per_page'] );
check( 'query uses Community term ID instead of the main-blog term ID', 155 === $GLOBALS['_test_query_args'][0]['tax_query'][0]['terms'] );
check( 'query is cheap and recent-activity ordered', true === $GLOBALS['_test_query_args'][0]['no_found_rows'] && '_bbp_last_active_time' === $GLOBALS['_test_query_args'][0]['meta_key'] );
check( 'cross-blog work restores the artist blog context', 4 === $GLOBALS['_test_blog_id'] );
check( 'invalid profile dependency fails closed', false === extrachill_community_artist_discussions_visible( 701, 55 ) );

$GLOBALS['_test_topic_ids'] = array();
$empty_data                 = extrachill_community_get_artist_discussions( 700, 56 );
check( 'real destination remains available when no recent topics exist', false !== strpos( $empty_data['url'], '/artist/quiet-artist/' ) && array() === $empty_data['topic_ids'] );

ob_start();
extrachill_community_render_artist_discussions_section( 700, 56 );
$rendered = ob_get_clean();
check( 'empty topic state points to the real Community archive', false !== strpos( $rendered, 'No discussions yet.' ) && false !== strpos( $rendered, 'View artist discussions' ) );
check( 'destination output is escaped', false !== strpos( $rendered, '?ref=&quot;unsafe' ) && false === strpos( $rendered, '?ref="unsafe' ) );

$missing_data = extrachill_community_get_artist_discussions( 700, 57 );
check( 'missing bound term fails closed', '' === $missing_data['url'] && array() === $missing_data['topic_ids'] );

if ( $failures > 0 ) {
	exit( 1 );
}

echo "All artist profile discussion tests passed.\n";
exit( 0 );
