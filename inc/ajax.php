<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Recebe o pedido do frontend, cria post dh_order com status dh-novo.
 * Retorna o número do pedido + URL do WhatsApp pré-formatado.
 */
add_action( 'wp_ajax_dh_place_order', 'dh_place_order' );
add_action( 'wp_ajax_nopriv_dh_place_order', 'dh_place_order' );

function dh_place_order() {
	check_ajax_referer( 'dh_order', 'nonce' );

	$raw = file_get_contents( 'php://input' );
	$payload = json_decode( $raw, true );

	if ( ! $payload || empty( $payload['items'] ) ) {
		wp_send_json_error( [ 'message' => 'Pedido vazio' ], 400 );
	}

	$customer     = $payload['customer'] ?? [];
	$name         = sanitize_text_field( $customer['name'] ?? '' );
	$phone        = preg_replace( '/\D/', '', $customer['phone'] ?? '' );
	$type         = sanitize_text_field( $customer['type'] ?? 'delivery' ); // delivery|pickup|dinein
	$address      = sanitize_textarea_field( $customer['address'] ?? '' );
	$cep          = preg_replace( '/\D/', '', $customer['cep'] ?? '' );
	$street       = sanitize_text_field( $customer['street'] ?? '' );
	$number       = sanitize_text_field( $customer['number'] ?? '' );
	$neighborhood = sanitize_text_field( $customer['neighborhood'] ?? '' );
	$complement   = sanitize_text_field( $customer['complement'] ?? '' );
	$reference    = sanitize_text_field( $customer['reference'] ?? '' );
	$city         = sanitize_text_field( $customer['city'] ?? '' );
	$state        = sanitize_text_field( $customer['state'] ?? '' );
	$payment      = sanitize_text_field( $customer['payment'] ?? '' );
	$change       = sanitize_text_field( $customer['change'] ?? '' );
	$notes        = sanitize_textarea_field( $customer['notes'] ?? '' );
	$table        = sanitize_text_field( $customer['table'] ?? '' );

	if ( empty( $name ) || empty( $phone ) ) {
		wp_send_json_error( [ 'message' => 'Nome e telefone são obrigatórios' ], 400 );
	}

	// Recalcula totais no server pra segurança.
	$items = [];
	$subtotal = 0;
	foreach ( $payload['items'] as $line ) {
		$pid = intval( $line['id'] ?? 0 );
		$post = get_post( $pid );
		if ( ! $post || $post->post_type !== 'menu_item' ) continue;

		$qty = max( 1, intval( $line['qty'] ?? 1 ) );
		$base = dh_item_price( $pid );
		$extras_total = 0;
		$chosen_extras = [];

		if ( ! empty( $line['options'] ) && is_array( $line['options'] ) ) {
			foreach ( $line['options'] as $opt ) {
				$chosen_extras[] = [
					'group' => sanitize_text_field( $opt['group'] ?? '' ),
					'label' => sanitize_text_field( $opt['label'] ?? '' ),
					'price' => floatval( $opt['price'] ?? 0 ),
				];
				$extras_total += floatval( $opt['price'] ?? 0 );
			}
		}

		$line_total = ( $base + $extras_total ) * $qty;
		$subtotal += $line_total;

		$items[] = [
			'id'      => $pid,
			'name'    => $post->post_title,
			'qty'     => $qty,
			'price'   => $base,
			'extras'  => $chosen_extras,
			'obs'     => sanitize_text_field( $line['obs'] ?? '' ),
			'total'   => $line_total,
		];
	}

	if ( empty( $items ) ) {
		wp_send_json_error( [ 'message' => 'Nenhum item válido' ], 400 );
	}

	$s = dh_get_settings();
	$delivery_fee = ( $type === 'delivery' ) ? (float) $s['delivery_fee'] : 0;

	if ( $subtotal < (float) $s['min_order'] ) {
		wp_send_json_error( [ 'message' => 'Pedido mínimo: ' . dh_money( $s['min_order'] ) ], 400 );
	}

	$total = $subtotal + $delivery_fee;

	// Cria o pedido
	$order_id = wp_insert_post( [
		'post_type'   => 'dh_order',
		'post_status' => 'dh-novo',
		'post_title'  => 'Pedido — ' . $name . ' — ' . current_time( 'd/m H:i' ),
	] );

	if ( is_wp_error( $order_id ) ) {
		wp_send_json_error( [ 'message' => 'Falha ao registrar pedido' ], 500 );
	}

	update_post_meta( $order_id, '_dh_customer', [
		'name'         => $name,
		'phone'        => $phone,
		'type'         => $type,
		'address'      => $address,
		'cep'          => $cep,
		'street'       => $street,
		'number'       => $number,
		'neighborhood' => $neighborhood,
		'complement'   => $complement,
		'reference'    => $reference,
		'city'         => $city,
		'state'        => $state,
		'payment'      => $payment,
		'change'       => $change,
		'notes'        => $notes,
		'table'        => $table,
	] );

	// Upsert cliente + salva endereço se veio na entrega
	$address_data = null;
	if ( $type === 'delivery' && ! empty( $street ) ) {
		$address_data = compact( 'cep', 'street', 'number', 'neighborhood', 'complement', 'reference', 'city', 'state' );
	}
	dh_upsert_customer( $phone, $name, $address_data );
	update_post_meta( $order_id, '_dh_items', $items );
	update_post_meta( $order_id, '_dh_subtotal', $subtotal );
	update_post_meta( $order_id, '_dh_delivery_fee', $delivery_fee );
	update_post_meta( $order_id, '_dh_total', $total );

	$wa_url = dh_build_whatsapp_url( $order_id );

	wp_send_json_success( [
		'order_id'     => $order_id,
		'total'        => $total,
		'whatsapp'     => $wa_url,
		'tracking_url' => home_url( '/pedido/' . $order_id ),
	] );
}

