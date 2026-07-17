<?php
/**
 * Verify missing Artist Platform/network/bbPress dependencies fail closed.
 *
 * Run: php tests/test-artist-profile-discussions-dependency-absent.php
 */

define( 'ABSPATH', __DIR__ );

function add_filter() {}

function add_action() {}

function __( $text ) {
	return $text;
}

function get_post_type() {
	return 'artist_profile';
}

require dirname( __DIR__ ) . '/inc/core/artist-profile-discussions.php';

$data = extrachill_community_get_artist_discussions( 700, 55 );
if ( '' !== $data['url'] || array() !== $data['topic_ids'] || extrachill_community_artist_discussions_visible( 700, 55 ) ) {
	echo "FAIL: dependency-absent artist discussions should fail closed.\n";
	exit( 1 );
}

echo "PASS: dependency-absent artist discussions fail closed.\n";
exit( 0 );
