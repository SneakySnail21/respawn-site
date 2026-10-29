<?php
/**
 * Optional membership on the front end: registration with a password chosen on the spot (no e-mail needed),
 * login, a "write" page where members submit articles for moderation, and a "my materials" list.
 * Readers never need an account; accounts unlock articles, forum posting, ratings, likes and bookmarks.
 */

defined( 'ABSPATH' ) || exit;

/* ---------- Helpers ---------- */

function rsp_page_url( $slug ) {
	$page = get_page_by_path( $slug );
	return $page ? get_permalink( $page ) : home_url( '/' . $slug . '/' );
}

function rsp_ip() {
	return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '0';
}

/**
 * Latin slug from a Russian display name, unique among users.
 */
function rsp_translit( $text ) {
	static $map = array(
		'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'e', 'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'y',
		'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f',
		'х' => 'kh', 'ц' => 'ts', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'shch', 'ъ' => '', 'ы' => 'y', 'ь' => '', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
	);
	return strtr( mb_strtolower( $text ), $map );
}

function rsp_unique_nicename( $name ) {
	$base = sanitize_title( rsp_translit( $name ) ) ?: 'user';
	$slug = $base;
	$i    = 1;
	while ( get_user_by( 'slug', $slug ) ) {
		$slug = $base . '-' . ++$i;
	}
	return $slug;
}

/**
 * Where to send the member after a form: a validated redirect_to or a default.
 */
function rsp_after_redirect( $default ) {
	$to = isset( $_REQUEST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_REQUEST['redirect_to'] ) ) : '';
	return $to ? wp_validate_redirect( $to, $default ) : $default;
}

/**
 * Form messages shared between the handler and the template.
 */
function rsp_form_errors( $set = null ) {
	static $errors = array();
	if ( null !== $set ) {
		$errors = (array) $set;
	}
	return $errors;
}

function rsp_old( $key ) {
	return isset( $_POST[ $key ] ) ? esc_attr( wp_unslash( $_POST[ $key ] ) ) : '';
}

/**
 * Tiny arithmetic question with a signed token: stops simple bots without a third-party captcha.
 */
function rsp_captcha_field() {
	$a       = wp_rand( 2, 9 );
	$b       = wp_rand( 2, 9 );
	$payload = $a . ':' . $b . ':' . time();
	return sprintf(
		'<div class="field"><label for="rsp_q">Проверка: сколько будет %d + %d?</label><input id="rsp_q" name="rsp_q" type="text" inputmode="numeric" autocomplete="off" required><input type="hidden" name="rsp_qt" value="%s"></div>',
		$a,
		$b,
		esc_attr( $payload . '.' . wp_hash( $payload ) )
	);
}

function rsp_captcha_ok() {
	$token = isset( $_POST['rsp_qt'] ) ? sanitize_text_field( wp_unslash( $_POST['rsp_qt'] ) ) : '';
	$ans   = isset( $_POST['rsp_q'] ) ? (int) $_POST['rsp_q'] : -1;
	$parts = explode( '.', $token );
	if ( 2 !== count( $parts ) || ! hash_equals( wp_hash( $parts[0] ), $parts[1] ) ) {
		return false;
	}
	list( $a, $b, $ts ) = array_map( 'intval', explode( ':', $parts[0] ) + array( 0, 0, 0 ) );
	$age = time() - $ts;
	return $age >= 3 && $age <= HOUR_IN_SECONDS && $ans === $a + $b;
}

/* ---------- URLs ---------- */

add_filter( 'register_url', fn() => rsp_page_url( 'register' ) );
add_filter( 'login_url', function ( $url, $redirect ) {
	$login = rsp_page_url( 'login' );
	return $redirect ? add_query_arg( 'redirect_to', rawurlencode( $redirect ), $login ) : $login;
}, 10, 2 );

function rsp_write_url() {
	if ( ! is_user_logged_in() ) {
		return rsp_page_url( 'register' );
	}
	return rsp_page_url( 'write' );
}

/* ---------- Registration ---------- */

