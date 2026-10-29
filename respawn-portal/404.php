<?php
/**
 * Not found.
 */

get_header();
?>
<div class="wrap page-wrap notfound">
	<p class="notfound__code" aria-hidden="true">404</p>
	<h1 class="page-head__title">Такой страницы нет</h1>
	<p>Возможно, материал переехал или ссылка набрана с ошибкой. Попробуйте поиск или вернитесь на главную.</p>
	<?php get_search_form(); ?>
	<p><a class="btn btn--ghost" href="<?php echo esc_url( home_url( '/' ) ); ?>">На главную</a></p>
</div>
<?php
get_footer();
