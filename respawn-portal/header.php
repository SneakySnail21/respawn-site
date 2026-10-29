<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip" href="#content">Перейти к содержимому</a>
<?php if ( is_singular( 'post' ) ) : ?><div class="progress" aria-hidden="true"></div><?php endif; ?>

<header class="site-header">
	<div class="wrap site-header__bar">
		<button class="icon-btn site-header__burger" type="button" aria-controls="site-nav" aria-expanded="false" aria-label="Меню" data-toggle-nav>
			<?php echo rsp_icon( 'menu' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		</button>

		<a class="logo" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home" aria-label="<?php bloginfo( 'name' ); ?>, на главную">
			<span class="logo__mark" aria-hidden="true"></span><span class="logo__text">Respawn</span>
		</a>

		<nav class="nav" id="site-nav" aria-label="Разделы">
			<?php
			wp_nav_menu( array(
				'theme_location' => 'primary',
				'container'      => false,
				'menu_class'     => 'nav__list',
				'depth'          => 1,
				'fallback_cb'    => 'rsp_menu_fallback',
			) );
			?>
		</nav>

		<div class="site-header__actions">
			<button class="icon-btn" type="button" aria-controls="site-search" aria-expanded="false" aria-label="Поиск" data-toggle-search>
				<?php echo rsp_icon( 'search' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			</button>
			<button class="icon-btn theme-toggle" type="button" aria-label="Сменить тему" data-theme-toggle>
				<?php echo str_replace( 'class="icon"', 'class="icon icon--sun"', rsp_icon( 'sun' ) ) . str_replace( 'class="icon"', 'class="icon icon--moon"', rsp_icon( 'moon' ) ); // phpcs:ignore ?>
			</button>
			<?php if ( function_exists( 'rsp_write_url' ) ) : ?>
				<a class="btn btn--primary site-header__write" href="<?php echo esc_url( rsp_write_url() ); ?>"><?php echo rsp_icon( 'pen' ); // phpcs:ignore ?><span>Написать</span></a>
			<?php endif; ?>
			<?php if ( is_user_logged_in() ) : $me = wp_get_current_user(); ?>
				<details class="usermenu">
					<summary aria-label="Меню профиля"><?php echo get_avatar( $me->ID, 32, '', '', array( 'class' => 'usermenu__avatar' ) ); ?></summary>
					<div class="usermenu__panel">
						<p class="usermenu__name"><?php echo esc_html( $me->display_name ); ?>
							<?php if ( rsp_core() ) : ?><span class="usermenu__karma">Рейтинг <?php echo esc_html( rsp_user_karma( $me->ID ) ); ?></span><?php endif; ?>
						</p>
						<a href="<?php echo esc_url( get_author_posts_url( $me->ID ) ); ?>">Мой профиль</a>
						<?php if ( function_exists( 'rsp_my_feed_url' ) ) : ?>
							<a href="<?php echo esc_url( rsp_my_feed_url() ); ?>">Моя лента</a>
							<a href="<?php echo esc_url( rsp_bookmarks_url() ); ?>">Закладки</a>
						<?php endif; ?>
						<?php if ( current_user_can( 'edit_posts' ) ) : ?>
							<a href="<?php echo esc_url( function_exists( 'rsp_page_url' ) ? add_query_arg( 'tab', 'mine', rsp_page_url( 'write' ) ) : admin_url( 'edit.php?author=' . $me->ID ) ); ?>">Мои статьи и черновики</a>
						<?php endif; ?>
						<?php if ( function_exists( 'bbp_get_user_profile_url' ) ) : ?>
							<a href="<?php echo esc_url( bbp_get_user_profile_url( $me->ID ) ); ?>">Профиль на форуме</a>
						<?php endif; ?>
						<a href="<?php echo esc_url( admin_url( 'profile.php' ) ); ?>">Настройки</a>
						<a href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>">Выйти</a>
					</div>
				</details>
			<?php else : ?>
				<a class="btn btn--ghost" href="<?php echo esc_url( wp_login_url( is_singular() ? get_permalink() : home_url( '/' ) ) ); ?>">Войти</a>
			<?php endif; ?>
		</div>
	</div>

	<div class="site-search" id="site-search" hidden>
		<div class="wrap"><?php get_search_form(); ?><div class="search-live" id="search-live" aria-live="polite"></div></div>
	</div>
</header>

<?php
$ticker = get_posts( array( 'posts_per_page' => 8, 'ignore_sticky_posts' => true ) );
if ( $ticker && ! is_singular( 'post' ) ) :
	?>
	<div class="ticker" aria-label="Последние материалы">
		<div class="wrap ticker__in">
			<span class="ticker__tag"><span class="ticker__dot"></span>Свежее</span>
			<div class="ticker__view">
				<div class="ticker__track">
					<?php for ( $half = 0; $half < 2; $half++ ) : ?>
						<div class="ticker__half"<?php echo $half ? ' aria-hidden="true"' : ''; ?>>
							<?php foreach ( $ticker as $tp ) : ?>
								<a href="<?php echo esc_url( get_permalink( $tp ) ); ?>"<?php echo $half ? ' tabindex="-1"' : ''; ?>><?php echo esc_html( get_the_title( $tp ) ); ?></a>
							<?php endforeach; ?>
						</div>
					<?php endfor; ?>
				</div>
			</div>
		</div>
	</div>
<?php endif; ?>

<main id="content" class="site-main">
