<?php
/**
 * Game cards: the "game" post type, facts, player ratings and a bbPress forum per game.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Game fields: meta key => label.
 */
function rsp_game_fields() {
	return array(
		'rsp_g_release'   => 'Дата выхода (ГГГГ-ММ-ДД или ГГГГ)',
		'rsp_g_platforms' => 'Платформы',
		'rsp_g_genre'     => 'Жанр',
		'rsp_g_developer' => 'Разработчик',
		'rsp_g_publisher' => 'Издатель',
		'rsp_g_steam'     => 'Steam App ID',
		'rsp_g_group'     => 'Подборка: popular или new',
	);
}

add_action( 'init', function () {
	register_post_type( 'game', array(
		'labels'        => array(
			'name'          => 'Игры',
			'singular_name' => 'Игра',
			'add_new_item'  => 'Добавить игру',
			'edit_item'     => 'Изменить игру',
			'all_items'     => 'Все игры',
			'search_items'  => 'Найти игру',
			'not_found'     => 'Игр не найдено',
		),
		'public'        => true,
		'has_archive'   => 'games',
		'rewrite'       => array( 'slug' => 'games', 'with_front' => false ),
		'menu_icon'     => 'dashicons-games',
		'menu_position' => 6,
		'supports'      => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
		'show_in_rest'  => true,
	) );

	foreach ( array_keys( rsp_game_fields() ) as $key ) {
		register_post_meta( 'game', $key, array(
			'type'          => 'string',
			'single'        => true,
			'show_in_rest'  => true,
			'auth_callback' => fn() => current_user_can( 'edit_posts' ),
		) );
	}
	register_post_meta( 'post', 'rsp_game_id', array(
		'type'          => 'integer',
		'single'        => true,
		'show_in_rest'  => true,
		'auth_callback' => fn() => current_user_can( 'edit_posts' ),
	) );
} );

add_action( 'add_meta_boxes_game', function () {
	add_meta_box( 'rsp-game', 'Данные об игре', function ( $post ) {
		wp_nonce_field( 'rsp_game_save', 'rsp_game_nonce' );
		echo '<table class="form-table" role="presentation"><tbody>';
		foreach ( rsp_game_fields() as $key => $label ) {
			printf(
				'<tr><th><label for="%1$s">%2$s</label></th><td><input id="%1$s" name="%1$s" type="text" value="%3$s" class="regular-text"></td></tr>',
				esc_attr( $key ),
				esc_html( $label ),
				esc_attr( get_post_meta( $post->ID, $key, true ) )
			);
		}
		echo '</tbody></table>';
		if ( $forum = rsp_game_forum_id( $post->ID ) ) {
			printf( '<p>Форум игры: <a href="%s">%s</a></p>', esc_url( get_permalink( $forum ) ), esc_html( get_the_title( $forum ) ) );
		}
	}, 'game', 'normal', 'high' );
} );

