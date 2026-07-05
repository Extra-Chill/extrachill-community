<?php
/**
 * Contribution Heatmap (GitHub-style activity calendar)
 *
 * Adds a per-day contribution heatmap to bbPress user profiles, modeled on
 * GitHub's contribution calendar. A "contribution" is any topic, reply, or
 * main-site post the user authored on a given calendar day — the same
 * date-bearing sources the rank/points system reads (see
 * inc/social/rank-system/point-calculation.php). Upvotes and filtered
 * extra-points are intentionally excluded: they carry no timestamp trail, and
 * inventing one would require a new table (out of scope).
 *
 * This file owns three things, colocated because they are one feature:
 *   1. The data layer (cross-site per-day aggregation + caching + busts).
 *   2. The public ability registration + execute callback (headless-first).
 *   3. The full-width profile card renderer (a consumer of the ability).
 *
 * @package ExtraChillCommunity
 * @since 1.11.5
 */

defined( 'ABSPATH' ) || exit;

/**
 * Cache TTL for the per-user contribution calendar (seconds).
 *
 * Mirrors the points-calc caching convention (1 hour). The cache is also
 * busted eagerly on new bbPress activity (see
 * ec_community_invalidate_contribution_calendar()), so streaks stay fresh.
 */
const EC_CONTRIB_CALENDAR_CACHE_TTL = HOUR_IN_SECONDS;

// ─── Data layer ─────────────────────────────────────────────────────────────

/**
 * Build a user's per-day contribution calendar over the trailing window.
 *
 * Counts topics + replies (community blog) and main-site posts (main blog)
 * authored by the user, grouped by local calendar day, then merges the per-day
 * maps. Summary metadata (total, max-day, current/longest streak) is derived
 * from the merged map.
 *
 * Timezone approach: WordPress stores `post_date` in the site's configured
 * timezone (America/New_York on this network — confirmed via
 * `get_option('timezone_string')`). Users perceive "today" in that same zone,
 * so `DATE(post_date)` yields correct local day boundaries with no off-by-one
 * at day edges and no GMT conversion needed. Both the community and main blogs
 * share the same timezone, so the two per-day maps merge on the same basis.
 * The window endpoints themselves are computed via `wp_timezone()` so the
 * "today" anchor is local, not UTC. This matches how the existing display
 * code (e.g. profile join date) treats stored dates.
 *
 * @param int $user_id User ID.
 * @param int $days    Window length in days. Default 365. Only the default
 *                     window is cached (see invalidation contract); non-default
 *                     windows are computed fresh.
 * @return array {
 *     @type array $days               Map of 'YYYY-MM-DD' => int count (only
 *                                     days with >=1 contribution; absent = 0).
 *     @type int   $total_contributions
 *     @type int   $max_day_count
 *     @type int   $current_streak
 *     @type int   $longest_streak
 *     @type string $window_start      'YYYY-MM-DD'.
 *     @type string $window_end        'YYYY-MM-DD'.
 * } Empty array on invalid user.
 */
