<?php
/**
 * Sidebar: popular, forum, categories, invitation to write.
 */
?>
<aside class="sidebar" aria-label="Боковая колонка">

	<?php if ( rsp_core() && ( $popular = rsp_popular_posts( 5 ) ) ) : ?>
		<section class="box">
			<h2 class="box__title">Популярное</h2>
			<ol class="ranked">
				<?php foreach ( $popular as $p ) : ?>
					<li>
						<a href="<?php echo esc_url( get_permalink( $p ) ); ?>"><?php echo esc_html( get_the_title( $p ) ); ?></a>
						<?php echo rsp_meta( $p, false ); // phpcs:ignore ?>
					</li>
				<?php endforeach; ?>
			</ol>
		</section>
	<?php endif; ?>

	<?php if ( function_exists( 'bbp_get_forums_url' ) ) : ?>
		<section class="box">
			<h2 class="box__title">Форум</h2>
			<?php
			$topics = get_posts( array( 'post_type' => bbp_get_topic_post_type(), 'posts_per_page' => 5, 'orderby' => 'meta_value', 'meta_key' => '_bbp_last_active_time', 'meta_type' => 'DATETIME' ) );
			if ( $topics ) :
				?>
				<ul class="plain-list">
					<?php foreach ( $topics as $t ) : ?>
						<li>
							<a href="<?php echo esc_url( bbp_get_topic_permalink( $t->ID ) ); ?>"><?php echo esc_html( bbp_get_topic_title( $t->ID ) ); ?></a>
							<span class="muted"><?php echo esc_html( bbp_get_forum_title( bbp_get_topic_forum_id( $t->ID ) ) ); ?>, ответов: <?php echo (int) bbp_get_topic_reply_count( $t->ID ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php else : ?>
				<ul class="plain-list">
					<?php foreach ( get_posts( array( 'post_type' => bbp_get_forum_post_type(), 'posts_per_page' => 6, 'post_parent' => 0, 'orderby' => 'menu_order', 'order' => 'ASC' ) ) as $f ) : ?>
						<li><a href="<?php echo esc_url( get_permalink( $f ) ); ?>"><?php echo esc_html( get_the_title( $f ) ); ?></a></li>
					<?php endforeach; ?>
				</ul>
				<p class="muted">Обсуждений пока нет. Начните первое.</p>
			<?php endif; ?>
			<a class="box__more" href="<?php echo esc_url( bbp_get_forums_url() ); ?>">Все разделы форума</a>
		</section>
	<?php endif; ?>

	<?php if ( ! is_user_logged_in() && function_exists( 'rsp_write_url' ) ) : ?>
		<section class="box box--accent">
			<h2 class="box__title">Пишите на Respawn</h2>
			<p>Обзоры, гайды, мнения о любимых играх. После проверки редакцией статья попадёт в ленту, а читатели смогут её оценить.</p>
			<a class="btn btn--primary" href="<?php echo esc_url( wp_registration_url() ); ?>">Зарегистрироваться</a>
		</section>
	<?php endif; ?>

	<section class="box">
		<h2 class="box__title">Рубрики</h2>
		<ul class="chips">
			<?php foreach ( get_categories( array( 'exclude' => rsp_hidden_categories(), 'hide_empty' => false ) ) as $cat ) : ?>
				<li><a class="chip" href="<?php echo esc_url( get_category_link( $cat ) ); ?>"><?php echo esc_html( $cat->name ); ?><?php if ( $cat->count ) : ?> <span><?php echo (int) $cat->count; ?></span><?php endif; ?></a></li>
			<?php endforeach; ?>
		</ul>
	</section>

	<?php dynamic_sidebar( 'sidebar' ); ?>
</aside>