/**
 * Alterna soldout de um item do cardápio direto do PDV.
 */
add_action( 'wp_ajax_dh_toggle_soldout', function () {
	check_ajax_referer( 'dh_pdv', 'nonce' );
	if ( ! current_user_can( 'edit_posts' ) ) wp_send_json_error( 'sem permissão', 403 );
	$item_id = intval( $_POST['item_id'] ?? 0 );
	$post = get_post( $item_id );
	if ( ! $post || $post->post_type !== 'menu_item' ) wp_send_json_error( 'item não encontrado', 404 );

	$new = get_post_meta( $item_id, '_dh_soldout', true ) ? '' : '1';
	update_post_meta( $item_id, '_dh_soldout', $new );

	wp_send_json_success( [
		'item_id' => $item_id,
		'soldout' => (bool) $new,
		'label'   => $new ? 'Marcado como esgotado' : 'Reativado no cardápio',
	] );
} );

/**
 * Duplica um pedido — cria novo status dh-novo com mesmos itens + cliente.
 */
add_action( 'wp_ajax_dh_reorder', function () {
	check_ajax_referer( 'dh_pdv', 'nonce' );
	if ( ! current_user_can( 'edit_posts' ) ) wp_send_json_error( 'sem permissão', 403 );
	$src_id = intval( $_POST['order_id'] ?? 0 );
	$src = get_post( $src_id );
	if ( ! $src || $src->post_type !== 'dh_order' ) wp_send_json_error( 'pedido inválido', 404 );

	$customer = get_post_meta( $src_id, '_dh_customer', true ) ?: [];
	$items    = get_post_meta( $src_id, '_dh_items', true ) ?: [];
	$subtotal = (float) get_post_meta( $src_id, '_dh_subtotal', true );
	$fee      = (float) get_post_meta( $src_id, '_dh_delivery_fee', true );
	$total    = (float) get_post_meta( $src_id, '_dh_total', true );

	$new_id = wp_insert_post( [
		'post_type'   => 'dh_order',
		'post_status' => 'dh-novo',
		'post_title'  => 'Pedido — ' . ( $customer['name'] ?? 'cliente' ) . ' — ' . current_time( 'd/m H:i' ) . ' (repetido do #' . $src_id . ')',
	] );
	if ( is_wp_error( $new_id ) ) wp_send_json_error( $new_id->get_error_message(), 500 );

	update_post_meta( $new_id, '_dh_customer',     $customer );
	update_post_meta( $new_id, '_dh_items',        $items );
	update_post_meta( $new_id, '_dh_subtotal',     $subtotal );
	update_post_meta( $new_id, '_dh_delivery_fee', $fee );
	update_post_meta( $new_id, '_dh_total',        $total );

	dh_log_change( $new_id, 'reordered_from', 'Duplicado a partir do pedido #' . $src_id );

	wp_send_json_success( [
		'new_id'   => $new_id,
		'edit_url' => get_edit_post_link( $new_id, 'raw' ),
	] );
} );

