<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Customização visual da tela de edição de pedido (post.php?post=X&action=edit).
 */

// Esconde caixas nativas do WP que não fazem sentido pra dh_order
add_action( 'admin_menu', function () {
	remove_meta_box( 'submitdiv', 'dh_order', 'side' );          // caixa Publicar
	remove_meta_box( 'slugdiv', 'dh_order', 'normal' );
	remove_meta_box( 'commentsdiv', 'dh_order', 'normal' );
	remove_meta_box( 'commentstatusdiv', 'dh_order', 'normal' );
	remove_meta_box( 'authordiv', 'dh_order', 'normal' );
	remove_meta_box( 'revisionsdiv', 'dh_order', 'normal' );
	remove_meta_box( 'postcustom', 'dh_order', 'normal' );
	remove_meta_box( 'trackbacksdiv', 'dh_order', 'normal' );
} );

// Remove suporte a título editável (não usamos — título é auto-gerado)
add_action( 'init', function () {
	remove_post_type_support( 'dh_order', 'title' );
	remove_post_type_support( 'dh_order', 'editor' );
}, 20 );

// Hero customizado no topo da tela de edição
add_action( 'edit_form_top', function ( $post ) {
	if ( $post->post_type !== 'dh_order' ) return;

	$statuses = dh_order_statuses();
	$cfg      = $statuses[ $post->post_status ] ?? [ 'label' => '-', 'color' => '#6b7280' ];
	$customer = get_post_meta( $post->ID, '_dh_customer', true ) ?: [];
	$total    = get_post_meta( $post->ID, '_dh_total', true );
	$type_map = [ 'delivery' => 'Entrega', 'pickup' => 'Retirada no balcão', 'dinein' => 'Consumir no local' ];
	$type_ic  = [
		'delivery' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="5.5" cy="17.5" r="3.5"/><circle cx="18.5" cy="17.5" r="3.5"/><path d="M15 6h4l3 6h-3"/><path d="M9 17.5V13.5a2 2 0 0 1 2-2h4l3 6"/><path d="M9 17.5H5.5"/></svg>',
		'pickup'   => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>',
		'dinein'   => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 3h6v18H4z"/><path d="M14 3h1v18h-1z"/><path d="M19 3h1v9a3 3 0 0 1-3 3h-.5"/></svg>',
	];
	$type_key = $customer['type'] ?? 'delivery';
	?>
	<div class="dh-order-hero">
		<div class="dh-hero-left">
			<div class="dh-hero-num">
				<small>Pedido</small>
				<strong>#<?php echo intval( $post->ID ); ?></strong>
			</div>
			<div class="dh-hero-meta">
				<span class="dh-hero-status" style="background:<?php echo esc_attr( $cfg['color'] ); ?>15;color:<?php echo esc_attr( $cfg['color'] ); ?>;border-color:<?php echo esc_attr( $cfg['color'] ); ?>50">
					<?php echo esc_html( $cfg['label'] ); ?>
				</span>
				<span class="dh-hero-type">
					<?php echo $type_ic[ $type_key ] ?? ''; ?>
					<?php echo esc_html( $type_map[ $type_key ] ?? '-' ); ?>
				</span>
				<span class="dh-hero-when">
					<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
					<?php echo esc_html( get_the_time( 'd/m/Y \à\s H:i', $post ) ); ?>
				</span>
			</div>
		</div>
		<div class="dh-hero-right">
			<div class="dh-hero-customer">
				<strong><?php echo esc_html( $customer['name'] ?? '-' ); ?></strong>
				<small><?php echo esc_html( $customer['phone'] ?? '' ); ?></small>
			</div>
			<div class="dh-hero-total">
				<small>Total</small>
				<strong><?php echo dh_money( $total ); ?></strong>
			</div>
		</div>
	</div>
	<?php
}, 10, 1 );

/**
 * CSS pesado só na tela de edição de dh_order.
 */