add_action( 'save_post_game', function ( $post_id, $post ) {
	if ( isset( $_POST['rsp_game_nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['rsp_game_nonce'] ), 'rsp_game_save' ) && current_user_can( 'edit_post', $post_id ) && ! wp_is_post_autosave( $post_id ) ) {
		foreach ( array_keys( rsp_game_fields() ) as $key ) {
			$value = isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';
			'' === $value ? delete_post_meta( $post_id, $key ) : update_post_meta( $post_id, $key, $value );
		}
	}
	rsp_game_sync_sort_keys( $post_id );
	if ( 'publish' === $post->post_status ) {
		rsp_game_ensure_forum( $post_id );
	}
}, 10, 2 );

/**
 * Sortable copies of the release date and rating, so archives can order by them.
 */
function rsp_game_sync_sort_keys( $game_id ) {
	$release = (string) get_post_meta( $game_id, 'rsp_g_release', true );
	if ( preg_match( '/^\d{4}$/', $release ) ) {
		$release .= '-12-31';
	}
	update_post_meta( $game_id, 'rsp_g_release_sort', preg_match( '/^\d{4}-\d{2}-\d{2}$/', $release ) ? $release : '0000-00-00' );
	add_post_meta( $game_id, 'rsp_g_group', 'new', true );
	add_post_meta( $game_id, 'rsp_rating_avg', 0, true );
	add_post_meta( $game_id, 'rsp_rating_count', 0, true );
}

/**
 * Facts for display, without empty values.
 */
function rsp_game_facts( $game_id ) {
	$release = (string) get_post_meta( $game_id, 'rsp_g_release', true );
	if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $release ) ) {
		$release = wp_date( 'j F Y', strtotime( $release . ' 12:00' ) );
	}
	return array_filter( array(
		'Жанр'        => get_post_meta( $game_id, 'rsp_g_genre', true ),
		'Выход'       => $release,
		'Платформы'   => get_post_meta( $game_id, 'rsp_g_platforms', true ),
		'Разработчик' => get_post_meta( $game_id, 'rsp_g_developer', true ),
		'Издатель'    => get_post_meta( $game_id, 'rsp_g_publisher', true ),
	) );
}

function rsp_game_is_released( $game_id ) {
	$sort = (string) get_post_meta( $game_id, 'rsp_g_release_sort', true );
	return $sort && '0000-00-00' !== $sort && $sort <= wp_date( 'Y-m-d' );
}

/* ---------- Forum per game ---------- */

/**
 * Parent forum that holds every game's forum.
 */
function rsp_games_parent_forum() {
	$id = (int) get_option( 'rsp_games_forum' );
	if ( $id && get_post( $id ) ) {
		return $id;
	}
	if ( ! function_exists( 'bbp_insert_forum' ) ) {
		return 0;
	}
	$id = bbp_insert_forum( array(
		'post_title'   => 'Сообщества игр',
		'post_name'    => 'games',
		'post_content' => 'У каждой игры на Respawn свой раздел: главное обсуждение и ветки, которые создают участники.',
		'menu_order'   => 1,
	) );
	if ( $id ) {
		update_option( 'rsp_games_forum', $id );
	}
	return (int) $id;
}

function rsp_game_forum_id( $game_id ) {
	$id = (int) get_post_meta( $game_id, 'rsp_g_forum', true );
	return $id && get_post( $id ) ? $id : 0;
}

/**
 * Create the game's forum and its pinned main thread once.
 */
function rsp_game_ensure_forum( $game_id ) {
	if ( rsp_game_forum_id( $game_id ) || ! function_exists( 'bbp_insert_forum' ) ) {
		return rsp_game_forum_id( $game_id );
	}
	$game   = get_post( $game_id );
	$parent = rsp_games_parent_forum();
	$forum  = bbp_insert_forum( array(
		'post_parent'  => $parent,
		'post_title'   => $game->post_title,
		'post_name'    => $game->post_name,
		'post_content' => sprintf( 'Сообщество игры %s: новости, вопросы, гайды и мнения.', $game->post_title ),
	) );
	if ( ! $forum ) {
		return 0;
	}
	update_post_meta( $game_id, 'rsp_g_forum', $forum );
	update_post_meta( $forum, 'rsp_game_id', $game_id );

	$author = function_exists( 'rsp_ai_user_id' ) ? rsp_ai_user_id() : (int) $game->post_author;
	$topic  = bbp_insert_topic(
		array(
			'post_parent'  => $forum,
			'post_title'   => sprintf( 'Главное обсуждение: %s', $game->post_title ),
			'post_content' => sprintf( "Общая ветка по %s. Делитесь впечатлениями, задавайте вопросы и обсуждайте новости.\n\nДля отдельных тем — гайдов, багов, поиска команды — создавайте свои ветки в разделе игры.", $game->post_title ),
			'post_author'  => $author,
		),
		array( 'forum_id' => $forum )
	);
	if ( $topic ) {
		bbp_stick_topic( $topic );
		update_post_meta( $game_id, 'rsp_g_main_topic', $topic );
	}
	return (int) $forum;
}

