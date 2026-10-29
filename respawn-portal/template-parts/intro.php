<?php
/**
 * Home intro: a one-time cinematic presentation (canvas + DOM scenes, see assets/js/intro.js).
 * Ends with the slogan panel and the "explore" button. Real site data feeds every scene.
 */

$intro_games = array();
if ( post_type_exists( 'game' ) ) {
	foreach ( get_posts( array( 'post_type' => 'game', 'posts_per_page' => 14, 'meta_key' => 'rsp_g_group', 'orderby' => array( 'meta_value' => 'DESC', 'title' => 'ASC' ) ) ) as $g ) {
		if ( has_post_thumbnail( $g ) ) {
			$intro_games[] = array( 'title' => $g->post_title, 'cover' => get_the_post_thumbnail_url( $g, 'medium_large' ) );
		}
	}
}
$intro_news = array();
foreach ( get_posts( array( 'posts_per_page' => 5, 'ignore_sticky_posts' => true ) ) as $p ) {
	$cat          = function_exists( 'rsp_primary_category' ) ? rsp_primary_category( $p ) : null;
	$intro_news[] = array(
		'title' => html_entity_decode( get_the_title( $p ), ENT_QUOTES ),
		'cat'   => $cat ? $cat->name : '',
		'img'   => get_the_post_thumbnail_url( $p, 'rsp-card' ) ?: '',
		'ai'    => function_exists( 'rsp_ai_user_id' ) && (int) $p->post_author === rsp_ai_user_id(),
	);
}
$intro_threads = array();
if ( post_type_exists( 'topic' ) ) {
	foreach ( get_posts( array( 'post_type' => 'topic', 'posts_per_page' => 4, 'post_status' => array( 'publish', 'closed' ) ) ) as $t ) {
		$intro_threads[] = html_entity_decode( get_the_title( $t ), ENT_QUOTES );
	}
}
$st          = function_exists( 'rsp_site_stats' ) ? rsp_site_stats() : array( 'posts' => 0, 'members' => 0 );
$intro_stats = array(
	array( (int) ( $st['posts'] ?? 0 ), 'материалов' ),
	array( post_type_exists( 'game' ) ? (int) wp_count_posts( 'game' )->publish : 0, 'игр с сообществами' ),
	array( post_type_exists( 'topic' ) ? (int) wp_count_posts( 'topic' )->publish : 0, 'веток на форуме' ),
);
$intro_data = array(
	'games'   => $intro_games,
	'news'    => $intro_news,
	'threads' => $intro_threads,
	'stats'   => $intro_stats,
	'words'   => array( 'Новости', 'Обзоры', 'Гайды', 'Железо', 'Киберспорт', 'Форум' ),
);
?>
<section class="intro" id="rsp-intro" aria-label="Презентация Respawn">
	<canvas class="intro__canvas" aria-hidden="true"></canvas>
	<div class="intro__vignette" aria-hidden="true"></div>

	<div class="intro__stage" aria-hidden="true">
		<div class="iscene iscene--boot">
			<div class="boot">
				<p class="boot__line">&gt; RESPAWN OS v2.6 — запуск</p>
				<p class="boot__line">&gt; загрузка миров: <b><?php echo (int) $intro_stats[1][0]; ?></b></p>
				<p class="boot__line">&gt; подключение игроков…</p>
				<p class="boot__line">&gt; синхронизация новостей… <b>ok</b></p>
				<div class="boot__bar"><span></span></div>
			</div>
		</div>

		<div class="iscene iscene--logo">
			<p class="ilogo__tag">игровой портал</p>
		</div>

		<div class="iscene iscene--warp"><div class="warp-words"></div></div>

		<div class="iscene iscene--games">
			<p class="ihead"><b class="ihead__num"><?php echo (int) $intro_stats[1][0]; ?></b> игр. <span>У каждой — своё сообщество.</span></p>
			<div class="ring-wrap"><div class="ring"></div></div>
			<p class="ring-label"></p>
		</div>

		<div class="iscene iscene--news">
			<p class="ihead">Свежее <span>— дважды в день</span></p>
			<div class="inews"></div>
		</div>

		<div class="iscene iscene--community">
			<p class="ihead">Голосуй. Спорь. <span>Пиши.</span></p>
			<div class="icomm">
				<ul class="ithreads"></ul>
				<div class="irate">
					<p class="irate__label">Оценка игроков</p>
					<div class="irate__scale"><?php for ( $i = 1; $i <= 10; $i++ ) : ?><span style="--d:<?php echo $i; ?>"><?php echo $i; ?></span><?php endfor; ?></div>
					<p class="irate__pen">✎ Твоя статья — в ленте после проверки</p>
				</div>
			</div>
		</div>

		<div class="iscene iscene--stats">
			<div class="istats">
				<?php foreach ( $intro_stats as $s ) : ?>
					<div class="istat"><b data-to="<?php echo (int) $s[0]; ?>">0</b><span><?php echo esc_html( $s[1] ); ?></span></div>
				<?php endforeach; ?>
			</div>
		</div>
	</div>

	<div class="intro__final" role="group" aria-label="Respawn">
		<p class="intro__kicker"><span class="logo__mark" aria-hidden="true"></span>Respawn</p>
		<p class="intro__slogan">Умри. Возродись. <em>Играй снова.</em></p>
		<p class="intro__sub">Новости, обзоры, карточки игр и сообщество, где каждый может стать автором. Твой следующий респаун начинается здесь.</p>
		<div class="intro__actions">
			<a class="btn btn--primary intro__go" href="#explore">Исследуй виртуальный мир</a>
			<button class="intro__replay" type="button">Смотреть заново</button>
		</div>
	</div>

	<div class="intro__hud">
		<div class="intro__progress" aria-hidden="true"><span></span></div>
		<button class="intro__skip" type="button">Пропустить ›</button>
	</div>

	<script type="application/json" id="rsp-intro-data"><?php echo wp_json_encode( $intro_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP ); ?></script>
</section>
