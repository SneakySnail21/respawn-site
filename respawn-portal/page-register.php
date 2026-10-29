<?php
/**
 * Registration on the site itself. No e-mail confirmation: the member picks a password here.
 */

get_header();
$errors = function_exists( 'rsp_form_errors' ) ? rsp_form_errors() : array();
$redir  = isset( $_REQUEST['redirect_to'] ) ? esc_url( wp_unslash( $_REQUEST['redirect_to'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
?>
<div class="wrap auth-wrap">
	<div class="auth">
		<div class="auth__intro">
			<h1 class="auth__title">Присоединяйтесь к Respawn</h1>
			<p>Регистрация необязательна: читать новости и обзоры можно и без неё. Аккаунт открывает больше:</p>
			<ul class="auth__perks">
				<li><b>Свои статьи.</b> Пишите в блоги, редакция проверит и опубликует.</li>
				<li><b>Форум.</b> Создавайте ветки и отвечайте в разделах каждой игры.</li>
				<li><b>Оценки и лайки.</b> Ставьте баллы играм и голосуйте за ветки.</li>
				<li><b>Закладки и подписки.</b> Сохраняйте материалы и следите за рубриками.</li>
			</ul>
		</div>

		<form class="auth__form" method="post" action="<?php echo esc_url( get_permalink() ); ?>" novalidate>
			<h2 class="auth__form-title">Создать аккаунт</h2>
			<?php if ( ! get_option( 'users_can_register' ) ) : ?>
				<p class="form-error">Регистрация сейчас закрыта.</p>
			<?php endif; ?>
			<?php foreach ( $errors as $e ) : ?><p class="form-error" role="alert"><?php echo esc_html( $e ); ?></p><?php endforeach; ?>

			<div class="field"><label for="reg-name">Имя на сайте</label><input id="reg-name" name="rsp_name" type="text" maxlength="30" autocomplete="nickname" required value="<?php echo function_exists( 'rsp_old' ) ? rsp_old( 'rsp_name' ) : ''; // phpcs:ignore ?>"><span class="field__hint">Его увидят другие участники. Менять можно в настройках профиля.</span></div>
			<div class="field"><label for="reg-email">Почта</label><input id="reg-email" name="email" type="email" autocomplete="email" required value="<?php echo function_exists( 'rsp_old' ) ? rsp_old( 'email' ) : ''; // phpcs:ignore ?>"><span class="field__hint">Нужна для входа и восстановления пароля. Мы не публикуем её.</span></div>
			<div class="field"><label for="reg-pass">Пароль</label><input id="reg-pass" name="password" type="password" minlength="8" autocomplete="new-password" required><span class="field__hint">Не короче 8 символов.</span></div>
			<?php echo function_exists( 'rsp_captcha_field' ) ? rsp_captcha_field() : ''; // phpcs:ignore ?>
			<p class="rsp-hp" aria-hidden="true"><label>Не заполняйте это поле<input type="text" name="rsp_website" value="" tabindex="-1" autocomplete="off"></label></p>
			<label class="check"><input type="checkbox" name="agree" value="1" required> Я согласен с <a href="<?php echo esc_url( home_url( '/rules/' ) ); ?>" target="_blank" rel="noopener">правилами сообщества</a></label>

			<?php wp_nonce_field( 'rsp_register' ); ?>
			<input type="hidden" name="rsp_register" value="1">
			<?php if ( $redir ) : ?><input type="hidden" name="redirect_to" value="<?php echo esc_attr( $redir ); ?>"><?php endif; ?>
			<button class="btn btn--primary btn--wide" type="submit">Зарегистрироваться</button>
			<p class="auth__alt">Уже есть аккаунт? <a href="<?php echo esc_url( wp_login_url( $redir ) ); ?>">Войти</a></p>
		</form>
	</div>
</div>
<?php
get_footer();
