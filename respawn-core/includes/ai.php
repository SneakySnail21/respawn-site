<?php
/**
 * Respawn AI: the editorial author account and helpers the scheduled news agent calls
 * through Novamira (rsp_ai_status, rsp_ai_publish, rsp_ai_upsert_game).
 */

defined( 'ABSPATH' ) || exit;

const RSP_AI_LOGIN = 'respawn-ai';

/**
 * The Respawn AI user, created on first use.
 */
function rsp_ai_user_id() {
	$user = get_user_by( 'login', RSP_AI_LOGIN );
	if ( $user ) {
		return (int) $user->ID;
	}
	$id = wp_insert_user( array(
		'user_login'   => RSP_AI_LOGIN,
		'user_pass'    => wp_generate_password( 40, true, true ),
		'user_email'   => 'respawn-ai@' . wp_parse_url( home_url(), PHP_URL_HOST ),
		'display_name' => 'Respawn AI',
		'nickname'     => 'Respawn AI',
		'first_name'   => 'Respawn AI',
		'role'         => 'author',
		'description'  => 'Редакционный ИИ Respawn. Дважды в день собирает новости индустрии из открытых источников и пишет о них. Ссылки на источники — в конце каждого материала.',
	) );
	return is_wp_error( $id ) ? 0 : (int) $id;
}

add_filter( 'pre_get_avatar_data', function ( $args, $id_or_email ) {
	$uid = 0;
	if ( is_numeric( $id_or_email ) ) {
		$uid = (int) $id_or_email;
	} elseif ( $id_or_email instanceof WP_User ) {
		$uid = $id_or_email->ID;
	} elseif ( $id_or_email instanceof WP_Comment ) {
		$uid = (int) $id_or_email->user_id;
	} elseif ( $id_or_email instanceof WP_Post ) {
		$uid = (int) $id_or_email->post_author;
	}
	$ai = get_user_by( 'login', RSP_AI_LOGIN );
	if ( $uid && $ai && $uid === (int) $ai->ID ) {
		$args['url'] = plugins_url( 'assets/respawn-ai.svg', __DIR__ . '/../respawn-core.php' );
	}
	return $args;
}, 10, 2 );

/**
 * Download a remote image into the media library; reuses an earlier copy of the same URL.
 */
