</main>

<footer class="site-footer">
	<div class="wrap site-footer__grid">
		<div class="site-footer__brand">
			<a class="logo" href="<?php echo esc_url( home_url( '/' ) ); ?>"><span class="logo__mark" aria-hidden="true"></span><span class="logo__text">Respawn</span></a>
			<p><?php bloginfo( 'description' ); ?></p>
		</div>
		<nav aria-label="Рубрики">
			<h2 class="site-footer__title">Рубрики</h2>
			<ul>
				<?php wp_list_categories( array( 'title_li' => '', 'exclude' => rsp_hidden_categories(), 'hide_empty' => false, 'depth' => 1 ) ); ?>
			</ul>
		</nav>
		<nav aria-label="О проекте">
			<h2 class="site-footer__title">Respawn</h2>
			<?php
			wp_nav_menu( array(
				'theme_location' => 'footer',
				'container'      => false,
				'depth'          => 1,
				'fallback_cb'    => false,
			) );
			?>
		</nav>
	</div>
	<div class="wrap site-footer__bottom">
		<span>© <?php echo esc_html( wp_date( 'Y' ) ); ?> Respawn. Материалы участников принадлежат их авторам.</span>
		<a href="<?php echo esc_url( get_bloginfo( 'rss2_url' ) ); ?>">RSS</a>
	</div>
</footer>

<button class="to-top" type="button" aria-label="Наверх"><?php echo rsp_icon( 'up' ); // phpcs:ignore ?></button>

<?php wp_footer(); ?>
</body>
</html>
