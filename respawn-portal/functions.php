<?php
/**
 * Respawn Portal theme setup and template helpers.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'after_setup_theme', function () {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ) );
	add_theme_support( 'bbpress' );

	add_image_size( 'rsp-card', 720, 405, true );
	add_image_size( 'rsp-lead', 1280, 720, true );

	register_nav_menus( array(
		'primary' => 'Главное меню',
		'footer'  => 'Меню в подвале',
	) );
} );

add_action( 'wp_enqueue_scripts', function () {
	$dir = get_template_directory();
	$uri = get_template_directory_uri();
	wp_enqueue_style( 'rsp-main', $uri . '/assets/css/main.css', array(), filemtime( $dir . '/assets/css/main.css' ) );
	wp_enqueue_script( 'rsp-main', $uri . '/assets/js/main.js', array(), filemtime( $dir . '/assets/js/main.js' ), array( 'strategy' => 'defer', 'in_footer' => true ) );
	if ( is_front_page() ) {
		wp_enqueue_style( 'rsp-intro', $uri . '/assets/css/intro.css', array( 'rsp-main' ), filemtime( $dir . '/assets/css/intro.css' ) );
		wp_enqueue_script( 'rsp-intro', $uri . '/assets/js/intro.js', array(), filemtime( $dir . '/assets/js/intro.js' ), array( 'strategy' => 'defer', 'in_footer' => true ) );
	}
	wp_enqueue_script( 'rsp-features', $uri . '/assets/js/features.js', array( 'rsp-main' ), filemtime( $dir . '/assets/js/features.js' ), array( 'strategy' => 'defer', 'in_footer' => true ) );
	wp_localize_script( 'rsp-main', 'RSP', array(
		'wp'     => esc_url_raw( rest_url( 'wp/v2/' ) ),
		'rest'   => esc_url_raw( rest_url( 'respawn/v1/' ) ),
		'nonce'  => wp_create_nonce( 'wp_rest' ),
		'postId' => is_singular( 'post' ) ? get_the_ID() : 0,
	) );
	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}
	// The block library is not used on the front end beyond post content; keep it, drop the global styles noise.
	wp_dequeue_style( 'classic-theme-styles' );
} );

// Set theme and "js" class before first paint so there is no flash.
add_action( 'wp_head', function () {
	echo "<script>(function(){var d=document.documentElement;d.classList.add('js');try{var t=localStorage.getItem('rsp-theme');if(t==='light'||t==='dark')d.dataset.theme=t;}catch(e){}})();</script>\n";
}, 1 );

add_action( 'wp_head', function () {
	foreach ( array( 'onest-cyrillic', 'unbounded-cyrillic' ) as $font ) {
		printf( '<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n", esc_url( get_template_directory_uri() . '/assets/fonts/' . $font . '.woff2' ) );
	}
	echo '<meta name="theme-color" content="#0F1114">' . "\n";
}, 2 );

// WordPress still prints its default colour and gradient presets for classic themes; the site uses only its own palette.
add_filter( 'wp_theme_json_data_default', function ( $theme_json ) {
	return $theme_json->update_with( array(
		'version'  => 3,
		'settings' => array(
			'color' => array(
				'palette'   => array(),
				'gradients' => array(),
				'duotone'   => array(),
			),
		),
	) );
} );

remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
remove_action( 'wp_print_styles', 'print_emoji_styles' );
remove_action( 'wp_head', 'wp_generator' );

// Readers and community authors browse the site without the admin toolbar.
add_filter( 'show_admin_bar', fn( $show ) => $show && current_user_can( 'edit_others_posts' ) );

add_filter( 'excerpt_length', fn() => 28 );
add_filter( 'excerpt_more', fn() => '…' );

add_action( 'widgets_init', function () {
	register_sidebar( array(
		'name'          => 'Боковая колонка',
		'id'            => 'sidebar',
		'description'   => 'Дополнительные виджеты под блоками «Популярное» и «Форум».',
		'before_widget' => '<section class="box %2$s">',
		'after_widget'  => '</section>',
		'before_title'  => '<h2 class="box__title">',
		'after_title'   => '</h2>',
	) );
} );

// Login and registration screens in the site's look.
add_action( 'login_enqueue_scripts', function () {
	wp_enqueue_style( 'rsp-login', get_template_directory_uri() . '/assets/css/login.css', array(), filemtime( get_template_directory() . '/assets/css/login.css' ) );
} );
add_filter( 'login_headerurl', fn() => home_url( '/' ) );
add_filter( 'login_headertext', fn() => get_bloginfo( 'name' ) );

/**
 * True when the Respawn Core plugin (likes, reviews, community) is active.
 */
function rsp_core() {
	return function_exists( 'rsp_like_button' );
}

/**
 * Category IDs never shown in lists: an empty "uncategorized".
 */
function rsp_hidden_categories() {
	$unc = get_category( 1 );
	return $unc && ! $unc->count && (int) get_option( 'default_category' ) !== 1 ? array( 1 ) : array();
}

/**
 * The category that labels a post: the first one other than the default.
 */
