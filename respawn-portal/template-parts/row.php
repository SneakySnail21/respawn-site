<?php
/**
 * Feed row: image left, text right. Stacks on phones.
 */
?>
<article <?php post_class( 'row' ); ?>>
	<?php echo rsp_thumb(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
	<div class="row__body">
		<div class="row__top"><?php echo rsp_category_label(); // phpcs:ignore ?><?php echo rsp_score_badge(); // phpcs:ignore ?></div>
		<h3 class="row__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
		<p class="row__excerpt"><?php echo esc_html( get_the_excerpt() ); ?></p>
		<?php echo rsp_meta(); // phpcs:ignore ?>
	</div>
</article>
