<?php
/**
 * Home: lead story with the top headlines, reviews, fresh feed and sidebar.
 */

get_header();

$sticky  = array_slice( get_option( 'sticky_posts' ), 0, 1 );
$latest  = get_posts( array( 'posts_per_page' => 16, 'post__not_in' => $sticky ) );
$stories = array_merge( $sticky ? get_posts( array( 'post__in' => $sticky ) ) : array(), $latest );
$lead    = array_shift( $stories );
$top     = array_splice( $stories, 0, 5 );
$feed    = $stories;
$all_url = get_option( 'page_for_posts' ) ? get_permalink( get_option( 'page_for_posts' ) ) : home_url( '/' );

$reviews = rsp_core() ? get_posts( array( 'posts_per_page' => 4, 'meta_key' => 'rsp_score', 'meta_compare' => 'EXISTS' ) ) : array();
?>

<?php get_template_part( 'template-parts/intro' ); ?>

<?php $st = rsp_site_stats(); ?>
<section class="wrap hero-intro" id="explore" aria-label="О портале">
	<h1 class="hero-intro__title">Всё о играх: <span class="rotator" data-rotate='["новости","обзоры","гайды","киберспорт","обсуждения"]'>новости</span></h1>
	<p class="hero-intro__sub">Свободный игровой портал: читайте редакцию, пишите свои статьи, оценивайте материалы и спорьте на форуме.</p>
	<div class="stats">
		<div class="stat"><b data-count="<?php echo (int) $st['posts']; ?>">0</b><span>материалов</span></div>
		<div class="stat"><b data-count="<?php echo (int) $st['members']; ?>">0</b><span>участников</span></div>
		<div class="stat"><b data-count="<?php echo (int) $st['comments']; ?>">0</b><span>комментариев</span></div>
		<?php if ( $st['topics'] ) : ?><div class="stat"><b data-count="<?php echo (int) $st['topics']; ?>">0</b><span>тем на форуме</span></div><?php endif; ?>
	</div>
</section>

<?php if ( $lead ) : $GLOBALS['post'] = $lead; setup_postdata( $lead ); ?>
	<section class="wrap lead-grid" aria-label="Главное">
		<article <?php post_class( 'lead' ); ?>>
			<?php echo rsp_thumb( $lead, 'rsp-lead', false ); // phpcs:ignore ?>
			<div class="lead__body">
				<div class="row__top"><?php echo rsp_category_label(); // phpcs:ignore ?><?php echo rsp_score_badge(); // phpcs:ignore ?></div>
				<h1 class="lead__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h1>
				<p class="lead__excerpt"><?php echo esc_html( get_the_excerpt() ); ?></p>
				<?php echo rsp_meta(); // phpcs:ignore ?>
			</div>
		</article>

		<?php if ( $top ) : ?>
			<div class="toplist">
				<h2 class="section-title">Главное</h2>
				<ul>
					<?php foreach ( $top as $p ) : ?>
						<li class="toplist__item">
							<?php echo rsp_category_label( $p ); // phpcs:ignore ?>
							<a class="toplist__title" href="<?php echo esc_url( get_permalink( $p ) ); ?>"><?php echo esc_html( get_the_title( $p ) ); ?></a>
							<?php echo rsp_meta( $p, false ); // phpcs:ignore ?>
						</li>
					<?php endforeach; ?>
				</ul>
				<a class="box__more" href="<?php echo esc_url( $all_url ); ?>">Вся лента</a>
			</div>
		<?php endif; ?>
	</section>
<?php wp_reset_postdata(); endif; ?>

<?php if ( $reviews ) : ?>
	<section class="wrap reviews-strip" aria-labelledby="reviews-title">
		<div class="section-head">
			<h2 class="section-title" id="reviews-title">Обзоры</h2>
			<?php if ( $cat = get_category_by_slug( 'reviews' ) ) : ?><a class="box__more" href="<?php echo esc_url( get_category_link( $cat ) ); ?>">Все обзоры</a><?php endif; ?>
		</div>
		<div class="reviews-strip__track">
			<?php foreach ( $reviews as $p ) : $r = rsp_get_review( $p ); ?>
				<article class="review-tile">
					<?php echo rsp_thumb( $p ); // phpcs:ignore ?>
					<span class="score"><?php echo esc_html( rsp_format_score( $r['score'] ) ); ?></span>
					<h3 class="review-tile__title"><a href="<?php echo esc_url( get_permalink( $p ) ); ?>"><?php echo esc_html( $r['game'] ?: get_the_title( $p ) ); ?></a></h3>
					<?php if ( $r['verdict'] ) : ?><p class="review-tile__verdict"><?php echo esc_html( $r['verdict'] ); ?></p><?php endif; ?>
				</article>
			<?php endforeach; ?>
		</div>
	</section>
