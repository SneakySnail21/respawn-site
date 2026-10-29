<?php
/**
 * Article: header, image, text, review box, likes, author, related, comments.
 */

get_header();

while ( have_posts() ) :
	the_post();
	$review    = rsp_core() ? rsp_get_review() : null;
	$author_id = (int) get_the_author_meta( 'ID' );
	?>

	<div class="wrap layout">
		<article <?php post_class( 'layout__main article' ); ?>>
			<header class="article__head">
				<div class="row__top">
					<?php echo rsp_category_label(); // phpcs:ignore ?>
					<?php if ( function_exists( 'rsp_is_community_author' ) && rsp_is_community_author( $author_id ) ) : ?><span class="label label--muted">Блог участника</span><?php endif; ?>
				</div>
				<h1 class="article__title"><?php the_title(); ?></h1>
				<?php if ( has_excerpt() ) : ?><p class="article__lede"><?php echo esc_html( get_the_excerpt() ); ?></p><?php endif; ?>
				<div class="article__meta">
					<?php echo rsp_meta(); // phpcs:ignore ?>
					<span class="meta"><?php echo rsp_icon( 'clock' ); // phpcs:ignore ?><?php echo esc_html( rsp_reading_time() ); ?> мин чтения</span>
				</div>
			</header>

			<?php if ( has_post_thumbnail() ) : ?>
				<figure class="article__image">
					<?php the_post_thumbnail( 'rsp-lead', array( 'loading' => 'eager', 'alt' => '' ) ); ?>
					<?php if ( $caption = get_the_post_thumbnail_caption() ?: get_post_meta( get_the_ID(), 'rsp_image_credit', true ) ) : ?><figcaption><?php echo esc_html( $caption ); ?></figcaption><?php endif; ?>
				</figure>
			<?php endif; ?>

			<?php if ( $review && ( $review['game'] || $review['platforms'] || $review['developer'] || $review['release'] ) ) : ?>
				<dl class="facts">
					<?php foreach ( array( 'game' => 'Игра', 'platforms' => 'Платформы', 'developer' => 'Разработчик', 'release' => 'Выход' ) as $k => $label ) : ?>
						<?php if ( $review[ $k ] ) : ?><div><dt><?php echo esc_html( $label ); ?></dt><dd><?php echo esc_html( $review[ $k ] ); ?></dd></div><?php endif; ?>
					<?php endforeach; ?>
				</dl>
			<?php endif; ?>

			<details class="toc" data-toc hidden open><summary>Содержание</summary></details>

			<div class="prose article__content">
				<?php the_content(); ?>
				<?php wp_link_pages( array( 'before' => '<nav class="pager">', 'after' => '</nav>' ) ); ?>
			</div>

			<?php if ( $review ) : ?>
				<section class="verdict" aria-label="Итог обзора">
					<div class="verdict__score">
						<span class="score score--lg"><?php echo esc_html( rsp_format_score( $review['score'] ) ); ?></span>
						<span class="verdict__of"><?php echo 'critics' === get_post_meta( get_the_ID(), 'rsp_score_source', true ) ? 'из 10 · средняя оценка критиков' : 'из 10'; ?></span>
					</div>
					<div class="verdict__body">
						<h2 class="verdict__title">Итог</h2>
						<?php if ( $review['verdict'] ) : ?><p class="verdict__text"><?php echo esc_html( $review['verdict'] ); ?></p><?php endif; ?>
						<?php if ( $review['pros'] || $review['cons'] ) : ?>
							<div class="verdict__lists">
								<?php if ( $review['pros'] ) : ?>
									<div><h3 class="pros-title">Понравилось</h3><ul class="pros"><?php foreach ( $review['pros'] as $line ) : ?><li><?php echo esc_html( $line ); ?></li><?php endforeach; ?></ul></div>
								<?php endif; ?>
								<?php if ( $review['cons'] ) : ?>
									<div><h3 class="cons-title">Не понравилось</h3><ul class="cons"><?php foreach ( $review['cons'] as $line ) : ?><li><?php echo esc_html( $line ); ?></li><?php endforeach; ?></ul></div>
								<?php endif; ?>
							</div>
						<?php endif; ?>
					</div>
				</section>
			<?php endif; ?>

			<footer class="article__foot">
				<?php
				$sources = array_filter( (array) get_post_meta( get_the_ID(), 'rsp_sources', true ) );
				if ( $sources ) :
					?>
					<section class="sources" aria-label="Источники">
						<h2 class="sources__title">Источники</h2>
						<ul>
							<?php foreach ( $sources as $s ) : ?>
								<li><a href="<?php echo esc_url( $s['url'] ); ?>" target="_blank" rel="noopener nofollow"><?php echo esc_html( $s['title'] ?: wp_parse_url( $s['url'], PHP_URL_HOST ) ); ?></a></li>
							<?php endforeach; ?>
						</ul>
					</section>
				<?php endif; ?>
				<?php
				$game_card = (int) get_post_meta( get_the_ID(), 'rsp_game_id', true );
				if ( $game_card && 'publish' === get_post_status( $game_card ) ) :
					?>
					<a class="game-link" href="<?php echo esc_url( get_permalink( $game_card ) ); ?>">
						<?php echo get_the_post_thumbnail( $game_card, 'thumbnail', array( 'alt' => '' ) ); ?>
						<span><small>Карточка игры и обсуждения</small><?php echo esc_html( get_the_title( $game_card ) ); ?></span>
					</a>
				<?php endif; ?>
				<?php the_tags( '<ul class="chips"><li>', '</li><li>', '</li></ul>' ); ?>
				<div class="article__actions">
					<div class="article__react">
						<?php if ( rsp_core() ) { echo rsp_like_button( get_the_ID() ); } // phpcs:ignore ?>
						<?php if ( function_exists( 'rsp_bookmark_button' ) ) { echo rsp_bookmark_button( get_the_ID() ); } // phpcs:ignore ?>
					</div>
					<div class="share" aria-label="Поделиться">
						<?php $u = rawurlencode( get_permalink() ); $t = rawurlencode( get_the_title() ); ?>
						<a href="https://t.me/share/url?url=<?php echo $u; ?>&amp;text=<?php echo $t; ?>" target="_blank" rel="noopener">Telegram</a>
						<a href="https://vk.com/share.php?url=<?php echo $u; ?>&amp;title=<?php echo $t; ?>" target="_blank" rel="noopener">VK</a>
						<button type="button" data-copy="<?php echo esc_url( get_permalink() ); ?>">Скопировать ссылку</button>
					</div>
				</div>

				<div class="author-card">
					<?php echo get_avatar( $author_id, 56, '', '', array( 'class' => 'author-card__avatar' ) ); ?>
					<div>
						<a class="author-card__name" href="<?php echo esc_url( get_author_posts_url( $author_id ) ); ?>"><?php the_author(); ?></a>
						<p class="muted">
							Статей: <?php echo (int) count_user_posts( $author_id, 'post', true ); ?><?php if ( rsp_core() ) : ?>, рейтинг: <?php echo (int) rsp_user_karma( $author_id ); ?><?php endif; ?>
						</p>
						<?php if ( $bio = get_the_author_meta( 'description' ) ) : ?><p><?php echo esc_html( $bio ); ?></p><?php endif; ?>
					</div>
				</div>
			</footer>

			<?php
			$prev = get_previous_post();
			$next = get_next_post();
			if ( $prev || $next ) :
				?>
				<nav class="postnav" aria-label="Соседние материалы">
					<?php if ( $prev ) : ?><a class="prev" href="<?php echo esc_url( get_permalink( $prev ) ); ?>"><small>← Раньше</small><?php echo esc_html( get_the_title( $prev ) ); ?></a><?php endif; ?>
					<?php if ( $next ) : ?><a class="next" href="<?php echo esc_url( get_permalink( $next ) ); ?>"><small>Позже →</small><?php echo esc_html( get_the_title( $next ) ); ?></a><?php endif; ?>
				</nav>
			<?php endif; ?>
			<?php
			$cat     = rsp_primary_category();
			$related = $cat ? get_posts( array( 'posts_per_page' => 4, 'cat' => $cat->term_id, 'post__not_in' => array( get_the_ID() ) ) ) : array();
			if ( $related ) :
				?>
				<section class="related" aria-labelledby="related-title">
					<h2 class="section-title" id="related-title">Ещё в рубрике «<?php echo esc_html( $cat->name ); ?>»</h2>
					<ul class="related__list">
						<?php foreach ( $related as $p ) : ?>
							<li>
								<?php echo rsp_thumb( $p ); // phpcs:ignore ?>
								<a class="related__title" href="<?php echo esc_url( get_permalink( $p ) ); ?>"><?php echo esc_html( get_the_title( $p ) ); ?></a>
								<?php echo rsp_meta( $p, false ); // phpcs:ignore ?>
							</li>
						<?php endforeach; ?>
					</ul>
				</section>
			<?php endif; ?>

			<?php
			if ( comments_open() || get_comments_number() ) {
				comments_template();
			}
			?>
		</article>

		<?php get_template_part( 'template-parts/sidebar' ); ?>
	</div>

	<?php
endwhile;

get_footer();
