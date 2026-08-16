<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'init', function () {

	register_post_type( 'menu_item', [
		'labels' => [
			'name'                  => 'Itens do Cardápio',
			'singular_name'         => 'Item',
			'menu_name'             => 'Cardápio',
			'name_admin_bar'        => 'Item do Cardápio',
			'all_items'             => 'Todos os Itens',
			'add_new'               => 'Adicionar Item',
			'add_new_item'          => 'Adicionar Item',
			'new_item'              => 'Novo Item',
			'edit_item'             => 'Editar Item',
			'view_item'             => 'Ver Item',
			'view_items'            => 'Ver Itens',
			'search_items'          => 'Buscar Itens',
			'not_found'             => 'Nenhum item encontrado',
			'not_found_in_trash'    => 'Nenhum item na lixeira',
			'parent_item_colon'     => 'Item pai:',
			'featured_image'        => 'Foto do item',
			'set_featured_image'    => 'Definir foto do item',
			'remove_featured_image' => 'Remover foto',
			'use_featured_image'    => 'Usar como foto',
			'archives'              => 'Arquivo de itens',
			'insert_into_item'      => 'Inserir no item',
			'uploaded_to_this_item' => 'Enviado para este item',
			'filter_items_list'     => 'Filtrar itens',
			'items_list'            => 'Lista de itens',
			'items_list_navigation' => 'Navegação da lista de itens',
		],
		'public'       => true,
		'show_in_rest' => true,
		'has_archive'  => false,
		'menu_icon'    => 'dashicons-food',
		'menu_position' => 5,
		'supports'     => [ 'title', 'editor', 'thumbnail', 'page-attributes' ],
		'rewrite'      => [ 'slug' => 'cardapio' ],
	] );

	register_taxonomy( 'menu_category', 'menu_item', [
		'labels' => [
			'name'                       => 'Categorias',
			'singular_name'              => 'Categoria',
			'menu_name'                  => 'Categorias',
			'all_items'                  => 'Todas as Categorias',
			'edit_item'                  => 'Editar Categoria',
			'view_item'                  => 'Ver Categoria',
			'update_item'                => 'Atualizar Categoria',
			'add_new_item'               => 'Adicionar Categoria',
			'new_item_name'              => 'Nome da nova Categoria',
			'search_items'               => 'Buscar Categorias',
			'popular_items'              => 'Categorias populares',
			'not_found'                  => 'Nenhuma categoria encontrada',
			'no_terms'                   => 'Sem categorias',
			'items_list'                 => 'Lista de categorias',
			'back_to_items'              => '← Voltar para categorias',
		],
		'hierarchical'      => true,
		'show_admin_column' => true,
		'show_in_rest'      => true,
		'rewrite'           => [ 'slug' => 'categoria' ],
	] );

	register_post_type( 'dh_order', [
		'labels' => [
			'name'                  => 'Pedidos',
			'singular_name'         => 'Pedido',
			'menu_name'             => 'Pedidos',
			'name_admin_bar'        => 'Pedido',
			'all_items'             => 'Todos os Pedidos',
			'add_new'               => 'Adicionar Pedido',
			'add_new_item'          => 'Adicionar Pedido',
			'new_item'              => 'Novo Pedido',
			'edit_item'             => 'Editar Pedido',
			'view_item'             => 'Ver Pedido',
			'view_items'            => 'Ver Pedidos',
			'search_items'          => 'Buscar Pedidos',
			'not_found'             => 'Nenhum pedido encontrado',
			'not_found_in_trash'    => 'Nenhum pedido na lixeira',
			'items_list'            => 'Lista de pedidos',
			'items_list_navigation' => 'Navegação da lista de pedidos',
			'filter_items_list'     => 'Filtrar pedidos',
		],
		'public'       => false,
		'show_ui'      => true,
		'show_in_menu' => true,
		'menu_icon'    => 'dashicons-list-view',
		'menu_position' => 4,
		'supports'     => [ 'title' ],
		'capability_type' => 'post',
	] );

	// Status do pedido (usados no Kanban) — ordem = fluxo natural
	$statuses = dh_order_statuses();
	foreach ( $statuses as $slug => $cfg ) {
		register_post_status( $slug, [
			'label'                     => $cfg['label'],
			'public'                    => false,
			'internal'                  => false,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			'label_count'               => _n_noop(
				$cfg['label'] . ' (%s)',
				$cfg['label'] . ' (%s)'
			),
		] );
	}
} );

/**
 * Lista canônica de status pra reutilização.
 */
function dh_order_statuses() {
	return [
		'dh-novo'       => [ 'label' => 'Novo',       'color' => '#3b82f6' ],
		'dh-preparando' => [ 'label' => 'Preparando', 'color' => '#f59e0b' ],
		'dh-pronto'     => [ 'label' => 'Pronto',     'color' => '#10b981' ],
		'dh-rota'       => [ 'label' => 'Em rota',    'color' => '#8b5cf6' ],
		'dh-entregue'   => [ 'label' => 'Entregue',   'color' => '#6b7280' ],
		'dh-cancelado'  => [ 'label' => 'Cancelado',  'color' => '#ef4444' ],
	];
}

/**
 * Fluxo linear de status pra determinar "próximo" e "anterior" na edição.
 * Não inclui 'cancelado' (transição lateral).
 */
function dh_status_flow() {
	return [ 'dh-novo', 'dh-preparando', 'dh-pronto', 'dh-rota', 'dh-entregue' ];
}

/**
 * Retorna o próximo status contextual — pra entrega vai pra 'em rota', retirada/local pula direto pra entregue.
 */
function dh_next_status( $current, $delivery_type = 'delivery' ) {
	$flow = dh_status_flow();
	$idx = array_search( $current, $flow, true );
	if ( $idx === false || $idx >= count( $flow ) - 1 ) return null;
	$next = $flow[ $idx + 1 ];
	// Se não é entrega, pula "em rota" e vai direto de pronto pra entregue
	if ( $next === 'dh-rota' && $delivery_type !== 'delivery' ) {
		return 'dh-entregue';
	}
	return $next;
}

function dh_prev_status( $current ) {
	$flow = dh_status_flow();
	$idx = array_search( $current, $flow, true );
	if ( $idx === false || $idx <= 0 ) return null;
	return $flow[ $idx - 1 ];
}

/**
 * Registra timeline: sempre que status muda, grava timestamp no meta _dh_status_history.
 */
add_action( 'transition_post_status', function ( $new, $old, $post ) {
	if ( $post->post_type !== 'dh_order' ) return;
	if ( $new === $old ) return;
	if ( ! array_key_exists( $new, dh_order_statuses() ) ) return;

	$history = get_post_meta( $post->ID, '_dh_status_history', true );
	if ( ! is_array( $history ) ) $history = [];
	$history[] = [
		'status' => $new,
		'at'     => current_time( 'mysql' ),
	];
	update_post_meta( $post->ID, '_dh_status_history', $history );
}, 10, 3 );
