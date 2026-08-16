<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Seed de dados de exemplo. Roda 1 vez, disparado por botão no admin (evita mexer com activation
 * hooks em tema). Ver menu "Cardápio → Popular exemplos".
 */
add_action( 'admin_menu', function () {
	add_submenu_page(
		'edit.php?post_type=menu_item',
		'Popular exemplos',
		'Popular exemplos',
		'manage_options',
		'dh-seed',
		'dh_seed_page'
	);
} );

function dh_seed_page() {
	if ( isset( $_POST['dh_do_seed'] ) && check_admin_referer( 'dh_seed' ) ) {
		$n = dh_run_seed();
		echo '<div class="notice notice-success"><p>Populados: ' . intval( $n ) . ' itens em 4 categorias.</p></div>';
	}
	?>
	<div class="wrap">
		<h1>Popular cardápio com exemplos</h1>
		<p>Cria 4 categorias (Pizzas, Hambúrgueres, Bebidas, Sobremesas) e 12 itens de exemplo pra você testar o visual.</p>
		<p><strong>Roda com segurança várias vezes</strong> — só adiciona o que ainda não existe.</p>
		<form method="post">
			<?php wp_nonce_field( 'dh_seed' ); ?>
			<button type="submit" name="dh_do_seed" value="1" class="button button-primary button-large">Popular agora</button>
		</form>
	</div>
	<?php
}

function dh_run_seed() {
	$categories = [
		'Pizzas'       => [ 'Sabores em massa fina, forno a lenha' ],
		'Hambúrgueres' => [ 'Blend suíno e bovino, pão brioche artesanal' ],
		'Bebidas'      => [ 'Refrigerantes, sucos, cervejas geladas' ],
		'Sobremesas'   => [ 'Docinhos pra fechar a noite' ],
	];

	$cat_ids = [];
	foreach ( $categories as $name => $meta ) {
		$existing = get_term_by( 'name', $name, 'menu_category' );
		if ( $existing ) {
			$cat_ids[ $name ] = $existing->term_id;
		} else {
			$t = wp_insert_term( $name, 'menu_category', [ 'description' => $meta[0] ] );
			$cat_ids[ $name ] = is_wp_error( $t ) ? 0 : $t['term_id'];
		}
	}

	$items = [
		// Pizzas
		[ 'cat' => 'Pizzas', 'name' => 'Margherita', 'price' => 49.90, 'desc' => 'Molho de tomate, mussarela, manjericão fresco, azeite extra virgem.' ],
		[ 'cat' => 'Pizzas', 'name' => 'Pepperoni', 'price' => 59.90, 'desc' => 'Molho, mussarela, pepperoni fatiado na hora.' ],
		[ 'cat' => 'Pizzas', 'name' => 'Quatro Queijos', 'price' => 65.00, 'promo' => 55.00, 'desc' => 'Mussarela, gorgonzola, parmesão, provolone.' ],
		[ 'cat' => 'Pizzas', 'name' => 'Portuguesa', 'price' => 62.00, 'desc' => 'Presunto, ovo, cebola, azeitona, orégano.' ],

		// Hambúrgueres
		[ 'cat' => 'Hambúrgueres', 'name' => 'Clássico Cheddar', 'price' => 32.00, 'desc' => 'Blend 180g, cheddar cremoso, alface, tomate, molho da casa.' ],
		[ 'cat' => 'Hambúrgueres', 'name' => 'Bacon Duplo', 'price' => 42.00, 'desc' => 'Dois blends 120g, bacon crocante em dobro, cheddar, cebola caramelizada.' ],
		[ 'cat' => 'Hambúrgueres', 'name' => 'Veggie Grão', 'price' => 34.90, 'desc' => 'Blend de grão de bico, muçarela vegetal, tomate, rúcula, molho tahine.' ],

		// Bebidas
		[ 'cat' => 'Bebidas', 'name' => 'Coca-Cola 350ml', 'price' => 7.00, 'desc' => 'Lata gelada.', 'bump' => 1 ],
		[ 'cat' => 'Bebidas', 'name' => 'Suco de Laranja 500ml', 'price' => 12.00, 'desc' => 'Natural, sem açúcar.', 'bump' => 1 ],
		[ 'cat' => 'Bebidas', 'name' => 'Água Mineral 500ml', 'price' => 4.50, 'desc' => 'Sem gás.', 'bump' => 1 ],

		// Sobremesas
		[ 'cat' => 'Sobremesas', 'name' => 'Petit Gâteau', 'price' => 22.00, 'desc' => 'Bolinho quente de chocolate com sorvete de creme.', 'bump' => 1 ],
		[ 'cat' => 'Sobremesas', 'name' => 'Cheesecake de Frutas Vermelhas', 'price' => 18.00, 'desc' => 'Fatia individual com calda artesanal.', 'bump' => 1 ],
	];

	$count = 0;
	foreach ( $items as $it ) {
		$existing = get_page_by_title( $it['name'], OBJECT, 'menu_item' );
		if ( $existing ) continue;

		$post_id = wp_insert_post( [
			'post_type'    => 'menu_item',
			'post_status'  => 'publish',
			'post_title'   => $it['name'],
			'post_content' => $it['desc'],
		] );
		if ( is_wp_error( $post_id ) ) continue;

		wp_set_post_terms( $post_id, [ $cat_ids[ $it['cat'] ] ], 'menu_category' );
		update_post_meta( $post_id, '_dh_price', $it['price'] );
		if ( ! empty( $it['promo'] ) ) update_post_meta( $post_id, '_dh_promo_price', $it['promo'] );
		if ( ! empty( $it['bump'] ) )  update_post_meta( $post_id, '_dh_is_bump', '1' );

		// Adicionais de exemplo para pizzas
		if ( $it['cat'] === 'Pizzas' ) {
			update_post_meta( $post_id, '_dh_extras', [
				[
					'name'     => 'Tamanho',
					'type'     => 'single',
					'required' => true,
					'max'      => '',
					'options'  => [
						[ 'label' => 'Média (6 fatias)',  'price' => '0' ],
						[ 'label' => 'Grande (8 fatias)', 'price' => '10' ],
						[ 'label' => 'Família (12 fatias)', 'price' => '20' ],
					],
				],
				[
					'name'     => 'Bordas',
					'type'     => 'single',
					'required' => false,
					'max'      => '',
					'options'  => [
						[ 'label' => 'Sem borda',        'price' => '0' ],
						[ 'label' => 'Borda de catupiry', 'price' => '8' ],
						[ 'label' => 'Borda de cheddar',  'price' => '8' ],
					],
				],
			] );
		}
		if ( $it['cat'] === 'Hambúrgueres' ) {
			update_post_meta( $post_id, '_dh_extras', [
				[
					'name'     => 'Ponto da carne',
					'type'     => 'single',
					'required' => true,
					'max'      => '',
					'options'  => [
						[ 'label' => 'Mal passado', 'price' => '0' ],
						[ 'label' => 'Ao ponto',    'price' => '0' ],
						[ 'label' => 'Bem passado', 'price' => '0' ],
					],
				],
				[
					'name'     => 'Adicionais',
					'type'     => 'multi',
					'required' => false,
					'max'      => 5,
					'options'  => [
						[ 'label' => 'Bacon extra',     'price' => '5' ],
						[ 'label' => 'Cheddar extra',   'price' => '4' ],
						[ 'label' => 'Ovo',             'price' => '3' ],
						[ 'label' => 'Cebola caramelizada', 'price' => '3' ],
					],
				],
			] );
		}
		$count++;
	}
	return $count;
}
