<?php
/**
 * Game catalogue: cards with cover, player rating, release and forum activity.
 */

get_header();

$sort  = isset( $_GET['sort'] ) ? sanitize_key( $_GET['sort'] ) : 'popular'; // phpcs:ignore WordPress.Security.NonceVerification
$tabs  = array(
	'popular' => 'Популярные',
	'new'     => 'Недавно вышли',
	'soon'    => 'Скоро выйдут',
	'top'     => 'Лучшие по оценкам',
);
$base  = get_post_type_archive_link( 'game' );
?>

<div class="wrap games-wrap">
	<header class="page-head">
		<h1 class="page-head__title">Игры</h1>
		<p class="page-head__desc">Карточки популярных и новых игр. У каждой — оценки игроков и свой раздел на форуме: главное обсуждение и ветки участников.</p>
	</header>

	<nav class="tabs" aria-label="Сортировка">
		<?php foreach ( $tabs as $key => $label ) : ?>
			<a class="tabs__item<?php echo $sort === $key ? ' is-active' : ''; ?>" href="<?php echo esc_url( 'popular' === $key ? $base : add_query_arg( 'sort', $key, $base ) ); ?>"<?php echo $sort === $key ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $label ); ?></a>
		<?php endforeach; ?>
	</nav>

	<?php if ( have_posts() ) : ?>
		<div class="game-grid">
			<?php
			while ( have_posts() ) :
				the_post();
				get_template_part( 'template-parts/game-card' );
			endwhile;
			?>
		</div>
		<?php rsp_pagination(); ?>
	<?php else : ?>
		<div class="empty">
			<p class="empty__title">Здесь пока пусто</p>
			<p>Игры в этой подборке скоро появятся.</p>
		</div>
	<?php endif; ?>
</div>

<?php
get_footer();
