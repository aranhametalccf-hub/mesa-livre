<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Endpoints REST do Delivery Hub.
 * Namespace: dh/v1
 *
 * IMPORTANTE: sem OTP nesta versão. Qualquer um com o telefone consegue ver dados salvos.
 * Aceito como risco pra MVP — camada mínima de "cookie do device" no frontend.
 */
add_action( 'rest_api_init', function () {

	register_rest_route( 'dh/v1', '/customer/(?P<phone>\d{10,15})', [
		'methods'  => 'GET',
		'permission_callback' => '__return_true',
		'callback' => function ( $req ) {
			$phone = dh_normalize_phone( $req['phone'] );
			$c = dh_find_customer_by_phone( $phone );
			if ( ! $c ) {
				return new WP_REST_Response( [ 'found' => false ], 200 );
			}
			return [
				'found'     => true,
				'name'      => $c->post_title,
				'phone'     => $phone,
				'addresses' => dh_get_customer_addresses( $c->ID ),
			];
		},
	] );

	register_rest_route( 'dh/v1', '/orders/(?P<phone>\d{10,15})', [
		'methods'  => 'GET',
		'permission_callback' => '__return_true',
		'callback' => function ( $req ) {
			$phone = dh_normalize_phone( $req['phone'] );
			$orders = dh_get_customer_orders( $phone, 20 );
			$statuses = dh_order_statuses();
			$store = dh_get_settings();

			$out = [];
			foreach ( $orders as $o ) {
				$items = get_post_meta( $o->ID, '_dh_items', true ) ?: [];
				$total = (float) get_post_meta( $o->ID, '_dh_total', true );
				$cfg = $statuses[ $o->post_status ] ?? null;
				$out[] = [
					'id'          => $o->ID,
					'created_at'  => get_the_time( 'c', $o ),
					'created_hum' => get_the_time( 'd/m/Y H:i', $o ),
					'status'      => $o->post_status,
					'status_label'=> $cfg['label'] ?? '',
					'status_color'=> $cfg['color'] ?? '#999',
					'total'       => $total,
					'total_fmt'   => dh_money( $total ),
					'items_count' => array_sum( array_map( fn( $i ) => intval( $i['qty'] ?? 0 ), $items ) ),
					'items_preview' => array_slice(
						array_map( fn( $i ) => $i['qty'] . 'x ' . $i['name'], $items ),
						0, 3
					),
					'is_open'     => ! in_array( $o->post_status, [ 'dh-entregue', 'dh-cancelado' ], true ),
					'tracking_url'=> home_url( '/pedido/' . $o->ID ),
				];
			}

			return $out;
		},
	] );

	register_rest_route( 'dh/v1', '/order/(?P<id>\d+)', [
		'methods'  => 'GET',
		'permission_callback' => '__return_true',
		'callback' => function ( $req ) {
			$id = intval( $req['id'] );
			$phone_check = dh_normalize_phone( $req->get_param( 'phone' ) );

			$order = get_post( $id );
			if ( ! $order || $order->post_type !== 'dh_order' ) {
				return new WP_REST_Response( [ 'error' => 'Pedido não encontrado' ], 404 );
			}

			$customer = get_post_meta( $id, '_dh_customer', true ) ?: [];
			$order_phone = dh_normalize_phone( $customer['phone'] ?? '' );
			if ( $phone_check && $order_phone !== $phone_check ) {
				return new WP_REST_Response( [ 'error' => 'Telefone não confere' ], 403 );
			}

			$items = get_post_meta( $id, '_dh_items', true ) ?: [];
			$statuses = dh_order_statuses();
			$cfg = $statuses[ $order->post_status ] ?? null;

			return [
				'id'           => $id,
				'created_at'   => get_the_time( 'c', $order ),
				'created_hum'  => get_the_time( 'd/m/Y H:i', $order ),
				'status'       => $order->post_status,
				'status_label' => $cfg['label'] ?? '',
				'status_color' => $cfg['color'] ?? '#999',
				'customer'     => [
					'name'  => $customer['name'] ?? '',
					'phone' => $order_phone,
					'type'  => $customer['type'] ?? 'delivery',
				],
				'address_line' => $customer['address'] ?? '',
				'items'        => $items,
				'subtotal'     => (float) get_post_meta( $id, '_dh_subtotal', true ),
				'delivery_fee' => (float) get_post_meta( $id, '_dh_delivery_fee', true ),
				'total'        => (float) get_post_meta( $id, '_dh_total', true ),
				'total_fmt'    => dh_money( get_post_meta( $id, '_dh_total', true ) ),
			];
		},
	] );

	register_rest_route( 'dh/v1', '/order/(?P<id>\d+)/confirm', [
		'methods'  => 'POST',
		'permission_callback' => '__return_true',
		'callback' => function ( $req ) {
			$id = intval( $req['id'] );
			$phone = dh_normalize_phone( $req->get_param( 'phone' ) );

			$order = get_post( $id );
			if ( ! $order || $order->post_type !== 'dh_order' ) {
				return new WP_REST_Response( [ 'error' => 'Pedido não encontrado' ], 404 );
			}

			$customer = get_post_meta( $id, '_dh_customer', true ) ?: [];
			$order_phone = dh_normalize_phone( $customer['phone'] ?? '' );

			if ( ! $phone || $order_phone !== $phone ) {
				return new WP_REST_Response( [ 'error' => 'Telefone não confere' ], 403 );
			}

			// Só permite confirmar se está numa etapa apropriada
			$allowed_from = [ 'dh-pronto', 'dh-rota' ];
			if ( ! in_array( $order->post_status, $allowed_from, true ) ) {
				return new WP_REST_Response( [ 'error' => 'Pedido ainda não pode ser confirmado' ], 400 );
			}

			$ok = wp_update_post( [ 'ID' => $id, 'post_status' => 'dh-entregue' ], true );
			if ( is_wp_error( $ok ) ) {
				return new WP_REST_Response( [ 'error' => $ok->get_error_message() ], 500 );
			}

			// Log especial marcando confirmação do cliente
			dh_log_change( $id, 'confirmed_by_customer', 'Cliente confirmou o recebimento' );

			return [ 'status' => 'dh-entregue', 'ok' => true ];
		},
	] );

	register_rest_route( 'dh/v1', '/menu-item/(?P<id>\d+)', [
		'methods'  => 'GET',
		'permission_callback' => '__return_true',
		'callback' => function ( $req ) {
			$post = get_post( intval( $req['id'] ) );
			if ( ! $post || $post->post_type !== 'menu_item' ) {
				return new WP_REST_Response( [ 'error' => 'Item não encontrado' ], 404 );
			}
			return dh_item_to_array( $post );
		},
	] );
} );
