<?php
/**
 * Author profile: who they are, their rating and articles.
 */

get_header();

$author   = get_queried_object();
$uid      = (int) $author->ID;
$articles = (int) count_user_posts( $uid, 'post', true );
$comments = (int) get_comments( array( 'user_id' => $uid, 'count' => true, 'status' => 'approve' ) );
$is_me    = get_current_user_id() === $uid;
?>

<div class="wrap layout">
	<div class="layout__main">
		<header class="profile">
			<?php echo get_avatar( $uid, 96, '', '', array( 'class' => 'profile__avatar' ) ); ?>
			<div class="profile__body">
				<h1 class="profile__name"><?php echo esc_html( $author->display_name ); ?></h1>
				<p class="muted">На Respawn с <?php echo esc_html( wp_date( 'F Y', strtotime( $author->user_registered ) ) ); ?></p>
				<?php if ( $author->description ) : ?><p class="profile__bio"><?php echo esc_html( $author->description ); ?></p><?php endif; ?>
				<dl class="profile__stats">
					<?php if ( rsp_core() ) : ?><div><dt>Рейтинг</dt><dd><?php echo (int) rsp_user_karma( $uid ); ?></dd></div><?php endif; ?>
					<div><dt>Статьи</dt><dd><?php echo $articles; // phpcs:ignore ?></dd></div>
					<div><dt>Комментарии</dt><dd><?php echo $comments; // phpcs:ignore ?></dd></div>
				</dl>
				<div class="profile__actions">
					<?php if ( $is_me ) : ?>
						<a class="btn btn--ghost" href="<?php echo esc_url( admin_url( 'profile.php' ) ); ?>">Редактировать профиль</a>
						<?php if ( function_exists( 'rsp_page_url' ) ) : ?><a class="btn btn--ghost" href="<?php echo esc_url( add_query_arg( 'tab', 'mine', rsp_page_url( 'write' ) ) ); ?>">Мои статьи</a><?php endif; ?>
					<?php endif; ?>
					<?php if ( function_exists( 'bbp_get_user_profile_url' ) ) : ?>
						<a class="btn btn--ghost" href="<?php echo esc_url( bbp_get_user_profile_url( $uid ) ); ?>">Активность на форуме</a>
					<?php endif; ?>
				</div>
			</div>
		</header>

		<h2 class="section-title">Статьи</h2>
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
				<p class="empty__title"><?php echo $is_me ? 'У вас пока нет опубликованных статей' : 'Статей пока нет'; ?></p>
				<?php if ( $is_me && function_exists( 'rsp_write_url' ) ) : ?>
					<p>Напишите первую: после проверки редакцией она появится в ленте и здесь.</p>
					<a class="btn btn--primary" href="<?php echo esc_url( rsp_write_url() ); ?>">Написать статью</a>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</div>

	<?php get_template_part( 'template-parts/sidebar' ); ?>
</div>

<?php
get_footer();
