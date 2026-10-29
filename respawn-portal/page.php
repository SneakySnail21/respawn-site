<?php
/**
 * Static pages: about, rules, contacts.
 */

get_header();

while ( have_posts() ) :
	the_post();
	?>
	<div class="wrap page-wrap">
		<article <?php post_class( 'page-article' ); ?>>
			<header class="page-head">
				<h1 class="page-head__title"><?php the_title(); ?></h1>
			</header>
			<div class="prose">
				<?php the_content(); ?>
			</div>
		</article>
	</div>
	<?php
endwhile;

get_footer();
