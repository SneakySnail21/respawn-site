<?php
/**
 * Game card: vertical cover, title, genre, release, player rating and thread count.
 */

$gid     = get_the_ID();
$rating  = rsp_game_rating( $gid );
$facts   = rsp_game_facts( $gid );
$forum   = rsp_game_forum_id( $gid );
$threads = $forum && function_exists( 'bbp_get_forum_topic_count' ) ? (int) bbp_get_forum_topic_count( $forum ) : 0;
$soon    = ! rsp_game_is_released( $gid );
?>
<article <?php post_class( 'game-card' ); ?>>
	<a class="game-card__cover" href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true">
		<?php if ( has_post_thumbnail() ) : ?>
			<?php the_post_thumbnail( 'medium_large', array( 'loading' => 'lazy', 'alt' => '' ) ); ?>
		<?php else : ?>
			<span class="thumb__ph thumb__ph--<?php echo (int) ( $gid % 4 ); ?>"><span><?php the_title(); ?></span></span>
		<?php endif; ?>
		<?php if ( $soon ) : ?><span class="game-card__flag">Скоро</span><?php endif; ?>
		<?php if ( $rating['count'] ) : ?><span class="score score--sm game-card__score" title="Оценка игроков"><?php echo esc_html( rsp_format_score( $rating['avg'] ) ); ?></span><?php endif; ?>
	</a>
	<div class="game-card__body">
		<h2 class="game-card__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
		<p class="game-card__meta"><?php echo esc_html( implode( ' · ', array_filter( array( $facts['Жанр'] ?? '', $facts['Выход'] ?? '' ) ) ) ); ?></p>
		<p class="game-card__stats"><span><?php echo esc_html( $rating['count'] ? sprintf( 'Оценок: %d', $rating['count'] ) : 'Без оценок' ); ?></span><span><?php echo esc_html( sprintf( 'Веток: %d', $threads ) ); ?></span></p>
	</div>
</article>
