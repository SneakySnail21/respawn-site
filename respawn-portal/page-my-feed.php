<?php
/**
 * Personal feed: new articles from the categories the member subscribed to.
 */

get_header();
$subs  = function_exists( 'rsp_user_subscriptions' ) ? rsp_user_subscriptions() : array();
$paged = max( 1, (int) get_query_var( 'paged' ) );
?>
<div class="wrap layout">
	<div class="layout__main">
		<header class="page-head">
			<h1 class="page-head__title">Моя лента</h1>
			<p class="page-head__desc">Свежие материалы из рубрик, на которые вы подписаны. О новых статьях мы также сообщаем на почту.</p>
		</header>

		<?php if ( ! is_user_logged_in() ) : ?>
			<div class="empty">
				<p class="empty__title">Войдите, чтобы собрать свою ленту</p>
				<p>Подпишитесь на рубрики — и здесь останутся только интересные вам темы.</p>
				<a class="btn btn--primary" href="<?php echo esc_url( wp_login_url( rsp_my_feed_url() ) ); ?>">Войти</a>
			</div>
		<?php else : ?>
			<section class="box subs-box">
				<h2 class="box__title">Рубрики</h2>
				<ul class="subs-list">
					<?php foreach ( get_categories( array( 'exclude' => rsp_hidden_categories(), 'hide_empty' => false ) ) as $c ) : ?>
						<li><a href="<?php echo esc_url( get_category_link( $c ) ); ?>"><?php echo esc_html( $c->name ); ?></a><?php echo rsp_subscribe_button( $c->term_id ); // phpcs:ignore ?></li>
					<?php endforeach; ?>
				</ul>
				<label class="switch"><input type="checkbox" data-sub-email <?php checked( 'off' !== get_user_meta( get_current_user_id(), 'rsp_sub_email', true ) ); ?>> Присылать письма о новых материалах</label>
			</section>

			<?php if ( ! $subs ) : ?>
				<div class="empty">
					<p class="empty__title">Подписок пока нет</p>
					<p>Нажмите «Подписаться» рядом с рубриками выше. Лента обновится после перезагрузки страницы.</p>
				</div>
			<?php else : ?>
				<?php
				$q = new WP_Query( array( 'category__in' => $subs, 'paged' => $paged, 'posts_per_page' => 12, 'ignore_sticky_posts' => true ) );
				if ( $q->have_posts() ) :
					?>
					<div class="feed">
						<?php
						while ( $q->have_posts() ) :
							$q->the_post();
							get_template_part( 'template-parts/row' );
						endwhile;
						?>
					</div>
					<nav class="pager">
						<?php
						echo paginate_links( array( 'total' => $q->max_num_pages, 'current' => $paged, 'prev_text' => 'Назад', 'next_text' => 'Дальше' ) ); // phpcs:ignore
						?>
					</nav>
					<?php
					wp_reset_postdata();
				else :
					?>
					<div class="empty"><p class="empty__title">В ваших рубриках пока нет материалов</p></div>
				<?php endif; ?>
			<?php endif; ?>
		<?php endif; ?>
	</div>
	<?php get_template_part( 'template-parts/sidebar' ); ?>
</div>
<?php
get_footer();
