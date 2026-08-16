<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Roles customizados do Delivery Hub.
 *
 * dh_cook       — cozinha (só vê modo cozinha + preparando/pronto)
 * dh_attendant  — atendente (PDV completo + entregadores + edição de pedido)
 * dh_manager    — gerente (tudo + settings + relatórios + cardápio + clientes)
 *
 * Administrator do WP tem tudo por padrão (não interferimos).
 */

/**
 * Registra roles na ativação do tema. Idempotente.
 */
add_action( 'after_switch_theme', function () {
	dh_register_roles();
} );

// Também roda 1x se opção não existe (auto-heal)
add_action( 'init', function () {
	if ( get_option( 'dh_roles_installed_v1' ) === '1' ) return;
	dh_register_roles();
	update_option( 'dh_roles_installed_v1', '1' );
}, 5 );

function dh_register_roles() {
	// Cozinheiro — vê modo cozinha + PDV limitado
	remove_role( 'dh_cook' );
	add_role( 'dh_cook', 'Cozinha', [
		'read'                => true,
		'edit_posts'          => true,      // pra poder ver PDV/kitchen
		'edit_others_posts'   => false,
		'publish_posts'       => false,
		'delete_posts'        => false,
	] );

	// Atendente — opera PDV, edita pedidos, atribui entregadores
	remove_role( 'dh_attendant' );
	add_role( 'dh_attendant', 'Atendente', [
		'read'                => true,
		'edit_posts'          => true,
		'edit_others_posts'   => true,
		'publish_posts'       => true,
		'delete_posts'        => false,
	] );

	// Gerente — tudo, incluindo settings e cardápio
	remove_role( 'dh_manager' );
	add_role( 'dh_manager', 'Gerente da Loja', [
		'read'                => true,
		'edit_posts'          => true,
		'edit_others_posts'   => true,
		'publish_posts'       => true,
		'delete_posts'        => true,
		'delete_others_posts' => true,
		'edit_published_posts'=> true,
		'manage_categories'   => true,
		'manage_options'      => false,   // não pode mexer nos settings core do WP
	] );
}

/**
 * Menu admin filtrado por role: cozinha só vê Modo Cozinha e sai.
 */
add_action( 'admin_menu', function () {
	if ( ! is_user_logged_in() ) return;
	$user = wp_get_current_user();
	if ( ! in_array( 'dh_cook', (array) $user->roles, true ) ) return;

	// Cozinha: esconde tudo exceto PDV/kitchen
	global $menu;
	if ( ! is_array( $menu ) ) return;
	foreach ( $menu as $i => $item ) {
		$slug = $item[2] ?? '';
		if ( ! in_array( $slug, [ 'dh-pdv', 'index.php', 'profile.php' ], true ) ) {
			unset( $menu[ $i ] );
		}
	}
}, 999 );

/**
 * Cozinha: redireciona ao PDV/Kitchen automaticamente.
 */
add_action( 'admin_init', function () {
	if ( ! is_user_logged_in() ) return;
	$user = wp_get_current_user();
	if ( ! in_array( 'dh_cook', (array) $user->roles, true ) ) return;

	$screen = $_GET['page'] ?? '';
	$allowed = [ 'dh-kitchen' ];
	if ( ! in_array( $screen, $allowed, true ) && ! wp_doing_ajax() ) {
		global $pagenow;
		if ( in_array( $pagenow, [ 'admin.php', 'index.php' ], true ) && $screen !== 'dh-kitchen' ) {
			wp_safe_redirect( admin_url( 'admin.php?page=dh-kitchen&fs=1' ) );
			exit;
		}
	}
} );

/**
 * Atendente: bloqueia settings da loja e páginas administrativas do tema (mas mantém PDV, pedidos, entregadores, clientes)
 */
add_action( 'admin_menu', function () {
	$user = wp_get_current_user();
	if ( ! in_array( 'dh_attendant', (array) $user->roles, true ) ) return;
	remove_menu_page( 'dh-settings' );
	remove_menu_page( 'dh-reports' );
	remove_menu_page( 'edit.php?post_type=menu_item' );
}, 999 );

/**
 * Info sobre roles nas Config. da Loja
 */
add_action( 'admin_notices', function () {
	if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'dh-settings' ) return;
	if ( ! current_user_can( 'manage_options' ) ) return;
	$counts = count_users();
	$dh_count = 0;
	foreach ( [ 'dh_cook', 'dh_attendant', 'dh_manager' ] as $r ) {
		$dh_count += intval( $counts['avail_roles'][ $r ] ?? 0 );
	}
	if ( $dh_count === 0 ) : ?>
		<div class="notice notice-info">
			<p>
				<strong>Roles Delivery Hub disponíveis:</strong>
				<em>Cozinha</em> (vê só modo cozinha), <em>Atendente</em> (opera PDV) e <em>Gerente da Loja</em> (tudo exceto settings do WP).
				<a href="<?php echo esc_url( admin_url( 'user-new.php' ) ); ?>">Criar novo usuário →</a>
			</p>
		</div>
	<?php endif;
} );
