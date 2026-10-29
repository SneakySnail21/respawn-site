<?php
/**
 * Bookmarks: members save articles, games and forum threads to read later.
 */

defined( 'ABSPATH' ) || exit;

function rsp_bookmark_types() {
	return array( 'post', 'game', 'topic' );
}

add_action( 'rest_api_init', function () {
	register_rest_route( 'respawn/v1', '/bookmark', array(
		'methods'             => 'POST',
		'permission_callback' => 'is_user_logged_in',
		'callback'            => 'rsp_rest_bookmark',
		'args'                => array(
			'id' => array( 'required' => true, 'type' => 'integer', 'minimum' => 1 ),
		),
	) );
} );

function rsp_rest_bookmark( WP_REST_Request $request ) {
	$id   = (int) $request['id'];
	$post = get_post( $id );
	if ( ! $post || ! in_array( $post->post_type, rsp_bookmark_types(), true ) || ! in_array( $post->post_status, array( 'publish', 'closed' ), true ) ) {
		return new WP_Error( 'rsp_not_found', 'Материал не найден.', array( 'status' => 404 ) );
	}
	$user  = get_current_user_id();
	$list  = rsp_user_bookmarks( $user );
	$saved = in_array( $id, $list, true );
	$list  = $saved ? array_values( array_diff( $list, array( $id ) ) ) : array_merge( array( $id ), $list );
	update_user_meta( $user, 'rsp_bookmarks', array_slice( $list, 0, 500 ) );
	$count = max( 0, (int) get_post_meta( $id, 'rsp_bookmarks', true ) + ( $saved ? -1 : 1 ) );
	update_post_meta( $id, 'rsp_bookmarks', $count );
	return array( 'saved' => ! $saved, 'count' => $count );
}

/**
 * Bookmarked post IDs, newest first.
 *
 * @return int[]
 */
function rsp_user_bookmarks( $user_id = 0 ) {
	$user_id = $user_id ?: get_current_user_id();
	if ( ! $user_id ) {
		return array();
	}
	return array_values( array_filter( array_map( 'intval', (array) get_user_meta( $user_id, 'rsp_bookmarks', true ) ) ) );
}

function rsp_is_bookmarked( $id ) {
	return in_array( (int) $id, rsp_user_bookmarks(), true );
}

function rsp_bookmarks_url() {
	$page = get_page_by_path( 'bookmarks' );
	return $page ? get_permalink( $page ) : home_url( '/bookmarks/' );
}

/**
 * Save button. Guests get a login link.
 */
function rsp_bookmark_button( $id, $compact = false ) {
	$icon  = '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path d="M6 3.5h12v17l-6-4.2-6 4.2z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>';
	$label = $compact ? '' : '<span class="bookmark__label">В закладки</span>';
	if ( ! is_user_logged_in() ) {
		return sprintf( '<a class="bookmark%s" href="%s" title="Войдите, чтобы сохранять в закладки">%s%s</a>', $compact ? ' bookmark--icon' : '', esc_url( wp_login_url( get_permalink( $id ) ) ), $icon, $label );
	}
	$saved = rsp_is_bookmarked( $id );
	return sprintf(
		'<button type="button" class="bookmark%s%s" data-bookmark-id="%d" aria-pressed="%s" title="%s">%s%s</button>',
		$compact ? ' bookmark--icon' : '',
		$saved ? ' is-saved' : '',
		(int) $id,
		$saved ? 'true' : 'false',
		$saved ? 'Убрать из закладок' : 'Сохранить в закладки',
		$icon,
		$compact ? '' : '<span class="bookmark__label">' . ( $saved ? 'В закладках' : 'В закладки' ) . '</span>'
	);
}
