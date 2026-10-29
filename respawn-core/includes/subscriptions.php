<?php
/**
 * Category subscriptions: a personal feed and e-mail notices about new articles.
 * Each subscription is its own "rsp_sub" user meta row, so subscribers are easy to query.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'rest_api_init', function () {
	register_rest_route( 'respawn/v1', '/subscribe', array(
		'methods'             => 'POST',
		'permission_callback' => 'is_user_logged_in',
		'callback'            => 'rsp_rest_subscribe',
		'args'                => array(
			'term' => array( 'required' => true, 'type' => 'integer', 'minimum' => 1 ),
		),
	) );
	register_rest_route( 'respawn/v1', '/subscribe-email', array(
		'methods'             => 'POST',
		'permission_callback' => 'is_user_logged_in',
		'callback'            => function ( WP_REST_Request $r ) {
			update_user_meta( get_current_user_id(), 'rsp_sub_email', $r['on'] ? 'on' : 'off' );
			return array( 'on' => (bool) $r['on'] );
		},
		'args'                => array( 'on' => array( 'required' => true, 'type' => 'boolean' ) ),
	) );
} );

function rsp_rest_subscribe( WP_REST_Request $request ) {
	$term = get_term( (int) $request['term'], 'category' );
	if ( ! $term || is_wp_error( $term ) ) {
		return new WP_Error( 'rsp_not_found', 'Рубрика не найдена.', array( 'status' => 404 ) );
	}
	$user = get_current_user_id();
	$on   = rsp_is_subscribed( $term->term_id, $user );
	if ( $on ) {
		delete_user_meta( $user, 'rsp_sub', $term->term_id );
	} else {
		add_user_meta( $user, 'rsp_sub', $term->term_id );
	}
	return array( 'subscribed' => ! $on, 'count' => rsp_subscriber_count( $term->term_id ) );
}

/**
 * @return int[]
 */
function rsp_user_subscriptions( $user_id = 0 ) {
	$user_id = $user_id ?: get_current_user_id();
	return $user_id ? array_values( array_unique( array_map( 'intval', get_user_meta( $user_id, 'rsp_sub' ) ) ) ) : array();
}

function rsp_is_subscribed( $term_id, $user_id = 0 ) {
	return in_array( (int) $term_id, rsp_user_subscriptions( $user_id ), true );
}

function rsp_subscriber_count( $term_id ) {
	global $wpdb;
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT user_id) FROM {$wpdb->usermeta} WHERE meta_key = 'rsp_sub' AND meta_value = %s", (string) (int) $term_id ) );
}

function rsp_my_feed_url() {
	$page = get_page_by_path( 'my-feed' );
	return $page ? get_permalink( $page ) : home_url( '/my-feed/' );
}

function rsp_subscribe_button( $term_id ) {
	$count = rsp_subscriber_count( $term_id );
	$num   = sprintf( '<span class="subscribe__count">%d</span>', $count );
	if ( ! is_user_logged_in() ) {
		return sprintf( '<a class="subscribe" href="%s">Подписаться %s</a>', esc_url( wp_login_url( get_term_link( (int) $term_id, 'category' ) ) ), $num );
	}
	$on = rsp_is_subscribed( $term_id );
	return sprintf(
		'<button type="button" class="subscribe%s" data-subscribe="%d" aria-pressed="%s"><span class="subscribe__label">%s</span> %s</button>',
		$on ? ' is-on' : '',
		(int) $term_id,
		$on ? 'true' : 'false',
		$on ? 'Вы подписаны' : 'Подписаться',
		$num
	);
}

// When an article goes live, queue an e-mail to the subscribers of its categories.
add_action( 'transition_post_status', function ( $new, $old, $post ) {
	if ( 'publish' === $new && 'publish' !== $old && 'post' === $post->post_type ) {
		wp_schedule_single_event( time() + 60, 'rsp_notify_subscribers', array( $post->ID ) );
	}
}, 10, 3 );

add_action( 'rsp_notify_subscribers', function ( $post_id ) {
	$post = get_post( $post_id );
	if ( ! $post || 'publish' !== $post->post_status || get_post_meta( $post_id, 'rsp_notified', true ) ) {
		return;
	}
	update_post_meta( $post_id, 'rsp_notified', 1 );
	$cats = array_map( 'strval', wp_get_post_categories( $post_id ) );
	if ( ! $cats ) {
		return;
	}
	global $wpdb;
	$in    = implode( ',', array_fill( 0, count( $cats ), '%s' ) );
	$users = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'rsp_sub' AND meta_value IN ($in)", $cats ) ); // phpcs:ignore WordPress.DB.PreparedSQL
	foreach ( $users as $uid ) {
		$u = get_userdata( (int) $uid );
		if ( ! $u || (int) $uid === (int) $post->post_author || 'off' === get_user_meta( $uid, 'rsp_sub_email', true ) ) {
			continue;
		}
		wp_mail(
			$u->user_email,
			sprintf( '[Respawn] %s', html_entity_decode( get_the_title( $post ), ENT_QUOTES ) ),
			sprintf(
				"Новый материал в рубриках, на которые вы подписаны:\n\n%s\n%s\n\n%s\n\nУправлять подписками: %s",
				html_entity_decode( get_the_title( $post ), ENT_QUOTES ),
				wp_strip_all_tags( get_the_excerpt( $post ) ),
				get_permalink( $post ),
				rsp_my_feed_url()
			)
		);
	}
} );