add_action( 'template_redirect', function () {
	if ( ! is_page( 'register' ) ) {
		return;
	}
	if ( is_user_logged_in() ) {
		wp_safe_redirect( rsp_page_url( 'write' ) );
		exit;
	}
	if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || empty( $_POST['rsp_register'] ) ) {
		return;
	}
	$errors = array();
	if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ), 'rsp_register' ) ) {
		$errors[] = 'Страница устарела. Обновите её и попробуйте ещё раз.';
	}
	if ( ! empty( $_POST['rsp_website'] ) ) {
		$errors[] = 'Регистрация отклонена. Попробуйте ещё раз.';
	}
	if ( ! get_option( 'users_can_register' ) ) {
		$errors[] = 'Регистрация сейчас закрыта.';
	}
	$name  = trim( sanitize_text_field( wp_unslash( $_POST['rsp_name'] ?? '' ) ) );
	$email = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
	$pass  = (string) wp_unslash( $_POST['password'] ?? '' );
	if ( mb_strlen( $name ) < 2 || mb_strlen( $name ) > 30 ) {
		$errors[] = 'Имя должно содержать от 2 до 30 символов.';
	}
	if ( ! is_email( $email ) ) {
		$errors[] = 'Укажите настоящий адрес почты.';
	} elseif ( email_exists( $email ) ) {
		$errors[] = 'Этот адрес уже зарегистрирован. Попробуйте войти.';
	}
	if ( mb_strlen( $pass ) < 8 ) {
		$errors[] = 'Пароль должен быть не короче 8 символов.';
	}
	if ( empty( $_POST['agree'] ) ) {
		$errors[] = 'Нужно согласиться с правилами сообщества.';
	}
	if ( ! rsp_captcha_ok() ) {
		$errors[] = 'Неверный ответ на проверочный вопрос.';
	}
	$key = 'rsp_reg_' . md5( rsp_ip() );
	if ( (int) get_transient( $key ) >= 5 ) {
		$errors[] = 'С вашего адреса слишком много регистраций. Попробуйте позже.';
	}
	if ( $errors ) {
		rsp_form_errors( $errors );
		return;
	}

	do {
		$login = 'u' . strtolower( wp_generate_password( 9, false, false ) );
	} while ( username_exists( $login ) );
	$id = wp_insert_user( array(
		'user_login'    => $login,
		'user_pass'     => $pass,
		'user_email'    => $email,
		'user_nicename' => rsp_unique_nicename( $name ),
		'display_name'  => $name,
		'nickname'      => $name,
		'role'          => get_option( 'default_role', 'contributor' ),
	) );
	if ( is_wp_error( $id ) ) {
		rsp_form_errors( array( 'Не получилось создать аккаунт. Попробуйте ещё раз.' ) );
		return;
	}
	set_transient( $key, (int) get_transient( $key ) + 1, HOUR_IN_SECONDS );
	update_user_meta( $id, 'rsp_karma', 0 );
	wp_set_current_user( $id );
	wp_set_auth_cookie( $id, true, is_ssl() );
	do_action( 'wp_login', $login, get_userdata( $id ) );
	wp_safe_redirect( add_query_arg( 'welcome', 1, rsp_after_redirect( rsp_page_url( 'write' ) ) ) );
	exit;
} );

/* ---------- Login ---------- */

add_action( 'template_redirect', function () {
	if ( ! is_page( 'login' ) ) {
		return;
	}
	if ( is_user_logged_in() ) {
		wp_safe_redirect( rsp_after_redirect( home_url( '/' ) ) );
		exit;
	}
	if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || empty( $_POST['rsp_login'] ) ) {
		return;
	}
	if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ), 'rsp_login' ) ) {
		rsp_form_errors( array( 'Страница устарела. Обновите её и попробуйте ещё раз.' ) );
		return;
	}
	$login = trim( sanitize_text_field( wp_unslash( $_POST['login'] ?? '' ) ) );
	$pass  = (string) wp_unslash( $_POST['password'] ?? '' );
	$key   = 'rsp_fail_' . md5( rsp_ip() . '|' . strtolower( $login ) );
	if ( (int) get_transient( $key ) >= 5 ) {
		rsp_form_errors( array( 'Слишком много неудачных попыток. Подождите 15 минут или восстановите пароль.' ) );
		return;
	}
	$user = wp_signon( array( 'user_login' => $login, 'user_password' => $pass, 'remember' => ! empty( $_POST['remember'] ) ), is_ssl() );
	if ( is_wp_error( $user ) ) {
		set_transient( $key, (int) get_transient( $key ) + 1, 15 * MINUTE_IN_SECONDS );
		rsp_form_errors( array( 'Неверный логин или пароль.' ) );
		return;
	}
	delete_transient( $key );
	wp_safe_redirect( rsp_after_redirect( home_url( '/' ) ) );
	exit;
} );

/* ---------- Writing articles ---------- */

function rsp_status_label( $status ) {
	return array(
		'publish' => 'Опубликовано',
		'pending' => 'На проверке',
		'draft'   => 'Черновик',
		'future'  => 'По расписанию',
	)[ $status ] ?? $status;
}