/* ---------- Player ratings ---------- */

add_action( 'rest_api_init', function () {
	register_rest_route( 'respawn/v1', '/rate', array(
		'methods'             => 'POST',
		'permission_callback' => 'is_user_logged_in',
		'callback'            => 'rsp_rest_rate',
		'args'                => array(
			'id'    => array( 'required' => true, 'type' => 'integer', 'minimum' => 1 ),
			'score' => array( 'required' => true, 'type' => 'integer', 'minimum' => 0, 'maximum' => 10 ),
		),
	) );
} );

/**
 * Set (1–10) or clear (0) the current user's rating of a game.
 */
function rsp_rest_rate( WP_REST_Request $request ) {
	$id   = (int) $request['id'];
	$game = get_post( $id );
	if ( ! $game || 'game' !== $game->post_type || 'publish' !== $game->post_status ) {
		return new WP_Error( 'rsp_not_found', 'Игра не найдена.', array( 'status' => 404 ) );
	}
	$user    = get_current_user_id();
	$ratings = (array) get_user_meta( $user, 'rsp_game_ratings', true );
	$score   = (int) $request['score'];
	if ( $score ) {
		$ratings[ $id ] = $score;
	} else {
		unset( $ratings[ $id ] );
	}
	update_user_meta( $user, 'rsp_game_ratings', array_filter( $ratings ) );
	update_post_meta( $id, 'rsp_rating_u' . $user, $score ?: '' );
	if ( ! $score ) {
		delete_post_meta( $id, 'rsp_rating_u' . $user );
	}
	$stats = rsp_game_recount( $id );
	return array_merge( $stats, array( 'mine' => $score ) );
}

/**
 * Recount a game's average from per-user meta rows.
 */
function rsp_game_recount( $game_id ) {
	global $wpdb;
	$row = $wpdb->get_row( $wpdb->prepare(
		"SELECT COUNT(*) AS n, AVG(meta_value) AS a FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key LIKE %s AND meta_value <> ''",
		$game_id,
		$wpdb->esc_like( 'rsp_rating_u' ) . '%'
	) );
	$count = (int) $row->n;
	$avg   = $count ? round( (float) $row->a, 1 ) : 0;
	update_post_meta( $game_id, 'rsp_rating_count', $count );
	update_post_meta( $game_id, 'rsp_rating_avg', $avg );
	return array( 'avg' => $avg, 'count' => $count );
}

function rsp_game_rating( $game_id ) {
	return array(
		'avg'   => (float) get_post_meta( $game_id, 'rsp_rating_avg', true ),
		'count' => (int) get_post_meta( $game_id, 'rsp_rating_count', true ),
	);
}

function rsp_user_game_rating( $game_id ) {
	if ( ! is_user_logged_in() ) {
		return 0;
	}
	$ratings = (array) get_user_meta( get_current_user_id(), 'rsp_game_ratings', true );
	return (int) ( $ratings[ $game_id ] ?? 0 );
}

/**
 * Rating widget: ten buttons, guests get a login link.
 */
