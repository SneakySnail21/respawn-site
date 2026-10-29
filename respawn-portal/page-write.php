<?php
/**
 * Member area: write an article for the "Блоги" tab and track your materials.
 */

get_header();

$tab    = isset( $_GET['tab'] ) && 'mine' === $_GET['tab'] ? 'mine' : 'new'; // phpcs:ignore WordPress.Security.NonceVerification
$errors = function_exists( 'rsp_form_errors' ) ? rsp_form_errors() : array();
$uid    = get_current_user_id();
$blogs  = get_category_by_slug( 'blogs' );
$edit   = null;
if ( is_user_logged_in() && isset( $_GET['edit'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
	$maybe = get_post( (int) $_GET['edit'] ); // phpcs:ignore WordPress.Security.NonceVerification
	if ( $maybe && (int) $maybe->post_author === $uid && in_array( $maybe->post_status, array( 'draft', 'pending' ), true ) ) {
		$edit = $maybe;
		$tab  = 'new';
	}
}
$val = fn( $key, $fallback = '' ) => isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : $fallback; // phpcs:ignore WordPress.Security.NonceVerification
?>
<div class="wrap page-wrap write">
	<header class="page-head">
		<h1 class="page-head__title">Мои материалы</h1>
		<p class="page-head__desc">Пишите статьи для вкладки «Блоги». Редакция проверит текст, и после одобрения он появится на сайте и в вашем профиле.</p>
	</header>

	<?php if ( ! is_user_logged_in() ) : ?>
		<div class="empty">
			<p class="empty__title">Чтобы писать статьи, нужен аккаунт</p>
			<p>Регистрация занимает минуту и не требует подтверждения по почте.</p>
			<div class="empty__actions">
				<a class="btn btn--primary" href="<?php echo esc_url( wp_registration_url() ); ?>">Зарегистрироваться</a>
				<a class="btn btn--ghost" href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>">Войти</a>
			</div>
		</div>
	<?php elseif ( ! current_user_can( 'edit_posts' ) ) : ?>
		<div class="empty"><p class="empty__title">Ваш аккаунт не может публиковать статьи</p><p>Напишите в форуме, и мы поможем.</p></div>
	<?php else : ?>

		<?php if ( isset( $_GET['welcome'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?><p class="form-note">Добро пожаловать на Respawn! Теперь вы можете писать статьи, создавать ветки на форуме и ставить оценки.</p><?php endif; ?>
		<?php if ( isset( $_GET['saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
			<p class="form-note"><?php echo 'draft' === $_GET['saved'] ? 'Черновик сохранён.' : 'Статья отправлена на проверку. Мы покажем её на сайте после одобрения.'; // phpcs:ignore ?></p>
		<?php endif; ?>
		<?php if ( isset( $_GET['deleted'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?><p class="form-note">Материал удалён.</p><?php endif; ?>

		<nav class="tabs" aria-label="Разделы">
			<a class="tabs__item<?php echo 'new' === $tab ? ' is-active' : ''; ?>" href="<?php echo esc_url( get_permalink() ); ?>"><?php echo $edit ? 'Редактирование' : 'Новая статья'; ?></a>
			<a class="tabs__item<?php echo 'mine' === $tab ? ' is-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'tab', 'mine', get_permalink() ) ); ?>">Мои статьи</a>
			<a class="tabs__item" href="<?php echo esc_url( get_author_posts_url( $uid ) ); ?>">Мой профиль</a>
			<?php if ( $blogs ) : ?><a class="tabs__item" href="<?php echo esc_url( get_category_link( $blogs ) ); ?>">Все блоги</a><?php endif; ?>
		</nav>

		<?php if ( 'mine' === $tab ) : ?>
			<?php
			$mine = get_posts( array( 'author' => $uid, 'post_status' => array( 'publish', 'pending', 'draft', 'future' ), 'posts_per_page' => 50, 'orderby' => 'modified', 'order' => 'DESC' ) );
			if ( $mine ) :
				?>
				<ul class="mine">
					<?php foreach ( $mine as $p ) : ?>
						<li class="mine__item">
							<div class="mine__main">
								<span class="status status--<?php echo esc_attr( $p->post_status ); ?>"><?php echo esc_html( rsp_status_label( $p->post_status ) ); ?></span>
								<?php if ( 'publish' === $p->post_status ) : ?>
									<a class="mine__title" href="<?php echo esc_url( get_permalink( $p ) ); ?>"><?php echo esc_html( get_the_title( $p ) ); ?></a>
								<?php else : ?>
									<span class="mine__title"><?php echo esc_html( get_the_title( $p ) ?: 'Без названия' ); ?></span>
								<?php endif; ?>
								<span class="muted"><?php echo esc_html( get_the_modified_date( 'j F Y, H:i', $p ) ); ?></span>
							</div>
							<?php if ( 'publish' === $p->post_status && function_exists( 'rsp_like_count' ) ) : ?>
								<span class="muted mine__stat">♥ <?php echo (int) rsp_like_count( $p->ID ); ?> · <?php echo (int) $p->comment_count; ?> комм.</span>
							<?php endif; ?>
							<?php if ( in_array( $p->post_status, array( 'draft', 'pending' ), true ) ) : ?>
								<div class="mine__actions">
									<a class="btn btn--ghost" href="<?php echo esc_url( add_query_arg( 'edit', $p->ID, get_permalink() ) ); ?>">Изменить</a>
									<form method="post" action="<?php echo esc_url( get_permalink() ); ?>" onsubmit="return confirm('Удалить материал?')">
										<?php wp_nonce_field( 'rsp_delete_' . $p->ID ); ?>
										<input type="hidden" name="rsp_delete" value="<?php echo (int) $p->ID; ?>">
										<button class="btn btn--ghost" type="submit">Удалить</button>
									</form>
								</div>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php else : ?>
				<div class="empty">
					<p class="empty__title">Пока нет ни одной статьи</p>
					<p>Расскажите об игре, которую прошли, соберите гайд или поделитесь мнением.</p>
					<a class="btn btn--primary" href="<?php echo esc_url( get_permalink() ); ?>">Написать статью</a>
				</div>
			<?php endif; ?>

		<?php else : ?>
			<form class="write__form" method="post" enctype="multipart/form-data" action="<?php echo esc_url( get_permalink() ); ?>">
				<?php foreach ( $errors as $e ) : ?><p class="form-error" role="alert"><?php echo esc_html( $e ); ?></p><?php endforeach; ?>

				<div class="field"><label for="w-title">Заголовок</label><input id="w-title" name="title" type="text" maxlength="150" required value="<?php echo esc_attr( $val( 'title', $edit ? $edit->post_title : '' ) ); ?>"></div>

				<div class="field">
					<label for="rsp_content">Текст</label>
					<?php
					wp_editor(
						$val( 'content', $edit ? $edit->post_content : '' ),
						'rsp_content',
						array(
							'textarea_name' => 'content',
							'textarea_rows' => 16,
							'media_buttons' => false,
							'teeny'         => true,
							'quicktags'     => false,
							'tinymce'       => array(
								'toolbar1'      => 'formatselect,bold,italic,bullist,numlist,blockquote,link,unlink,undo,redo',
								'block_formats' => 'Абзац=p;Заголовок раздела=h2;Подзаголовок=h3',
								'content_style' => 'body{background:#171A1F;color:#ECEEF1;font:17px/1.6 system-ui,sans-serif;padding:8px}a{color:#FF5B2E}blockquote{border-left:3px solid #FF5B2E;margin-left:0;padding-left:14px}',
							),
						)
					);
					?>
					<span class="field__hint">Минимум 300 символов. Пишите своими словами; чужие тексты и картинки без указания источника публиковать нельзя.</span>
				</div>

				<div class="field-row">
					<?php
					$games = post_type_exists( 'game' ) ? get_posts( array( 'post_type' => 'game', 'posts_per_page' => 100, 'orderby' => 'title', 'order' => 'ASC' ) ) : array();
					$sel   = (int) $val( 'game_id', $edit ? (int) get_post_meta( $edit->ID, 'rsp_game_id', true ) : 0 );
					if ( $games ) :
						?>
						<div class="field">
							<label for="w-game">Об игре (необязательно)</label>
							<select id="w-game" name="game_id">
								<option value="0">— не привязывать —</option>
								<?php foreach ( $games as $g ) : ?><option value="<?php echo (int) $g->ID; ?>"<?php selected( $sel, $g->ID ); ?>><?php echo esc_html( $g->post_title ); ?></option><?php endforeach; ?>
							</select>
							<span class="field__hint">Статья появится на странице игры.</span>
						</div>
					<?php endif; ?>
					<div class="field"><label for="w-tags">Теги через запятую</label><input id="w-tags" name="tags" type="text" value="<?php echo esc_attr( $val( 'tags', $edit ? implode( ', ', wp_get_post_tags( $edit->ID, array( 'fields' => 'names' ) ) ) : '' ) ); ?>"><span class="field__hint">Не больше шести.</span></div>
				</div>

				<div class="field">
					<label for="w-cover">Обложка (необязательно)</label>
					<input id="w-cover" name="cover" type="file" accept="image/jpeg,image/png,image/webp">
					<span class="field__hint">JPG, PNG или WebP до 3 МБ, лучше 16:9. Без обложки сайт покажет заставку рубрики.<?php echo $edit && has_post_thumbnail( $edit ) ? ' Текущая обложка останется, если не выбрать новую.' : ''; ?></span>
				</div>

				<?php wp_nonce_field( 'rsp_write' ); ?>
				<input type="hidden" name="rsp_write" value="1">
				<input type="hidden" name="post_id" value="<?php echo $edit ? (int) $edit->ID : 0; ?>">
				<div class="write__actions">
					<button class="btn btn--primary" type="submit">Отправить на проверку</button>
					<button class="btn btn--ghost" type="submit" name="save_draft" value="1">Сохранить черновик</button>
				</div>
			</form>
		<?php endif; ?>
	<?php endif; ?>
</div>
<?php
get_footer();