add_action( 'template_redirect', function () {
	if ( ! is_page( 'write' ) || ! is_user_logged_in() || ! current_user_can( 'edit_posts' ) ) {
		return;
	}
	if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
		return;
	}
	$uid = get_current_user_id();

	// Delete one of your own drafts.
	if ( ! empty( $_POST['rsp_delete'] ) ) {
		$pid = (int) $_POST['rsp_delete'];
		$p   = get_post( $pid );
		if ( isset( $_POST['_wpnonce'] ) && wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ), 'rsp_delete_' . $pid ) && $p && (int) $p->post_author === $uid && in_array( $p->post_status, array( 'draft', 'pending' ), true ) ) {
			wp_trash_post( $pid );
		}
		wp_safe_redirect( add_query_arg( array( 'tab' => 'mine', 'deleted' => 1 ), rsp_page_url( 'write' ) ) );
		exit;
	}

	if ( empty( $_POST['rsp_write'] ) ) {
		return;
	}
	$errors = array();
	if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ), 'rsp_write' ) ) {
		$errors[] = 'Страница устарела. Скопируйте текст, обновите страницу и отправьте ещё раз.';
	}
	$pid     = (int) ( $_POST['post_id'] ?? 0 );
	$title   = trim( sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) ) );
	$content = wp_kses_post( wp_unslash( $_POST['content'] ?? '' ) );
	$draft   = ! empty( $_POST['save_draft'] );
	$text    = trim( wp_strip_all_tags( $content ) );
	if ( mb_strlen( $title ) < 6 || mb_strlen( $title ) > 150 ) {
		$errors[] = 'Заголовок должен содержать от 6 до 150 символов.';
	}
	if ( ! $draft && mb_strlen( $text ) < 300 ) {
		$errors[] = 'Текст слишком короткий: напишите хотя бы несколько абзацев (от 300 символов).';
	}
	if ( mb_strlen( $text ) > 60000 ) {
		$errors[] = 'Текст слишком длинный.';
	}
	$existing = null;
	if ( $pid ) {
		$existing = get_post( $pid );
		if ( ! $existing || (int) $existing->post_author !== $uid || ! in_array( $existing->post_status, array( 'draft', 'pending' ), true ) ) {
			$errors[] = 'Эту статью нельзя изменить.';
		}
	} elseif ( ! $draft && count( get_posts( array( 'author' => $uid, 'post_status' => 'pending', 'fields' => 'ids', 'posts_per_page' => 10 ) ) ) >= 5 ) {
		$errors[] = 'У вас уже пять статей на проверке. Дождитесь решения редакции.';
	}
	$cover = ! empty( $_FILES['cover']['name'] ) && UPLOAD_ERR_NO_FILE !== (int) $_FILES['cover']['error'];
	if ( $cover ) {
		$check = wp_check_filetype_and_ext( $_FILES['cover']['tmp_name'], $_FILES['cover']['name'], array( 'jpg|jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp' ) );
		if ( UPLOAD_ERR_OK !== (int) $_FILES['cover']['error'] || empty( $check['type'] ) ) {
			$errors[] = 'Обложка должна быть изображением JPG, PNG или WebP.';
		} elseif ( $_FILES['cover']['size'] > 3 * MB_IN_BYTES ) {
			$errors[] = 'Обложка слишком большая: максимум 3 МБ.';
		}
	}
	if ( $errors ) {
		rsp_form_errors( $errors );
		return;
	}

	$blogs = get_term_by( 'slug', 'blogs', 'category' );
	$args  = array(
		'post_type'     => 'post',
		'post_status'   => $draft ? 'draft' : 'pending',
		'post_author'   => $uid,
		'post_title'    => $title,
		'post_content'  => $content,
		'post_excerpt'  => wp_trim_words( $text, 28, '…' ),
		'post_category' => $blogs ? array( $blogs->term_id ) : array(),
		'tags_input'    => array_slice( array_filter( array_map( 'trim', explode( ',', sanitize_text_field( wp_unslash( $_POST['tags'] ?? '' ) ) ) ) ), 0, 6 ),
	);
	if ( $existing ) {
		$args['ID'] = $pid;
	}
	$saved = wp_insert_post( $args, true );
	if ( is_wp_error( $saved ) ) {
		rsp_form_errors( array( 'Не получилось сохранить статью. Попробуйте ещё раз.' ) );
		return;
	}
	$game = (int) ( $_POST['game_id'] ?? 0 );
	if ( $game && 'game' === get_post_type( $game ) && 'publish' === get_post_status( $game ) ) {
		update_post_meta( $saved, 'rsp_game_id', $game );
	} else {
		delete_post_meta( $saved, 'rsp_game_id' );
	}
	if ( $cover ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$att = media_handle_upload( 'cover', $saved );
		if ( ! is_wp_error( $att ) ) {
			set_post_thumbnail( $saved, $att );
		}
	}
	wp_safe_redirect( add_query_arg( array( 'tab' => 'mine', 'saved' => $draft ? 'draft' : 'pending' ), rsp_page_url( 'write' ) ) );
	exit;
} );

/* ---------- Keep members out of the dashboard ---------- */

add_action( 'admin_init', function () {
	if ( wp_doing_ajax() || wp_doing_cron() || current_user_can( 'edit_others_posts' ) || ! is_user_logged_in() ) {
		return;
	}
	$script = basename( $_SERVER['PHP_SELF'] ?? '' );
	if ( in_array( $script, array( 'profile.php', 'admin-post.php', 'admin-ajax.php', 'async-upload.php', 'media-upload.php' ), true ) ) {
		return;
	}
	wp_safe_redirect( rsp_page_url( 'write' ) );
	exit;
} );

// Personal pages are not for search engines or the public index.
add_filter( 'wp_robots', function ( $robots ) {
	if ( is_page( array( 'login', 'register', 'write', 'bookmarks', 'my-feed' ) ) ) {
		$robots['noindex']  = true;
		$robots['nofollow'] = true;
		unset( $robots['max-image-preview'] );
	}
	return $robots;
} );
