<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Customização da tela de edição de item do cardápio.
 */

// Força classic editor (metaboxes clássicos) — nossos hooks e CSS assumem essa UI.
// Sem isso, o Gutenberg engole title/description/thumbnail e joga extras num painel colapsado.
add_filter( 'use_block_editor_for_post_type', function ( $use, $post_type ) {
	if ( in_array( $post_type, [ 'menu_item', 'dh_order', 'dh_customer' ], true ) ) {
		return false;
	}
	return $use;
}, 10, 2 );

// Remove metaboxes nativos que não fazem sentido
add_action( 'admin_menu', function () {
	remove_meta_box( 'commentsdiv', 'menu_item', 'normal' );
	remove_meta_box( 'commentstatusdiv', 'menu_item', 'normal' );
	remove_meta_box( 'authordiv', 'menu_item', 'normal' );
	remove_meta_box( 'trackbacksdiv', 'menu_item', 'normal' );
	remove_meta_box( 'postcustom', 'menu_item', 'normal' );
	remove_meta_box( 'slugdiv', 'menu_item', 'normal' );
	remove_meta_box( 'revisionsdiv', 'menu_item', 'normal' );
} );

// Move imagem destacada de side pra normal (topo) — mais visível e maior
add_action( 'do_meta_boxes', function () {
	remove_meta_box( 'postimagediv', 'menu_item', 'side' );
	add_meta_box(
		'postimagediv',
		'Foto do item',
		'post_thumbnail_meta_box',
		'menu_item',
		'side',
		'high'
	);
} );

// Muda placeholder do título
add_filter( 'enter_title_here', function ( $placeholder, $post ) {
	if ( $post->post_type === 'menu_item' ) return 'Nome do item (ex: Pizza Margherita)';
	return $placeholder;
}, 10, 2 );

// Hero no topo com preview visual
add_action( 'edit_form_after_title', function ( $post ) {
	if ( $post->post_type !== 'menu_item' ) return;

	$price   = dh_item_price( $post->ID );
	$original = dh_item_original_price( $post->ID );
	$has_promo = $original && $original > $price;
	$soldout = dh_item_is_soldout( $post->ID );
	$is_bump = get_post_meta( $post->ID, '_dh_is_bump', true );
	$thumb   = get_the_post_thumbnail_url( $post->ID, 'menu-item-card' );
	$cats    = wp_get_post_terms( $post->ID, 'menu_category' );
	?>
	<div class="dh-item-hero">
		<div class="dh-item-hero-preview" <?php if ( $thumb ) : ?>style="background-image:url('<?php echo esc_url( $thumb ); ?>');"<?php endif; ?>>
			<?php if ( ! $thumb ) : ?>
				<svg width="42" height="42" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
				<small>Sem foto</small>
			<?php endif; ?>
		</div>
		<div class="dh-item-hero-info">
			<div class="dh-item-cats">
				<?php if ( $cats ) : ?>
					<?php foreach ( $cats as $c ) : ?>
						<span class="dh-item-cat"><?php echo esc_html( $c->name ); ?></span>
					<?php endforeach; ?>
				<?php else : ?>
					<span class="dh-item-cat is-empty">Sem categoria</span>
				<?php endif; ?>
			</div>
			<h2><?php echo esc_html( $post->post_title ?: 'Item novo' ); ?></h2>
			<div class="dh-item-hero-price">
				<?php if ( $has_promo ) : ?>
					<span class="dh-hero-old"><?php echo dh_money( $original ); ?></span>
				<?php endif; ?>
				<strong><?php echo dh_money( $price ); ?></strong>
			</div>
			<div class="dh-item-flags">
				<?php if ( $soldout ) : ?>
					<span class="dh-flag dh-flag-soldout">Esgotado</span>
				<?php endif; ?>
				<?php if ( $is_bump ) : ?>
					<span class="dh-flag dh-flag-bump">Order Bump</span>
				<?php endif; ?>
			</div>
		</div>
	</div>
	<?php
} );

/**
 * CSS específico da tela de edição de item do cardápio.
 */
