<?php
/**
 * Visibility-filtered public profile helpers.
 *
 * @package ExtraChill\Community
 */

defined( 'ABSPATH' ) || exit;

/**
 * Get a user's public Local Scene through the Users-owned profile contract.
 *
 * @param int $user_id User ID.
 * @return array|null Resolved public Local Scene, or null when private/unavailable.
 */
function extrachill_community_get_public_local_scene( $user_id ) {
	static $scenes = array();

	$user_id = absint( $user_id );
	if ( ! $user_id || ! function_exists( 'wp_get_ability' ) ) {
		return null;
	}

	if ( array_key_exists( $user_id, $scenes ) ) {
		return $scenes[ $user_id ];
	}

	$ability = wp_get_ability( 'extrachill/get-user-profile' );
	if ( ! $ability ) {
		$scenes[ $user_id ] = null;
		return null;
	}

	$profile = $ability->execute( array( 'user_id' => $user_id ) );
	$scene   = ! is_wp_error( $profile ) && is_array( $profile ) && isset( $profile['local_scene'] ) && is_array( $profile['local_scene'] )
		? $profile['local_scene']
		: null;

	$scenes[ $user_id ] = $scene;
	return $scene;
}

/**
 * Display the About card directly under the profile header.
 *
 * Who this person is (bio + local scene) is the first thing a visitor should
 * see — before the heatmap, concert history, and stat cards. Hooked at
 * priority 0 on bbp_template_after_user_details so it renders ahead of the
 * contribution heatmap (priority 1). Skips entirely when the user has no
 * description and no public local scene, so no empty bordered box renders.
 */
function extrachill_community_display_about_card() {
	if ( function_exists( 'bbp_is_single_user_profile' ) && ! bbp_is_single_user_profile() ) {
		return;
	}

	$user_id = bbp_get_displayed_user_id();
	if ( ! $user_id ) {
		return;
	}

	$description = bbp_get_displayed_user_field( 'description' );
	$local_scene = extrachill_community_get_public_local_scene( (int) $user_id );

	if ( empty( $description ) && empty( $local_scene['name'] ) ) {
		return;
	}
	?>
	<div class="bbp-user-profile-card ec-about-card">
		<?php if ( $description ) : ?>
			<h3><?php esc_html_e( 'About', 'extra-chill-community' ); ?></h3>
			<p class="bbp-user-description"><?php echo wp_kses_post( bbp_rel_nofollow( $description ) ); ?></p>
		<?php endif; ?>

		<?php if ( ! empty( $local_scene['name'] ) ) : ?>
			<p class="bbp-user-local-scene-inline"><strong><?php esc_html_e( 'Local Scene:', 'extra-chill-community' ); ?></strong> <?php echo esc_html( $local_scene['name'] ); ?></p>
		<?php endif; ?>
	</div>
	<?php
}

// Priority 0: the About card leads the profile body, ahead of the heatmap (1)
// and Concert History (5).
add_action( 'bbp_template_after_user_details', 'extrachill_community_display_about_card', 0 );
