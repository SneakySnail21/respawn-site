<?php
/**
 * Community authoring: user articles go to moderation, registration anti-spam, login flow.
 */

defined( 'ABSPATH' ) || exit;

// rsp_write_url() lives in members.php: guests go to registration, members to the front-end "write" page.

/**
 * True when the user writes as a community member rather than as editorial staff.
 */
function rsp_is_community_author( $user_id ) {
	return ! user_can( $user_id, 'publish_posts' );
}

// Community articles without a chosen category land in "Блоги".
add_action( 'save_post_post', function ( $post_id, $post ) {
	if ( wp_is_post_revision( $post_id ) || 'auto-draft' === $post->post_status || ! rsp_is_community_author( $post->post_author ) ) {
		return;
	}
	$cats = wp_get_post_categories( $post_id );
	if ( ! $cats || array( (int) get_option( 'default_category' ) ) === array_map( 'intval', $cats ) ) {
		$blogs = get_term_by( 'slug', 'blogs', 'category' );
		if ( $blogs ) {
			wp_set_post_categories( $post_id, array( $blogs->term_id ) );
		}
	}
}, 20, 2 );

// Tell the editors when an article is waiting for review.
add_action( 'transition_post_status', function ( $new, $old, $post ) {
	if ( 'pending' !== $new || 'pending' === $old || 'post' !== $post->post_type ) {
		return;
	}
	$author = get_userdata( $post->post_author );
	wp_mail(
		get_option( 'admin_email' ),
		sprintf( '[Respawn] Статья на модерации: %s', $post->post_title ),
		sprintf(
			"%s отправил(а) статью на проверку.\n\n%s\n\nОткрыть: %s",
			$author ? $author->display_name : 'Участник',
			$post->post_title,
			admin_url( 'post.php?post=' . $post->ID . '&action=edit' )
		)
	);
}, 10, 3 );

// Honeypot on the registration form: bots fill every field, people never see this one.
add_action( 'register_form', function () {
	echo '<p class="rsp-hp" aria-hidden="true" style="position:absolute;left:-9999px"><label>Не заполняйте это поле<input type="text" name="rsp_website" value="" tabindex="-1" autocomplete="off"></label></p>';
} );

add_filter( 'registration_errors', function ( $errors ) {
	if ( ! empty( $_POST['rsp_website'] ) ) {
		$errors->add( 'rsp_spam', 'Регистрация отклонена. Попробуйте ещё раз.' );
	}
	return $errors;
} );

// Readers land back on the site after login, not in the dashboard.
add_filter( 'login_redirect', function ( $redirect_to, $requested, $user ) {
	if ( $user instanceof WP_User && ! user_can( $user, 'edit_others_posts' ) && ( ! $requested || str_contains( $requested, 'wp-admin' ) && ! str_contains( $requested, 'post' ) ) ) {
		return home_url( '/' );
	}
	return $redirect_to;
}, 10, 3 );

// Keep the dashboard short for community authors.
add_action( 'admin_menu', function () {
	if ( current_user_can( 'edit_others_posts' ) ) {
		return;
	}
	remove_menu_page( 'tools.php' );
	remove_menu_page( 'edit-comments.php' );
}, 99 );

add_action( 'wp_dashboard_setup', function () {
	if ( current_user_can( 'edit_others_posts' ) ) {
		return;
	}
	remove_meta_box( 'dashboard_primary', 'dashboard', 'side' );
	remove_meta_box( 'dashboard_quick_press', 'dashboard', 'side' );
	remove_meta_box( 'dashboard_site_health', 'dashboard', 'normal' );
	wp_add_dashboard_widget( 'rsp_author_help', 'Как опубликовать статью на Respawn', function () {
		printf(
			'<ol><li>Нажмите <a href="%s">«Добавить запись»</a> и напишите статью.</li><li>Выберите рубрику и добавьте изображение записи (обложку).</li><li>Нажмите «Отправить на утверждение». Редакция проверит материал и опубликует его.</li></ol><p><a href="%s">Правила сообщества</a></p>',
			esc_url( admin_url( 'post-new.php' ) ),
			esc_url( home_url( '/rules/' ) )
		);
	} );
} );
