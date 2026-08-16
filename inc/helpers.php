<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function dh_money( $value ) {
	return 'R$ ' . number_format( (float) $value, 2, ',', '.' );
}

function dh_item_price( $post_id ) {
	$promo = get_post_meta( $post_id, '_dh_promo_price', true );
	$price = get_post_meta( $post_id, '_dh_price', true );
	return $promo !== '' && $promo > 0 ? (float) $promo : (float) $price;
}

function dh_item_original_price( $post_id ) {
	return (float) get_post_meta( $post_id, '_dh_price', true );
}

function dh_item_is_soldout( $post_id ) {
	return (bool) get_post_meta( $post_id, '_dh_soldout', true );
}

function dh_get_menu_categories() {
	return get_terms( [
		'taxonomy'   => 'menu_category',
		'hide_empty' => true,
		'orderby'    => 'menu_order',
	] );
}

function dh_get_items_by_category( $term_id, $limit = -1 ) {
	return get_posts( [
		'post_type'      => 'menu_item',
		'posts_per_page' => $limit,
		'orderby'        => 'menu_order title',
		'order'          => 'ASC',
		'tax_query'      => [
			[
				'taxonomy' => 'menu_category',
				'field'    => 'term_id',
				'terms'    => $term_id,
			],
		],
	] );
}

function dh_get_order_bumps() {
	return get_posts( [
		'post_type'      => 'menu_item',
		'posts_per_page' => 6,
		'meta_query'     => [
			[ 'key' => '_dh_is_bump', 'value' => '1' ],
		],
	] );
}

/**
 * Retorna array serializável do item pra o carrinho JS.
 */
/**
 * Grava evento no audit log do pedido.
 */
function dh_log_change( $order_id, $action, $details = '' ) {
	$log = get_post_meta( $order_id, '_dh_change_log', true );
	if ( ! is_array( $log ) ) $log = [];

	$user = wp_get_current_user();
	$log[] = [
		'at'        => current_time( 'mysql' ),
		'user_id'   => $user->ID,
		'user_name' => $user->ID ? $user->display_name : 'Sistema',
		'action'    => $action,
		'details'   => $details,
	];

	update_post_meta( $order_id, '_dh_change_log', $log );
}

/**
 * Recalcula subtotal/total após edição de itens.
 * Retorna array com valores atualizados (não persiste — chamador salva).
 */
function dh_recalc_order_totals( $order_id ) {
	$items = get_post_meta( $order_id, '_dh_items', true ) ?: [];
	$subtotal = 0;
	foreach ( $items as $it ) {
		$subtotal += floatval( $it['total'] ?? 0 );
	}
	$fee = (float) get_post_meta( $order_id, '_dh_delivery_fee', true );
	$total = $subtotal + $fee;

	update_post_meta( $order_id, '_dh_subtotal', $subtotal );
	update_post_meta( $order_id, '_dh_total', $total );

	return [ 'subtotal' => $subtotal, 'delivery_fee' => $fee, 'total' => $total ];
}

/**
 * Recalcula o total de uma linha (usado após mudança de qty).
 */
function dh_recalc_line_total( $line ) {
	$extras_sum = 0;
	foreach ( ( $line['extras'] ?? [] ) as $e ) {
		$extras_sum += floatval( $e['price'] ?? 0 );
	}
	$qty = max( 1, intval( $line['qty'] ?? 1 ) );
	return ( floatval( $line['price'] ?? 0 ) + $extras_sum ) * $qty;
}

function dh_item_to_array( $post ) {
	$extras = get_post_meta( $post->ID, '_dh_extras', true );
	return [
		'id'          => $post->ID,
		'name'        => $post->post_title,
		'description' => wp_strip_all_tags( $post->post_content ),
		'image'       => get_the_post_thumbnail_url( $post->ID, 'menu-item-card' ) ?: '',
		'price'       => dh_item_price( $post->ID ),
		'original'    => dh_item_original_price( $post->ID ),
		'soldout'     => dh_item_is_soldout( $post->ID ),
		'extras'      => is_array( $extras ) ? $extras : [],
	];
}
