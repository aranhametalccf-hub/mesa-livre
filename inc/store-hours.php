<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Horários de funcionamento + estado manual da loja (fechado / pausado).
 *
 * Prioridade de decisão:
 *   1. manual_closed = true       → fechado ("Loja pausada indefinidamente")
 *   2. paused_until > agora       → pausado até HH:MM
 *   3. exceção do dia (fechado)   → fechado ("Fechado hoje")
 *   4. exceção do dia (horário)   → checa se now bate faixa da exceção
 *   5. horários do dia da semana  → checa se now bate alguma faixa
 */

function dh_days_of_week() {
	return [
		'mon' => 'Segunda',
		'tue' => 'Terça',
		'wed' => 'Quarta',
		'thu' => 'Quinta',
		'fri' => 'Sexta',
		'sat' => 'Sábado',
		'sun' => 'Domingo',
	];
}

function dh_get_hours() {
	$s = dh_get_settings();
	$hours = $s['hours_by_day'] ?? [];
	if ( ! is_array( $hours ) ) $hours = [];
	// Default: todos os dias 18h-23h se nada configurado
	foreach ( array_keys( dh_days_of_week() ) as $day ) {
		if ( ! isset( $hours[ $day ] ) ) $hours[ $day ] = [];
	}
	return $hours;
}

function dh_get_hours_exceptions() {
	$s = dh_get_settings();
	$ex = $s['hours_exceptions'] ?? [];
	return is_array( $ex ) ? $ex : [];
}

function dh_get_store_state() {
	$state = get_option( 'dh_store_state', [] );
	return wp_parse_args( is_array( $state ) ? $state : [], [
		'manual_closed'     => 0,
		'paused_until'      => 0,
		'manual_open_until' => 0, // força aberto (override do horário) até esse timestamp
	] );
}

function dh_set_store_state( $state ) {
	update_option( 'dh_store_state', $state, false );
}

/**
 * Dia da semana como chave (mon..sun) a partir de timestamp UNIX.
 * Usa ISO 8601 (1=segunda, 7=domingo) pra evitar dependência de locale.
 */
function dh_day_key_from_ts( $ts ) {
	$iso = intval( wp_date( 'N', $ts ) );
	$map = [ 1 => 'mon', 2 => 'tue', 3 => 'wed', 4 => 'thu', 5 => 'fri', 6 => 'sat', 7 => 'sun' ];
	return $map[ $iso ] ?? 'mon';
}

function dh_current_day_key() {
	return dh_day_key_from_ts( current_time( 'timestamp' ) );
}

/**
 * "HH:MM" → minutos desde meia-noite.
 */
function dh_hm_to_minutes( $hm ) {
	$parts = explode( ':', trim( (string) $hm ) );
	if ( count( $parts ) < 2 ) return null;
	return intval( $parts[0] ) * 60 + intval( $parts[1] );
}

/**
 * Cria timestamp Unix a partir de "Y-m-d" + "H:M" no timezone do WordPress.
 * strtotime() usa TZ do servidor (geralmente UTC) — não podemos confiar nele aqui.
 */
function dh_ts_from_local( $date, $hm ) {
	$dt = date_create_from_format( 'Y-m-d H:i', $date . ' ' . $hm, wp_timezone() );
	return $dt ? $dt->getTimestamp() : null;
}

/**
 * Retorna estado completo da loja.
 * [
 *   'open'        => bool,
 *   'reason'      => 'manual_closed' | 'paused' | 'exception_closed' | 'out_of_hours' | 'open_hours' | 'open_exception',
 *   'message'     => 'Fechada · Reabre às 18h',
 *   'reopens_at'  => timestamp UNIX (WP local) ou null,
 *   'closes_at'   => timestamp UNIX (WP local) ou null,
 * ]
 */
