<?php
/**
 * Comments list and form.
 */

if ( post_password_required() ) {
	return;
}
?>
<section id="comments" class="comments">
	<h2 class="section-title">Комментарии<?php if ( get_comments_number() ) : ?> <span class="muted"><?php echo (int) get_comments_number(); ?></span><?php endif; ?></h2>

	<?php if ( have_comments() ) : ?>
		<ol class="comment-list">
			<?php wp_list_comments( array( 'callback' => 'rsp_comment', 'style' => 'ol', 'avatar_size' => 36 ) ); ?>
		</ol>
		<?php the_comments_pagination( array( 'prev_text' => 'Назад', 'next_text' => 'Дальше' ) ); ?>
	<?php endif; ?>

	<?php if ( comments_open() ) : ?>
		<?php
		comment_form( array(
			'title_reply'          => have_comments() ? 'Ваш комментарий' : 'Начните обсуждение',
			'title_reply_before'   => '<h3 id="reply-title" class="comment-reply-title">',
			'title_reply_after'    => '</h3>',
			'label_submit'         => 'Отправить',
			'class_submit'         => 'btn btn--primary',
			'comment_notes_before' => '',
			'logged_in_as'         => '',
			'must_log_in'          => sprintf(
				'<p class="must-log-in"><a href="%s">Войдите</a> или <a href="%s">зарегистрируйтесь</a>, чтобы оставить комментарий.</p>',
				esc_url( wp_login_url( get_permalink() . '#respond' ) ),
				esc_url( wp_registration_url() )
			),
			'comment_field'        => '<p class="comment-form-comment"><label class="screen-reader-text" for="comment">Комментарий</label><textarea id="comment" name="comment" rows="4" required placeholder="Что думаете?"></textarea></p>',
		) );
		?>
	<?php else : ?>
		<p class="muted">Обсуждение закрыто.</p>
	<?php endif; ?>
</section>
