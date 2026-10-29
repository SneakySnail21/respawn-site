<?php
/**
 * Likes on posts and comments, author rating (karma), view counter, popular posts.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'rest_api_init', function () {
	register_rest_route( 'respawn/v1', '/like', array(
		'methods'             => 'POST',
		'permission_callback' => 'is_user_logged_in',
		'callback'            => 'rsp_rest_like',
		'args'                => array(
			'type' => array( 'required' => true, 'enum' => array( 'post', 'comment' ) ),
			'id'   => array( 'required' => true, 'type' => 'integer', 'minimum' => 1 ),
		),
	) );
	register_rest_route( 'respawn/v1', '/view/(?P<id>\d+)', array(
		'methods'             => 'POST',
		'permission_callback' => '__return_true',
		'callback'            => 'rsp_rest_view',
	) );
} );

/**
 * Toggle the current user's like on a post or comment.
 */
function rsp_rest_like( WP_REST_Request $request ) {
	$user_id = get_current_user_id();
	$type    = $request['type'];
	$id      = (int) $request['id'];

	if ( 'post' === $type ) {
		$target = get_post( $id );
		if ( ! $target || 'publish' !== $target->post_status || 'post' !== $target->post_type ) {
			return new WP_Error( 'rsp_not_found', 'Материал не найден.', array( 'status' => 404 ) );
		}
		$owner = (int) $target->post_author;
	} else {
		$target = get_comment( $id );
		if ( ! $target || '1' !== (string) $target->comment_approved ) {
			return new WP_Error( 'rsp_not_found', 'Комментарий не найден.', array( 'status' => 404 ) );
		}
		$owner = (int) $target->user_id;
	}

	if ( $owner && $owner === $user_id ) {
		return new WP_Error( 'rsp_own', 'Нельзя оценивать свои материалы.', array( 'status' => 403 ) );
	}

	$list_key = 'post' === $type ? 'rsp_liked_posts' : 'rsp_liked_comments';
	$liked    = array_map( 'intval', (array) get_user_meta( $user_id, $list_key, true ) );
	$liked    = array_values( array_filter( $liked ) );
	$is_liked = in_array( $id, $liked, true );
	$delta    = $is_liked ? -1 : 1;

	$liked = $is_liked ? array_values( array_diff( $liked, array( $id ) ) ) : array_merge( $liked, array( $id ) );
	update_user_meta( $user_id, $list_key, $liked );

	if ( 'post' === $type ) {
		$count = max( 0, (int) get_post_meta( $id, 'rsp_likes', true ) + $delta );
		update_post_meta( $id, 'rsp_likes', $count );
		delete_transient( 'rsp_popular_' . RSP_CORE_VERSION );
	} else {
		$count = max( 0, (int) get_comment_meta( $id, 'rsp_likes', true ) + $delta );
		update_comment_meta( $id, 'rsp_likes', $count );
	}

	if ( $owner ) {
		update_user_meta( $owner, 'rsp_karma', (int) get_user_meta( $owner, 'rsp_karma', true ) + $delta );
	}

	return array(
		'liked' => ! $is_liked,
		'count' => $count,
	);
}

function rsp_rest_view( WP_REST_Request $request ) {
	$id   = (int) $request['id'];
	$post = get_post( $id );
	if ( ! $post || 'publish' !== $post->post_status || 'post' !== $post->post_type ) {
		return new WP_Error( 'rsp_not_found', 'Материал не найден.', array( 'status' => 404 ) );
	}
	$views = (int) get_post_meta( $id, 'rsp_views', true ) + 1;
	update_post_meta( $id, 'rsp_views', $views );
	return array( 'views' => $views );
}

add_action( 'wp_insert_post', function ( $post_id, $post ) {
	if ( 'post' === $post->post_type ) {
		add_post_meta( $post_id, 'rsp_likes', 0, true );
		add_post_meta( $post_id, 'rsp_views', 0, true );
	}
}, 10, 2 );