function dh_store_status() {
	$state = dh_get_store_state();
	$now   = time(); // UNIX timestamp real (UTC)

	// 1. Fechado manualmente
	if ( ! empty( $state['manual_closed'] ) ) {
		return [
			'open'       => false,
			'reason'     => 'manual_closed',
			'message'    => 'Loja fechada',
			'reopens_at' => null,
			'closes_at'  => null,
		];
	}

	// 2. Pausado temporariamente
	if ( ! empty( $state['paused_until'] ) && $state['paused_until'] > $now ) {
		$mins = (int) ceil( ( $state['paused_until'] - $now ) / 60 );
		return [
			'open'       => false,
			'reason'     => 'paused',
			'message'    => 'Pedidos pausados · volta em ' . $mins . 'min',
			'reopens_at' => $state['paused_until'],
			'closes_at'  => null,
		];
	}

	// 2b. Aberto manualmente (override do horário)
	if ( ! empty( $state['manual_open_until'] ) && $state['manual_open_until'] > $now ) {
		return [
			'open'       => true,
			'reason'     => 'manual_open',
			'message'    => 'Aberta manualmente até ' . wp_date( 'H:i', $state['manual_open_until'] ),
			'reopens_at' => null,
			'closes_at'  => $state['manual_open_until'],
		];
	}

	$today       = wp_date( 'Y-m-d', $now );
	$now_minutes = intval( wp_date( 'G', $now ) ) * 60 + intval( wp_date( 'i', $now ) );
	$exceptions  = dh_get_hours_exceptions();

	// 3/4. Exceção do dia
	if ( isset( $exceptions[ $today ] ) ) {
		$ex = $exceptions[ $today ];
		if ( ! empty( $ex['closed'] ) ) {
			return [
				'open'       => false,
				'reason'     => 'exception_closed',
				'message'    => 'Fechado hoje · ' . dh_next_opening_label(),
				'reopens_at' => dh_next_opening_timestamp(),
				'closes_at'  => null,
			];
		}
		if ( ! empty( $ex['open'] ) && ! empty( $ex['close'] ) ) {
			$o = dh_hm_to_minutes( $ex['open'] );
			$c = dh_hm_to_minutes( $ex['close'] );
			if ( $now_minutes >= $o && $now_minutes < $c ) {
				return [
					'open'       => true,
					'reason'     => 'open_exception',
					'message'    => 'Aberta até ' . $ex['close'],
					'reopens_at' => null,
					'closes_at'  => dh_ts_from_local( $today, $ex['close'] ),
				];
			}
			// Fora do horário da exceção
			return [
				'open'       => false,
				'reason'     => 'out_of_hours',
				'message'    => 'Fechada · reabre às ' . $ex['open'],
				'reopens_at' => dh_ts_from_local( $today, $ex['open'] ),
				'closes_at'  => null,
			];
		}
	}

	// 5. Horário do dia da semana
	$day = dh_current_day_key();
	$slots = dh_get_hours()[ $day ] ?? [];
	foreach ( $slots as $slot ) {
		$o = dh_hm_to_minutes( $slot['open'] ?? '' );
		$c = dh_hm_to_minutes( $slot['close'] ?? '' );
		if ( $o === null || $c === null ) continue;
		if ( $now_minutes >= $o && $now_minutes < $c ) {
			return [
				'open'       => true,
				'reason'     => 'open_hours',
				'message'    => 'Aberta até ' . $slot['close'],
				'reopens_at' => null,
				'closes_at'  => dh_ts_from_local( $today, $slot['close'] ),
			];
		}
	}

	// Fechada. Descobre próxima abertura.
	$next = dh_next_opening_timestamp();
	return [
		'open'       => false,
		'reason'     => 'out_of_hours',
		'message'    => $next ? 'Fechada · ' . dh_next_opening_label( $next ) : 'Fechada',
		'reopens_at' => $next,
		'closes_at'  => null,
	];
}

/**
 * Timestamp UNIX da próxima janela de abertura (respeita exceções).
 */
function dh_next_opening_timestamp() {
	$hours = dh_get_hours();
	$exceptions = dh_get_hours_exceptions();
	$now = time();
	$now_minutes = intval( wp_date( 'G', $now ) ) * 60 + intval( wp_date( 'i', $now ) );

	for ( $offset = 0; $offset < 8; $offset++ ) {
		$ts = $now + ( $offset * 86400 );
		$date = wp_date( 'Y-m-d', $ts );
		$dow  = dh_day_key_from_ts( $ts );

		// Exceção fechada pula
		if ( isset( $exceptions[ $date ] ) ) {
			$ex = $exceptions[ $date ];
			if ( ! empty( $ex['closed'] ) ) continue;
			if ( ! empty( $ex['open'] ) ) {
				$slots = [ [ 'open' => $ex['open'], 'close' => $ex['close'] ?? '23:59' ] ];
			} else {
				$slots = $hours[ $dow ] ?? [];
			}
		} else {
			$slots = $hours[ $dow ] ?? [];
		}

		foreach ( $slots as $slot ) {
			$open_m = dh_hm_to_minutes( $slot['open'] ?? '' );
			if ( $open_m === null ) continue;
			if ( $offset === 0 && $now_minutes >= $open_m ) continue;
			return dh_ts_from_local( $date, $slot['open'] );
		}
	}
	return null;
}

function dh_next_opening_label( $ts = null ) {
	if ( $ts === null ) $ts = dh_next_opening_timestamp();
	if ( ! $ts ) return 'sem horário definido';

	$now   = time();
	$today = wp_date( 'Y-m-d', $now );
	$date  = wp_date( 'Y-m-d', $ts );

	if ( $date === $today )                             return 'reabre hoje às ' . wp_date( 'H:i', $ts );
	if ( $date === wp_date( 'Y-m-d', $now + 86400 ) )   return 'reabre amanhã às ' . wp_date( 'H:i', $ts );

	return 'reabre ' . wp_date( 'l \à\s H:i', $ts );
}

function dh_is_open_now() {
	$s = dh_store_status();
	return (bool) $s['open'];
}

/**
 * Sanitiza estrutura de horários no save do settings.
 */