function ec_community_get_user_contribution_calendar( int $user_id, int $days = 365 ): array {
	if ( $user_id <= 0 ) {
		return array();
	}

	// Bound the window: at least 1 day, at most ~3 years to keep the query
	// and the per-day map finite regardless of caller input.
	$days = max( 1, min( 1095, (int) $days ) );

	$is_default_window = ( 365 === $days );
	$cache_key         = 'ec_contrib_calendar_' . $user_id;

	if ( $is_default_window ) {
		$cached = get_transient( $cache_key );
		if ( is_array( $cached ) && isset( $cached['days'] ) ) {
			return $cached;
		}
	}

	$tz           = wp_timezone();
	$today        = new DateTimeImmutable( 'today', $tz );
	$window_end   = $today;
	$window_start = $today->modify( '-' . ( $days - 1 ) . ' days' );

	// post_date is site-local on both blogs; compare directly.
	$window_start_mysql = $window_start->format( 'Y-m-d 00:00:00' );

	$days_map = array();

	// --- Source 1: bbPress topics + replies on the community (current) blog.
	global $wpdb;
	$community_rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT DATE(post_date) AS d, COUNT(*) AS c
			 FROM {$wpdb->posts}
			 WHERE post_author = %d
			   AND post_type IN ('topic', 'reply')
			   AND post_status = 'publish'
			   AND post_date >= %s
			 GROUP BY DATE(post_date)",
			$user_id,
			$window_start_mysql
		)
	);

	if ( is_array( $community_rows ) ) {
		foreach ( $community_rows as $row ) {
			$days_map[ $row->d ] = (int) $row->c;
		}
	}

	// --- Source 2: main-site posts (cross-blog). Mirrors the switch_to_blog
	// pattern in point-calculation.php, with restore_current_blog() in a
	// finally so the switched context can never leak.
	$main_blog_id = function_exists( 'ec_get_blog_id' ) ? ec_get_blog_id( 'main' ) : null;
	if ( $main_blog_id ) {
		switch_to_blog( $main_blog_id );
		try {
			$main_rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT DATE(post_date) AS d, COUNT(*) AS c
					 FROM {$wpdb->posts}
					 WHERE post_author = %d
					   AND post_type = 'post'
					   AND post_status = 'publish'
					   AND post_date >= %s
					 GROUP BY DATE(post_date)",
					$user_id,
					$window_start_mysql
				)
			);
		} finally {
			restore_current_blog();
		}

		if ( is_array( $main_rows ) ) {
			foreach ( $main_rows as $row ) {
				$existing          = isset( $days_map[ $row->d ] ) ? $days_map[ $row->d ] : 0;
				$days_map[ $row->d ] = $existing + (int) $row->c;
			}
		}
	}

	$total   = array_sum( $days_map );
	$max_day = $days_map ? (int) max( $days_map ) : 0;

	// --- Current streak: consecutive contributed days ending today, with a
	// GitHub-style grace period — if today has no contribution yet, the
	// streak still counts up to the most recent contributed day.
	$current_streak = 0;
	$cursor         = $today;
	if ( ! isset( $days_map[ $cursor->format( 'Y-m-d' ) ] ) ) {
		$cursor = $cursor->modify( '-1 day' );
	}
	while ( isset( $days_map[ $cursor->format( 'Y-m-d' ) ] ) ) {
		++$current_streak;
		$cursor = $cursor->modify( '-1 day' );
	}

	// --- Longest streak: longest run of contributed days anywhere in window.
	$longest_streak = 0;
	$run            = 0;
	$iter           = $window_start;
	while ( $iter <= $today ) {
		if ( isset( $days_map[ $iter->format( 'Y-m-d' ) ] ) ) {
			++$run;
			if ( $run > $longest_streak ) {
				$longest_streak = $run;
			}
		} else {
			$run = 0;
		}
		$iter = $iter->modify( '+1 day' );
	}

	$payload = array(
		'days'                => $days_map,
		'total_contributions' => (int) $total,
		'max_day_count'       => $max_day,
		'current_streak'      => $current_streak,
		'longest_streak'      => $longest_streak,
		'window_start'        => $window_start->format( 'Y-m-d' ),
		'window_end'          => $window_end->format( 'Y-m-d' ),
	);

	if ( $is_default_window ) {
		set_transient( $cache_key, $payload, EC_CONTRIB_CALENDAR_CACHE_TTL );
	}

	return $payload;
}

/**
 * Bust a user's contribution-calendar cache on new bbPress activity.
 *
 * Hooked alongside the points recalculation queue (bbp_new_topic /
 * bbp_new_reply) so streaks and counts never lag behind a fresh post. Main-site
 * post creation happens on a different blog and has no hook here; the 1-hour
 * TTL covers that drift (acceptable — main-site posts are infrequent).
 *
 * @param int $post_id Topic or reply post ID.
 */
