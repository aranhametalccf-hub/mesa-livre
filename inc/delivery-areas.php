<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Áreas de entrega com taxa/ETA dinâmicos por região.
 * RFC-001. Fonte de dados: wp_options.dh_settings['delivery_areas'].
 *
 * Estrutura por área:
 *   [
 *     'id'            => int,
 *     'name'          => string,
 *     'match_type'    => 'cep_range' | 'cep_list' | 'neighborhoods',
 *     'cep_from'      => '01000000',        // se cep_range
 *     'cep_to'        => '01099999',        // se cep_range
 *     'ceps'          => [ '01310100', ... ], // se cep_list (8 dígitos)
 *     'neighborhoods' => [ 'centro', ... ], // se neighborhoods (normalizados)
 *     'fee'           => float,
 *     'eta_min'       => int,
 *     'min_order'     => float,
 *     'active'        => bool,
 *   ]
 *
 * Ordem do array = prioridade. Primeira área que dá match ganha.
 */

/**
 * Normaliza CEP pra 8 dígitos limpos.
 */
function dh_normalize_cep( $cep ) {
	return preg_replace( '/\D/', '', (string) $cep );
}

/**
 * Normaliza string pra comparação (lowercase + sem acento + trim + colapsa espaços).
 * "Alphaville Empresarial" → "alphaville empresarial"
 * "Vl. Nova " → "vl. nova"
 */
function dh_normalize_string( $s ) {
	$s = strtolower( trim( (string) $s ) );
	$s = remove_accents( $s );
	$s = preg_replace( '/\s+/', ' ', $s );
	return $s;
}

/**
 * Retorna áreas cadastradas (só as ativas).
 */
function dh_get_delivery_areas( $only_active = true ) {
	$s = dh_get_settings();
	$areas = $s['delivery_areas'] ?? [];
	if ( ! is_array( $areas ) ) return [];
	if ( ! $only_active ) return array_values( $areas );
	return array_values( array_filter( $areas, fn( $a ) => ! empty( $a['active'] ) ) );
}

function dh_get_delivery_areas_config() {
	$s = dh_get_settings();
	$cfg = $s['delivery_areas_config'] ?? [];
	return wp_parse_args( is_array( $cfg ) ? $cfg : [], [
		'fallback_action' => 'block',    // block | allow_with_default_fee
		'suggest_pickup'  => 1,
	] );
}

/**
 * Verifica se CEP + bairro batem em alguma área. Retorna primeira que casa.
 *
 * @return array|null Área que deu match, ou null se nenhuma.
 */
function dh_match_area( $cep, $neighborhood = '' ) {
	$cep = dh_normalize_cep( $cep );
	if ( strlen( $cep ) !== 8 ) return null;

	$bairro_norm = dh_normalize_string( $neighborhood );
	$areas = dh_get_delivery_areas( true );

	foreach ( $areas as $area ) {
		$type = $area['match_type'] ?? 'cep_range';

		if ( $type === 'cep_range' ) {
			$from = dh_normalize_cep( $area['cep_from'] ?? '' );
			$to   = dh_normalize_cep( $area['cep_to'] ?? '' );
			if ( strlen( $from ) === 8 && strlen( $to ) === 8 && $cep >= $from && $cep <= $to ) {
				return $area;
			}
		}

		if ( $type === 'cep_list' ) {
			$list = array_map( 'dh_normalize_cep', (array) ( $area['ceps'] ?? [] ) );
			if ( in_array( $cep, $list, true ) ) return $area;
		}

		if ( $type === 'neighborhoods' && $bairro_norm !== '' ) {
			$saved = array_map( 'dh_normalize_string', (array) ( $area['neighborhoods'] ?? [] ) );
			foreach ( $saved as $n ) {
				if ( $n === '' ) continue;
				// Match por igualdade OU substring — tolera "Alphaville Empresarial" bater "alphaville"
				if ( $n === $bairro_norm || str_contains( $bairro_norm, $n ) || str_contains( $n, $bairro_norm ) ) {
					return $area;
				}
			}
		}
	}

	return null;
}

/**
 * API principal — retorna decisão completa pra checkout.
 * Cacheia em transient por 24h.
 *
 * @return array {
 *   allowed: bool,
 *   area: array|null,
 *   fee: float|null,
 *   eta_min: int|null,
 *   min_order: float|null,
 *   reason: string,        // no_areas | matched | no_area_match | fallback_allowed
 *   message: string,
 *   suggest_pickup: bool,
 * }
 */