add_filter( 'dh_sanitize_settings_ext', function ( $out, $input ) {
	$hours = [];
	if ( isset( $input['hours_by_day'] ) && is_array( $input['hours_by_day'] ) ) {
		foreach ( dh_days_of_week() as $day => $_ ) {
			$slots = $input['hours_by_day'][ $day ] ?? [];
			$clean = [];
			if ( is_array( $slots ) ) {
				foreach ( $slots as $slot ) {
					$o = sanitize_text_field( $slot['open'] ?? '' );
					$c = sanitize_text_field( $slot['close'] ?? '' );
					if ( preg_match( '/^\d{1,2}:\d{2}$/', $o ) && preg_match( '/^\d{1,2}:\d{2}$/', $c ) ) {
						$clean[] = [ 'open' => $o, 'close' => $c ];
					}
				}
			}
			$hours[ $day ] = $clean;
		}
	}
	$out['hours_by_day'] = $hours;

	$exc = [];
	if ( isset( $input['hours_exceptions'] ) && is_array( $input['hours_exceptions'] ) ) {
		foreach ( $input['hours_exceptions'] as $row ) {
			$date = sanitize_text_field( $row['date'] ?? '' );
			if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) continue;
			if ( ! empty( $row['closed'] ) ) {
				$exc[ $date ] = [ 'closed' => true, 'note' => sanitize_text_field( $row['note'] ?? '' ) ];
			} elseif ( ! empty( $row['open'] ) && ! empty( $row['close'] ) ) {
				$exc[ $date ] = [
					'open'  => sanitize_text_field( $row['open'] ),
					'close' => sanitize_text_field( $row['close'] ),
					'note'  => sanitize_text_field( $row['note'] ?? '' ),
				];
			}
		}
	}
	$out['hours_exceptions'] = $exc;

	return $out;
}, 10, 2 );

/**
 * AJAX — toggle fechado manual + pausar + retomar.
 */
add_action( 'wp_ajax_dh_store_toggle', function () {
	check_ajax_referer( 'dh_pdv', 'nonce' );
	if ( ! current_user_can( 'edit_posts' ) ) wp_send_json_error( 'sem permissão', 403 );
	$state = dh_get_store_state();
	$state['manual_closed'] = empty( $state['manual_closed'] ) ? 1 : 0;
	$state['paused_until']  = 0;
	dh_set_store_state( $state );
	wp_send_json_success( dh_store_status() );
} );

add_action( 'wp_ajax_dh_store_pause', function () {
	check_ajax_referer( 'dh_pdv', 'nonce' );
	if ( ! current_user_can( 'edit_posts' ) ) wp_send_json_error( 'sem permissão', 403 );
	$minutes = intval( $_POST['minutes'] ?? 0 );
	$mode    = sanitize_text_field( $_POST['mode'] ?? 'minutes' );

	$state = dh_get_store_state();
	$state['manual_closed'] = 0;
	if ( $mode === 'end_of_day' ) {
		$status = dh_store_status();
		$state['paused_until'] = $status['closes_at'] ?: ( time() + 3600 );
	} else {
		$minutes = max( 5, min( 480, $minutes ) );
		$state['paused_until'] = time() + ( $minutes * 60 );
	}
	dh_set_store_state( $state );
	wp_send_json_success( dh_store_status() );
} );

add_action( 'wp_ajax_dh_store_resume', function () {
	check_ajax_referer( 'dh_pdv', 'nonce' );
	if ( ! current_user_can( 'edit_posts' ) ) wp_send_json_error( 'sem permissão', 403 );

	// Limpa fechamento manual e pausa
	$state = [ 'manual_closed' => 0, 'paused_until' => 0, 'manual_open_until' => 0 ];

	// Se estamos fora do horário configurado, força a loja aberta até o fim do dia
	// (evita o efeito "cliquei em reabrir mas continua fechada por horário")
	$now = time();
	dh_set_store_state( $state ); // limpa antes pra o cálculo abaixo não considerar override antigo
	$after_clear = dh_store_status();
	if ( ! $after_clear['open'] ) {
		$state['manual_open_until'] = dh_end_of_today_ts();
		dh_set_store_state( $state );
	}

	wp_send_json_success( dh_store_status() );
} );

/**
 * Timestamp UNIX do fim do dia atual (23:59:59) no timezone do WP.
 */
function dh_end_of_today_ts() {
	$today = wp_date( 'Y-m-d', time() );
	return dh_ts_from_local( $today, '23:59' ) ?: ( time() + 3600 );
}

/**
 * Bloqueia criação de pedido pelo endpoint AJAX quando loja fechada.
 * Hook cedo (before payload validation).
 */
add_action( 'wp_ajax_dh_place_order', 'dh_block_order_if_closed', 1 );
add_action( 'wp_ajax_nopriv_dh_place_order', 'dh_block_order_if_closed', 1 );
function dh_block_order_if_closed() {
	$status = dh_store_status();
	if ( ! $status['open'] ) {
		wp_send_json_error( [
			'message' => 'A loja está fechada agora. ' . $status['message'],
			'reason'  => $status['reason'],
		], 400 );
	}
}
