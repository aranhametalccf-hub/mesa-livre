<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Rewrite rules pra páginas do cliente:
 *   /meus-pedidos       → templates/my-orders.php
 *   /pedido/{id}        → templates/order-tracking.php
 */
add_action( 'init', function () {
	add_rewrite_rule( '^meus-pedidos/?$', 'index.php?dh_page=my_orders', 'top' );
	add_rewrite_rule( '^pedido/([0-9]+)/?$', 'index.php?dh_page=tracking&dh_order=$matches[1]', 'top' );
} );

add_filter( 'query_vars', function ( $vars ) {
	$vars[] = 'dh_page';
	$vars[] = 'dh_order';
	return $vars;
} );

add_filter( 'template_include', function ( $template ) {
	$page = get_query_var( 'dh_page' );
	if ( $page === 'my_orders' ) {
		$t = locate_template( 'templates/my-orders.php' );
		if ( $t ) return $t;
	}
	if ( $page === 'tracking' ) {
		$t = locate_template( 'templates/order-tracking.php' );
		if ( $t ) return $t;
	}
	return $template;
} );

/**
 * Flush rewrite rules 1 vez após ativação/upgrade do tema.
 */
add_action( 'after_switch_theme', function () {
	flush_rewrite_rules();
} );

// Fallback: se as regras ainda não foram flushadas, garante ao menos 1 flush
add_action( 'init', function () {
	if ( get_option( 'dh_rewrite_flushed_' . DH_VERSION ) !== '1' ) {
		flush_rewrite_rules( false );
		update_option( 'dh_rewrite_flushed_' . DH_VERSION, '1' );
	}
}, 20 );
