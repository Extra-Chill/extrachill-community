<?php
/**
 * User Profile
 *
 * @package bbPress
 * @subpackage Theme
 */

// Exit if accessed directly
defined('ABSPATH') || exit;

do_action('bbp_template_before_user_profile');
?>
	
<div id="bbp-user-profile" class="bbp-user-profile">
	<?php bbp_get_template_part( 'user-details' ); ?>
	
	<?php
	$displayed_user_id = bbp_get_displayed_user_id();
	$current_user_id   = get_current_user_id();
	$is_artist         = get_user_meta( $displayed_user_id, 'user_is_artist', true );
	$is_professional   = get_user_meta( $displayed_user_id, 'user_is_professional', true );
	?>

<?php
// Compute About-card content up front so the card wrapper only renders when
// there is actually something to show (avoids an empty bordered box for users
// with no description and no local scene).
$about_description = bbp_get_displayed_user_field('description');
$about_local_scene = extrachill_community_get_public_local_scene( bbp_get_displayed_user_id() );
$has_about_content = ! empty( $about_description ) || ! empty( $about_local_scene['name'] );
?>
<div class="bbp-user-profile-cards-container"> <?php // Start Flex Grid Container ?>
<?php if ( $has_about_content ) : ?>
<div class="bbp-user-profile-card">
			<?php if ( $about_description ) : ?>
				<h3><?php esc_html_e( 'About', 'extra-chill-community' ); ?></h3>
		<p class="bbp-user-description"><?php echo wp_kses_post( bbp_rel_nofollow( $about_description ) ); ?></p>
			<?php endif; ?>

			<?php if ( ! empty( $about_local_scene['name'] ) ) : ?>
		<p class="bbp-user-local-scene-inline"><strong><?php esc_html_e( 'Local Scene:', 'extra-chill-community' ); ?></strong> <?php echo esc_html( $about_local_scene['name'] ); ?></p>
			<?php endif; ?>
</div>
<?php endif; // End has_about_content ?>
			<?php do_action( 'bbp_template_before_user_details_menu_items' ); ?>
<?php
// The former "Community Activity" card is gone: its unique data (join date,
// thread/reply sub-page links, cross-site article + blog-comment counts) now
// renders as the compact meta line in the profile hero — see
// extrachill_display_profile_meta_line() in inc/user-profiles/custom-user-profile.php.
?>
<?php
// Wrap the entire conditional artist section in a card
// Check if the user is marked as an artist or professional
if ( $is_artist || $is_professional ) :
	// Use canonical function from extrachill-users plugin
	$user_artist_ids  = function_exists('ec_get_artists_for_user') ? ec_get_artists_for_user( bbp_get_displayed_user_id() ) : array();
	$can_manage_card  = ( bbp_get_displayed_user_id() === get_current_user_id() || current_user_can( 'manage_options' ) );

	// A visitor viewing an artist with no artist profiles gets nothing useful
	// from this card (no list, no actions) — skip it entirely.
	if ( empty( $user_artist_ids ) && ! $can_manage_card ) :
		// No card.
	else :
		$display_name = bbp_get_displayed_user_field('display_name');
		if ( ! empty( $user_artist_ids ) ) {
			/* translators: %s: user display name */
			$artist_card_heading = sprintf( __( "%s's Artists", 'extra-chill-community' ), $display_name );
		} else {
			// Own profile (or admin view) with no bands yet.
			$artist_card_heading = __( 'Your Artist Profile & Link Page', 'extra-chill-community' );
		}
	?>
	<div class="bbp-user-profile-card user-artist-cards-fullwidth">
		<h2><?php echo esc_html( $artist_card_heading ); ?></h2>
		<?php if ( ! empty($user_artist_ids) ) : ?>
			<ul class="user-artist-list">
				<?php
				$artist_blog_id = function_exists( 'ec_get_blog_id' ) ? ec_get_blog_id( 'artist' ) : null;
				if ( $artist_blog_id ) {
					switch_to_blog( $artist_blog_id ); // Switch to artist.extrachill.com for post data
				}
				foreach ( $user_artist_ids as $user_artist_id ) :
					$artist_post = get_post( $user_artist_id );

					if ( $artist_post ) :
						$artist_url = ec_get_site_url( 'artist' ) . '/' . $artist_post->post_name . '/';
						?>
					<li class="user-artist-item">
						<a href="<?php echo esc_url( $artist_url ); ?>" class="button-3 button-small">
							<?php echo esc_html( $artist_post->post_title ); ?>
						</a>
					</li>
						<?php
					endif;
				endforeach;
				restore_current_blog();
				?>
			</ul>
		<?php else : ?>
			<p><?php esc_html_e( 'No artist profiles yet.', 'extra-chill-community' ); ?></p>
		<?php endif; ?>

		<?php
		// Management buttons - only show if viewing own profile or user is admin
		if ( $can_manage_card ) :
			$artist_count                 = is_array( $user_artist_ids ) ? count( $user_artist_ids ) : 0;
			$base_manage_artists_url_card = ec_get_site_url( 'artist' ) . '/manage-artist/';

			echo '<div class="user-artist-management-actions">';

			if ( $artist_count > 0 ) :
				$artist_label = 1 === $artist_count
					? __( 'Manage Artist', 'extra-chill-community' )
					: __( 'Manage Artists', 'extra-chill-community' );
				?>
				<a href="<?php echo esc_url( $base_manage_artists_url_card ); ?>" class="button-1 button-small"><?php echo esc_html( $artist_label ); ?></a>
			<?php else : // No artist profiles, but user can create ?>
				<a href="<?php echo esc_url( ec_get_site_url( 'artist' ) . '/create-artist/' ); ?>" class="button-1 button-small"><?php esc_html_e( 'Create Artist Profile', 'extra-chill-community' ); ?></a>
				<?php
			endif;

			echo '</div>'; // End .user-artist-management-actions
		endif; // End permission check
		?>
	</div>
	<?php endif; // End has-artists-or-can-manage check ?>
<?php endif; // End if user_is_artist or user_is_professional ?>

</div> <?php // End .bbp-user-profile-cards-container (Flex Grid) ?>

</div><!-- #bbp-user-profile -->

<?php do_action('bbp_template_after_user_profile'); ?>
