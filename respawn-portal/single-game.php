<?php
/**
 * Game card page: hero, facts, player rating, community threads (Reddit-style) and news.
 */

get_header();

while ( have_posts() ) :
	the_post();
	$gid     = get_the_ID();
	$facts   = rsp_game_facts( $gid );
	$forum   = rsp_game_forum_id( $gid );
	$hero    = (int) get_post_meta( $gid, 'rsp_g_hero', true );
	$shots   = array_filter( (array) get_post_meta( $gid, 'rsp_g_shots', true ) );
	$steam   = (int) get_post_meta( $gid, 'rsp_g_steam', true );
	$sort    = isset( $_GET['threads'] ) ? sanitize_key( $_GET['threads'] ) : 'hot'; // phpcs:ignore WordPress.Security.NonceVerification
	$sorts   = array( 'hot' => 'Горячие', 'new' => 'Новые', 'top' => 'Лучшие', 'active' => 'Активные' );
	$sort    = isset( $sorts[ $sort ] ) ? $sort : 'hot';
	$threads = $forum ? rsp_forum_threads( $forum, $sort ) : array();
	$news    = rsp_game_posts( $gid );
	?>

	<section class="game-hero">
		<div class="game-hero__bg" aria-hidden="true">
			<?php if ( $hero ) { echo wp_get_attachment_image( $hero, 'full', false, array( 'alt' => '', 'loading' => 'eager' ) ); } ?>
		</div>
		<div class="wrap game-hero__in">
			<div class="game-hero__cover">
				<?php if ( has_post_thumbnail() ) : ?>
					<?php the_post_thumbnail( 'medium_large', array( 'alt' => get_the_title(), 'loading' => 'eager' ) ); ?>
				<?php else : ?>
					<span class="thumb__ph thumb__ph--<?php echo (int) ( $gid % 4 ); ?>"><span><?php the_title(); ?></span></span>
				<?php endif; ?>
			</div>
			<div class="game-hero__body">
				<a class="label" href="<?php echo esc_url( get_post_type_archive_link( 'game' ) ); ?>">Игры</a>
				<h1 class="game-hero__title"><?php the_title(); ?></h1>
				<?php if ( has_excerpt() ) : ?><p class="game-hero__lede"><?php echo esc_html( get_the_excerpt() ); ?></p><?php endif; ?>
				<div class="game-hero__actions">
					<?php if ( $forum ) : ?><a class="btn btn--primary" href="#threads">Обсуждения</a><?php endif; ?>
					<?php if ( function_exists( 'rsp_bookmark_button' ) ) { echo rsp_bookmark_button( $gid ); } // phpcs:ignore ?>
					<?php if ( $steam ) : ?><a class="btn btn--ghost" href="<?php echo esc_url( 'https://store.steampowered.com/app/' . $steam . '/' ); ?>" target="_blank" rel="noopener">Steam</a><?php endif; ?>
				</div>
			</div>
		</div>
	</section>

	<div class="wrap layout game-layout">
		<div class="layout__main">

			<section class="threads" id="threads" aria-labelledby="threads-title">
				<div class="section-head">
					<h2 class="section-title" id="threads-title">Сообщество</h2>
					<?php if ( $forum ) : ?><a class="box__more" href="<?php echo esc_url( get_permalink( $forum ) ); ?>">Раздел на форуме</a><?php endif; ?>
				</div>

				<?php if ( $forum ) : ?>
					<nav class="tabs tabs--sm" aria-label="Сортировка веток">
						<?php foreach ( $sorts as $key => $label ) : ?>
							<a class="tabs__item<?php echo $sort === $key ? ' is-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'threads', $key, get_permalink() ) . '#threads' ); ?>"><?php echo esc_html( $label ); ?></a>
						<?php endforeach; ?>
					</nav>

					<?php if ( $threads ) : ?>
						<ul class="thread-list">
							<?php foreach ( $threads as $t ) : $sticky = function_exists( 'bbp_is_topic_sticky' ) && bbp_is_topic_sticky( $t->ID ); ?>
								<li class="thread<?php echo $sticky ? ' thread--main' : ''; ?>">
									<?php echo rsp_vote_widget( $t->ID ); // phpcs:ignore ?>
									<div class="thread__body">
										<?php if ( $sticky ) : ?><span class="label">Главное обсуждение</span><?php endif; ?>
										<a class="thread__title" href="<?php echo esc_url( get_permalink( $t ) ); ?>"><?php echo esc_html( get_the_title( $t ) ); ?></a>
										<p class="thread__meta">
											<a href="<?php echo esc_url( function_exists( 'bbp_get_user_profile_url' ) ? bbp_get_user_profile_url( $t->post_author ) : get_author_posts_url( $t->post_author ) ); ?>"><?php echo esc_html( get_the_author_meta( 'display_name', $t->post_author ) ); ?></a>
											<span><?php echo esc_html( human_time_diff( get_post_timestamp( $t ) ) . ' назад' ); ?></span>
											<span><?php echo esc_html( sprintf( 'Ответов: %d', (int) get_post_meta( $t->ID, '_bbp_reply_count', true ) ) ); ?></span>
										</p>
									</div>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php else : ?>
						<p class="muted">Веток пока нет. Начните первую.</p>
					<?php endif; ?>

					<details class="new-thread"<?php echo $threads && count( $threads ) > 1 ? '' : ' open'; ?>>
						<summary class="btn btn--ghost">+ Создать ветку</summary>
						<div class="new-thread__form">
							<?php if ( is_user_logged_in() ) : ?>
								<?php echo do_shortcode( '[bbp-topic-form forum_id="' . (int) $forum . '"]' ); ?>
							<?php else : ?>
								<p><a href="<?php echo esc_url( wp_login_url( get_permalink() . '#threads' ) ); ?>">Войдите</a> или <a href="<?php echo esc_url( wp_registration_url() ); ?>">зарегистрируйтесь</a>, чтобы создавать ветки и голосовать.</p>
							<?php endif; ?>
						</div>
					</details>
				<?php else : ?>
					<p class="muted">Раздел форума для этой игры скоро появится.</p>
				<?php endif; ?>
			</section>

			<?php if ( get_the_content() ) : ?>
				<section class="game-about" aria-labelledby="about-title">
					<h2 class="section-title" id="about-title">Об игре</h2>
					<div class="prose"><?php the_content(); ?></div>
				</section>
			<?php endif; ?>

			<?php if ( $shots ) : ?>
				<section aria-labelledby="shots-title">
					<h2 class="section-title" id="shots-title">Кадры</h2>
					<div class="shots">
						<?php foreach ( $shots as $s ) : ?>
							<a href="<?php echo esc_url( $s ); ?>" target="_blank" rel="noopener"><img src="<?php echo esc_url( $s ); ?>" alt="Кадр из <?php the_title_attribute(); ?>" loading="lazy" referrerpolicy="no-referrer"></a>
						<?php endforeach; ?>
					</div>
					<p class="muted shots__credit">Изображения: Steam / правообладатель игры</p>
				</section>
			<?php endif; ?>

			<?php if ( $news ) : ?>
				<section aria-labelledby="gnews-title">
					<h2 class="section-title" id="gnews-title">Материалы об игре</h2>
					<div class="feed">
						<?php foreach ( $news as $p ) : $GLOBALS['post'] = $p; setup_postdata( $p ); ?>
							<?php get_template_part( 'template-parts/row' ); ?>
						<?php endforeach; wp_reset_postdata(); ?>
					</div>
				</section>
			<?php endif; ?>
		</div>

		<aside class="sidebar" aria-label="Об игре">
			<section class="box">
				<h2 class="box__title">Оценка игроков</h2>
				<?php echo rsp_rating_widget( $gid ); // phpcs:ignore ?>
			</section>
			<?php if ( $facts ) : ?>
				<section class="box">
					<h2 class="box__title">Факты</h2>
					<dl class="game-facts">
						<?php foreach ( $facts as $label => $value ) : ?>
							<div><dt><?php echo esc_html( $label ); ?></dt><dd><?php echo esc_html( $value ); ?></dd></div>
						<?php endforeach; ?>
					</dl>
				</section>
			<?php endif; ?>
			<?php
			$more = get_posts( array( 'post_type' => 'game', 'posts_per_page' => 5, 'post__not_in' => array( $gid ), 'orderby' => 'rand' ) );
			if ( $more ) :
				?>
				<section class="box">
					<h2 class="box__title">Другие игры</h2>
					<ul class="mini-games">
						<?php foreach ( $more as $m ) : ?>
							<li><a href="<?php echo esc_url( get_permalink( $m ) ); ?>"><?php echo get_the_post_thumbnail( $m, 'thumbnail', array( 'alt' => '' ) ); ?><span><?php echo esc_html( get_the_title( $m ) ); ?></span></a></li>
						<?php endforeach; ?>
					</ul>
					<a class="box__more" href="<?php echo esc_url( get_post_type_archive_link( 'game' ) ); ?>">Все игры</a>
				</section>
			<?php endif; ?>
		</aside>
	</div>

	<?php
endwhile;

get_footer();