function ec_community_invalidate_contribution_calendar( $post_id ) {
	if ( ! function_exists( 'bbp_is_reply' ) ) {
		return;
	}

	$user_id = bbp_is_reply( $post_id )
		? bbp_get_reply_author_id( $post_id )
		: bbp_get_topic_author_id( $post_id );

	if ( $user_id ) {
		delete_transient( 'ec_contrib_calendar_' . (int) $user_id );
	}
}
add_action( 'bbp_new_topic', 'ec_community_invalidate_contribution_calendar' );
add_action( 'bbp_new_reply', 'ec_community_invalidate_contribution_calendar' );

// ─── Ability ────────────────────────────────────────────────────────────────

add_action( 'wp_abilities_api_init', 'ec_community_register_contribution_calendar_ability' );

/**
 * Register the get-user-contribution-calendar ability.
 *
 * Registered the same way as the rank abilities
 * (inc/social/rank-system/rank-abilities.php) so the calendar payload is
 * available over REST / MCP / chat (headless-first). `permission_callback` is
 * public because contribution counts are derived from public published posts.
 */
function ec_community_register_contribution_calendar_ability() {
	wp_register_ability(
		'extrachill/get-user-contribution-calendar',
		array(
			'label'               => __( 'Get User Contribution Calendar', 'extra-chill-community' ),
			'description'         => __( 'Get a per-day contribution calendar (topics + replies + main-site posts) for a user over the last N days, with streak and total metadata.', 'extra-chill-community' ),
			'category'            => 'extrachill-community',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'user_id' => array(
						'type'        => 'integer',
						'description' => __( 'User ID (required).', 'extra-chill-community' ),
					),
					'days'    => array(
						'type'        => 'integer',
						'description' => __( 'Window length in days. Default 365.', 'extra-chill-community' ),
					),
				),
				'required'   => array( 'user_id' ),
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'days'                => array( 'type' => 'object' ),
					'total_contributions' => array( 'type' => 'integer' ),
					'max_day_count'       => array( 'type' => 'integer' ),
					'current_streak'      => array( 'type' => 'integer' ),
					'longest_streak'      => array( 'type' => 'integer' ),
					'window_start'        => array( 'type' => 'string' ),
					'window_end'          => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => 'ec_community_ability_get_user_contribution_calendar',
			'permission_callback' => '__return_true',
			'meta'                => array(
				'show_in_rest' => false,
				'annotations'  => array(
					'readonly'    => true,
					'idempotent'  => true,
					'destructive' => false,
				),
			),
		)
	);
}

/**
 * Execute callback for the get-user-contribution-calendar ability.
 *
 * @param array $input Ability input.
 * @return array|WP_Error
 */
function ec_community_ability_get_user_contribution_calendar( $input ) {
	$user_id = isset( $input['user_id'] ) ? (int) $input['user_id'] : 0;
	if ( ! $user_id ) {
		return new WP_Error( 'missing_user', __( 'A user_id is required.', 'extra-chill-community' ) );
	}

	$user = get_userdata( $user_id );
	if ( ! $user ) {
		return new WP_Error( 'user_not_found', __( 'User not found.', 'extra-chill-community' ) );
	}

	$days = isset( $input['days'] ) ? (int) $input['days'] : 365;

	return ec_community_get_user_contribution_calendar( $user_id, $days );
}

// ─── Renderer ───────────────────────────────────────────────────────────────

/**
 * Map a per-day contribution count to a 0..4 shade level.
 *
 * GitHub-style quartile buckets relative to the user's own max-day count, so
 * the ramp scales with their activity level. level-0 is always zero
 * (no contribution).
 *
 * @param int $count Day's contribution count.
 * @param int $max   The user's max day count across the window.
 * @return int 0..4.
 */