function rsp_primary_category( $post = null ) {
	$cats = get_the_category( get_post( $post )->ID );
	if ( ! $cats ) {
		return null;
	}
	$default = (int) get_option( 'default_category' );
	foreach ( $cats as $cat ) {
		if ( $cat->term_id !== $default && 'news' !== $cat->slug ) {
			return $cat;
		}
	}
	return $cats[0];
}

function rsp_category_label( $post = null ) {
	$cat = rsp_primary_category( $post );
	if ( ! $cat ) {
		return '';
	}
	return sprintf( '<a class="label" href="%s">%s</a>', esc_url( get_category_link( $cat ) ), esc_html( $cat->name ) );
}

/**
 * Post image, or an on-palette placeholder carrying the category name.
 */
function rsp_thumb( $post = null, $size = 'rsp-card', $link = true ) {
	$post = get_post( $post );
	if ( has_post_thumbnail( $post ) ) {
		$inner = get_the_post_thumbnail( $post, $size, array( 'class' => 'thumb__img', 'loading' => 'rsp-lead' === $size ? 'eager' : 'lazy', 'alt' => '' ) );
	} else {
		$cat   = rsp_primary_category( $post );
		$seed  = $post->ID % 4;
		$inner = sprintf( '<span class="thumb__ph thumb__ph--%d" aria-hidden="true"><span>%s</span></span>', $seed, esc_html( $cat ? $cat->name : get_bloginfo( 'name' ) ) );
	}
	if ( ! $link ) {
		return '<div class="thumb">' . $inner . '</div>';
	}
	return sprintf( '<a class="thumb" href="%s" tabindex="-1" aria-hidden="true">%s</a>', esc_url( get_permalink( $post ) ), $inner );
}

/**
 * "5 минут назад" for fresh posts, a date for older ones.
 */
function rsp_time( $post = null ) {
	$post = get_post( $post );
	$ts   = get_post_timestamp( $post );
	$diff = time() - $ts;
	$text = $diff < DAY_IN_SECONDS * 2 ? sprintf( '%s назад', human_time_diff( $ts ) ) : wp_date( 'j F Y', $ts );
	return sprintf( '<time datetime="%s">%s</time>', esc_attr( get_the_date( 'c', $post ) ), esc_html( $text ) );
}

function rsp_reading_time( $post = null ) {
	$words = count( preg_split( '/\s+/u', wp_strip_all_tags( get_post_field( 'post_content', $post ) ), -1, PREG_SPLIT_NO_EMPTY ) );
	return max( 1, (int) round( $words / 200 ) );
}

function rsp_icon( $name ) {
	$paths = array(
		'comment' => '<path d="M4 5h16v11H9l-5 4z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>',
		'eye'     => '<path d="M2 12s3.6-6.5 10-6.5S22 12 22 12s-3.6 6.5-10 6.5S2 12 2 12z" fill="none" stroke="currentColor" stroke-width="1.8"/><circle cx="12" cy="12" r="2.8" fill="currentColor"/>',
		'search'  => '<circle cx="11" cy="11" r="6.5" fill="none" stroke="currentColor" stroke-width="2"/><path d="m16 16 4.5 4.5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>',
		'menu'    => '<path d="M4 7h16M4 12h16M4 17h16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>',
		'close'   => '<path d="m6 6 12 12M18 6 6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>',
		'pen'     => '<path d="m4 20 1-4L16 5l3 3L8 19z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>',
		'sun'     => '<g fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="12" cy="12" r="4"/><path d="M12 2.5v2.5M12 19v2.5M2.5 12H5M19 12h2.5M5.3 5.3l1.8 1.8M16.9 16.9l1.8 1.8M18.7 5.3l-1.8 1.8M7.1 16.9l-1.8 1.8"/></g>',
		'moon'    => '<path d="M20 14.5A8 8 0 0 1 9.5 4a8 8 0 1 0 10.5 10.5z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>',
		'up'      => '<path d="m6 14 6-6 6 6" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>',
		'clock'   =>'<circle cx="12" cy="12" r="8" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M12 8v4l3 2" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>',
	);
	return '<svg class="icon" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true">' . ( $paths[ $name ] ?? '' ) . '</svg>';
}

/**
 * Meta line under a post: author, time, and real counters.
 */
function rsp_meta( $post = null, $with_author = true ) {
	$post  = get_post( $post );
	$parts = array();
	if ( $with_author ) {
		$parts[] = sprintf(
			'<a class="meta__author" href="%s">%s%s</a>',
			esc_url( get_author_posts_url( $post->post_author ) ),
			get_avatar( $post->post_author, 22, '', '', array( 'class' => 'meta__avatar' ) ),
			esc_html( get_the_author_meta( 'display_name', $post->post_author ) )
		);
	}
	$parts[] = rsp_time( $post );
	$counts  = '';
	if ( rsp_core() && ( $views = rsp_view_count( $post->ID ) ) ) {
		$counts .= sprintf( '<span title="Просмотры">%s%s</span>', rsp_icon( 'eye' ), esc_html( number_format_i18n( $views ) ) );
	}
	if ( $post->comment_count ) {
		$counts .= sprintf( '<a href="%s#comments" title="Комментарии">%s%d</a>', esc_url( get_permalink( $post ) ), rsp_icon( 'comment' ), (int) $post->comment_count );
	}
	if ( rsp_core() && ( $likes = rsp_like_count( $post->ID ) ) ) {
		$counts .= sprintf( '<span class="meta__likes" title="Лайки">♥ %d</span>', $likes );
	}
	if ( $counts ) {
		$parts[] = '<span class="meta__counts">' . $counts . '</span>';
	}
	return '<div class="meta">' . implode( '', $parts ) . '</div>';
}