function rsp_like_count( $id, $type = 'post' ) {
	return (int) ( 'post' === $type ? get_post_meta( $id, 'rsp_likes', true ) : get_comment_meta( $id, 'rsp_likes', true ) );
}

function rsp_user_liked( $id, $type = 'post' ) {
	if ( ! is_user_logged_in() ) {
		return false;
	}
	$list = (array) get_user_meta( get_current_user_id(), 'post' === $type ? 'rsp_liked_posts' : 'rsp_liked_comments', true );
	return in_array( (int) $id, array_map( 'intval', $list ), true );
}

function rsp_user_karma( $user_id ) {
	return (int) get_user_meta( $user_id, 'rsp_karma', true );
}

function rsp_view_count( $post_id ) {
	return (int) get_post_meta( $post_id, 'rsp_views', true );
}

/**
 * Like button markup. Guests get a link to log in.
 */
function rsp_like_button( $id, $type = 'post' ) {
	$count = rsp_like_count( $id, $type );
	$liked = rsp_user_liked( $id, $type );
	$icon  = '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path d="M12 20.5s-7.5-4.6-7.5-10.2A4.3 4.3 0 0 1 12 7.6a4.3 4.3 0 0 1 7.5 2.7c0 5.6-7.5 10.2-7.5 10.2z" fill="currentColor"/></svg>';
	if ( ! is_user_logged_in() ) {
		return sprintf(
			'<a class="like" href="%s" title="Войдите, чтобы оценить">%s<span class="like__count">%d</span></a>',
			esc_url( wp_login_url( get_permalink() ) ),
			$icon,
			$count
		);
	}
	return sprintf(
		'<button type="button" class="like%s" data-like-type="%s" data-like-id="%d" aria-pressed="%s" title="Нравится">%s<span class="like__count">%d</span></button>',
		$liked ? ' is-liked' : '',
		esc_attr( $type ),
		(int) $id,
		$liked ? 'true' : 'false',
		$icon,
		$count
	);
}

/**
 * Popular posts: likes weigh most, then comments, then views. Last $days, topped up with all-time if short.
 *
 * @return WP_Post[]
 */
function rsp_popular_posts( $limit = 5, $days = 30 ) {
	$cache_key = 'rsp_popular_' . RSP_CORE_VERSION;
	$ids       = get_transient( $cache_key );
	if ( false === $ids ) {
		global $wpdb;
		$sql = "SELECT p.ID FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->postmeta} l ON l.post_id = p.ID AND l.meta_key = 'rsp_likes'
			LEFT JOIN {$wpdb->postmeta} v ON v.post_id = p.ID AND v.meta_key = 'rsp_views'
			WHERE p.post_type = 'post' AND p.post_status = 'publish' %s
			ORDER BY (COALESCE(l.meta_value, 0) * 5 + p.comment_count * 3 + COALESCE(v.meta_value, 0) / 25) DESC, p.post_date DESC
			LIMIT 20";
		$ids = $wpdb->get_col( sprintf( $sql, $wpdb->prepare( 'AND p.post_date >= %s', gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS ) ) ) );
		if ( count( $ids ) < 20 ) {
			$ids = array_values( array_unique( array_merge( $ids, $wpdb->get_col( sprintf( $sql, '' ) ) ) ) );
		}
		set_transient( $cache_key, $ids, 10 * MINUTE_IN_SECONDS );
	}
	if ( ! $ids ) {
		return array();
	}
	return get_posts( array(
		'post__in'            => array_slice( array_map( 'intval', $ids ), 0, $limit ),
		'orderby'             => 'post__in',
		'posts_per_page'      => $limit,
		'ignore_sticky_posts' => true,
	) );
}

add_action( 'transition_post_status', function ( $new, $old, $post ) {
	if ( 'post' === $post->post_type && $new !== $old ) {
		delete_transient( 'rsp_popular_' . RSP_CORE_VERSION );
	}
}, 10, 3 );
