<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Cálculo de tempos por etapa e métricas agregadas do dia.
 * Fonte: _dh_status_history (gravado por transition_post_status).
 */

/**
 * Metas de SLA em minutos. Merge com defaults se config incompleta.
 */
function dh_get_slas() {
	$s = dh_get_settings();
	$defaults = [
		'sla_total'    => 45,
		'sla_accept'   => 5,   // novo → preparando
		'sla_prep'     => 25,  // preparando → pronto
		'sla_dispatch' => 10,  // pronto → em rota  (só delivery)
		'sla_delivery' => 20,  // em rota → entregue (só delivery)
		'sla_pickup'   => 15,  // pronto → entregue (pickup/dinein)
	];
	$out = [];
	foreach ( $defaults as $k => $v ) {
		$out[ $k ] = isset( $s[ $k ] ) && $s[ $k ] !== '' ? intval( $s[ $k ] ) : $v;
	}
	return $out;
}

/**
 * Retorna o histórico normalizado do pedido.
 * Estrutura: [ ['status' => 'dh-novo', 'at' => 'YYYY-MM-DD HH:MM:SS', 'ts' => 12345], ... ]
 */
function dh_order_history( $order_id ) {
	$h = get_post_meta( $order_id, '_dh_status_history', true );
	if ( ! is_array( $h ) || empty( $h ) ) {
		$post = get_post( $order_id );
		if ( ! $post ) return [];
		$h = [ [ 'status' => $post->post_status, 'at' => $post->post_date ] ];
	}
	return array_map( function ( $e ) {
		return [
			'status' => $e['status'],
			'at'     => $e['at'],
			'ts'     => strtotime( $e['at'] ),
		];
	}, $h );
}

/**
 * Retorna timestamp da primeira transição pra determinado status. null se não passou.
 */
function dh_status_reached_at( $order_id, $status ) {
	foreach ( dh_order_history( $order_id ) as $h ) {
		if ( $h['status'] === $status ) return $h['ts'];
	}
	return null;
}

/**
 * Segundos entre dois status. Se $to_status não foi atingido, usa now.
 * Retorna null se $from não foi atingido.
 */
function dh_stage_seconds( $order_id, $from_status, $to_status = null ) {
	$from = dh_status_reached_at( $order_id, $from_status );
	if ( $from === null ) return null;
	$to = $to_status ? dh_status_reached_at( $order_id, $to_status ) : null;
	if ( $to === null ) {
		// Se tá esperando esse status ainda, mede desde o início da etapa até agora
		$to = current_time( 'timestamp' );
	}
	return max( 0, $to - $from );
}

/**
 * Segundos desde a última mudança de status (usado pro timer live).
 */
function dh_current_stage_seconds( $order_id ) {
	$h = dh_order_history( $order_id );
	if ( empty( $h ) ) return 0;
	$last = end( $h );
	return max( 0, current_time( 'timestamp' ) - $last['ts'] );
}

/**
 * SLA aplicável ao status atual (em segundos). Retorna 0 se etapa não tem SLA.
 */
function dh_current_stage_sla( $order_id ) {
	$slas = dh_get_slas();
	$post = get_post( $order_id );
	if ( ! $post ) return 0;
	$customer = get_post_meta( $order_id, '_dh_customer', true ) ?: [];
	$type = $customer['type'] ?? 'delivery';

	switch ( $post->post_status ) {
		case 'dh-novo':       return $slas['sla_accept'] * 60;
		case 'dh-preparando': return $slas['sla_prep'] * 60;
		case 'dh-pronto':     return ( $type === 'delivery' ? $slas['sla_dispatch'] : $slas['sla_pickup'] ) * 60;
		case 'dh-rota':       return $slas['sla_delivery'] * 60;
	}
	return 0;
}

/**
 * Métricas agregadas do dia — médias por etapa, contadores.
 * Cacheada em transient por 60s pra não pesar em cada page load.
 */
