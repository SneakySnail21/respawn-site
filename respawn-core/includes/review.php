<?php
/**
 * Review data for posts: score, verdict, pros, cons and game facts.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Review fields: meta key => label, type.
 */
function rsp_review_fields() {
	return array(
		'rsp_score'     => array( 'Оценка (0–10)', 'number' ),
		'rsp_verdict'   => array( 'Вердикт в одну фразу', 'text' ),
		'rsp_pros'      => array( 'Плюсы (каждый с новой строки)', 'textarea' ),
		'rsp_cons'      => array( 'Минусы (каждый с новой строки)', 'textarea' ),
		'rsp_game'      => array( 'Игра', 'text' ),
		'rsp_platforms' => array( 'Платформы', 'text' ),
		'rsp_developer' => array( 'Разработчик', 'text' ),
		'rsp_release'   => array( 'Дата выхода', 'text' ),
	);
}

add_action( 'init', function () {
	foreach ( rsp_review_fields() as $key => $field ) {
		register_post_meta( 'post', $key, array(
			'type'              => 'number' === $field[1] ? 'number' : 'string',
			'single'            => true,
			'show_in_rest'      => true,
			'sanitize_callback' => 'number' === $field[1] ? 'rsp_sanitize_score' : ( 'textarea' === $field[1] ? 'sanitize_textarea_field' : 'sanitize_text_field' ),
			'auth_callback'     => fn() => current_user_can( 'edit_posts' ),
		) );
	}
	foreach ( array( 'rsp_likes', 'rsp_views' ) as $key ) {
		register_post_meta( 'post', $key, array(
			'type'          => 'integer',
			'single'        => true,
			'default'       => 0,
			'show_in_rest'  => array( 'schema' => array( 'readonly' => true ) ),
			'auth_callback' => '__return_false',
		) );
	}
} );

function rsp_sanitize_score( $value ) {
	if ( '' === $value || null === $value ) {
		return '';
	}
	return max( 0, min( 10, round( (float) str_replace( ',', '.', $value ), 1 ) ) );
}

add_action( 'add_meta_boxes_post', function () {
	add_meta_box( 'rsp-review', 'Обзор: оценка и данные об игре', 'rsp_review_metabox', 'post', 'normal', 'high' );
} );

function rsp_review_metabox( $post ) {
	wp_nonce_field( 'rsp_review_save', 'rsp_review_nonce' );
	echo '<p style="color:#646970">Заполните, если материал является обзором игры. Пустая оценка означает, что блок обзора не показывается.</p>';
	echo '<table class="form-table" role="presentation"><tbody>';
	foreach ( rsp_review_fields() as $key => $field ) {
		$value = get_post_meta( $post->ID, $key, true );
		printf( '<tr><th><label for="%1$s">%2$s</label></th><td>', esc_attr( $key ), esc_html( $field[0] ) );
		if ( 'textarea' === $field[1] ) {
			printf( '<textarea id="%1$s" name="%1$s" rows="4" class="large-text">%2$s</textarea>', esc_attr( $key ), esc_textarea( $value ) );
		} elseif ( 'number' === $field[1] ) {
			printf( '<input id="%1$s" name="%1$s" type="number" min="0" max="10" step="0.1" value="%2$s" style="width:6em">', esc_attr( $key ), esc_attr( $value ) );
		} else {
			printf( '<input id="%1$s" name="%1$s" type="text" value="%2$s" class="regular-text">', esc_attr( $key ), esc_attr( $value ) );
		}
		echo '</td></tr>';
	}
	echo '</tbody></table>';
}

add_action( 'save_post_post', function ( $post_id ) {
	if ( ! isset( $_POST['rsp_review_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['rsp_review_nonce'] ), 'rsp_review_save' ) ) {
		return;
	}
	if ( wp_is_post_autosave( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	foreach ( rsp_review_fields() as $key => $field ) {
		$raw = isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : '';
		if ( 'number' === $field[1] ) {
			$value = rsp_sanitize_score( $raw );
		} elseif ( 'textarea' === $field[1] ) {
			$value = sanitize_textarea_field( $raw );
		} else {
			$value = sanitize_text_field( $raw );
		}
		if ( '' === $value ) {
			delete_post_meta( $post_id, $key );
		} else {
			update_post_meta( $post_id, $key, $value );
		}
	}
} );

/**
 * Review data for a post, or null when the post is not a scored review.
 */
function rsp_get_review( $post = null ) {
	$post  = get_post( $post );
	$score = $post ? get_post_meta( $post->ID, 'rsp_score', true ) : '';
	if ( '' === $score ) {
		return null;
	}
	$lines = fn( $key ) => array_values( array_filter( array_map( 'trim', explode( "\n", (string) get_post_meta( $post->ID, $key, true ) ) ) ) );
	return array(
		'score'     => (float) $score,
		'verdict'   => get_post_meta( $post->ID, 'rsp_verdict', true ),
		'pros'      => $lines( 'rsp_pros' ),
		'cons'      => $lines( 'rsp_cons' ),
		'game'      => get_post_meta( $post->ID, 'rsp_game', true ),
		'platforms' => get_post_meta( $post->ID, 'rsp_platforms', true ),
		'developer' => get_post_meta( $post->ID, 'rsp_developer', true ),
		'release'   => get_post_meta( $post->ID, 'rsp_release', true ),
	);
}

/**
 * Score formatted for display: 8.5, 10, 7.
 */
function rsp_format_score( $score ) {
	return rtrim( rtrim( number_format( (float) $score, 1, ',', '' ), '0' ), ',' );
}
