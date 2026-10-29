<?php
/**
 * Reddit-style voting on forum threads and hot / new / top ordering.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'rest_api_init', function () {
	register_rest_route( 'respawn/v1', '/vote', array(
		'methods'             => 'POST',
		'permission_callback' => 'is_user_logged_in',
		'callback'            => 'rsp_rest_vote',
		'args'                => array(
			'id'  => array( 'required' => true, 'type' => 'integer', 'minimum' => 1 ),
			'dir' => array( 'required' => true, 'type' => 'integer', 'enum' => array( -1, 0, 1 ) ),
		),
	) );
} );

/**
 * Set the current user's vote on a thread: 1 up, -1 down, 0 none.
 */
function rsp_rest_vote( WP_REST_Request $request ) {
	$id    = (int) $request['id'];
	$topic = get_post( $id );
	if ( ! $topic || 'topic' !== $topic->post_type || ! in_array( $topic->post_status, array( 'publish', 'closed' ), true ) ) {
		return new WP_Error( 'rsp_not_found', 'Ветка не найдена.', array( 'status' => 404 ) );
	}
	$user  = get_current_user_id();
	$owner = (int) $topic->post_author;
	if ( $owner === $user ) {
		return new WP_Error( 'rsp_own', 'Нельзя голосовать за свою ветку.', array( 'status' => 403 ) );
	}
	$votes = (array) get_user_meta( $user, 'rsp_topic_votes', true );
	$old   = (int) ( $votes[ $id ] ?? 0 );
	$dir   = (int) $request['dir'];
	if ( $dir ) {
		$votes[ $id ] = $dir;
	} else {
		unset( $votes[ $id ] );
	}
	update_user_meta( $user, 'rsp_topic_votes', array_filter( $votes ) );

	$delta = $dir - $old;
	$score = (int) get_post_meta( $id, 'rsp_votes', true ) + $delta;
	update_post_meta( $id, 'rsp_votes', $score );
	if ( $owner && $delta ) {
		update_user_meta( $owner, 'rsp_karma', rsp_user_karma( $owner ) + $delta );
	}
	return array( 'score' => $score, 'mine' => $dir );
}

function rsp_topic_score( $topic_id ) {
	return (int) get_post_meta( $topic_id, 'rsp_votes', true );
}

function rsp_user_topic_vote( $topic_id ) {
	if ( ! is_user_logged_in() ) {
		return 0;
	}
	$votes = (array) get_user_meta( get_current_user_id(), 'rsp_topic_votes', true );
	return (int) ( $votes[ $topic_id ] ?? 0 );
}

/**
 * Up/down arrows with the score between them.
 */
function rsp_vote_widget( $topic_id ) {
	$score = rsp_topic_score( $topic_id );
	$mine  = rsp_user_topic_vote( $topic_id );
	$arrow = fn( $d ) => '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path d="' . ( $d > 0 ? 'M12 5l7 9h-4.5v5h-5v-5H5z' : 'M12 19l-7-9h4.5V5h5v5H19z' ) . '" fill="currentColor"/></svg>';
	if ( ! is_user_logged_in() ) {
		return sprintf(
			'<div class="vote"><a class="vote__btn" href="%1$s" title="Войдите, чтобы голосовать">%2$s</a><b class="vote__score">%3$d</b><a class="vote__btn" href="%1$s" title="Войдите, чтобы голосовать">%4$s</a></div>',
			esc_url( wp_login_url( get_permalink( $topic_id ) ) ),
			$arrow( 1 ),
			$score,
			$arrow( -1 )
		);
	}
	return sprintf(
		'<div class="vote" data-vote-id="%1$d" data-mine="%2$d"><button type="button" class="vote__btn vote__up%3$s" data-dir="1" aria-label="Голос за">%4$s</button><b class="vote__score">%5$d</b><button type="button" class="vote__btn vote__down%6$s" data-dir="-1" aria-label="Голос против">%7$s</button></div>',
		(int) $topic_id,
		$mine,
		1 === $mine ? ' is-on' : '',
		$arrow( 1 ),
		$score,
		-1 === $mine ? ' is-on' : '',
		$arrow( -1 )
	);
}

/**
 * Threads of a forum ordered like Reddit. Sticky threads stay on top.
 *
 * @return WP_Post[]
 */
function rsp_forum_threads( $forum_id, $sort = 'hot', $limit = 30 ) {
	$topics = get_posts( array(
		'post_type'      => 'topic',
		'post_parent'    => $forum_id,
		'post_status'    => array( 'publish', 'closed' ),
		'posts_per_page' => 200,
	) );
	$sticky = function_exists( 'bbp_get_stickies' ) ? array_map( 'intval', bbp_get_stickies( $forum_id ) ) : array();
	$now    = time();
	$rank   = function ( $t ) use ( $sort, $now ) {
		$score = rsp_topic_score( $t->ID );
		$age_h = max( 0, ( $now - get_post_timestamp( $t ) ) / HOUR_IN_SECONDS );
		$last  = strtotime( (string) get_post_meta( $t->ID, '_bbp_last_active_time', true ) ) ?: get_post_timestamp( $t );
		switch ( $sort ) {
			case 'new':
				return get_post_timestamp( $t );
			case 'top':
				return $score * 1000 + (int) get_post_meta( $t->ID, '_bbp_reply_count', true );
			case 'active':
				return $last;
			default:
				$replies = (int) get_post_meta( $t->ID, '_bbp_reply_count', true );
				return ( $score + $replies * 0.5 + 1 ) / pow( $age_h + 2, 1.5 );
		}
	};
	usort( $topics, function ( $a, $b ) use ( $rank, $sticky ) {
		$sa = in_array( $a->ID, $sticky, true );
		$sb = in_array( $b->ID, $sticky, true );
		if ( $sa !== $sb ) {
			return $sa ? -1 : 1;
		}
		return $rank( $b ) <=> $rank( $a );
	} );
	return array_slice( $topics, 0, $limit );
}

// Score next to thread titles in bbPress lists, and on the thread page.
add_action( 'bbp_theme_before_topic_title', function () {
	printf( '<span class="vote-mini" title="Рейтинг ветки">%+d</span> ', rsp_topic_score( bbp_get_topic_id() ) );
} );
add_action( 'bbp_template_before_single_topic', function () {
	$id = bbp_get_topic_id();
	echo '<div class="thread-bar">' . rsp_vote_widget( $id ) . ( function_exists( 'rsp_bookmark_button' ) ? rsp_bookmark_button( $id ) : '' ); // phpcs:ignore
	$game = (int) get_post_meta( bbp_get_topic_forum_id( $id ), 'rsp_game_id', true );
	if ( $game ) {
		printf( '<a class="thread-bar__game" href="%s">← Карточка игры %s</a>', esc_url( get_permalink( $game ) ), esc_html( get_the_title( $game ) ) );
	}
	echo '</div>';
} );
