<?php
/**
 * Bookmarks: articles, games and threads the member saved.
 */

get_header();
$ids = function_exists( 'rsp_user_bookmarks' ) ? rsp_user_bookmarks() : array();
?>
<div class="wrap page-wrap">
	<header class="page-head">
		<h1 class="page-head__title">Закладки</h1>
		<p class="page-head__desc">Материалы, игры и ветки форума, которые вы сохранили, чтобы вернуться к ним позже.</p>
	</header>

	<?php if ( ! is_user_logged_in() ) : ?>
		<div class="empty">
			<p class="empty__title">Войдите, чтобы пользоваться закладками</p>
			<p>Закладки хранятся в вашем профиле и доступны с любого устройства.</p>
			<a class="btn btn--primary" href="<?php echo esc_url( wp_login_url( rsp_bookmarks_url() ) ); ?>">Войти</a>
		</div>
	<?php elseif ( ! $ids ) : ?>
		<div class="empty">
			<p class="empty__title">Закладок пока нет</p>
			<p>Нажмите «В закладки» под статьёй, на странице игры или в ветке форума.</p>
		</div>
	<?php else : ?>
		<?php
		$items  = get_posts( array( 'post_type' => array( 'post', 'game', 'topic' ), 'post_status' => array( 'publish', 'closed' ), 'post__in' => $ids, 'orderby' => 'post__in', 'posts_per_page' => -1 ) );
		$groups = array( 'post' => 'Статьи', 'game' => 'Игры', 'topic' => 'Ветки форума' );
		foreach ( $groups as $type => $label ) :
			$list = array_filter( $items, fn( $p ) => $type === $p->post_type );
			if ( ! $list ) {
				continue;
			}
			?>
			<section class="bm-group">
				<h2 class="section-title"><?php echo esc_html( $label ); ?> <span class="muted"><?php echo count( $list ); ?></span></h2>
				<ul class="bm-list">
					<?php foreach ( $list as $p ) : ?>
						<li class="bm-item">
							<a class="bm-item__thumb" href="<?php echo esc_url( get_permalink( $p ) ); ?>" tabindex="-1" aria-hidden="true"><?php echo has_post_thumbnail( $p ) ? get_the_post_thumbnail( $p, 'thumbnail', array( 'alt' => '' ) ) : '<span class="thumb__ph thumb__ph--' . (int) ( $p->ID % 4 ) . '"></span>'; ?></a>
							<div class="bm-item__body">
								<a class="bm-item__title" href="<?php echo esc_url( get_permalink( $p ) ); ?>"><?php echo esc_html( get_the_title( $p ) ); ?></a>
								<span class="muted"><?php echo esc_html( get_the_date( 'j F Y', $p ) ); ?></span>
							</div>
							<?php echo rsp_bookmark_button( $p->ID, true ); // phpcs:ignore ?>
						</li>
					<?php endforeach; ?>
				</ul>
			</section>
		<?php endforeach; ?>
	<?php endif; ?>
</div>
<?php
get_footer();
