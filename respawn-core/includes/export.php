<?php
/**
 * Site transfer: one zip with the database (URLs already switched to the new address), the Respawn theme,
 * the Respawn Core plugin and uploads, plus a short install guide. Streamed to the administrator only;
 * nothing is left on the server and secrets (API tokens, sessions, MCP/OAuth data) are not included.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu', function () {
	add_management_page( 'Перенос сайта', 'Перенос Respawn', 'manage_options', 'rsp-export', 'rsp_export_page' );
} );

function rsp_export_page() {
	?>
	<div class="wrap">
		<h1>Перенос сайта на другой хостинг</h1>
		<p>Архив содержит базу данных, тему, плагин Respawn Core, загруженные картинки и инструкцию по установке. Адрес сайта в базе заменяется на новый, а токены и сессии в архив не попадают.</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="rsp_export">
			<?php wp_nonce_field( 'rsp_export' ); ?>
			<table class="form-table" role="presentation"><tbody>
				<tr><th><label for="rsp_to">Новый адрес сайта</label></th><td><input id="rsp_to" name="to" type="url" class="regular-text" placeholder="https://mysite.infinityfreeapp.com" required><p class="description">Без слеша в конце. Для проверки на своём компьютере можно указать, например, http://localhost.</p></td></tr>
				<tr><th>Картинки</th><td><label><input type="checkbox" name="uploads" value="1" checked> Включить папку uploads</label></td></tr>
			</tbody></table>
			<?php submit_button( 'Скачать архив' ); ?>
		</form>
	</div>
	<?php
}

add_action( 'admin_post_rsp_export', function () {
	if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'rsp_export' ) ) {
		wp_die( 'Недостаточно прав.' );
	}
	$to = isset( $_POST['to'] ) ? untrailingslashit( esc_url_raw( wp_unslash( $_POST['to'] ) ) ) : '';
	if ( ! $to ) {
		wp_die( 'Укажите новый адрес сайта.' );
	}
	@set_time_limit( 300 );
	@ini_set( 'memory_limit', '512M' );

	$tmp = wp_tempnam( 'rsp-export' );
	$zip = new ZipArchive();
	if ( true !== $zip->open( $tmp, ZipArchive::OVERWRITE ) ) {
		wp_die( 'Не удалось создать архив.' );
	}
	$sql = wp_tempnam( 'rsp-sql' );
	rsp_export_sql( $sql, untrailingslashit( home_url() ), $to );
	$zip->addFile( $sql, 'database.sql' );
	$zip->addFromString( 'INSTALL.txt', rsp_export_guide( $to ) );
	rsp_export_dir( $zip, get_theme_root() . '/respawn-portal', 'wp-content/themes/respawn-portal' );
	rsp_export_dir( $zip, WP_PLUGIN_DIR . '/respawn-core', 'wp-content/plugins/respawn-core' );
	if ( ! empty( $_POST['uploads'] ) ) {
		rsp_export_dir( $zip, wp_upload_dir()['basedir'], 'wp-content/uploads' );
	}
	$zip->close();
	@unlink( $sql );

	nocache_headers();
	header( 'Content-Type: application/zip' );
	header( 'Content-Disposition: attachment; filename="respawn-transfer-' . gmdate( 'Ymd' ) . '.zip"' );
	header( 'Content-Length: ' . filesize( $tmp ) );
	readfile( $tmp );
	@unlink( $tmp );
	exit;
} );

function rsp_export_dir( ZipArchive $zip, $dir, $prefix ) {
	if ( ! is_dir( $dir ) ) {
		return;
	}
	$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $it as $f ) {
		if ( $f->isFile() && ! preg_match( '/\.(log|zip|tmp)$/i', $f->getFilename() ) ) {
			$zip->addFile( $f->getPathname(), $prefix . '/' . str_replace( '\\', '/', substr( $f->getPathname(), strlen( $dir ) + 1 ) ) );
		}
	}
}

/**
 * Replace the old address in a value, including serialized and JSON-escaped forms.
 */
function rsp_export_replace( $value, $from, $to ) {
	if ( is_string( $value ) ) {
		if ( is_serialized( $value ) ) {
			$data = @unserialize( $value, array( 'allowed_classes' => false ) ); // phpcs:ignore
			if ( false !== $data || 'b:0;' === $value ) {
				return serialize( rsp_export_replace( $data, $from, $to ) ); // phpcs:ignore
			}
		}
		$alt_from = array( $from, str_replace( '/', '\/', $from ), preg_replace( '#^https?:#', '', $from ) );
		$alt_to   = array( $to, str_replace( '/', '\/', $to ), preg_replace( '#^https?:#', '', $to ) );
		// Protocol-relative form last, and only when it still carries the host.
		return str_replace( $alt_from, $alt_to, $value );
	}
	if ( is_array( $value ) ) {
		foreach ( $value as $k => $v ) {
			$value[ $k ] = rsp_export_replace( $v, $from, $to );
		}
	}
	return $value;
}

