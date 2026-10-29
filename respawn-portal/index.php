<?php
/**
 * Feed, categories, tags, search results and any listing without its own template.
 */

get_header();

if ( is_search() ) {
	$title = sprintf( 'Поиск: %s', get_search_query() );
	$desc  = '';
} elseif ( is_home() ) {
	$title = 'Вся лента';
	$desc  = 'Новости, обзоры, гайды и статьи участников по порядку публикации.';
} else {
	$title = wp_strip_all_tags( get_the_archive_title() );
	$desc  = get_the_archive_description();
}
?>

<div class="wrap layout">
	<div class="layout__main">
		<header class="page-head">
			<div class="page-head__row">
				<h1 class="page-head__title"><?php echo esc_html( $title ); ?></h1>
				<?php if ( is_category() && function_exists( 'rsp_subscribe_button' ) ) { echo rsp_subscribe_button( get_queried_object_id() ); } // phpcs:ignore ?>
			</div>
			<?php if ( $desc ) : ?><div class="page-head__desc"><?php echo wp_kses_post( $desc ); ?></div><?php endif; ?>
			<?php if ( is_search() ) : ?><?php get_search_form(); ?><?php endif; ?>
			<?php if ( is_category( 'blogs' ) && function_exists( 'rsp_write_url' ) ) : ?>
				<p class="page-head__cta"><a class="btn btn--primary" href="<?php echo esc_url( rsp_write_url() ); ?>"><?php echo rsp_icon( 'pen' ); // phpcs:ignore ?><span>Написать статью</span></a><span class="muted"><?php echo is_user_logged_in() ? 'Редакция проверяет тексты перед публикацией.' : 'Нужна регистрация: она занимает минуту.'; ?></span></p>
			<?php endif; ?>
		</header>

		<?php if ( have_posts() ) : ?>
			<div class="feed">
				<?php
				while ( have_posts() ) :
					the_post();
					get_template_part( 'template-parts/row' );
				endwhile;
				?>
			</div>
			<?php rsp_pagination(); ?>
		<?php else : ?>
			<div class="empty">
				<p class="empty__title"><?php echo is_search() ? 'Ничего не нашлось' : 'Здесь пока пусто'; ?></p>
				<p><?php echo is_search() ? 'Попробуйте другое название игры или более короткий запрос.' : 'Материалы в этом разделе скоро появятся.'; ?></p>
			</div>
		<?php endif; ?>
	</div>

	<?php get_template_part( 'template-parts/sidebar' ); ?>
</div>

<?php
get_footer();