function rsp_rating_widget( $game_id ) {
	$r    = rsp_game_rating( $game_id );
	$mine = rsp_user_game_rating( $game_id );
	$out  = sprintf(
		'<div class="rating" data-rate-id="%d"><div class="rating__sum"><b class="rating__avg">%s</b><span class="rating__count">%s</span></div>',
		(int) $game_id,
		$r['count'] ? esc_html( rsp_format_score( $r['avg'] ) ) : '—',
		esc_html( $r['count'] ? sprintf( 'оценок игроков: %d', $r['count'] ) : 'оценок пока нет' )
	);
	if ( ! rsp_game_is_released( $game_id ) ) {
		return $out . '<p class="rating__hint">Оценки откроются после выхода игры.</p></div>';
	}
	if ( ! is_user_logged_in() ) {
		return $out . sprintf( '<p class="rating__hint"><a href="%s">Войдите</a>, чтобы поставить оценку.</p></div>', esc_url( wp_login_url( get_permalink( $game_id ) ) ) );
	}
	$out .= '<div class="rating__scale" role="group" aria-label="Ваша оценка">';
	for ( $i = 1; $i <= 10; $i++ ) {
		$out .= sprintf( '<button type="button" data-score="%1$d" aria-pressed="%2$s"%3$s>%1$d</button>', $i, $mine === $i ? 'true' : 'false', $mine >= $i ? ' class="is-on"' : '' );
	}
	$out .= '</div><p class="rating__hint">' . ( $mine ? sprintf( 'Ваша оценка: %d. Нажмите ещё раз, чтобы убрать.', $mine ) : 'Поставьте свою оценку от 1 до 10.' ) . '</p></div>';
	return $out;
}

/**
 * Archive ordering: ?sort=popular|new|top|soon.
 */
add_action( 'pre_get_posts', function ( WP_Query $q ) {
	if ( is_admin() || ! $q->is_main_query() || ! $q->is_post_type_archive( 'game' ) ) {
		return;
	}
	$q->set( 'posts_per_page', 24 );
	$sort = isset( $_GET['sort'] ) ? sanitize_key( $_GET['sort'] ) : 'popular';
	$today = wp_date( 'Y-m-d' );
	switch ( $sort ) {
		case 'new':
			$q->set( 'meta_query', array( 'rel' => array( 'key' => 'rsp_g_release_sort', 'value' => $today, 'compare' => '<=', 'type' => 'DATE' ) ) );
			$q->set( 'orderby', array( 'rel' => 'DESC' ) );
			break;
		case 'soon':
			$q->set( 'meta_query', array( 'rel' => array( 'key' => 'rsp_g_release_sort', 'value' => $today, 'compare' => '>', 'type' => 'DATE' ) ) );
			$q->set( 'orderby', array( 'rel' => 'ASC' ) );
			break;
		case 'top':
			$q->set( 'meta_query', array( 'avg' => array( 'key' => 'rsp_rating_avg', 'type' => 'DECIMAL(4,1)' ), 'cnt' => array( 'key' => 'rsp_rating_count', 'type' => 'NUMERIC' ) ) );
			$q->set( 'orderby', array( 'avg' => 'DESC', 'cnt' => 'DESC' ) );
			break;
		default:
			$q->set( 'meta_query', array( 'grp' => array( 'key' => 'rsp_g_group', 'compare' => 'EXISTS' ), 'cnt' => array( 'key' => 'rsp_rating_count', 'type' => 'NUMERIC' ) ) );
			$q->set( 'orderby', array( 'grp' => 'DESC', 'cnt' => 'DESC', 'title' => 'ASC' ) );
	}
} );

/**
 * Posts written about a game, newest first.
 */
function rsp_game_posts( $game_id, $limit = 6 ) {
	return get_posts( array(
		'posts_per_page' => $limit,
		'meta_key'       => 'rsp_game_id',
		'meta_value'     => (int) $game_id,
	) );
}

/**
 * Find a game card by title (case-insensitive) or Steam id.
 */
function rsp_find_game( $title = '', $steam = 0 ) {
	if ( $steam ) {
		$ids = get_posts( array( 'post_type' => 'game', 'meta_key' => 'rsp_g_steam', 'meta_value' => (string) (int) $steam, 'fields' => 'ids', 'posts_per_page' => 1 ) );
		if ( $ids ) {
			return (int) $ids[0];
		}
	}
	if ( $title ) {
		global $wpdb;
		$id = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'game' AND post_status = 'publish' AND LOWER(post_title) = LOWER(%s) LIMIT 1", $title ) );
		return (int) $id;
	}
	return 0;
}