/**
 * Contagem de pedidos "Novo" — usado pelo PDV pra tocar som quando entra pedido.
 */
add_action( 'wp_ajax_dh_count_new', function () {
	check_ajax_referer( 'dh_pdv', 'nonce' );
	$c = count( get_posts( [
		'post_type'      => 'dh_order',
		'post_status'    => 'dh-novo',
		'posts_per_page' => 100,
		'fields'         => 'ids',
	] ) );
	wp_send_json_success( [ 'count' => $c ] );
} );

/**
 * Muda status do pedido pelo Kanban (drag-drop).
 */
add_action( 'wp_ajax_dh_move_order', function () {
	check_ajax_referer( 'dh_pdv', 'nonce' );
	if ( ! current_user_can( 'edit_posts' ) ) wp_send_json_error( 'sem permissão', 403 );

	$id = intval( $_POST['id'] ?? 0 );
	$status = sanitize_text_field( $_POST['status'] ?? '' );
	if ( ! array_key_exists( $status, dh_order_statuses() ) ) {
		wp_send_json_error( 'status inválido', 400 );
	}

	$ok = wp_update_post( [ 'ID' => $id, 'post_status' => $status ], true );
	if ( is_wp_error( $ok ) ) wp_send_json_error( $ok->get_error_message(), 500 );

	wp_send_json_success();
} );

/**
 * Editor de pedido: itens (qty/add/remove), taxa, observação e cliente.
 * Todas as ações registradas no audit log (_dh_change_log via dh_log_change).
 */
