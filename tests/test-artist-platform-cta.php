<?php
/**
 * Focused render and responsive-style tests for the Artist Platform CTA.
 *
 * Run: php tests/test-artist-platform-cta.php
 */

define( 'ABSPATH', __DIR__ );

$GLOBALS['_test_actions'] = array();

function add_action( $tag, $callback ) {
	$GLOBALS['_test_actions'][ $tag ][] = $callback;
}

function ec_get_site_url( $site ) {
	return 'artist' === $site ? 'https://artist.extrachill.com' : '';
}

function esc_url( $url ) {
	return htmlspecialchars( $url, ENT_QUOTES );
}

function esc_html_e( $text ) {
	echo htmlspecialchars( $text, ENT_QUOTES );
}

require dirname( __DIR__ ) . '/inc/home/artist-platform-buttons.php';

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

ob_start();
ec_community_add_artist_platform_buttons();
$markup = ob_get_clean();
$css    = file_get_contents( dirname( __DIR__ ) . '/inc/assets/css/home.css' );

preg_match( '/class="([^"]*button-2[^"]*)"/', $markup, $button_classes );
preg_match( '/\.artist-platform-homepage-actions\s*\{([^}]*)\}/s', $css, $desktop_rule );
preg_match( '/@media\s*\(max-width:\s*768px\)\s*\{(.*)\}\s*$/s', $css, $mobile_rules );

check( 'CTA renders the shared secondary button class', ! empty( $button_classes ) );
check( 'CTA keeps the shared medium button size', false !== strpos( $button_classes[1] ?? '', 'button-medium' ) );
check( 'desktop container remains a centered flex row', false !== strpos( $desktop_rule[1] ?? '', 'display: flex' ) && false !== strpos( $desktop_rule[1] ?? '', 'justify-content: center' ) );
check( 'desktop rule does not apply mobile width constraints', false === strpos( $desktop_rule[1] ?? '', 'width:' ) );
check( 'mobile viewport targets the rendered secondary button', false !== strpos( $mobile_rules[1] ?? '', '.artist-platform-homepage-actions .button-2' ) );
check( 'mobile viewport applies the intended width constraints', false !== strpos( $mobile_rules[1] ?? '', 'width: 100%' ) && false !== strpos( $mobile_rules[1] ?? '', 'max-width: 300px' ) );

if ( $failures > 0 ) {
	exit( 1 );
}

echo "All Artist Platform CTA tests passed.\n";
exit( 0 );