function dh_daily_metrics( $date = null ) {
	if ( ! $date ) $date = current_time( 'Y-m-d' );
	$cache_key = 'dh_metrics_' . $date;
	$cached = get_transient( $cache_key );
	if ( $cached !== false ) return $cached;

	$orders = get_posts( [
		'post_type'      => 'dh_order',
		'post_status'    => array_keys( dh_order_statuses() ),
		'posts_per_page' => -1,
		'date_query'     => [ [ 'column' => 'post_date', 'after' => $date . ' 00:00:00', 'inclusive' => true ] ],
	] );

	$stages = [
		'accept'   => [ 'from' => 'dh-novo',       'to' => 'dh-preparando' ],
		'prep'     => [ 'from' => 'dh-preparando', 'to' => 'dh-pronto' ],
		'dispatch' => [ 'from' => 'dh-pronto',     'to' => 'dh-rota' ],
		'delivery' => [ 'from' => 'dh-rota',       'to' => 'dh-entregue' ],
		'pickup'   => [ 'from' => 'dh-pronto',     'to' => 'dh-entregue' ],
		'total'    => [ 'from' => 'dh-novo',       'to' => 'dh-entregue' ],
	];

	$sums = array_fill_keys( array_keys( $stages ), 0 );
	$cnts = array_fill_keys( array_keys( $stages ), 0 );
	$overdue = 0;
	$total_rev = 0;
	$count_orders = 0;

	foreach ( $orders as $o ) {
		if ( $o->post_status === 'dh-cancelado' ) continue;

		$count_orders++;
		$total_rev += (float) get_post_meta( $o->ID, '_dh_total', true );

		$cust = get_post_meta( $o->ID, '_dh_customer', true ) ?: [];
		$type = $cust['type'] ?? 'delivery';

		foreach ( $stages as $k => $st ) {
			// pickup só conta pra retirada/no local, delivery/dispatch só pra delivery
			if ( $k === 'pickup' && $type === 'delivery' ) continue;
			if ( in_array( $k, [ 'dispatch', 'delivery' ], true ) && $type !== 'delivery' ) continue;

			$secs = dh_stage_seconds( $o->ID, $st['from'], $st['to'] );
			$reached_to = dh_status_reached_at( $o->ID, $st['to'] );

			if ( $secs !== null && $reached_to !== null ) {
				$sums[ $k ] += $secs;
				$cnts[ $k ]++;
			}
		}

		// Está atrasado agora?
		$sla = dh_current_stage_sla( $o->ID );
		if ( $sla > 0 && dh_current_stage_seconds( $o->ID ) > $sla && ! in_array( $o->post_status, [ 'dh-entregue', 'dh-cancelado' ], true ) ) {
			$overdue++;
		}
	}

	$averages = [];
	foreach ( $stages as $k => $_ ) {
		$averages[ $k ] = $cnts[ $k ] > 0 ? intval( round( $sums[ $k ] / $cnts[ $k ] ) ) : null;
	}

	$out = [
		'averages'     => $averages,   // segundos
		'counts'       => $cnts,
		'overdue'      => $overdue,
		'total_orders' => $count_orders,
		'total_rev'    => $total_rev,
		'ticket_avg'   => $count_orders > 0 ? $total_rev / $count_orders : 0,
	];

	set_transient( $cache_key, $out, 60 );
	return $out;
}

/**
 * Invalida cache de métricas quando pedido muda de status ou é criado.
 */
add_action( 'transition_post_status', function ( $new, $old, $post ) {
	if ( $post->post_type !== 'dh_order' ) return;
	delete_transient( 'dh_metrics_' . current_time( 'Y-m-d' ) );
}, 30, 3 );

/**
 * Formata segundos em "12min", "1h20", "45s".
 */
function dh_fmt_seconds( $s ) {
	if ( $s === null ) return '—';
	$s = intval( $s );
	if ( $s < 60 ) return $s . 's';
	$m = floor( $s / 60 );
	if ( $m < 60 ) return $m . 'min';
	$h = floor( $m / 60 );
	$rem = $m % 60;
	return $h . 'h' . ( $rem > 0 ? str_pad( $rem, 2, '0', STR_PAD_LEFT ) : '' );
}
