<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * CPT dh_customer — chaveado por telefone (post_name = telefone só dígitos).
 * Guarda: nome (post_title), addresses[] (meta), last_seen (meta).
 */
add_action( 'init', function () {
	register_post_type( 'dh_customer', [
		'labels' => [
			'name'                  => 'Clientes',
			'singular_name'         => 'Cliente',
			'menu_name'             => 'Clientes',
			'name_admin_bar'        => 'Cliente',
			'all_items'             => 'Todos os Clientes',
			'add_new'               => 'Adicionar Cliente',
			'add_new_item'          => 'Adicionar Cliente',
			'new_item'              => 'Novo Cliente',
			'edit_item'             => 'Editar Cliente',
			'view_item'             => 'Ver Cliente',
			'view_items'            => 'Ver Clientes',
			'search_items'          => 'Buscar Clientes',
			'not_found'             => 'Nenhum cliente encontrado',
			'not_found_in_trash'    => 'Nenhum cliente na lixeira',
			'items_list'            => 'Lista de clientes',
			'items_list_navigation' => 'Navegação da lista de clientes',
		],
		'public'        => false,
		'show_ui'       => true,
		'show_in_menu'  => true,
		'menu_icon'     => 'dashicons-groups',
		'menu_position' => 6,
		'supports'      => [ 'title' ],
		'capability_type' => 'post',
	] );
} );

function dh_normalize_phone( $phone ) {
	return preg_replace( '/\D/', '', (string) $phone );
}

function dh_find_customer_by_phone( $phone ) {
	$phone = dh_normalize_phone( $phone );
	if ( ! $phone ) return null;

	$q = new WP_Query( [
		'post_type'      => 'dh_customer',
		'name'           => $phone,
		'posts_per_page' => 1,
		'no_found_rows'  => true,
	] );
	return $q->have_posts() ? $q->posts[0] : null;
}

function dh_upsert_customer( $phone, $name, $address = null ) {
	$phone = dh_normalize_phone( $phone );
	if ( ! $phone ) return 0;

	$existing = dh_find_customer_by_phone( $phone );

	if ( $existing ) {
		$post_id = $existing->ID;
		wp_update_post( [
			'ID'         => $post_id,
			'post_title' => $name ?: $existing->post_title,
		] );
	} else {
		$post_id = wp_insert_post( [
			'post_type'   => 'dh_customer',
			'post_status' => 'publish',
			'post_title'  => $name ?: 'Cliente ' . $phone,
			'post_name'   => $phone,
		] );
		update_post_meta( $post_id, '_dh_phone', $phone );
	}

	update_post_meta( $post_id, '_dh_last_seen', current_time( 'mysql' ) );

	if ( $address && ! empty( $address['street'] ) ) {
		dh_add_customer_address( $post_id, $address );
	}

	return $post_id;
}

function dh_get_customer_addresses( $customer_id ) {
	$addrs = get_post_meta( $customer_id, '_dh_addresses', true );
	return is_array( $addrs ) ? $addrs : [];
}

function dh_add_customer_address( $customer_id, $address ) {
	$fields = [ 'cep', 'street', 'number', 'neighborhood', 'complement', 'reference', 'city', 'state' ];
	$normalized = [];
	foreach ( $fields as $f ) $normalized[ $f ] = trim( (string) ( $address[ $f ] ?? '' ) );

	$sig = md5( strtolower( $normalized['cep'] . '|' . $normalized['street'] . '|' . $normalized['number'] . '|' . $normalized['complement'] ) );

	$addrs = dh_get_customer_addresses( $customer_id );
	foreach ( $addrs as $a ) {
		if ( ( $a['sig'] ?? '' ) === $sig ) return; // já existe
	}

	$normalized['sig']       = $sig;
	$normalized['label']     = dh_guess_address_label( $normalized, $addrs );
	$normalized['added_at']  = current_time( 'mysql' );

	array_unshift( $addrs, $normalized ); // mais recente primeiro
	$addrs = array_slice( $addrs, 0, 8 ); // limita 8 endereços por cliente

	update_post_meta( $customer_id, '_dh_addresses', $addrs );
}

function dh_guess_address_label( $addr, $existing ) {
	// Se é o primeiro, chama de "Casa"
	if ( empty( $existing ) ) return 'Casa';
	// Senão, tenta bairro; senão, "Endereço N"
	if ( ! empty( $addr['neighborhood'] ) ) return $addr['neighborhood'];
	return 'Endereço ' . ( count( $existing ) + 1 );
}