function rsp_export_sql( $file, $from, $to ) {
	global $wpdb;
	$h = fopen( $file, 'w' );
	fwrite( $h, "-- Respawn transfer dump " . gmdate( 'c' ) . "\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\nSET sql_mode='NO_AUTO_VALUE_ON_ZERO';\n\n" );
	$skip_table  = '/(novamira|mcp|oauth|miniorange|actionscheduler|googlesitekit|tsaic|_e_events|yoast_(indexable|migrations|seo_links))/i';
	$skip_option = '/(novamira|miniorange|mcp_|oauth|googlesitekit|_transient|_site_transient|application_pass|akismet_api_key|wordpress_api_key|rsp_stats|^cron$|^recently_activated$|^tsaic|openai|^ai_)/i';
	$skip_meta   = array( 'session_tokens', '_application_passwords', 'wp_user-settings', 'wp_user-settings-time' );

	foreach ( $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $wpdb->prefix ) . '%' ) ) as $table ) {
		if ( preg_match( $skip_table, $table ) ) {
			continue;
		}
		$create = $wpdb->get_row( "SHOW CREATE TABLE `$table`", ARRAY_N ); // phpcs:ignore
		$ddl    = str_replace( array( 'utf8mb4_unicode_520_ci', 'utf8mb4_0900_ai_ci' ), 'utf8mb4_unicode_ci', $create[1] );
		fwrite( $h, "DROP TABLE IF EXISTS `$table`;\n$ddl;\n\n" );
		$offset = 0;
		while ( true ) {
			$rows = $wpdb->get_results( "SELECT * FROM `$table` LIMIT $offset, 300", ARRAY_A ); // phpcs:ignore
			if ( ! $rows ) {
				break;
			}
			$values = array();
			foreach ( $rows as $row ) {
				if ( $table === $wpdb->options && preg_match( $skip_option, $row['option_name'] ) ) {
					continue;
				}
				// Only plugins that travel with the site stay active; local-only tools (MCP, API connectors) are dropped.
				if ( $table === $wpdb->options && 'active_plugins' === $row['option_name'] ) {
					$keep = array_values( array_filter( (array) maybe_unserialize( $row['option_value'] ), fn( $p ) => preg_match( '#^(bbpress|respawn-core|akismet|wordpress-seo)/#', $p ) ) );
					$row['option_value'] = serialize( $keep );
				}
				if ( $table === $wpdb->usermeta && in_array( $row['meta_key'], $skip_meta, true ) ) {
					continue;
				}
				$cells = array();
				foreach ( $row as $col => $val ) {
					if ( null === $val ) {
						$cells[] = 'NULL';
						continue;
					}
					if ( 'guid' !== $col ) {
						$val = rsp_export_replace( $val, $from, $to );
					}
					$cells[] = "'" . esc_sql( $val ) . "'";
				}
				$values[] = '(' . implode( ',', $cells ) . ')';
			}
			if ( $values ) {
				fwrite( $h, "INSERT INTO `$table` VALUES\n" . implode( ",\n", $values ) . ";\n" );
			}
			$offset += 300;
		}
		fwrite( $h, "\n" );
	}
	fwrite( $h, "SET FOREIGN_KEY_CHECKS=1;\n" );
	fclose( $h );
}

function rsp_export_guide( $to ) {
	global $wpdb;
	return "ПЕРЕНОС RESPAWN НА НОВЫЙ ХОСТИНГ (например, InfinityFree)\n"
		. "Новый адрес: $to\n"
		. "Префикс таблиц в базе: {$wpdb->prefix}\n\n"
		. "1. На хостинге создайте сайт и базу MySQL. Запомните имя базы, пользователя, пароль и сервер (host).\n"
		. "2. Установите чистый WordPress (через установщик хостинга, например Softaculous) с тем же префиксом таблиц ({$wpdb->prefix}), либо загрузите файлы WordPress вручную.\n"
		. "3. В phpMyAdmin выберите базу и импортируйте database.sql (вкладка «Импорт»). Если файл больше лимита, разделите его на части или включите сжатие.\n"
		. "4. Через FTP или Файловый менеджер залейте содержимое папки wp-content из архива в wp-content на хостинге (тема, плагин, картинки).\n"
		. "5. Установите и активируйте плагины из каталога WordPress: bbPress (форум), Yoast SEO (по желанию), Akismet (защита комментариев). Плагин Respawn Core уже в архиве, активируйте его.\n"
		. "6. Войдите в админку по новому адресу. Пароль и логин администратора те же, что на старом сайте. После входа смените пароль.\n"
		. "7. Настройки → Постоянные ссылки: просто нажмите «Сохранить», чтобы обновить адреса страниц.\n"
		. "8. Письма. Бесплатные хостинги часто блокируют отправку почты. Участники регистрируются без писем, а для писем-подписок добавьте в wp-config.php строки:\n"
		. "   define('RSP_SMTP_HOST','smtp.example.com'); define('RSP_SMTP_USER','login'); define('RSP_SMTP_PASS','password'); define('RSP_SMTP_FROM','no-reply@yourdomain.ru');\n"
		. "9. Автопубликация от Respawn AI работает отдельно (с компьютера владельца) и подключается к новому сайту заново.\n\n"
		. "В архив не включены: токены доступа, сессии пользователей и настройки сторонних подключений (Google Site Kit, MCP).\n";
}
