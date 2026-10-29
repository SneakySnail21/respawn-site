<?php
/**
 * Plugin Name: Respawn Core
 * Description: Функции портала Respawn: оценки в обзорах, лайки и рейтинг, популярное, статьи от пользователей, карточки игр с форумами, закладки, подписки, Respawn AI.
 * Version: 1.1.0
 * Requires at least: 6.4
 * Requires PHP: 8.0
 * Author: Respawn
 * Text Domain: respawn-core
 */

defined( 'ABSPATH' ) || exit;

const RSP_CORE_VERSION = '1.1.0';

require __DIR__ . '/includes/review.php';
require __DIR__ . '/includes/likes.php';
require __DIR__ . '/includes/community.php';
require __DIR__ . '/includes/games.php';
require __DIR__ . '/includes/threads.php';
require __DIR__ . '/includes/bookmarks.php';
require __DIR__ . '/includes/subscriptions.php';
require __DIR__ . '/includes/ai.php';
require __DIR__ . '/includes/members.php';
require __DIR__ . '/includes/hardening.php';
require __DIR__ . '/includes/export.php';

register_activation_hook( __FILE__, 'rsp_core_activate' );

// After a deploy that adds post types, rebuild permalinks once on the next request.
add_action( 'init', function () {
	if ( get_option( 'rsp_flush_rewrite' ) ) {
		delete_option( 'rsp_flush_rewrite' );
		flush_rewrite_rules( false );
	}
}, 99 );

/**
 * One-time setup: role caps, community category, zero counters for existing posts.
 */
function rsp_core_activate() {
	$contributor = get_role( 'contributor' );
	if ( $contributor ) {
		$contributor->add_cap( 'upload_files' );
	}
	update_option( 'default_role', 'contributor' );
	update_option( 'users_can_register', 1 );

	if ( ! term_exists( 'blogs', 'category' ) ) {
		wp_insert_term( 'Блоги', 'category', array(
			'slug'        => 'blogs',
			'description' => 'Статьи участников сообщества Respawn.',
		) );
	}

	$ids = get_posts( array(
		'post_type'      => 'post',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	) );
	foreach ( $ids as $id ) {
		add_post_meta( $id, 'rsp_likes', 0, true );
		add_post_meta( $id, 'rsp_views', 0, true );
	}
}