function dh_check_delivery_cep( $cep, $neighborhood = '' ) {
	$cep = dh_normalize_cep( $cep );
	if ( strlen( $cep ) !== 8 ) {
		return [
			'allowed'        => false,
			'area'           => null,
			'fee'            => null,
			'eta_min'        => null,
			'min_order'      => null,
			'reason'         => 'invalid_cep',
			'message'        => 'CEP inválido',
			'suggest_pickup' => false,
		];
	}

	$cache_key = 'dh_area_' . $cep . '_' . md5( dh_normalize_string( $neighborhood ) );
	$cached = get_transient( $cache_key );
	if ( $cached !== false ) return $cached;

	$s = dh_get_settings();
	$cfg = dh_get_delivery_areas_config();
	$areas = dh_get_delivery_areas( true );

	// Sem áreas cadastradas → comportamento legado (aceita, taxa global)
	if ( empty( $areas ) ) {
		$out = [
			'allowed'        => true,
			'area'           => null,
			'fee'            => (float) ( $s['delivery_fee'] ?? 0 ),
			'eta_min'        => null,
			'min_order'      => (float) ( $s['min_order'] ?? 0 ),
			'reason'         => 'no_areas',
			'message'        => 'Entrega padrão',
			'suggest_pickup' => false,
		];
		set_transient( $cache_key, $out, HOUR_IN_SECONDS * 24 );
		return $out;
	}

	$area = dh_match_area( $cep, $neighborhood );

	if ( $area ) {
		$out = [
			'allowed'        => true,
			'area'           => [ 'id' => $area['id'] ?? 0, 'name' => $area['name'] ?? '' ],
			'fee'            => (float) ( $area['fee'] ?? 0 ),
			'eta_min'        => isset( $area['eta_min'] ) && $area['eta_min'] !== '' ? intval( $area['eta_min'] ) : null,
			'min_order'      => (float) ( $area['min_order'] ?? 0 ) ?: (float) ( $s['min_order'] ?? 0 ),
			'reason'         => 'matched',
			'message'        => 'Entregamos em ' . ( $area['name'] ?? 'sua região' ),
			'suggest_pickup' => false,
		];
		set_transient( $cache_key, $out, HOUR_IN_SECONDS * 24 );
		return $out;
	}

	// Sem match — respeita fallback
	if ( $cfg['fallback_action'] === 'allow_with_default_fee' ) {
		$out = [
			'allowed'        => true,
			'area'           => null,
			'fee'            => (float) ( $s['delivery_fee'] ?? 0 ),
			'eta_min'        => null,
			'min_order'      => (float) ( $s['min_order'] ?? 0 ),
			'reason'         => 'fallback_allowed',
			'message'        => 'Confirmaremos a entrega pelo WhatsApp',
			'suggest_pickup' => false,
		];
	} else {
		$out = [
			'allowed'        => false,
			'area'           => null,
			'fee'            => null,
			'eta_min'        => null,
			'min_order'      => null,
			'reason'         => 'no_area_match',
			'message'        => 'Ainda não entregamos nessa região.',
			'suggest_pickup' => ! empty( $cfg['suggest_pickup'] ) && ! empty( $s['accept_pickup'] ),
		];
	}

	set_transient( $cache_key, $out, HOUR_IN_SECONDS * 24 );
	return $out;
}

/**
 * Sanitiza áreas vindas do form de settings.
 */
add_filter( 'dh_sanitize_settings_ext', function ( $out, $input ) {
	$areas = [];
	if ( isset( $input['delivery_areas'] ) && is_array( $input['delivery_areas'] ) ) {
		$next_id = 1;
		foreach ( $input['delivery_areas'] as $row ) {
			$name = sanitize_text_field( $row['name'] ?? '' );
			if ( $name === '' ) continue;

			$type = sanitize_text_field( $row['match_type'] ?? 'cep_range' );
			if ( ! in_array( $type, [ 'cep_range', 'cep_list', 'neighborhoods' ], true ) ) $type = 'cep_range';

			$ceps_raw = $row['ceps'] ?? '';
			if ( is_string( $ceps_raw ) ) $ceps_raw = preg_split( '/[\s,;]+/', $ceps_raw );
			$ceps = array_values( array_filter( array_map( 'dh_normalize_cep', (array) $ceps_raw ), fn( $c ) => strlen( $c ) === 8 ) );

			$nb_raw = $row['neighborhoods'] ?? '';
			if ( is_string( $nb_raw ) ) $nb_raw = explode( ',', $nb_raw );
			$neighborhoods = array_values( array_filter( array_map( fn( $n ) => trim( sanitize_text_field( $n ) ), (array) $nb_raw ) ) );

			$areas[] = [
				'id'            => intval( $row['id'] ?? 0 ) ?: $next_id++,
				'name'          => $name,
				'match_type'    => $type,
				'cep_from'      => dh_normalize_cep( $row['cep_from'] ?? '' ),
				'cep_to'        => dh_normalize_cep( $row['cep_to'] ?? '' ),
				'ceps'          => $ceps,
				'neighborhoods' => $neighborhoods,
				'fee'           => floatval( str_replace( ',', '.', $row['fee'] ?? 0 ) ),
				'eta_min'       => $row['eta_min'] !== '' ? intval( $row['eta_min'] ) : '',
				'min_order'     => floatval( str_replace( ',', '.', $row['min_order'] ?? 0 ) ),
				'active'        => ! empty( $row['active'] ),
			];
			if ( intval( $row['id'] ?? 0 ) >= $next_id ) $next_id = intval( $row['id'] ) + 1;
		}
	}
	$out['delivery_areas'] = $areas;

	$out['delivery_areas_config'] = [
		'fallback_action' => in_array( $input['delivery_areas_config']['fallback_action'] ?? '', [ 'block', 'allow_with_default_fee' ], true )
			? $input['delivery_areas_config']['fallback_action']
			: 'block',
		'suggest_pickup'  => ! empty( $input['delivery_areas_config']['suggest_pickup'] ) ? 1 : 0,
	];

	// Invalida cache de checagens
	dh_flush_area_cache();

	return $out;
}, 20, 2 );

/**
 * Invalida todos os transients de área (chamado após save de settings).
 */
function dh_flush_area_cache() {
	global $wpdb;
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_dh_area_%' OR option_name LIKE '_transient_timeout_dh_area_%'" );
}
