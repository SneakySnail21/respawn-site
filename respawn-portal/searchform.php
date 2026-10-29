<?php $rsp_sid = wp_unique_id( 's-' ); ?>
<form role="search" method="get" class="searchform" action="<?php echo esc_url( home_url( '/' ) ); ?>">
	<label class="screen-reader-text" for="<?php echo esc_attr( $rsp_sid ); ?>">Поиск</label>
	<input type="search" id="<?php echo esc_attr( $rsp_sid ); ?>" name="s" value="<?php echo esc_attr( get_search_query() ); ?>" placeholder="Игра, новость или автор" autocomplete="off">
	<button class="btn btn--primary" type="submit">Найти</button>
</form>