<?php endif; ?>

<?php
$hot_games = post_type_exists( 'game' ) ? get_posts( array( 'post_type' => 'game', 'posts_per_page' => 8, 'meta_key' => 'rsp_g_group', 'orderby' => array( 'meta_value' => 'DESC', 'menu_order' => 'ASC', 'date' => 'DESC' ) ) ) : array();
if ( $hot_games ) :
	?>
	<section class="wrap games-strip" aria-labelledby="games-title">
		<div class="section-head">
			<h2 class="section-title" id="games-title">Игры и сообщества</h2>
			<a class="box__more" href="<?php echo esc_url( get_post_type_archive_link( 'game' ) ); ?>">Все игры</a>
		</div>
		<div class="games-strip__track">
			<?php foreach ( $hot_games as $p ) : $GLOBALS['post'] = $p; setup_postdata( $p ); ?>
				<?php get_template_part( 'template-parts/game-card' ); ?>
			<?php endforeach; wp_reset_postdata(); ?>
		</div>
	</section>
<?php endif; ?>

<div class="wrap layout">
	<div class="layout__main">
		<?php if ( $feed ) : ?>
			<section aria-labelledby="feed-title">
				<div class="section-head">
					<h2 class="section-title" id="feed-title">Свежее</h2>
				</div>
				<div class="feed">
					<?php foreach ( $feed as $p ) : $GLOBALS['post'] = $p; setup_postdata( $p ); ?>
						<?php get_template_part( 'template-parts/row' ); ?>
					<?php endforeach; wp_reset_postdata(); ?>
				</div>
				<a class="btn btn--ghost btn--wide" href="<?php echo esc_url( $all_url ); ?>">Вся лента</a>
			</section>
		<?php endif; ?>

		<?php
		$blogs     = get_category_by_slug( 'blogs' );
		$community = $blogs ? get_posts( array( 'posts_per_page' => 4, 'cat' => $blogs->term_id ) ) : array();
		?>
		<section class="sections" aria-labelledby="sections-title">
			<div class="section-head"><h2 class="section-title" id="sections-title">Разделы</h2></div>
			<div class="cats">
				<?php foreach ( get_categories( array( 'exclude' => rsp_hidden_categories(), 'hide_empty' => false, 'parent' => 0 ) ) as $c ) : ?>
					<a class="cat-card" href="<?php echo esc_url( get_category_link( $c ) ); ?>" data-initial="<?php echo esc_attr( mb_strtoupper( mb_substr( $c->name, 0, 1 ) ) ); ?>">
						<span class="cat-card__name"><?php echo esc_html( $c->name ); ?></span>
						<span class="cat-card__count"><?php echo esc_html( $c->count ? sprintf( 'Материалов: %d', $c->count ) : 'Скоро' ); ?></span>
					</a>
				<?php endforeach; ?>
			</div>
		</section>

		<section class="community" aria-labelledby="community-title">
			<div class="section-head">
				<h2 class="section-title" id="community-title">Блоги участников</h2>
				<?php if ( $community ) : ?><a class="box__more" href="<?php echo esc_url( get_category_link( $blogs ) ); ?>">Все блоги</a><?php endif; ?>
			</div>
			<?php if ( $community ) : ?>
				<div class="feed">
					<?php foreach ( $community as $p ) : $GLOBALS['post'] = $p; setup_postdata( $p ); ?>
						<?php get_template_part( 'template-parts/row' ); ?>
					<?php endforeach; wp_reset_postdata(); ?>
				</div>
			<?php else : ?>
				<div class="empty">
					<p class="empty__title">Здесь будут статьи читателей</p>
					<p>Расскажите об игре, которую прошли, соберите гайд или поделитесь мнением. Редакция проверит текст и опубликует его в блогах.</p>
					<?php if ( function_exists( 'rsp_write_url' ) ) : ?>
						<a class="btn btn--primary" href="<?php echo esc_url( rsp_write_url() ); ?>">Написать первую статью</a>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</section>
	</div>

	<?php get_template_part( 'template-parts/sidebar' ); ?>
</div>

<?php
get_footer();
