<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * CPT dh_delivery — cadastro de entregadores.
 * Fields:
 *   post_title       = nome
 *   featured image   = foto (opcional)
 *   _dh_deliv_phone  = telefone WhatsApp
 *   _dh_deliv_active = '1' se ativo (aparece no dropdown do PDV)
 */
add_action( 'init', function () {
	register_post_type( 'dh_delivery', [
		'labels' => [
			'name'                  => 'Entregadores',
			'singular_name'         => 'Entregador',
			'menu_name'             => 'Entregadores',
			'all_items'             => 'Todos os Entregadores',
			'add_new'               => 'Adicionar Entregador',
			'add_new_item'          => 'Adicionar Entregador',
			'new_item'              => 'Novo Entregador',
			'edit_item'             => 'Editar Entregador',
			'view_item'             => 'Ver Entregador',
			'search_items'          => 'Buscar Entregadores',
			'not_found'             => 'Nenhum entregador cadastrado',
			'not_found_in_trash'    => 'Nenhum entregador na lixeira',
			'featured_image'        => 'Foto do entregador',
			'set_featured_image'    => 'Definir foto',
			'remove_featured_image' => 'Remover foto',
		],
		'public'        => false,
		'show_ui'       => true,
		'show_in_menu'  => true,
		'menu_icon'     => 'dashicons-car',
		'menu_position' => 7,
		'supports'      => [ 'title', 'thumbnail' ],
		'capability_type' => 'post',
	] );

	// Habilita Gutenberg desativado (usa classic editor)
	add_filter( 'use_block_editor_for_post_type', function ( $use, $post_type ) {
		if ( $post_type === 'dh_delivery' ) return false;
		return $use;
	}, 10, 2 );
} );

// Metabox: telefone + ativo
add_action( 'add_meta_boxes', function () {
	add_meta_box( 'dh_deliv_meta', 'Informações do entregador', 'dh_mb_deliv', 'dh_delivery', 'normal', 'high' );
} );

function dh_mb_deliv( $post ) {
	$phone  = get_post_meta( $post->ID, '_dh_deliv_phone', true );
	$active = get_post_meta( $post->ID, '_dh_deliv_active', true );
	if ( $active === '' ) $active = '1'; // default novo = ativo

	// Exibição inicial em formato BR sem DDI: (11) 99999-9999
	$display_phone = '';
	if ( $phone ) {
		// Remove DDI 55 se veio salvo com ele
		$d = preg_replace( '/^55/', '', $phone );
		if ( strlen( $d ) === 11 ) {
			$display_phone = '(' . substr( $d, 0, 2 ) . ') ' . substr( $d, 2, 5 ) . '-' . substr( $d, 7 );
		} elseif ( strlen( $d ) === 10 ) {
			$display_phone = '(' . substr( $d, 0, 2 ) . ') ' . substr( $d, 2, 4 ) . '-' . substr( $d, 6 );
		} else {
			$display_phone = $d;
		}
	}
	wp_nonce_field( 'dh_save_deliv', 'dh_deliv_nonce' );
	?>
	<p>
		<label><strong>WhatsApp</strong></label><br>
		<input type="tel"
			id="dh-deliv-phone"
			name="_dh_deliv_phone"
			value="<?php echo esc_attr( $display_phone ); ?>"
			placeholder="(11) 99999-9999"
			maxlength="15"
			autocomplete="tel"
			inputmode="numeric"
			class="widefat">
		<span class="description">DDD + número. O DDI 55 é adicionado automaticamente ao salvar.</span>
	</p>
	<p>
		<label>
			<input type="checkbox" name="_dh_deliv_active" value="1" <?php checked( $active, '1' ); ?>>
			Ativo (aparece na lista pra atribuir a pedidos)
		</label>
	</p>

	<script>
	(function () {
		const input = document.getElementById('dh-deliv-phone');
		if (!input) return;

		function mask(value) {
			let d = (value || '').replace(/\D/g, '').slice(0, 11);
			if (d.length === 0)  return '';
			if (d.length <= 2)   return `(${d}`;
			if (d.length <= 6)   return `(${d.slice(0,2)}) ${d.slice(2)}`;
			if (d.length <= 10)  return `(${d.slice(0,2)}) ${d.slice(2,6)}-${d.slice(6)}`;
			return `(${d.slice(0,2)}) ${d.slice(2,7)}-${d.slice(7)}`;
		}

		input.addEventListener('input', function () {
			input.value = mask(input.value);
		});

		// Ao submeter, envia só dígitos
		const form = input.closest('form');
		if (form) form.addEventListener('submit', () => {
			input.value = input.value.replace(/\D/g, '');
		}, true);
	})();
	</script>
	<?php
}

