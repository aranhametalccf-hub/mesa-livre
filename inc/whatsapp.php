<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Monta a URL wa.me com a mensagem formatada do pedido.
 */
function dh_build_whatsapp_url( $order_id ) {
	$s = dh_get_settings();
	$customer = get_post_meta( $order_id, '_dh_customer', true ) ?: [];
	$items    = get_post_meta( $order_id, '_dh_items', true ) ?: [];
	$subtotal = (float) get_post_meta( $order_id, '_dh_subtotal', true );
	$fee      = (float) get_post_meta( $order_id, '_dh_delivery_fee', true );
	$total    = (float) get_post_meta( $order_id, '_dh_total', true );

	$type_map = [
		'delivery' => 'Entrega',
		'pickup'   => 'Retirada no balcão',
		'dinein'   => 'Consumir no local',
	];
	$type_label = $type_map[ $customer['type'] ?? 'delivery' ] ?? 'Entrega';

	$lines = [];
	$lines[] = '*NOVO PEDIDO — #' . $order_id . '*';
	$lines[] = str_repeat( '━', 20 );
	$lines[] = '*Cliente:* ' . $customer['name'];
	$lines[] = '*Telefone:* ' . $customer['phone'];
	$lines[] = '*Tipo:* ' . $type_label;

	if ( ( $customer['type'] ?? '' ) === 'delivery' ) {
		$has_struct = ! empty( $customer['street'] ) || ! empty( $customer['cep'] );
		if ( $has_struct ) {
			$rua = trim( ( $customer['street'] ?? '' ) . ( ! empty( $customer['number'] ) ? ', ' . $customer['number'] : '' ) );
			if ( ! empty( $customer['complement'] ) ) $rua .= ' — ' . $customer['complement'];
			$lines[] = '*Endereço:* ' . $rua;
			if ( ! empty( $customer['neighborhood'] ) ) $lines[] = '*Bairro:* ' . $customer['neighborhood'];
			$cidade_uf = trim( ( $customer['city'] ?? '' ) . ( ! empty( $customer['state'] ) ? '/' . $customer['state'] : '' ) );
			if ( $cidade_uf ) $lines[] = '*Cidade:* ' . $cidade_uf;
			if ( ! empty( $customer['cep'] ) ) {
				$cep_fmt = preg_replace( '/^(\d{5})(\d{3})$/', '$1-$2', $customer['cep'] );
				$lines[] = '*CEP:* ' . $cep_fmt;
			}
			if ( ! empty( $customer['reference'] ) ) $lines[] = '*Referência:* ' . $customer['reference'];
		} elseif ( ! empty( $customer['address'] ) ) {
			$lines[] = '*Endereço:* ' . $customer['address'];
		}
	}
	if ( ( $customer['type'] ?? '' ) === 'dinein' && ! empty( $customer['table'] ) ) {
		$lines[] = '*Mesa:* ' . $customer['table'];
	}
	$lines[] = '';
	$lines[] = '*ITENS*';

	foreach ( $items as $it ) {
		$lines[] = $it['qty'] . 'x ' . $it['name'] . ' — ' . dh_money( $it['total'] );
		if ( ! empty( $it['extras'] ) ) {
			foreach ( $it['extras'] as $e ) {
				$suffix = ( ! empty( $e['price'] ) ) ? ' (+' . dh_money( $e['price'] ) . ')' : '';
				$lines[] = '   • ' . $e['label'] . $suffix;
			}
		}
		if ( ! empty( $it['obs'] ) ) {
			$lines[] = '   _obs: ' . $it['obs'] . '_';
		}
	}

	$lines[] = '';
	$lines[] = '*Subtotal:* ' . dh_money( $subtotal );
	if ( $fee > 0 ) {
		$lines[] = '*Taxa de entrega:* ' . dh_money( $fee );
	}
	$lines[] = '*TOTAL:* ' . dh_money( $total );
	$lines[] = '';
	$lines[] = '*Pagamento:* ' . ( $customer['payment'] ?? '-' );
	if ( ! empty( $customer['change'] ) ) {
		$lines[] = '*Troco para:* ' . $customer['change'];
	}
	if ( ! empty( $customer['notes'] ) ) {
		$lines[] = '';
		$lines[] = '*Observações do pedido:* ' . $customer['notes'];
	}

	$msg = implode( "\n", $lines );

	$whatsapp = preg_replace( '/\D/', '', $s['whatsapp'] );
	return 'https://wa.me/' . $whatsapp . '?text=' . rawurlencode( $msg );
}