add_action( 'wp_ajax_dh_order_edit_items', function () {
	$id = intval( $_POST['order_id'] ?? 0 );
	check_ajax_referer( 'dh_order_edit_' . $id, 'nonce' );
	if ( ! current_user_can( 'edit_post', $id ) ) wp_send_json_error( [ 'message' => 'sem permissão' ], 403 );

	$order = get_post( $id );
	if ( ! $order || $order->post_type !== 'dh_order' ) wp_send_json_error( [ 'message' => 'pedido inválido' ], 404 );

	if ( in_array( $order->post_status, [ 'dh-entregue', 'dh-cancelado' ], true ) ) {
		wp_send_json_error( [ 'message' => 'pedido finalizado não pode ser editado' ], 400 );
	}

	$action = sanitize_text_field( $_POST['edit_action'] ?? '' );
	$items  = get_post_meta( $id, '_dh_items', true ) ?: [];

	switch ( $action ) {
		case 'set_qty': {
			$i = intval( $_POST['item_index'] ?? -1 );
			$q = max( 1, intval( $_POST['qty'] ?? 1 ) );
			if ( ! isset( $items[ $i ] ) ) wp_send_json_error( [ 'message' => 'item inexistente' ], 400 );
			$old_qty = intval( $items[ $i ]['qty'] );
			$items[ $i ]['qty']   = $q;
			$items[ $i ]['total'] = dh_recalc_line_total( $items[ $i ] );
			update_post_meta( $id, '_dh_items', $items );
			$totals = dh_recalc_order_totals( $id );
			dh_log_change( $id, 'qty_changed', $items[ $i ]['name'] . ': ' . $old_qty . 'x → ' . $q . 'x' );
			wp_send_json_success( [
				'line_total_fmt' => dh_money( $items[ $i ]['total'] ),
				'totals' => [
					'subtotal_fmt' => dh_money( $totals['subtotal'] ),
					'total_fmt'    => dh_money( $totals['total'] ),
				],
			] );
		}
		case 'remove_line': {
			$i = intval( $_POST['item_index'] ?? -1 );
			if ( ! isset( $items[ $i ] ) ) wp_send_json_error( [ 'message' => 'item inexistente' ], 400 );
			$removed = $items[ $i ];
			array_splice( $items, $i, 1 );
			update_post_meta( $id, '_dh_items', $items );
			dh_recalc_order_totals( $id );
			dh_log_change( $id, 'item_removed', $removed['qty'] . 'x ' . $removed['name'] . ' (' . dh_money( $removed['total'] ) . ')' );
			wp_send_json_success();
		}
		case 'add_item': {
			$pid = intval( $_POST['item_id'] ?? 0 );
			$post_item = get_post( $pid );
			if ( ! $post_item || $post_item->post_type !== 'menu_item' ) {
				wp_send_json_error( [ 'message' => 'item do cardápio não encontrado' ], 400 );
			}
			$price = dh_item_price( $pid );
			$new_line = [
				'id'     => $pid,
				'name'   => $post_item->post_title,
				'qty'    => 1,
				'price'  => $price,
				'extras' => [],
				'obs'    => '',
				'total'  => $price,
			];
			$items[] = $new_line;
			update_post_meta( $id, '_dh_items', $items );
			dh_recalc_order_totals( $id );
			dh_log_change( $id, 'item_added', '1x ' . $new_line['name'] . ' (' . dh_money( $price ) . ')' );
			wp_send_json_success();
		}
		case 'set_fee': {
			$new_fee = floatval( str_replace( ',', '.', $_POST['fee'] ?? 0 ) );
			$old_fee = (float) get_post_meta( $id, '_dh_delivery_fee', true );
			update_post_meta( $id, '_dh_delivery_fee', $new_fee );
			$totals = dh_recalc_order_totals( $id );
			dh_log_change( $id, 'fee_changed', dh_money( $old_fee ) . ' → ' . dh_money( $new_fee ) );
			wp_send_json_success( [
				'totals' => [
					'subtotal_fmt' => dh_money( $totals['subtotal'] ),
					'total_fmt'    => dh_money( $totals['total'] ),
				],
			] );
		}
		case 'get_line': {
			$i = intval( $_POST['item_index'] ?? -1 );
			if ( ! isset( $items[ $i ] ) ) wp_send_json_error( [ 'message' => 'item inexistente' ], 400 );
			wp_send_json_success( $items[ $i ] );
		}
		case 'set_line': {
			$i = intval( $_POST['item_index'] ?? -1 );
			if ( ! isset( $items[ $i ] ) ) wp_send_json_error( [ 'message' => 'item inexistente' ], 400 );

			$extras_raw = wp_unslash( $_POST['extras'] ?? '[]' );
			$extras_in  = json_decode( $extras_raw, true );
			$extras_in  = is_array( $extras_in ) ? $extras_in : [];

			$new_extras = [];
			foreach ( $extras_in as $e ) {
				$new_extras[] = [
					'group' => sanitize_text_field( $e['group'] ?? '' ),
					'label' => sanitize_text_field( $e['label'] ?? '' ),
					'price' => floatval( $e['price'] ?? 0 ),
				];
			}
			$new_obs = sanitize_text_field( $_POST['obs'] ?? '' );

			// Diff pro audit log
			$old_extras_str = implode( ' + ', array_map( fn( $e ) => $e['label'], $items[ $i ]['extras'] ?? [] ) ) ?: '(nenhum)';
			$new_extras_str = implode( ' + ', array_map( fn( $e ) => $e['label'], $new_extras ) ) ?: '(nenhum)';
			$old_obs = $items[ $i ]['obs'] ?? '';

			$items[ $i ]['extras'] = $new_extras;
			$items[ $i ]['obs']    = $new_obs;
			$items[ $i ]['total']  = dh_recalc_line_total( $items[ $i ] );
			update_post_meta( $id, '_dh_items', $items );
			$totals = dh_recalc_order_totals( $id );

			$changes = [];
			if ( $old_extras_str !== $new_extras_str ) {
				$changes[] = 'extras: ' . $old_extras_str . ' → ' . $new_extras_str;
			}
			if ( $old_obs !== $new_obs ) {
				$changes[] = 'obs: "' . ( $old_obs ?: '-' ) . '" → "' . ( $new_obs ?: '-' ) . '"';
			}
			if ( $changes ) {
				dh_log_change( $id, 'item_edited', $items[ $i ]['name'] . ' — ' . implode( '; ', $changes ) );
			}

			wp_send_json_success( [
				'line_total_fmt' => dh_money( $items[ $i ]['total'] ),
				'totals' => [
					'subtotal_fmt' => dh_money( $totals['subtotal'] ),
					'total_fmt'    => dh_money( $totals['total'] ),
				],
			] );
		}
		case 'set_notes': {
			$notes = sanitize_textarea_field( $_POST['notes'] ?? '' );
			$customer = get_post_meta( $id, '_dh_customer', true ) ?: [];
			$old = $customer['notes'] ?? '';
			$customer['notes'] = $notes;
			update_post_meta( $id, '_dh_customer', $customer );
			$snippet = mb_strlen( $notes ) > 60 ? mb_substr( $notes, 0, 60 ) . '…' : $notes;
			dh_log_change( $id, 'notes_changed', $snippet ?: '(vazio)' );
			wp_send_json_success();
		}
		case 'set_customer': {
			$customer = get_post_meta( $id, '_dh_customer', true ) ?: [];
			$diff = [];
			$new_name = sanitize_text_field( $_POST['name'] ?? '' );
			$new_phone = preg_replace( '/\D/', '', $_POST['phone'] ?? '' );
			$new_addr = sanitize_textarea_field( $_POST['address'] ?? '' );
			if ( $new_name !== ( $customer['name'] ?? '' ) )       $diff[] = 'nome';
			if ( $new_phone !== ( $customer['phone'] ?? '' ) )     $diff[] = 'telefone';
			if ( $new_addr !== ( $customer['address'] ?? '' ) )    $diff[] = 'endereço';
			$customer['name']    = $new_name;
			$customer['phone']   = $new_phone;
			$customer['address'] = $new_addr;
			update_post_meta( $id, '_dh_customer', $customer );
			if ( $diff ) dh_log_change( $id, 'customer_changed', 'alterou: ' . implode( ', ', $diff ) );
			wp_send_json_success();
		}
		default:
			wp_send_json_error( [ 'message' => 'ação inválida' ], 400 );
	}
} );