function ec_community_heat_level( int $count, int $max ): int {
	if ( $count <= 0 ) {
		return 0;
	}
	if ( $max <= 0 ) {
		return 0;
	}
	if ( $count >= $max ) {
		return 4;
	}

	$ratio = $count / $max;
	if ( $ratio <= 0.25 ) {
		return 1;
	}
	if ( $ratio <= 0.50 ) {
		return 2;
	}
	if ( $ratio <= 0.75 ) {
		return 3;
	}
	return 4;
}

/**
 * Render the full-width contribution heatmap card on the bbPress user profile.
 *
 * Server-rendered CSS grid (53 week-columns x 7 day-rows, Sun..Sat), with
 * month labels across the top, weekday labels (Mon/Wed/Fri) down the left, a
 * summary line, streak readout, and a Less/More legend. Pure PHP + CSS, no JS.
 * Tooltips use native `title` attributes.
 *
 * Hooked on `bbp_template_after_user_details` at priority 3 so it renders
 * directly under the header card, above the Concert History card (priority 5)
 * and above the 2-column `.bbp-user-profile-cards-container` flex grid. The
 * `bbp_is_single_user_profile()` guard keeps it to the main profile tab only
 * (not the topics/replies/edit sub-tabs).
 */
function ec_community_render_contribution_heatmap() {
	if ( ! function_exists( 'bbp_is_single_user_profile' ) || ! bbp_is_single_user_profile() ) {
		return;
	}
	if ( ! function_exists( 'bbp_get_displayed_user_id' ) ) {
		return;
	}

	$user_id = (int) bbp_get_displayed_user_id();
	if ( $user_id <= 0 ) {
		return;
	}

	$data = ec_community_get_user_contribution_calendar( $user_id, 365 );

	$days_map       = isset( $data['days'] ) && is_array( $data['days'] ) ? $data['days'] : array();
	$total          = isset( $data['total_contributions'] ) ? (int) $data['total_contributions'] : 0;
	$max_day        = isset( $data['max_day_count'] ) ? (int) $data['max_day_count'] : 0;
	$current_streak = isset( $data['current_streak'] ) ? (int) $data['current_streak'] : 0;
	$longest_streak = isset( $data['longest_streak'] ) ? (int) $data['longest_streak'] : 0;

	$is_own  = ( (int) get_current_user_id() === $user_id );
	$heading = $is_own
		? __( 'My Contributions', 'extra-chill-community' )
		: __( 'Contributions', 'extra-chill-community' );

	$tz       = wp_timezone();
	$today    = new DateTimeImmutable( 'today', $tz );
	$today_dow = (int) $today->format( 'w' ); // 0 = Sun .. 6 = Sat.

	// Grid origin: the Sunday beginning the first week. Back up from this
	// week's Sunday by 52 weeks so the grid spans 53 week-columns ending at
	// the current week (the GitHub layout).
	$this_week_sunday = $today->modify( '-' . $today_dow . ' days' );
	$first_sunday     = $this_week_sunday->modify( '-52 weeks' );

	$weeks = 53;

	// Weekday labels down the left (GitHub labels Mon/Wed/Fri).
	$weekday_label_rows = array(
		1 => 'Mon',
		3 => 'Wed',
		5 => 'Fri',
	);

	// Month labels: emit one on the first column whose week-start month
	// differs from the previously emitted column's month.
	$month_labels = array();
	$prev_month   = null;
	for ( $w = 0; $w < $weeks; $w++ ) {
		$week_start = $first_sunday->modify( '+' . ( $w * 7 ) . ' days' );
		$m          = (int) $week_start->format( 'n' );
		if ( $prev_month !== $m ) {
			$month_labels[ $w ] = $week_start->format( 'M' );
			$prev_month         = $m;
		}
	}

	$aria_label = sprintf(
		/* translators: %s: total contributions count, formatted. */
		__( '%s contributions in the last year', 'extra-chill-community' ),
		number_format_i18n( $total )
	);
	?>
	<div class="bbp-user-profile-card ec-contribution-heatmap">
		<h3><?php echo esc_html( $heading ); ?></h3>
		<p class="ec-heatmap-summary">
			<?php
			printf(
				/* translators: %s: total contributions count, formatted (wrapped in <strong>). */
				esc_html__( '%s contributions in the last year', 'extra-chill-community' ),
				'<strong>' . esc_html( number_format_i18n( $total ) ) . '</strong>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- intentionally wrapped, inner value escaped.
			);
			?>
		</p>

		<div class="ec-heatmap-scroll">
			<div class="ec-heatmap" role="img" aria-label="<?php echo esc_attr( $aria_label ); ?>">
				<?php
				// Month labels (grid row 1). Each placed at column = week_index + 2
				// (column 1 is reserved for weekday labels).
				foreach ( $month_labels as $mw => $mlabel ) {
					printf(
						'<span class="ec-heat-month" style="grid-column:%1$d;grid-row:1">%2$s</span>',
						(int) $mw + 2,
						esc_html( $mlabel )
					);
				}

				// Weekday labels (column 1, rows 2..8). Row = dow + 2.
				foreach ( $weekday_label_rows as $dow => $label ) {
					printf(
						'<span class="ec-heat-weekday" style="grid-column:1;grid-row:%1$d">%2$s</span>',
						(int) $dow + 2,
						esc_html( $label )
					);
				}

				// Day cells (53 weeks x 7 days). Future slots (after today) are
				// skipped so the current week renders partial.
				for ( $w = 0; $w < $weeks; $w++ ) {
					for ( $dow = 0; $dow < 7; $dow++ ) {
						$date = $first_sunday->modify( '+' . ( $w * 7 + $dow ) . ' days' );
						if ( $date > $today ) {
							continue;
						}

						$date_str = $date->format( 'Y-m-d' );
						$count    = isset( $days_map[ $date_str ] ) ? (int) $days_map[ $date_str ] : 0;
						$level    = ec_community_heat_level( $count, $max_day );
						$localized = wp_date( get_option( 'date_format' ), $date->getTimestamp(), $tz );

						if ( $count > 0 ) {
							$title = sprintf(
								/* translators: 1: contribution count, 2: localized date. */
								_n( '%1$s contribution on %2$s', '%1$s contributions on %2$s', $count, 'extra-chill-community' ),
								$count,
								$localized
							);
						} else {
							$title = sprintf(
								/* translators: %s: localized date. */
								__( 'No contributions on %s', 'extra-chill-community' ),
								$localized
							);
						}

						printf(
							'<span class="ec-heat-cell level-%1$d" style="grid-column:%2$d;grid-row:%3$d" title="%4$s"></span>',
							$level,
							(int) $w + 2,
							(int) $dow + 2,
							esc_attr( $title )
						);
					}
				}
				?>
			</div>
		</div>

		<div class="ec-heatmap-footer">
			<span class="ec-heatmap-streaks">
				<?php
				printf(
					/* translators: 1: current streak days, 2: longest streak days. */
					esc_html__( 'Current streak: %1$d days · Longest: %2$d days', 'extra-chill-community' ),
					$current_streak,
					$longest_streak
				);
				?>
			</span>
			<span class="ec-heatmap-legend" aria-hidden="true">
				<span class="ec-heat-legend-label"><?php esc_html_e( 'Less', 'extra-chill-community' ); ?></span>
				<span class="ec-heat-cell level-0 ec-heat-cell-legend"></span>
				<span class="ec-heat-cell level-1 ec-heat-cell-legend"></span>
				<span class="ec-heat-cell level-2 ec-heat-cell-legend"></span>
				<span class="ec-heat-cell level-3 ec-heat-cell-legend"></span>
				<span class="ec-heat-cell level-4 ec-heat-cell-legend"></span>
				<span class="ec-heat-legend-label"><?php esc_html_e( 'More', 'extra-chill-community' ); ?></span>
			</span>
		</div>
	</div>
	<?php
}
add_action( 'bbp_template_after_user_details', 'ec_community_render_contribution_heatmap', 3 );