function rsp_ai_sideload( $url, $post_id = 0, $alt = '' ) {
	if ( ! $url || ! wp_http_validate_url( $url ) ) {
		return 0;
	}
	$found = get_posts( array( 'post_type' => 'attachment', 'meta_key' => 'rsp_src', 'meta_value' => $url, 'fields' => 'ids', 'posts_per_page' => 1, 'post_status' => 'inherit' ) );
	if ( $found ) {
		return (int) $found[0];
	}
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$tmp = download_url( $url, 30 );
	if ( is_wp_error( $tmp ) ) {
		return 0;
	}
	$mime = wp_get_image_mime( $tmp );
	$ext  = array( 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif' )[ $mime ] ?? '';
	if ( ! $ext ) {
		wp_delete_file( $tmp );
		return 0;
	}
	$name = sanitize_title( rsp_translit( $alt ) ) ?: 'respawn';
	$id   = media_handle_sideload( array( 'name' => substr( $name, 0, 60 ) . '-' . substr( md5( $url ), 0, 6 ) . '.' . $ext, 'tmp_name' => $tmp ), $post_id, $alt );
	if ( is_wp_error( $id ) ) {
		wp_delete_file( $tmp );
		return 0;
	}
	update_post_meta( $id, 'rsp_src', $url );
	if ( $alt ) {
		update_post_meta( $id, '_wp_attachment_image_alt', $alt );
	}
	return (int) $id;
}

/**
 * Official Steam images for a game: cover (vertical), hero, header, screenshots.
 */
function rsp_ai_steam_images( $appid ) {
	$appid = (int) $appid;
	if ( ! $appid ) {
		return array();
	}
	$key    = 'rsp_steam_' . $appid;
	$cached = get_transient( $key );
	if ( false !== $cached ) {
		return $cached;
	}
	$out  = array( 'screens' => array() );
	$resp = wp_remote_get( 'https://store.steampowered.com/api/appdetails?appids=' . $appid . '&l=russian&cc=us', array( 'timeout' => 20 ) );
	$data = json_decode( wp_remote_retrieve_body( $resp ), true );
	$app  = $data[ $appid ]['data'] ?? null;
	if ( $app ) {
		$out['name']   = $app['name'] ?? '';
		$out['header'] = $app['header_image'] ?? '';
		foreach ( array_slice( $app['screenshots'] ?? array(), 0, 6 ) as $s ) {
			$out['screens'][] = $s['path_full'];
		}
	}
	$base = 'https://shared.akamai.steamstatic.com/store_item_assets/steam/apps/' . $appid . '/';
	foreach ( array( 'cover' => 'library_600x900_2x.jpg', 'cover1' => 'library_600x900.jpg', 'hero' => 'library_hero.jpg' ) as $k => $file ) {
		$head = wp_remote_head( $base . $file, array( 'timeout' => 10 ) );
		if ( 200 === wp_remote_retrieve_response_code( $head ) ) {
			$out[ $k ] = $base . $file;
		}
	}
	if ( empty( $out['cover'] ) && ! empty( $out['cover1'] ) ) {
		$out['cover'] = $out['cover1'];
	}
	set_transient( $key, $out, DAY_IN_SECONDS );
	return $out;
}

/**
 * Branded cover for stories without an official image (GD + Unbounded font).
 */
function rsp_ai_make_cover( $title, $label, $post_id = 0 ) {
	if ( ! function_exists( 'imagettftext' ) ) {
		return 0;
	}
	$up   = wp_upload_dir();
	$font = $up['basedir'] . '/rsp/Unbounded.ttf';
	if ( ! file_exists( $font ) ) {
		wp_mkdir_p( dirname( $font ) );
		$r = wp_remote_get( 'https://github.com/google/fonts/raw/main/ofl/unbounded/Unbounded%5Bwght%5D.ttf', array( 'timeout' => 30 ) );
		if ( 200 !== wp_remote_retrieve_response_code( $r ) ) {
			return 0;
		}
		file_put_contents( $font, wp_remote_retrieve_body( $r ) );
	}
	$w  = 1600;
	$h  = 900;
	$im = imagecreatetruecolor( $w, $h );
	imagefill( $im, 0, 0, imagecolorallocate( $im, 15, 17, 20 ) );
	// Orange glow in a corner chosen by the title, so covers differ.
	$seed = crc32( $title );
	$cx   = $seed % 2 ? $w * 0.85 : $w * 0.15;
	$cy   = ( $seed >> 3 ) % 2 ? $h * 0.1 : $h * 0.9;
	for ( $r = 900; $r > 0; $r -= 6 ) {
		$a = (int) max( 0, min( 127, 127 - ( 900 - $r ) / 900 * 60 ) );
		imagefilledellipse( $im, (int) $cx, (int) $cy, $r * 2, $r * 2, imagecolorallocatealpha( $im, 255, 91, 46, $a ) );
	}
	$grid = imagecolorallocatealpha( $im, 255, 255, 255, 122 );
	for ( $x = 0; $x < $w; $x += 64 ) {
		imageline( $im, $x, 0, $x, $h, $grid );
	}
	for ( $y = 0; $y < $h; $y += 64 ) {
		imageline( $im, 0, $y, $w, $y, $grid );
	}
	$ink    = imagecolorallocate( $im, 236, 238, 241 );
	$accent = imagecolorallocate( $im, 255, 91, 46 );
	imagettftext( $im, 26, 0, 96, 140, $accent, $font, mb_strtoupper( $label ) );
	// Word-wrap the title.
	$size  = mb_strlen( $title ) > 70 ? 50 : 62;
	$lines = array();
	$line  = '';
	foreach ( preg_split( '/\s+/u', $title ) as $word ) {
		$try = trim( $line . ' ' . $word );
		$box = imagettfbbox( $size, 0, $font, $try );
		if ( $box[2] - $box[0] > $w - 200 && $line ) {
			$lines[] = $line;
			$line    = $word;
		} else {
			$line = $try;
		}
	}
	$lines[] = $line;
	$lines   = array_slice( $lines, 0, 5 );
	$y       = $h - 120 - ( count( $lines ) - 1 ) * ( $size * 1.35 );
	foreach ( $lines as $l ) {
		imagettftext( $im, $size, 0, 96, (int) $y, $ink, $font, $l );
		$y += $size * 1.35;
	}
	imagefilledpolygon( $im, array( $w - 150, 110, $w - 125, 85, $w - 100, 110, $w - 125, 135 ), $accent );
	imagettftext( $im, 22, 0, $w - 330, 122, $ink, $font, 'RESPAWN' );

	$file = $up['path'] . '/cover-' . substr( ( sanitize_title( rsp_translit( $title ) ) ?: 'post' ), 0, 40 ) . '-' . substr( md5( $title ), 0, 6 ) . '.jpg';
	imagejpeg( $im, $file, 88 );
	imagedestroy( $im );

	require_once ABSPATH . 'wp-admin/includes/image.php';
	$att = wp_insert_attachment( array( 'post_mime_type' => 'image/jpeg', 'post_title' => $title, 'post_status' => 'inherit' ), $file, $post_id );
	if ( is_wp_error( $att ) || ! $att ) {
		return 0;
	}
	wp_update_attachment_metadata( $att, wp_generate_attachment_metadata( $att, $file ) );
	update_post_meta( $att, '_wp_attachment_image_alt', $title );
	return (int) $att;
}

/**
 * What the agent needs to plan a run: category balance, recent titles and sources, game cards.
 */
function rsp_ai_status( $days = 7 ) {
	$ai   = rsp_ai_user_id();
	$out  = array( 'now' => wp_date( 'Y-m-d H:i' ), 'categories' => array(), 'recent' => array(), 'games' => array() );
	foreach ( get_categories( array( 'hide_empty' => false, 'exclude' => array( 1 ) ) ) as $c ) {
		$out['categories'][ $c->slug ] = array(
			'name'      => $c->name,
			'total'     => (int) $c->count,
			'last_days' => count( get_posts( array( 'cat' => $c->term_id, 'fields' => 'ids', 'posts_per_page' => -1, 'date_query' => array( array( 'after' => $days . ' days ago' ) ) ) ) ),
		);
	}
	foreach ( get_posts( array( 'posts_per_page' => 60 ) ) as $p ) {
		$src             = (array) get_post_meta( $p->ID, 'rsp_sources', true );
		$out['recent'][] = array(
			'title'   => html_entity_decode( get_the_title( $p ), ENT_QUOTES ),
			'date'    => get_the_date( 'Y-m-d H:i', $p ),
			'cats'    => wp_get_post_categories( $p->ID, array( 'fields' => 'slugs' ) ),
			'by_ai'   => (int) $p->post_author === $ai,
			'sources' => array_values( array_filter( array_map( fn( $s ) => $s['url'] ?? '', $src ) ) ),
		);
	}
	foreach ( get_posts( array( 'post_type' => 'game', 'posts_per_page' => -1 ) ) as $g ) {
		$out['games'][] = array( 'id' => $g->ID, 'title' => $g->post_title, 'steam' => (int) get_post_meta( $g->ID, 'rsp_g_steam', true ), 'release' => get_post_meta( $g->ID, 'rsp_g_release', true ), 'group' => get_post_meta( $g->ID, 'rsp_g_group', true ) );
	}
	return $out;
}

/**
 * Publish one article as Respawn AI.
 *
 * $a: title, excerpt, content (HTML), category (slug), tags[], game, steam_appid, image_url,
 *     image_credit, sources[{title,url}], review{score,verdict,pros[],cons[],platforms,developer,release}
 */
function rsp_ai_publish( array $a ) {
	$title = trim( wp_strip_all_tags( $a['title'] ?? '' ) );
	if ( ! $title || empty( $a['content'] ) ) {
		return array( 'error' => 'title and content are required' );
	}
	// Skip duplicates: same title, or same first source as an earlier story.
	global $wpdb;
	if ( $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'post' AND post_status IN ('publish','future','draft','pending') AND post_title = %s LIMIT 1", $title ) ) ) {
		return array( 'skipped' => 'duplicate title', 'title' => $title );
	}
	$first_src = $a['sources'][0]['url'] ?? '';
	if ( $first_src && $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'rsp_source_main' AND meta_value = %s LIMIT 1", $first_src ) ) ) {
		return array( 'skipped' => 'duplicate source', 'title' => $title );
	}

	$cat   = get_category_by_slug( $a['category'] ?? 'news' ) ?: get_category_by_slug( 'news' );
	$cats  = array( $cat->term_id );
	$news  = get_category_by_slug( 'news' );
	if ( $news && in_array( $cat->slug, array( 'industry', 'games', 'hardware', 'esports' ), true ) ) {
		$cats[] = $news->term_id;
	}
	$steam   = (int) ( $a['steam_appid'] ?? 0 );
	$game_id = rsp_find_game( (string) ( $a['game'] ?? '' ), $steam );
	if ( ! $steam && $game_id ) {
		$steam = (int) get_post_meta( $game_id, 'rsp_g_steam', true );
	}
	$images  = $steam ? rsp_ai_steam_images( $steam ) : array();
	$content = wp_kses_post( $a['content'] );

	$post_id = wp_insert_post( array(
		'post_type'     => 'post',
		'post_status'   => 'draft',
		'post_author'   => rsp_ai_user_id(),
		'post_title'    => $title,
		'post_excerpt'  => sanitize_textarea_field( $a['excerpt'] ?? '' ),
		'post_content'  => $content,
		'post_category' => $cats,
		'tags_input'    => array_map( 'sanitize_text_field', (array) ( $a['tags'] ?? array() ) ),
	), true );
	if ( is_wp_error( $post_id ) ) {
		return array( 'error' => $post_id->get_error_message() );
	}

	// Featured image: first Steam screenshot, the given press image, or a generated cover.
	$label  = $a['game'] ?? $cat->name;
	$credit = sanitize_text_field( $a['image_credit'] ?? '' );
	$thumb  = 0;
	if ( ! empty( $images['screens'][0] ) ) {
		$thumb  = rsp_ai_sideload( $images['screens'][0], $post_id, $label );
		$credit = $credit ?: 'Изображение: Steam / ' . ( $images['name'] ?? $label );
	}
	if ( ! $thumb && ! empty( $a['image_url'] ) ) {
		$thumb = rsp_ai_sideload( $a['image_url'], $post_id, $label );
	}
	if ( ! $thumb ) {
		$thumb  = rsp_ai_make_cover( $title, $cat->name, $post_id );
		$credit = '';
	}
	if ( $thumb ) {
		set_post_thumbnail( $post_id, $thumb );
	}
	if ( $credit ) {
		update_post_meta( $post_id, 'rsp_image_credit', $credit );
	}

	// A second screenshot inside the text, before the second section heading.
	if ( ! empty( $images['screens'][1] ) && ( $shot = rsp_ai_sideload( $images['screens'][1], $post_id, $label ) ) ) {
		$figure = sprintf(
			'<figure class="wp-block-image size-large">%s<figcaption class="wp-element-caption">%s</figcaption></figure>',
			wp_get_attachment_image( $shot, 'large', false, array( 'alt' => esc_attr( $label ) ) ),
			esc_html( 'Кадр из ' . ( $images['name'] ?? $label ) . '. Изображение: Steam' )
		);
		$pos     = preg_match_all( '/<h2[\s>]/i', $content, $m, PREG_OFFSET_CAPTURE ) >= 2 ? $m[0][1][1] : strlen( $content );
		$content = substr( $content, 0, $pos ) . $figure . substr( $content, $pos );
	}

	$sources = array();
	foreach ( (array) ( $a['sources'] ?? array() ) as $s ) {
		if ( ! empty( $s['url'] ) && wp_http_validate_url( $s['url'] ) ) {
			$sources[] = array( 'title' => sanitize_text_field( $s['title'] ?? wp_parse_url( $s['url'], PHP_URL_HOST ) ), 'url' => esc_url_raw( $s['url'] ) );
		}
	}
	update_post_meta( $post_id, 'rsp_sources', $sources );
	if ( $sources ) {
		update_post_meta( $post_id, 'rsp_source_main', $sources[0]['url'] );
	}
	if ( $game_id ) {
		update_post_meta( $post_id, 'rsp_game_id', $game_id );
	}
	if ( ! empty( $a['review']['score'] ) ) {
		$rv = $a['review'];
		update_post_meta( $post_id, 'rsp_score', rsp_sanitize_score( $rv['score'] ) );
		update_post_meta( $post_id, 'rsp_score_source', 'critics' );
		foreach ( array( 'verdict', 'platforms', 'developer', 'release' ) as $k ) {
			if ( ! empty( $rv[ $k ] ) ) {
				update_post_meta( $post_id, 'rsp_' . $k, sanitize_text_field( $rv[ $k ] ) );
			}
		}
		foreach ( array( 'pros', 'cons' ) as $k ) {
			if ( ! empty( $rv[ $k ] ) ) {
				update_post_meta( $post_id, 'rsp_' . $k, sanitize_textarea_field( implode( "\n", (array) $rv[ $k ] ) ) );
			}
		}
		update_post_meta( $post_id, 'rsp_game', sanitize_text_field( $a['game'] ?? '' ) );
	}
	update_post_meta( $post_id, 'rsp_ai', 1 );

	wp_update_post( array( 'ID' => $post_id, 'post_content' => $content, 'post_status' => 'publish' ) );
	return array( 'id' => $post_id, 'url' => get_permalink( $post_id ), 'thumb' => $thumb, 'game_id' => $game_id );
}

/**
 * Create or update a game card by slug, with Steam cover and hero images.
 *
 * $g: title, slug, summary_ru, release, platforms, developer, publisher, genre, steam_appid, image_url, group
 */
function rsp_ai_upsert_game( array $g ) {
	$title = trim( wp_strip_all_tags( $g['title'] ?? '' ) );
	if ( ! $title ) {
		return array( 'error' => 'title required' );
	}
	$slug     = sanitize_title( $g['slug'] ?? $title );
	$existing = get_page_by_path( $slug, OBJECT, 'game' );
	$args     = array(
		'post_type'    => 'game',
		'post_status'  => 'publish',
		'post_author'  => rsp_ai_user_id(),
		'post_title'   => $title,
		'post_name'    => $slug,
		'post_excerpt' => sanitize_textarea_field( $g['summary_ru'] ?? '' ),
		'post_content' => wpautop( esc_html( $g['summary_ru'] ?? '' ) ),
	);
	if ( $existing ) {
		$args['ID'] = $existing->ID;
		unset( $args['post_author'] );
	}
	$map = array( 'release' => 'rsp_g_release', 'platforms' => 'rsp_g_platforms', 'developer' => 'rsp_g_developer', 'publisher' => 'rsp_g_publisher', 'genre' => 'rsp_g_genre', 'steam_appid' => 'rsp_g_steam', 'group' => 'rsp_g_group' );
	$id  = wp_insert_post( $args, true );
	if ( is_wp_error( $id ) ) {
		return array( 'error' => $id->get_error_message() );
	}
	foreach ( $map as $in => $key ) {
		if ( isset( $g[ $in ] ) && '' !== (string) $g[ $in ] && null !== $g[ $in ] ) {
			update_post_meta( $id, $key, sanitize_text_field( (string) $g[ $in ] ) );
		}
	}
	rsp_game_sync_sort_keys( $id );

	$images = ! empty( $g['steam_appid'] ) ? rsp_ai_steam_images( $g['steam_appid'] ) : array();
	if ( ! has_post_thumbnail( $id ) ) {
		$cover = 0;
		foreach ( array( $images['cover'] ?? '', $images['header'] ?? '', $g['image_url'] ?? '' ) as $url ) {
			if ( $url && ( $cover = rsp_ai_sideload( $url, $id, $title ) ) ) {
				break;
			}
		}
		if ( $cover ) {
			set_post_thumbnail( $id, $cover );
		}
	}
	if ( ! get_post_meta( $id, 'rsp_g_hero', true ) ) {
		$hero_url = $images['hero'] ?? ( $images['screens'][0] ?? '' );
		if ( $hero_url && ( $hero = rsp_ai_sideload( $hero_url, $id, $title ) ) ) {
			update_post_meta( $id, 'rsp_g_hero', $hero );
		}
	}
	if ( ! get_post_meta( $id, 'rsp_g_shots', true ) && ! empty( $images['screens'] ) ) {
		update_post_meta( $id, 'rsp_g_shots', array_slice( $images['screens'], 0, 4 ) );
	}
	$forum = rsp_game_ensure_forum( $id );
	return array( 'id' => $id, 'url' => get_permalink( $id ), 'forum' => $forum, 'cover' => get_post_thumbnail_id( $id ) );
}
