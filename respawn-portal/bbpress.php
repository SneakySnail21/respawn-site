<?php
/**
 * Forum pages (bbPress): forums, topics, user profiles.
 */

get_header();
?>
<div class="wrap forum-wrap">
	<?php
	while ( have_posts() ) :
		the_post();
		?>
		<header class="page-head">
			<h1 class="page-head__title"><?php the_title(); ?></h1>
		</header>
		<div class="forum">
			<?php the_content(); ?>
		</div>
		<?php
	endwhile;
	?>
</div>
<?php
get_footer();