function dh_get_customer_orders( $customer_id_or_phone, $limit = 20 ) {
	$phone = is_numeric( $customer_id_or_phone ) && strlen( (string) $customer_id_or_phone ) > 6
		? dh_normalize_phone( $customer_id_or_phone )
		: null;

	if ( ! $phone ) {
		$c = get_post( $customer_id_or_phone );
		if ( ! $c ) return [];
		$phone = get_post_meta( $c->ID, '_dh_phone', true );
	}
	if ( ! $phone ) return [];

	// Busca pedidos por telefone gravado no meta _dh_customer['phone']
	global $wpdb;
	$like = '%' . $wpdb->esc_like( '"phone";s:' . strlen( $phone ) . ':"' . $phone . '"' ) . '%';
	$ids = $wpdb->get_col( $wpdb->prepare(
		"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_dh_customer' AND meta_value LIKE %s ORDER BY meta_id DESC LIMIT %d",
		$like,
		$limit
	) );

	if ( empty( $ids ) ) return [];

	return get_posts( [
		'post_type'      => 'dh_order',
		'post_status'    => array_keys( dh_order_statuses() ),
		'post__in'       => array_map( 'intval', $ids ),
		'orderby'        => 'post__in',
		'posts_per_page' => $limit,
	] );
}

/**
 * Metabox no admin do cliente mostrando endereços e histórico.
 */
add_action( 'add_meta_boxes', function () {
	add_meta_box( 'dh_customer_detail', 'Endereços e Pedidos', 'dh_mb_customer_detail', 'dh_customer', 'normal', 'high' );
} );

function dh_mb_customer_detail( $post ) {
	$phone = get_post_meta( $post->ID, '_dh_phone', true );
	$addrs = dh_get_customer_addresses( $post->ID );
	$orders = dh_get_customer_orders( $post->ID, 10 );
	$statuses = dh_order_statuses();
	?>
	<p><strong>Telefone:</strong> <?php echo esc_html( $phone ); ?></p>

	<h3>Endereços salvos (<?php echo count( $addrs ); ?>)</h3>
	<?php if ( $addrs ) : ?>
		<table class="widefat striped">
			<thead><tr><th>Rótulo</th><th>Endereço</th><th>CEP</th></tr></thead>
			<tbody>
			<?php foreach ( $addrs as $a ) : ?>
				<tr>
					<td><strong><?php echo esc_html( $a['label'] ); ?></strong></td>
					<td>
						<?php echo esc_html( $a['street'] . ( $a['number'] ? ', ' . $a['number'] : '' ) ); ?>
						<?php if ( $a['complement'] ) echo ' — ' . esc_html( $a['complement'] ); ?>
						<br><small><?php echo esc_html( $a['neighborhood'] . ' — ' . $a['city'] . '/' . $a['state'] ); ?></small>
					</td>
					<td><?php echo esc_html( preg_replace( '/^(\d{5})(\d{3})$/', '$1-$2', $a['cep'] ) ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	<?php else : ?>
		<p><em>Nenhum endereço salvo.</em></p>
	<?php endif; ?>

	<h3>Histórico de pedidos (<?php echo count( $orders ); ?>)</h3>
	<?php if ( $orders ) : ?>
		<table class="widefat striped">
			<thead><tr><th>#</th><th>Data</th><th>Total</th><th>Status</th></tr></thead>
			<tbody>
			<?php foreach ( $orders as $o ) :
				$cfg = $statuses[ $o->post_status ] ?? null;
				?>
				<tr>
					<td><a href="<?php echo esc_url( get_edit_post_link( $o->ID ) ); ?>"><strong>#<?php echo $o->ID; ?></strong></a></td>
					<td><?php echo esc_html( get_the_time( 'd/m/y H:i', $o ) ); ?></td>
					<td><?php echo dh_money( get_post_meta( $o->ID, '_dh_total', true ) ); ?></td>
					<td>
						<?php if ( $cfg ) : ?>
							<span class="dh-status-pill" style="background: <?php echo esc_attr( $cfg['color'] ); ?>"><?php echo esc_html( $cfg['label'] ); ?></span>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	<?php else : ?>
		<p><em>Nenhum pedido ainda.</em></p>
	<?php endif; ?>
	<?php
}
