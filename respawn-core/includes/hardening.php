<?php
/**
 * Technical hardening and portability: security headers, closed XML-RPC, no user enumeration,
 * crawler rules, optional SMTP from wp-config constants, Apache rules for caching and compression.
 */

defined( 'ABSPATH' ) || exit;

// Security headers that work on any PHP host (no mod_headers needed).
add_action( 'send_headers', function () {
	header( 'X-Content-Type-Options: nosniff' );
	header( 'X-Frame-Options: SAMEORIGIN' );
	header( 'Referrer-Policy: strict-origin-when-cross-origin' );
	header( 'Permissions-Policy: camera=(), microphone=(), geolocation=(), interest-cohort=()' );
	header_remove( 'X-Powered-By' );
} );

// XML-RPC is a common brute-force target and the site does not use it.
add_filter( 'xmlrpc_enabled', '__return_false' );
add_filter( 'wp_headers', function ( $headers ) {
	unset( $headers['X-Pingback'] );
	return $headers;
} );
remove_action( 'wp_head', 'rsd_link' );
remove_action( 'wp_head', 'wlwmanifest_link' );
remove_action( 'wp_head', 'wp_shortlink_wp_head' );
remove_action( 'wp_head', 'wp_generator' );
add_filter( 'the_generator', '__return_empty_string' );

// Do not reveal user names to visitors: REST user list and ?author=N redirects.
add_filter( 'rest_endpoints', function ( $endpoints ) {
	if ( ! is_user_logged_in() ) {
		unset( $endpoints['/wp/v2/users'], $endpoints['/wp/v2/users/(?P<id>[\d]+)'] );
	}
	return $endpoints;
} );
add_action( 'template_redirect', function () {
	if ( ! is_user_logged_in() && isset( $_GET['author'] ) && is_numeric( $_GET['author'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		wp_safe_redirect( home_url( '/' ), 301 );
		exit;
	}
}, 1 );
// Account log-in names stay private: the feed and oEmbed show the display name only.
add_filter( 'oembed_response_data', function ( $data ) {
	unset( $data['author_url'] );
	return $data;
} );

// Crawler rules.
add_filter( 'robots_txt', function ( $output, $public ) {
	if ( ! $public ) {
		return $output;
	}
	$rules  = "User-agent: *\n";
	$rules .= "Disallow: /wp-admin/\nAllow: /wp-admin/admin-ajax.php\n";
	$rules .= "Disallow: /login/\nDisallow: /register/\nDisallow: /write/\nDisallow: /bookmarks/\nDisallow: /my-feed/\n";
	$rules .= "Disallow: /*?s=\nDisallow: /search/\nDisallow: /*?replytocom=\nDisallow: /*?sort=\nDisallow: /*?threads=\n";
	// Runs after SEO plugins (priority 100000) so their sitemap lines stay; two groups for "*" are merged by crawlers.
	return $rules . "\n" . trim( $output ) . "\n";
}, 100000, 2 );

// Optional SMTP: free hosts often block PHP mail(). Set these constants in wp-config.php to send through a real mailbox.
add_action( 'phpmailer_init', function ( $mailer ) {
	if ( ! defined( 'RSP_SMTP_HOST' ) ) {
		return;
	}
	$mailer->isSMTP();
	$mailer->Host       = RSP_SMTP_HOST;
	$mailer->Port       = defined( 'RSP_SMTP_PORT' ) ? (int) RSP_SMTP_PORT : 587;
	$mailer->SMTPSecure = defined( 'RSP_SMTP_SECURE' ) ? RSP_SMTP_SECURE : 'tls';
	if ( defined( 'RSP_SMTP_USER' ) ) {
		$mailer->SMTPAuth = true;
		$mailer->Username = RSP_SMTP_USER;
		$mailer->Password = defined( 'RSP_SMTP_PASS' ) ? RSP_SMTP_PASS : '';
	}
	if ( defined( 'RSP_SMTP_FROM' ) ) {
		$mailer->setFrom( RSP_SMTP_FROM, 'Respawn', false );
	}
} );

/**
 * Apache rules: compression, long cache for static files, no directory listing, protected config files.
 * Written between markers, so WordPress keeps them when it rewrites the rest of .htaccess.
 */
function rsp_write_htaccess() {
	if ( ! function_exists( 'insert_with_markers' ) ) {
		require_once ABSPATH . 'wp-admin/includes/misc.php';
	}
	$file = ABSPATH . '.htaccess';
	if ( ! file_exists( $file ) && ! is_writable( ABSPATH ) ) {
		return false;
	}
	$rules = array(
		'Options -Indexes',
		'<IfModule mod_deflate.c>',
		'	AddOutputFilterByType DEFLATE text/html text/plain text/css text/xml application/javascript application/json application/xml image/svg+xml',
		'</IfModule>',
		'<IfModule mod_expires.c>',
		'	ExpiresActive On',
		'	ExpiresByType image/jpeg "access plus 1 year"',
		'	ExpiresByType image/png "access plus 1 year"',
		'	ExpiresByType image/webp "access plus 1 year"',
		'	ExpiresByType image/svg+xml "access plus 1 year"',
		'	ExpiresByType font/woff2 "access plus 1 year"',
		'	ExpiresByType text/css "access plus 1 month"',
		'	ExpiresByType application/javascript "access plus 1 month"',
		'</IfModule>',
		'<IfModule mod_headers.c>',
		'	<FilesMatch "\.(woff2|jpe?g|png|webp|svg)$">',
		'		Header set Cache-Control "public, max-age=31536000, immutable"',
		'	</FilesMatch>',
		'</IfModule>',
		'<FilesMatch "^(wp-config\.php|xmlrpc\.php|readme\.html|license\.txt)$">',
		'	<IfModule mod_authz_core.c>',
		'		Require all denied',
		'	</IfModule>',
		'	<IfModule !mod_authz_core.c>',
		'		Order allow,deny',
		'		Deny from all',
		'	</IfModule>',
		'</FilesMatch>',
	);
	return insert_with_markers( $file, 'Respawn', $rules );
}
