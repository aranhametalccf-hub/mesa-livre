<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$order_id = intval( get_query_var( 'dh_order' ) );
$type     = sanitize_text_field( get_query_var( 'dh_print_type' ) ?: 'cliente' ); // cliente | cozinha | etiqueta
$auto     = ! empty( $_GET['auto'] );

$order = get_post( $order_id );
if ( ! $order || $order->post_type !== 'dh_order' ) {
	status_header( 404 );
	wp_die( 'Pedido não encontrado' );
}

$s = dh_get_settings();
$customer = get_post_meta( $order_id, '_dh_customer', true ) ?: [];
$items    = get_post_meta( $order_id, '_dh_items', true ) ?: [];
$subtotal = (float) get_post_meta( $order_id, '_dh_subtotal', true );
$fee      = (float) get_post_meta( $order_id, '_dh_delivery_fee', true );
$total    = (float) get_post_meta( $order_id, '_dh_total', true );
$type_map = [ 'delivery' => 'ENTREGA', 'pickup' => 'RETIRADA', 'dinein' => 'CONSUMIR NO LOCAL' ];
$deliv    = function_exists( 'dh_order_delivery' ) ? dh_order_delivery( $order_id ) : null;

nocache_headers();
?><!DOCTYPE html>
<html lang="pt-BR">
<head>
	<meta charset="UTF-8">
	<title>Cupom #<?php echo $order_id; ?> — <?php echo esc_html( $type ); ?></title>
	<style>
		/* Impressora térmica 80mm — cabe em ~72mm úteis */
		@page {
			size: 80mm auto;
			margin: 0;
		}
		@media print {
			html, body { width: 80mm; margin: 0 !important; padding: 0 !important; }
			.no-print { display: none !important; }
		}

		* { box-sizing: border-box; }
		html, body {
			background: #fff;
			color: #000;
			font-family: 'Courier New', ui-monospace, monospace;
			font-size: 12px;
			line-height: 1.35;
			margin: 0;
			padding: 4mm 3mm;
			width: 80mm;
		}
		h1, h2, h3, p { margin: 0; }
		h1 { font-size: 14px; text-align: center; font-weight: 800; }
		h2 { font-size: 13px; font-weight: 700; margin-top: 4mm; }
		.dh-sep { border-top: 1px dashed #000; margin: 2mm 0; }
		.dh-solid { border-top: 2px solid #000; margin: 2mm 0; }
		.dh-center { text-align: center; }
		.dh-right  { text-align: right; }
		.dh-big { font-size: 16px; font-weight: 800; }
		.dh-xl  { font-size: 22px; font-weight: 800; }
		.dh-mini { font-size: 10px; }
		.dh-row {
			display: flex;
			justify-content: space-between;
			gap: 8px;
		}
		.dh-item {
			margin: 1.5mm 0;
		}
		.dh-item-line {
			display: flex;
			gap: 4mm;
		}
		.dh-item-qty {
			font-weight: 800;
			min-width: 8mm;
		}
		.dh-item-name { flex: 1; font-weight: 700; }
		.dh-item-price { font-weight: 700; }
		.dh-item-extra { padding-left: 8mm; font-size: 11px; }
		.dh-item-obs {
			padding-left: 8mm;
			font-size: 11px;
			font-weight: 700;
			border-left: 3px solid #000;
			padding-left: 3mm;
			margin: 1mm 0 1mm 5mm;
		}
		.dh-total {
			font-size: 16px;
			font-weight: 800;
			border-top: 1px solid #000;
			border-bottom: 3px double #000;
			padding: 2mm 0;
			margin: 2mm 0;
		}
		.dh-bar {
			background: #000;
			color: #fff;
			padding: 2mm 3mm;
			font-weight: 800;
			text-align: center;
			margin: 2mm 0;
			letter-spacing: 1px;
		}
		.no-print {
			background: #f4f4f5;
			padding: 12px;
			text-align: center;
			border-bottom: 1px solid #ddd;
		}
		.no-print button, .no-print a {
			display: inline-block;
			padding: 8px 16px;
			margin: 0 4px;
			background: #EA1D2C;
			color: white;
			border: none;
			border-radius: 6px;
			font-family: -apple-system, sans-serif;
			font-size: 13px;
			font-weight: 700;
			cursor: pointer;
			text-decoration: none;
		}
		.no-print .dh-print-tabs { margin-top: 8px; font-family: -apple-system, sans-serif; }
		.no-print .dh-print-tabs a { background: #6b7280; font-size: 11px; padding: 4px 10px; }
		.no-print .dh-print-tabs a.is-active { background: #10b981; }

		/* ETIQUETA — layout menor, foco em endereço */
		body.dh-print-etiqueta {
			font-family: -apple-system, Arial, sans-serif;
			font-size: 13px;
			padding: 3mm;
		}
		body.dh-print-etiqueta .dh-xl { font-size: 20px; }
	</style>
</head>
<body class="dh-print-<?php echo esc_attr( $type ); ?>">

	<div class="no-print">
		<button onclick="window.print()">Imprimir</button>
		<a href="<?php echo esc_url( admin_url( 'post.php?post=' . $order_id . '&action=edit' ) ); ?>" target="_top">Voltar ao pedido</a>
		<div class="dh-print-tabs">
			<a href="<?php echo esc_url( home_url( '/print/' . $order_id . '/cliente' ) ); ?>" class="<?php echo $type === 'cliente' ? 'is-active' : ''; ?>">Cliente</a>
			<a href="<?php echo esc_url( home_url( '/print/' . $order_id . '/cozinha' ) ); ?>" class="<?php echo $type === 'cozinha' ? 'is-active' : ''; ?>">Cozinha</a>
			<a href="<?php echo esc_url( home_url( '/print/' . $order_id . '/etiqueta' ) ); ?>" class="<?php echo $type === 'etiqueta' ? 'is-active' : ''; ?>">Etiqueta</a>
		</div>
	</div>

	<?php if ( $type === 'etiqueta' ) : ?>
		<!-- ================ ETIQUETA (fita 80x50mm) ================ -->
		<h1 class="dh-xl"><?php echo esc_html( $customer['name'] ?? '-' ); ?></h1>
		<p class="dh-center dh-mini"><?php echo esc_html( $customer['phone'] ?? '' ); ?></p>
		<div class="dh-sep"></div>
		<p class="dh-big"><?php echo esc_html( $type_map[ $customer['type'] ?? 'delivery' ] ?? '' ); ?></p>

		<?php if ( ( $customer['type'] ?? '' ) === 'delivery' && ! empty( $customer['street'] ) ) : ?>
			<p><strong><?php echo esc_html( $customer['street'] . ( ! empty( $customer['number'] ) ? ', ' . $customer['number'] : '' ) ); ?></strong></p>
			<?php if ( ! empty( $customer['complement'] ) ) : ?><p><?php echo esc_html( $customer['complement'] ); ?></p><?php endif; ?>
			<?php if ( ! empty( $customer['neighborhood'] ) ) : ?><p><?php echo esc_html( $customer['neighborhood'] ); ?></p><?php endif; ?>
			<?php if ( ! empty( $customer['city'] ) ) : ?>
				<p><?php echo esc_html( $customer['city'] . ( ! empty( $customer['state'] ) ? ' - ' . $customer['state'] : '' ) ); ?></p>
			<?php endif; ?>
			<?php if ( ! empty( $customer['cep'] ) ) : ?>
				<p class="dh-mini">CEP <?php echo esc_html( preg_replace( '/^(\d{5})(\d{3})$/', '$1-$2', $customer['cep'] ) ); ?></p>
			<?php endif; ?>
			<?php if ( ! empty( $customer['reference'] ) ) : ?>
				<p class="dh-mini"><em>Ref: <?php echo esc_html( $customer['reference'] ); ?></em></p>
			<?php endif; ?>
		<?php endif; ?>

		<div class="dh-solid"></div>
		<p class="dh-row"><strong>Pedido #<?php echo $order_id; ?></strong><strong><?php echo esc_html( count( $items ) ); ?> item(s)</strong></p>
		<p class="dh-row"><span>Total</span><strong><?php echo dh_money( $total ); ?></strong></p>

	<?php elseif ( $type === 'cozinha' ) : ?>
		<!-- ================ COZINHA (só itens + obs) ================ -->
		<h1>PEDIDO #<?php echo $order_id; ?></h1>
		<p class="dh-center"><?php echo esc_html( get_the_time( 'd/m \à\s H:i', $order ) ); ?></p>
		<div class="dh-bar"><?php echo esc_html( $type_map[ $customer['type'] ?? 'delivery' ] ?? '' ); ?></div>
		<p class="dh-center"><strong><?php echo esc_html( $customer['name'] ?? '' ); ?></strong></p>

		<?php if ( ( $customer['type'] ?? '' ) === 'dinein' && ! empty( $customer['table'] ) ) : ?>
			<p class="dh-center dh-big">MESA <?php echo esc_html( $customer['table'] ); ?></p>
		<?php endif; ?>

		<div class="dh-solid"></div>

		<?php foreach ( $items as $it ) : ?>
			<div class="dh-item">
				<div class="dh-item-line">
					<span class="dh-item-qty"><?php echo esc_html( $it['qty'] ); ?>x</span>
					<span class="dh-item-name"><?php echo esc_html( strtoupper( $it['name'] ) ); ?></span>
				</div>
				<?php foreach ( ( $it['extras'] ?? [] ) as $e ) : ?>
					<div class="dh-item-extra">+ <?php echo esc_html( $e['label'] ); ?></div>
				<?php endforeach; ?>
				<?php if ( ! empty( $it['obs'] ) ) : ?>
					<div class="dh-item-obs">OBS: <?php echo esc_html( strtoupper( $it['obs'] ) ); ?></div>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>

		<?php if ( ! empty( $customer['notes'] ) ) : ?>
			<div class="dh-sep"></div>
			<p><strong>OBSERVAÇÃO GERAL:</strong></p>
			<p><?php echo esc_html( strtoupper( $customer['notes'] ) ); ?></p>
		<?php endif; ?>

	<?php else : ?>
		<!-- ================ CLIENTE (completo com preços) ================ -->
		<h1><?php echo esc_html( strtoupper( $s['store_name'] ) ); ?></h1>
		<?php if ( ! empty( $s['address'] ) ) : ?>
			<p class="dh-center dh-mini"><?php echo esc_html( $s['address'] ); ?></p>
		<?php endif; ?>
		<?php if ( ! empty( $s['whatsapp'] ) ) : ?>
			<p class="dh-center dh-mini">WhatsApp: <?php echo esc_html( $s['whatsapp'] ); ?></p>
		<?php endif; ?>

		<div class="dh-solid"></div>

		<h2>PEDIDO #<?php echo $order_id; ?></h2>
		<p><?php echo esc_html( get_the_time( 'd/m/Y \à\s H:i', $order ) ); ?></p>
		<p><strong><?php echo esc_html( $type_map[ $customer['type'] ?? 'delivery' ] ?? '' ); ?></strong></p>

		<div class="dh-sep"></div>

		<p><strong>Cliente:</strong> <?php echo esc_html( $customer['name'] ?? '-' ); ?></p>
		<p><strong>Fone:</strong> <?php echo esc_html( $customer['phone'] ?? '-' ); ?></p>

		<?php if ( ( $customer['type'] ?? '' ) === 'delivery' && ! empty( $customer['street'] ) ) : ?>
			<p>
				<strong>Entrega:</strong><br>
				<?php echo esc_html( $customer['street'] . ( ! empty( $customer['number'] ) ? ', ' . $customer['number'] : '' ) ); ?>
				<?php if ( ! empty( $customer['complement'] ) ) echo ' — ' . esc_html( $customer['complement'] ); ?>
				<?php if ( ! empty( $customer['neighborhood'] ) ) echo '<br>' . esc_html( $customer['neighborhood'] ); ?>
				<?php if ( ! empty( $customer['city'] ) ) echo '<br>' . esc_html( $customer['city'] . ( ! empty( $customer['state'] ) ? '/' . $customer['state'] : '' ) ); ?>
				<?php if ( ! empty( $customer['cep'] ) ) echo '<br>CEP ' . esc_html( preg_replace( '/^(\d{5})(\d{3})$/', '$1-$2', $customer['cep'] ) ); ?>
				<?php if ( ! empty( $customer['reference'] ) ) echo '<br><em>Ref: ' . esc_html( $customer['reference'] ) . '</em>'; ?>
			</p>
		<?php endif; ?>

		<?php if ( $deliv ) : ?>
			<p><strong>Entregador:</strong> <?php echo esc_html( $deliv['name'] ); ?></p>
		<?php endif; ?>

		<div class="dh-sep"></div>

		<h2>ITENS</h2>
		<?php foreach ( $items as $it ) : ?>
			<div class="dh-item">
				<div class="dh-item-line">
					<span class="dh-item-qty"><?php echo esc_html( $it['qty'] ); ?>x</span>
					<span class="dh-item-name"><?php echo esc_html( $it['name'] ); ?></span>
					<span class="dh-item-price"><?php echo dh_money( $it['total'] ); ?></span>
				</div>
				<?php foreach ( ( $it['extras'] ?? [] ) as $e ) : ?>
					<div class="dh-item-extra">
						+ <?php echo esc_html( $e['label'] ); ?>
						<?php if ( ! empty( $e['price'] ) ) echo ' (' . dh_money( $e['price'] ) . ')'; ?>
					</div>
				<?php endforeach; ?>
				<?php if ( ! empty( $it['obs'] ) ) : ?>
					<div class="dh-item-extra"><em>obs: <?php echo esc_html( $it['obs'] ); ?></em></div>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>

		<div class="dh-sep"></div>

		<p class="dh-row"><span>Subtotal</span><span><?php echo dh_money( $subtotal ); ?></span></p>
		<?php if ( $fee > 0 ) : ?>
			<p class="dh-row"><span>Taxa entrega</span><span><?php echo dh_money( $fee ); ?></span></p>
		<?php endif; ?>

		<div class="dh-total dh-row">
			<span>TOTAL</span><span><?php echo dh_money( $total ); ?></span>
		</div>

		<p><strong>Pagamento:</strong> <?php echo esc_html( $customer['payment'] ?? '-' ); ?></p>
		<?php if ( ! empty( $customer['change'] ) ) : ?>
			<p><strong>Troco para:</strong> <?php echo esc_html( $customer['change'] ); ?></p>
		<?php endif; ?>

		<?php if ( ! empty( $customer['notes'] ) ) : ?>
			<div class="dh-sep"></div>
			<p><strong>Observação:</strong> <?php echo esc_html( $customer['notes'] ); ?></p>
		<?php endif; ?>

		<div class="dh-solid"></div>
		<p class="dh-center dh-mini">Obrigado pela preferência!</p>
	<?php endif; ?>

	<?php if ( $auto ) : ?>
		<script>
			window.addEventListener('load', () => {
				setTimeout(() => { window.print(); }, 250);
				window.addEventListener('afterprint', () => { setTimeout(() => window.close(), 500); });
			});
		</script>
	<?php endif; ?>
</body>
</html>
<?php exit;
