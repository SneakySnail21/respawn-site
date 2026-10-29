<?php
/**
 * Login on the site itself.
 */

get_header();
$errors = function_exists( 'rsp_form_errors' ) ? rsp_form_errors() : array();
$redir  = isset( $_REQUEST['redirect_to'] ) ? esc_url( wp_unslash( $_REQUEST['redirect_to'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
?>
<div class="wrap auth-wrap">
	<div class="auth auth--narrow">
		<form class="auth__form" method="post" action="<?php echo esc_url( get_permalink() ); ?>">
			<h1 class="auth__form-title">Вход</h1>
			<?php if ( isset( $_GET['loggedout'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?><p class="form-note">Вы вышли из аккаунта.</p><?php endif; ?>
			<?php foreach ( $errors as $e ) : ?><p class="form-error" role="alert"><?php echo esc_html( $e ); ?></p><?php endforeach; ?>

			<div class="field"><label for="log-name">Почта или логин</label><input id="log-name" name="login" type="text" autocomplete="username" required value="<?php echo function_exists( 'rsp_old' ) ? rsp_old( 'login' ) : ''; // phpcs:ignore ?>"></div>
			<div class="field"><label for="log-pass">Пароль</label><input id="log-pass" name="password" type="password" autocomplete="current-password" required></div>
			<label class="check"><input type="checkbox" name="remember" value="1" checked> Запомнить меня</label>

			<?php wp_nonce_field( 'rsp_login' ); ?>
			<input type="hidden" name="rsp_login" value="1">
			<?php if ( $redir ) : ?><input type="hidden" name="redirect_to" value="<?php echo esc_attr( $redir ); ?>"><?php endif; ?>
			<button class="btn btn--primary btn--wide" type="submit">Войти</button>
			<p class="auth__alt"><a href="<?php echo esc_url( wp_lostpassword_url() ); ?>">Забыли пароль?</a></p>
			<p class="auth__alt">Нет аккаунта? <a href="<?php echo esc_url( add_query_arg( 'redirect_to', rawurlencode( $redir ), wp_registration_url() ) ); ?>">Зарегистрироваться</a></p>
		</form>
	</div>
</div>
<?php
get_footer();