add_action( 'admin_head-post.php', 'dh_order_admin_head' );
add_action( 'admin_head-post-new.php', 'dh_order_admin_head' );
function dh_order_admin_head() {
	global $post_type;
	if ( $post_type !== 'dh_order' ) return;
	?>
	<style>
		/* Reset visual da tela de edição de pedido */
		#post-body-content { display: none; } /* esconde placeholder do editor removido */
		#titlediv, #edit-slug-box { display: none !important; }
		.page-title-action { display: none; }

		#poststuff h1 { display: none; }
		body.post-type-dh_order.wp-admin { background: #f4f4f5; }
		body.post-type-dh_order #wpbody-content { padding-top: 0; }

		/* Hero do pedido */
		.dh-order-hero {
			background: white;
			border-radius: 12px;
			padding: 20px 24px;
			display: flex;
			justify-content: space-between;
			align-items: center;
			gap: 24px;
			margin: 20px 20px 20px 0;
			box-shadow: 0 1px 3px rgba(0,0,0,.06);
			flex-wrap: wrap;
		}
		.dh-hero-left, .dh-hero-right { display: flex; align-items: center; gap: 20px; }
		.dh-hero-num small {
			display: block;
			font-size: 11px;
			text-transform: uppercase;
			color: #9ca3af;
			letter-spacing: .5px;
			font-weight: 600;
		}
		.dh-hero-num strong {
			display: block;
			font-size: 28px;
			font-weight: 800;
			color: #1f2937;
			line-height: 1;
		}
		.dh-hero-meta { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
		.dh-hero-status {
			padding: 6px 14px;
			border-radius: 999px;
			font-size: 12px;
			font-weight: 700;
			text-transform: uppercase;
			letter-spacing: .5px;
			border: 1px solid transparent;
		}
		.dh-hero-type, .dh-hero-when {
			display: inline-flex;
			align-items: center;
			gap: 5px;
			font-size: 13px;
			color: #6b7280;
			background: #f3f4f6;
			padding: 5px 10px;
			border-radius: 6px;
		}
		.dh-hero-customer strong { display: block; font-size: 14px; color: #1f2937; }
		.dh-hero-customer small { color: #6b7280; font-size: 12px; }
		.dh-hero-total {
			text-align: right;
			padding-left: 20px;
			border-left: 1px solid #e5e7eb;
		}
		.dh-hero-total small {
			display: block;
			font-size: 11px;
			color: #9ca3af;
			text-transform: uppercase;
			letter-spacing: .5px;
			font-weight: 600;
		}
		.dh-hero-total strong {
			display: block;
			font-size: 22px;
			color: #ea1d2c;
			font-weight: 800;
			line-height: 1;
			margin-top: 2px;
		}

		/* Metaboxes como cards */
		#poststuff .postbox {
			border: none;
			border-radius: 12px;
			box-shadow: 0 1px 3px rgba(0,0,0,.05);
			background: white;
			margin-bottom: 16px;
		}
		#poststuff .postbox > .postbox-header {
			border-bottom: 1px solid #f3f4f6;
			background: transparent;
			padding: 4px 6px;
		}
		#poststuff .postbox > .postbox-header .hndle {
			font-size: 13px;
			font-weight: 700;
			color: #4b5563;
			text-transform: uppercase;
			letter-spacing: .5px;
			padding: 12px 14px;
		}
		#poststuff .postbox .inside { padding: 16px 20px; margin: 0; }
		#poststuff .postbox .handle-actions button { color: #9ca3af; }

		/* Ordem e destaque de metaboxes específicos */
		#dh_order_timeline .inside { padding: 0; }
		#dh_order_timeline .postbox-header .hndle { color: #1f2937; }

		#dh_order_actions { border-top: 3px solid #ea1d2c !important; }
		#dh_order_actions .hndle { color: #ea1d2c !important; }

		/* Sidebar mais respirável */
		#postbox-container-1 { padding-top: 0; }

		/* Botão hero de avançar */
		.dh-order-actions .button-hero {
			padding: 12px 22px !important;
			font-size: 14px !important;
			height: auto !important;
			border-radius: 8px !important;
			letter-spacing: .3px;
		}

		/* Detail heading refinado */
		.dh-order-detail .dh-detail-heading {
			border-bottom: 1px solid #f3f4f6;
			padding-bottom: 8px;
			margin: 24px 0 14px;
			text-transform: uppercase;
			letter-spacing: .5px;
			font-size: 12px;
			color: #6b7280;
			font-weight: 700;
		}
		.dh-order-detail .dh-detail-heading:first-child { margin-top: 0; }

		/* Tabela mais limpa */
		.dh-items-table {
			border: 1px solid #f3f4f6 !important;
			border-radius: 8px;
			overflow: hidden;
			background: white;
		}
		.dh-items-table thead th {
			background: #f9fafb !important;
			color: #6b7280;
			font-size: 11px;
			text-transform: uppercase;
			letter-spacing: .5px;
			border-bottom: 1px solid #f3f4f6;
			padding: 10px !important;
		}

		/* Card de totais */
		.dh-totals-box {
			background: linear-gradient(180deg, #fafafa 0%, #f4f4f5 100%);
			border: 1px solid #f3f4f6;
			border-radius: 10px;
		}

		/* Audit log com melhor respiro */
		#dh_order_history .dh-audit-log li { padding: 12px 6px; }

		/* Screen options e Help buttons ficam mais discretos */
		#screen-meta-links { margin-top: 8px; }

		/* Publish/update meta box removido — full width usa footer submit não é needed */
	</style>
	<?php
}

/**
 * Adiciona botão "Voltar pro PDV" no topo (substitui o "Adicionar novo" que não faz sentido).
 */
add_action( 'admin_notices', function () {
	global $post_type, $pagenow;
	if ( $pagenow !== 'post.php' || $post_type !== 'dh_order' ) return;
	?>
	<style>.wrap > .wp-heading-inline { display: none; }</style>
	<?php
} );