/**
 * Log de mudança de status (chama dh_log_change do hook transition_post_status).
 */
add_action( 'transition_post_status', function ( $new, $old, $post ) {
	if ( $post->post_type !== 'dh_order' ) return;
	if ( $new === $old || $old === 'auto-draft' || $old === 'new' ) return;
	if ( ! array_key_exists( $new, dh_order_statuses() ) ) return;
	$statuses = dh_order_statuses();
	$old_label = $statuses[ $old ]['label'] ?? $old;
	$new_label = $statuses[ $new ]['label'] ?? $new;
	dh_log_change( $post->ID, 'status_changed', $old_label . ' → ' . $new_label );
}, 20, 3 );

/**
 * Muda status do pedido pela tela de edição (nonce por-pedido).
 */
add_action( 'wp_ajax_dh_order_action', function () {
	$id = intval( $_POST['id'] ?? 0 );
	check_ajax_referer( 'dh_order_action_' . $id, 'nonce' );
	if ( ! current_user_can( 'edit_post', $id ) ) {
		wp_send_json_error( [ 'message' => 'sem permissão' ], 403 );
	}

	$status = sanitize_text_field( $_POST['status'] ?? '' );
	if ( ! array_key_exists( $status, dh_order_statuses() ) ) {
		wp_send_json_error( [ 'message' => 'status inválido' ], 400 );
	}

	$ok = wp_update_post( [ 'ID' => $id, 'post_status' => $status ], true );
	if ( is_wp_error( $ok ) ) wp_send_json_error( [ 'message' => $ok->get_error_message() ], 500 );

	wp_send_json_success( [ 'status' => $status ] );
} );
