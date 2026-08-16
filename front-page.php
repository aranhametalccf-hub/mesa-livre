<?php
if ( ! defined( 'ABSPATH' ) ) exit;

get_header();

$s = dh_get_settings();
$categories = dh_get_menu_categories();
?>

<?php $store_status = dh_store_status(); ?>
<?php if ( ! $store_status['open'] ) : ?>
	<div class="dh-store-banner">
		<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
		<span><?php echo esc_html( $store_status['message'] ); ?></span>
	</div>
<?php endif; ?>

<header class="dh-header">
	<div class="dh-cover" <?php if ( $s['cover_image'] ) : ?>style="background-image: url('<?php echo esc_url( $s['cover_image'] ); ?>');"<?php endif; ?>>
		<a href="<?php echo esc_url( home_url( '/meus-pedidos' ) ); ?>" class="dh-my-orders-link">
			<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
			Meus pedidos
		</a>
	</div>
	<div class="dh-header-info">
		<div class="dh-logo">
			<?php if ( $s['logo'] ) : ?>
				<img src="<?php echo esc_url( $s['logo'] ); ?>" alt="<?php echo esc_attr( $s['store_name'] ); ?>">
			<?php else : ?>
				<div class="dh-logo-placeholder"><?php echo esc_html( mb_substr( $s['store_name'], 0, 2 ) ); ?></div>
			<?php endif; ?>
		</div>
		<div class="dh-store-meta">
			<h1><?php echo esc_html( $s['store_name'] ); ?></h1>
			<div class="dh-meta-row">
				<?php if ( $s['hours'] ) : ?>
					<span class="dh-tag dh-tag-open">Aberto agora</span>
					<span class="dh-meta-item"><?php echo esc_html( $s['hours'] ); ?></span>
				<?php endif; ?>
				<?php if ( (float) $s['delivery_fee'] > 0 ) : ?>
					<span class="dh-meta-item">Entrega <?php echo dh_money( $s['delivery_fee'] ); ?></span>
				<?php endif; ?>
				<?php if ( (float) $s['min_order'] > 0 ) : ?>
					<span class="dh-meta-item">Pedido mín. <?php echo dh_money( $s['min_order'] ); ?></span>
				<?php endif; ?>
			</div>
			<?php if ( $s['address'] ) : ?>
				<p class="dh-address"><?php echo esc_html( $s['address'] ); ?></p>
			<?php endif; ?>
		</div>
	</div>
</header>

<nav class="dh-categories-nav" x-data x-init="initCategoriesNav($el)">
	<div class="dh-categories-scroll">
		<?php foreach ( $categories as $cat ) : ?>
			<a href="#cat-<?php echo esc_attr( $cat->term_id ); ?>" data-cat="<?php echo esc_attr( $cat->term_id ); ?>">
				<?php echo esc_html( $cat->name ); ?>
			</a>
		<?php endforeach; ?>
	</div>
</nav>

<main class="dh-main">

	<?php foreach ( $categories as $cat ) :
		$items = dh_get_items_by_category( $cat->term_id );
		if ( ! $items ) continue;
		?>
		<section class="dh-category" id="cat-<?php echo esc_attr( $cat->term_id ); ?>">
			<h2 class="dh-category-title"><?php echo esc_html( $cat->name ); ?></h2>
			<?php if ( $cat->description ) : ?>
				<p class="dh-category-desc"><?php echo esc_html( $cat->description ); ?></p>
			<?php endif; ?>

			<div class="dh-items-grid">
				<?php foreach ( $items as $item ) :
					$data = dh_item_to_array( $item );
					$soldout = $data['soldout'];
					?>
					<article class="dh-item <?php echo $soldout ? 'is-soldout' : ''; ?>"
						@click="<?php echo $soldout ? 'null' : "openItem(" . esc_attr( wp_json_encode( $data ) ) . ")"; ?>">
						<div class="dh-item-info">
							<h3 class="dh-item-name"><?php echo esc_html( $data['name'] ); ?></h3>
							<?php if ( $data['description'] ) : ?>
								<p class="dh-item-desc"><?php echo esc_html( wp_trim_words( $data['description'], 20 ) ); ?></p>
							<?php endif; ?>
							<div class="dh-item-price-row">
								<?php if ( $data['original'] && $data['original'] > $data['price'] ) : ?>
									<span class="dh-price-old"><?php echo dh_money( $data['original'] ); ?></span>
								<?php endif; ?>
								<span class="dh-price"><?php echo dh_money( $data['price'] ); ?></span>
								<?php if ( $soldout ) : ?>
									<span class="dh-badge-soldout">Esgotado</span>
								<?php endif; ?>
							</div>
						</div>
						<?php if ( $data['image'] ) : ?>
							<div class="dh-item-image" style="background-image: url('<?php echo esc_url( $data['image'] ); ?>');"></div>
						<?php endif; ?>
					</article>
				<?php endforeach; ?>
			</div>
		</section>
	<?php endforeach; ?>

	<?php if ( empty( $categories ) ) : ?>
		<div class="dh-empty">
			<h2>Cardápio ainda não configurado</h2>
			<p>Adicione categorias e itens em <strong>Cardápio → Adicionar Item</strong>.</p>
		</div>
	<?php endif; ?>
</main>

<?php get_footer(); ?>
