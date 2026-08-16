<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'wp_enqueue_scripts', function () {
	$s = dh_get_settings();

	wp_enqueue_style( 'dh-theme', DH_URI . '/assets/css/theme.css', [], DH_VERSION );

	// dh-app PRIMEIRO (registra window.deliveryApp), Alpine DEPOIS.
	wp_enqueue_script( 'dh-app', DH_URI . '/assets/js/app.js', [], DH_VERSION, true );

	// Alpine.js standalone (auto-inicializa on DOMContentLoaded). Depende de dh-app pra garantir ordem.
	wp_enqueue_script( 'alpinejs', 'https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js', [ 'dh-app' ], '3.14.1', true );
	wp_script_add_data( 'alpinejs', 'defer', true );

	$store_status = dh_store_status();
	wp_localize_script( 'dh-app', 'DH', [
		'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
		'nonce'    => wp_create_nonce( 'dh_order' ),
		'store'    => [
			'name'            => $s['store_name'],
			'whatsapp'        => $s['whatsapp'],
			'delivery_fee'    => (float) $s['delivery_fee'],
			'min_order'       => (float) $s['min_order'],
			'accept_delivery' => (int) $s['accept_delivery'],
			'accept_pickup'   => (int) $s['accept_pickup'],
			'accept_dinein'   => (int) $s['accept_dinein'],
			'payment_methods' => $s['payment_methods'],
			'greeting'        => $s['greeting'],
			'is_open'         => (int) $store_status['open'],
			'closed_message'  => $store_status['message'],
		],
	] );

	// CSS variável dinâmica pra cor primária
	$css = ':root{--dh-primary:' . esc_attr( $s['primary_color'] ) . '}';
	wp_add_inline_style( 'dh-theme', $css );
} );

add_action( 'admin_enqueue_scripts', function ( $hook ) {
	wp_enqueue_style( 'dh-admin', DH_URI . '/assets/css/admin.css', [], DH_VERSION );

	// Kanban page carrega libs extras
	if ( strpos( $hook, 'dh-pdv' ) !== false ) {
		wp_enqueue_script( 'sortablejs', 'https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js', [], '1.15.2', true );
		wp_enqueue_script( 'dh-pdv', DH_URI . '/assets/js/pdv.js', [ 'jquery', 'sortablejs' ], DH_VERSION, true );
		wp_localize_script( 'dh-pdv', 'DH_PDV', [
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'dh_pdv' ),
		] );
	}
} );