add_action( 'save_post_dh_delivery', function ( $post_id ) {
	if ( ! isset( $_POST['dh_deliv_nonce'] ) || ! wp_verify_nonce( $_POST['dh_deliv_nonce'], 'dh_save_deliv' ) ) return;
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
	if ( ! current_user_can( 'edit_post', $post_id ) ) return;

	// Limpa e adiciona DDI 55 se veio só com DDD+número (10 ou 11 dígitos)
	$phone_digits = preg_replace( '/\D/', '', $_POST['_dh_deliv_phone'] ?? '' );
	if ( strlen( $phone_digits ) === 10 || strlen( $phone_digits ) === 11 ) {
		$phone_digits = '55' . $phone_digits;
	}
	update_post_meta( $post_id, '_dh_deliv_phone', $phone_digits );
	update_post_meta( $post_id, '_dh_deliv_active', isset( $_POST['_dh_deliv_active'] ) ? '1' : '' );
} );

/**
 * Listagem de entregadores ativos (usado no PDV).
 */
function dh_get_active_deliveries() {
	return get_posts( [
		'post_type'      => 'dh_delivery',
		'posts_per_page' => -1,
		'meta_query'     => [ [ 'key' => '_dh_deliv_active', 'value' => '1' ] ],
		'orderby'        => 'title',
		'order'          => 'ASC',
	] );
}

function dh_delivery_info( $delivery_id ) {
	if ( ! $delivery_id ) return null;
	$post = get_post( $delivery_id );
	if ( ! $post || $post->post_type !== 'dh_delivery' ) return null;
	return [
		'id'    => $post->ID,
		'name'  => $post->post_title,
		'phone' => preg_replace( '/\D/', '', get_post_meta( $post->ID, '_dh_deliv_phone', true ) ),
		'photo' => get_the_post_thumbnail_url( $post->ID, 'thumbnail' ) ?: '',
	];
}

/**
 * Retorna entregador atribuído ao pedido (ou null).
 */
function dh_order_delivery( $order_id ) {
	$id = intval( get_post_meta( $order_id, '_dh_delivery_id', true ) );
	return $id ? dh_delivery_info( $id ) : null;
}

/**
 * Colunas da listagem admin de entregadores.
 */
add_filter( 'manage_dh_delivery_posts_columns', function ( $cols ) {
	return [
		'cb'         => $cols['cb'] ?? '',
		'title'      => 'Nome',
		'dh_photo'   => 'Foto',
		'dh_phone'   => 'WhatsApp',
		'dh_active'  => 'Status',
		'dh_orders'  => 'Em rota agora',
	];
} );