function rsp_score_badge( $post = null ) {
	if ( ! rsp_core() || ! ( $review = rsp_get_review( $post ) ) ) {
		return '';
	}
	$title = 'critics' === get_post_meta( get_post( $post )->ID, 'rsp_score_source', true ) ? 'Средняя оценка критиков' : 'Оценка редакции';
	return sprintf( '<span class="score score--sm" title="%s">%s</span>', esc_attr( $title ), esc_html( rsp_format_score( $review['score'] ) ) );
}

/**
 * Site-wide counters for the home page, cached for ten minutes.
 */
function rsp_site_stats() {
	$stats = get_transient( 'rsp_stats' );
	if ( false === $stats ) {
		$users = count_users();
		$stats = array(
			'posts'    => (int) wp_count_posts()->publish,
			'members'  => (int) $users['total_users'],
			'comments' => (int) get_comments( array( 'status' => 'approve', 'count' => true ) ),
			'topics'   => function_exists( 'bbp_get_total_topic_count' ) ? (int) bbp_get_total_topic_count() : 0,
		);
		set_transient( 'rsp_stats', $stats, 10 * MINUTE_IN_SECONDS );
	}
	return $stats;
}

add_action( 'transition_post_status', function ( $new, $old ) {
	if ( $new !== $old ) {
		delete_transient( 'rsp_stats' );
	}
}, 10, 2 );

/**
 * Fallback for the primary menu: top-level categories with posts, plus the forum.
 */
function rsp_menu_fallback() {
	echo '<ul class="nav__list">';
	foreach ( get_categories( array( 'parent' => 0, 'hide_empty' => true, 'exclude' => rsp_hidden_categories() ) ) as $cat ) {
		printf( '<li><a href="%s">%s</a></li>', esc_url( get_category_link( $cat ) ), esc_html( $cat->name ) );
	}
	if ( post_type_exists( 'game' ) ) {
		printf( '<li><a href="%s">Игры</a></li>', esc_url( get_post_type_archive_link( 'game' ) ) );
	}
	if ( function_exists( 'bbp_get_forums_url' ) ) {
		printf( '<li><a href="%s">Форум</a></li>', esc_url( bbp_get_forums_url() ) );
	}
	echo '</ul>';
}

function rsp_pagination() {
	the_posts_pagination( array(
		'mid_size'           => 1,
		'prev_text'          => 'Назад',
		'next_text'          => 'Дальше',
		'screen_reader_text' => 'Страницы',
		'class'              => 'pager',
	) );
}

/**
 * Comment markup with avatar, author rating and a like button.
 */
function rsp_comment( $comment, $args, $depth ) {
	$tag = 'div' === $args['style'] ? 'div' : 'li';
	?>
	<<?php echo $tag; ?> id="comment-<?php comment_ID(); ?>" <?php comment_class( 'comment' ); ?>>
		<article class="comment__body">
			<header class="comment__head">
				<?php echo get_avatar( $comment, 36, '', '', array( 'class' => 'comment__avatar' ) ); ?>
				<div>
					<?php if ( $comment->user_id ) : ?>
						<a class="comment__author" href="<?php echo esc_url( get_author_posts_url( $comment->user_id ) ); ?>"><?php comment_author(); ?></a>
					<?php else : ?>
						<span class="comment__author"><?php comment_author(); ?></span>
					<?php endif; ?>
					<a class="comment__time" href="<?php echo esc_url( get_comment_link( $comment ) ); ?>"><time datetime="<?php comment_time( 'c' ); ?>"><?php printf( '%s назад', esc_html( human_time_diff( get_comment_time( 'U' ) ) ) ); ?></time></a>
				</div>
			</header>
			<?php if ( '0' === $comment->comment_approved ) : ?>
				<p class="comment__pending">Комментарий ожидает проверки модератором.</p>
			<?php endif; ?>
			<div class="comment__text prose"><?php comment_text(); ?></div>
			<footer class="comment__actions">
				<?php
				if ( rsp_core() && '1' === (string) $comment->comment_approved ) {
					echo rsp_like_button( $comment->comment_ID, 'comment' ); // phpcs:ignore WordPress.Security.EscapeOutput -- built with escaping.
				}
				comment_reply_link( array_merge( $args, array(
					'depth'      => $depth,
					'reply_text' => 'Ответить',
					'login_text' => 'Войдите, чтобы ответить',
				) ) );
				edit_comment_link( 'Изменить' );
				?>
			</footer>
		</article>
	<?php
}