add_action( 'admin_head-post.php', 'dh_menu_item_admin_head' );
add_action( 'admin_head-post-new.php', 'dh_menu_item_admin_head' );
function dh_menu_item_admin_head() {
	global $post_type;
	if ( $post_type !== 'menu_item' ) return;
	?>
	<style>
		body.post-type-menu_item.wp-admin { background: #f4f4f5; }

		/* Header do WP */
		body.post-type-menu_item #post-body {
			padding-top: 0;
		}

		/* Título grande e mais visual */
		body.post-type-menu_item #titlediv #title {
			font-size: 22px !important;
			padding: 12px 16px !important;
			border-radius: 10px;
			border: 1px solid #e5e7eb;
			background: white;
			font-weight: 700;
			box-shadow: 0 1px 3px rgba(0,0,0,.04);
		}
		body.post-type-menu_item #titlediv #title:focus {
			border-color: #ea1d2c;
			box-shadow: 0 0 0 3px rgba(234,29,44,.1);
			outline: none;
		}
		body.post-type-menu_item #titlediv { margin-bottom: 20px; }

		/* Preview hero */
		.dh-item-hero {
			background: white;
			border-radius: 12px;
			padding: 20px;
			display: flex;
			gap: 20px;
			align-items: center;
			box-shadow: 0 1px 3px rgba(0,0,0,.05);
			margin-bottom: 20px;
		}
		.dh-item-hero-preview {
			width: 140px;
			height: 140px;
			background-size: cover;
			background-position: center;
			background-color: #f4f4f5;
			border-radius: 10px;
			display: flex;
			flex-direction: column;
			align-items: center;
			justify-content: center;
			gap: 6px;
			color: #9ca3af;
			flex-shrink: 0;
		}
		.dh-item-hero-preview small { font-size: 11px; text-transform: uppercase; letter-spacing: .5px; }
		.dh-item-hero-info { flex: 1; min-width: 0; }
		.dh-item-hero-info h2 {
			margin: 8px 0 6px;
			font-size: 22px;
			font-weight: 800;
			color: #1f2937;
			line-height: 1.15;
		}
		.dh-item-cats { display: flex; gap: 6px; flex-wrap: wrap; }
		.dh-item-cat {
			background: #fef2f2;
			color: #b91c1c;
			padding: 2px 10px;
			border-radius: 999px;
			font-size: 11px;
			font-weight: 700;
			text-transform: uppercase;
			letter-spacing: .3px;
		}
		.dh-item-cat.is-empty { background: #f3f4f6; color: #9ca3af; }
		.dh-item-hero-price { display: flex; align-items: baseline; gap: 8px; margin-bottom: 8px; }
		.dh-item-hero-price strong { font-size: 22px; color: #ea1d2c; font-weight: 800; }
		.dh-hero-old { color: #9ca3af; text-decoration: line-through; font-size: 14px; }
		.dh-item-flags { display: flex; gap: 6px; flex-wrap: wrap; }
		.dh-flag {
			padding: 3px 10px;
			border-radius: 999px;
			font-size: 11px;
			font-weight: 700;
			text-transform: uppercase;
			letter-spacing: .3px;
		}
		.dh-flag-soldout { background: #f3f4f6; color: #6b7280; }
		.dh-flag-bump { background: #dbeafe; color: #1e40af; }

		/* Metaboxes como cards */
		body.post-type-menu_item #poststuff .postbox {
			border: none;
			border-radius: 12px;
			box-shadow: 0 1px 3px rgba(0,0,0,.05);
			background: white;
			margin-bottom: 16px;
		}
		body.post-type-menu_item #poststuff .postbox > .postbox-header {
			border-bottom: 1px solid #f3f4f6;
			background: transparent;
			padding: 4px 6px;
		}
		body.post-type-menu_item #poststuff .postbox > .postbox-header .hndle {
			font-size: 13px !important;
			font-weight: 700 !important;
			color: #4b5563 !important;
			text-transform: uppercase;
			letter-spacing: .5px;
			padding: 12px 14px !important;
		}
		body.post-type-menu_item #poststuff .postbox .inside {
			padding: 16px 20px !important;
			margin: 0 !important;
		}

		/* Editor de descrição (WP nativo) mais respirável */
		body.post-type-menu_item #postdivrich {
			border: none;
			border-radius: 12px;
			box-shadow: 0 1px 3px rgba(0,0,0,.05);
			background: white;
			overflow: hidden;
		}
		body.post-type-menu_item .wp-editor-tools { background: transparent; padding: 10px 14px !important; }
		body.post-type-menu_item .wp-editor-container { border: 1px solid #f3f4f6; margin: 0 14px 14px; border-radius: 8px; overflow: hidden; }

		/* Preço e promoção */
		#dh_item_price input[type=number] {
			font-size: 16px;
			padding: 8px 12px;
			border-radius: 8px;
			border: 1px solid #d1d5db;
		}
		#dh_item_price input[type=number]:focus {
			border-color: #ea1d2c;
			box-shadow: 0 0 0 3px rgba(234,29,44,.1);
			outline: none;
		}
		#dh_item_price label { font-size: 12px; text-transform: uppercase; letter-spacing: .4px; color: #6b7280; font-weight: 700; }

		/* Bump */
		#dh_item_bump .inside { text-align: left; }

		/* Featured image maior */
		#postimagediv .inside { padding: 16px !important; }
		#postimagediv img { max-width: 100%; border-radius: 8px; }
		#set-post-thumbnail {
			display: block;
			padding: 30px 12px !important;
			background: #f9fafb;
			border: 2px dashed #d1d5db !important;
			border-radius: 10px;
			text-align: center;
			color: #6b7280;
			font-weight: 600;
			transition: border-color .15s, background .15s, color .15s;
		}
		#set-post-thumbnail:hover {
			border-color: #ea1d2c !important;
			background: #fef2f2;
			color: #ea1d2c;
		}
		#remove-post-thumbnail { color: #b91c1c !important; }

		/* Categorias */
		#menu_categorydiv .inside { padding: 12px 16px !important; }
		.categorydiv div.tabs-panel { border-radius: 8px; border-color: #e5e7eb; }

		/* CSS dos grupos de adicionais fica em assets/css/admin.css (dh-ex-*) */

		/* Publish box mais compacto */
		#submitdiv .inside { padding: 12px 16px !important; }
	</style>
	<?php
}