add_action( 'manage_dh_delivery_posts_custom_column', function ( $col, $post_id ) {
	switch ( $col ) {
		case 'dh_photo':
			$url = get_the_post_thumbnail_url( $post_id, 'thumbnail' );
			if ( $url ) echo '<img src="' . esc_url( $url ) . '" style="width:36px;height:36px;border-radius:50%;object-fit:cover">';
			else echo '<span style="color:#9ca3af">—</span>';
			break;
		case 'dh_phone':
			$p = get_post_meta( $post_id, '_dh_deliv_phone', true );
			echo esc_html( $p ? preg_replace( '/(\d{2})(\d{2})(\d{5})(\d{4})/', '+$1 ($2) $3-$4', $p ) : '—' );
			break;
		case 'dh_active':
			$active = get_post_meta( $post_id, '_dh_deliv_active', true );
			echo $active
				? '<span style="background:#d1fae5;color:#065f46;padding:2px 10px;border-radius:999px;font-size:11px;font-weight:700">ATIVO</span>'
				: '<span style="background:#f3f4f6;color:#6b7280;padding:2px 10px;border-radius:999px;font-size:11px;font-weight:700">Inativo</span>';
			break;
		case 'dh_orders':
			global $wpdb;
			$c = intval( $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} p
				 INNER JOIN {$wpdb->postmeta} m ON p.ID = m.post_id
				 WHERE p.post_type = 'dh_order' AND p.post_status = 'dh-rota'
				 AND m.meta_key = '_dh_delivery_id' AND m.meta_value = %s",
				$post_id
			) ) );
			echo $c > 0 ? '<strong>' . $c . '</strong>' : '<span style="color:#9ca3af">0</span>';
			break;
	}
}, 10, 2 );

/**
 * AJAX — atribuir entregador ao pedido.
 * Aceita opcionalmente 'set_status' pra avançar o pedido pra dh-rota junto.
 */
add_action( 'wp_ajax_dh_assign_delivery', function () {
	check_ajax_referer( 'dh_pdv', 'nonce' );
	if ( ! current_user_can( 'edit_posts' ) ) wp_send_json_error( 'sem permissão', 403 );

	$order_id    = intval( $_POST['order_id'] ?? 0 );
	$delivery_id = intval( $_POST['delivery_id'] ?? 0 );
	$set_status  = sanitize_text_field( $_POST['set_status'] ?? '' );

	$order = get_post( $order_id );
	if ( ! $order || $order->post_type !== 'dh_order' ) wp_send_json_error( 'pedido inválido', 404 );

	$prev = intval( get_post_meta( $order_id, '_dh_delivery_id', true ) );
	if ( $delivery_id ) {
		$info = dh_delivery_info( $delivery_id );
		if ( ! $info ) wp_send_json_error( 'entregador inválido', 404 );
		update_post_meta( $order_id, '_dh_delivery_id', $delivery_id );
		if ( $prev !== $delivery_id ) {
			$prev_name = $prev ? ( dh_delivery_info( $prev )['name'] ?? '?' ) : '(nenhum)';
			dh_log_change( $order_id, 'delivery_assigned', $prev_name . ' → ' . $info['name'] );
		}
	} else {
		delete_post_meta( $order_id, '_dh_delivery_id' );
		if ( $prev ) {
			$prev_name = dh_delivery_info( $prev )['name'] ?? '?';
			dh_log_change( $order_id, 'delivery_assigned', $prev_name . ' → (nenhum)' );
		}
	}

	if ( $set_status === 'dh-rota' && $order->post_status !== 'dh-rota' ) {
		wp_update_post( [ 'ID' => $order_id, 'post_status' => 'dh-rota' ] );
	}

	wp_send_json_success( [ 'delivery' => dh_order_delivery( $order_id ) ] );
} );

/**
 * Bloqueia transição pra dh-rota se nenhum entregador atribuído E existe algum ativo cadastrado.
 * (Se não tem nenhum ativo cadastrado, permite — evita quebrar quem não usa entregador.)
 */
add_filter( 'wp_insert_post_data', function ( $data, $postarr ) {
	if ( ( $data['post_type'] ?? '' ) !== 'dh_order' ) return $data;
	if ( ( $data['post_status'] ?? '' ) !== 'dh-rota' ) return $data;
	if ( empty( $postarr['ID'] ) ) return $data;

	$existing_delivery = intval( get_post_meta( $postarr['ID'], '_dh_delivery_id', true ) );
	if ( $existing_delivery ) return $data;

	$has_active = ! empty( dh_get_active_deliveries() );
	if ( ! $has_active ) return $data;

	// Sem entregador → reverte pra "pronto"
	$data['post_status'] = 'dh-pronto';
	return $data;
}, 10, 2 );
