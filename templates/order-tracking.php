<?php
if ( ! defined( 'ABSPATH' ) ) exit;
get_header();

$order_id     = intval( get_query_var( 'dh_order' ) );
$order        = get_post( $order_id );
$statuses_all = dh_order_statuses();
$s            = dh_get_settings();

if ( ! $order || $order->post_type !== 'dh_order' ) {
	echo '<div class="dh-mo-wrap"><header class="dh-mo-topbar"><h1>Pedido não encontrado</h1></header><div class="dh-mo-empty"><h2>Ops!</h2><p>Pedido inexistente ou removido.</p><a href="' . esc_url( home_url( '/' ) ) . '" class="dh-btn dh-btn-primary">Voltar ao cardápio</a></div></div>';
	get_footer();
	return;
}

$cust  = get_post_meta( $order->ID, '_dh_customer', true ) ?: [];
$type  = $cust['type'] ?? 'delivery';
$items = get_post_meta( $order->ID, '_dh_items', true ) ?: [];
$history = get_post_meta( $order->ID, '_dh_status_history', true ) ?: [];

$subtotal = (float) get_post_meta( $order->ID, '_dh_subtotal', true );
$fee      = (float) get_post_meta( $order->ID, '_dh_delivery_fee', true );
$total    = (float) get_post_meta( $order->ID, '_dh_total', true );

// Timeline (só delivery passa por 'em rota')
$timeline = [
	'dh-novo'       => 'Recebido',
	'dh-preparando' => 'Preparando',
	'dh-pronto'     => $type === 'pickup' ? 'Pronto pra retirar' : 'Pronto',
];
if ( $type === 'delivery' ) {
	$timeline['dh-rota'] = 'Saiu';
}
$timeline['dh-entregue'] = 'Entregue';

$order_slugs = array_keys( $timeline );
$current_idx = array_search( $order->post_status, $order_slugs, true );
if ( $current_idx === false ) $current_idx = 0;
$is_cancelled = $order->post_status === 'dh-cancelado';
$current_cfg  = $statuses_all[ $order->post_status ] ?? [ 'label' => '-', 'color' => '#999' ];

// Mapa de reached (status → timestamp)
$reached = [];
foreach ( $history as $h ) $reached[ $h['status'] ] = $h['at'];

// Copy contextual do hero
$hero_texts = [
	'dh-novo'       => 'Estamos preparando tudo pra começar seu pedido.',
	'dh-preparando' => 'Sua comida está sendo preparada com carinho.',
	'dh-pronto'     => $type === 'delivery' ? 'Pedido pronto! Aguardando o entregador.' : ( $type === 'pickup' ? 'Pode passar aqui pra buscar!' : 'Pedido pronto! Chegando na sua mesa.' ),
	'dh-rota'       => 'Já está a caminho do seu endereço.',
	'dh-entregue'   => 'Pedido concluído. Bom apetite!',
	'dh-cancelado'  => 'Este pedido foi cancelado. Fale com a loja se foi engano.',
];
$hero_text = $hero_texts[ $order->post_status ] ?? '';

// Ícones SVG por status
$icons = [
	'dh-novo'       => '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>',
	'dh-preparando' => '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5z"/></svg>',
	'dh-pronto'     => '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v3"/><path d="M3 8h18v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8z"/><line x1="10" y1="12" x2="14" y2="12"/></svg>',
	'dh-rota'       => '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="5.5" cy="17.5" r="3.5"/><circle cx="18.5" cy="17.5" r="3.5"/><path d="M15 6h4l3 6h-3"/><path d="M9 17.5V13.5a2 2 0 0 1 2-2h4l3 6"/><path d="M9 17.5H5.5"/></svg>',
	'dh-entregue'   => '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/><polyline points="20 12 15 17"/></svg>',
	'dh-cancelado'  => '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>',
];
$icon_current = $icons[ $order->post_status ] ?? $icons['dh-novo'];

$type_labels = [ 'delivery' => 'Entrega', 'pickup' => 'Retirada', 'dinein' => 'Consumir no local' ];
$wa_store = $s['whatsapp']
	? 'https://wa.me/' . preg_replace( '/\D/', '', $s['whatsapp'] ) . '?text=' . rawurlencode( 'Olá! Sobre o pedido #' . $order_id )
	: '';
?>

<div class="dh-mo-wrap" x-data="orderTracking(<?php echo $order_id; ?>)" x-init="startPoll()">

	<header class="dh-mo-topbar">
		<a href="<?php echo esc_url( home_url( '/meus-pedidos' ) ); ?>" class="dh-mo-back" aria-label="Voltar">
			<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5"/><polyline points="12 19 5 12 12 5"/></svg>
		</a>
		<h1>Pedido #<?php echo $order_id; ?></h1>
	</header>

	<main class="dh-mo-main dh-tk-main">

		<!-- HERO STATUS -->
		<div class="dh-tk-hero" :style="`--tk-color:${statusColor}`" style="--tk-color: <?php echo esc_attr( $current_cfg['color'] ); ?>">
			<div class="dh-tk-hero-icon" x-html="statusIcon"><?php echo $icon_current; ?></div>
			<div class="dh-tk-hero-body">
				<span class="dh-tk-live" x-show="!isCancelled">
					<span class="dh-live-dot"></span>
					Atualizado às <span x-text="lastUpdate">agora</span>
				</span>
				<h2 x-text="statusLabel"><?php echo esc_html( $current_cfg['label'] ); ?></h2>
				<p x-text="statusText"><?php echo esc_html( $hero_text ); ?></p>
			</div>
		</div>

		<!-- TIMELINE -->
		<?php if ( ! $is_cancelled ) : ?>
		<div class="dh-tk-timeline">
			<?php foreach ( $timeline as $slug => $label ) :
				$idx = array_search( $slug, $order_slugs, true );
				$state = $idx < $current_idx ? 'done' : ( $idx === $current_idx ? 'active' : 'pending' );
				$cfg = $statuses_all[ $slug ] ?? [];
				$ts = $reached[ $slug ] ?? '';
				?>
				<div class="dh-tk-step is-<?php echo $state; ?>"
					x-bind:class="{ 'is-done': stepDone(<?php echo $idx; ?>), 'is-active': stepActive(<?php echo $idx; ?>) }">
					<div class="dh-tk-dot" style="--dot-color: <?php echo esc_attr( $cfg['color'] ?? '#999' ); ?>">
						<span class="dh-tk-check">
							<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
						</span>
					</div>
					<div class="dh-tk-step-label"><?php echo esc_html( $label ); ?></div>
					<?php if ( $ts ) : ?>
						<time><?php echo esc_html( date_i18n( 'H:i', strtotime( $ts ) ) ); ?></time>
					<?php else : ?>
						<time>—</time>
					<?php endif; ?>
				</div>
				<?php if ( $idx < count( $timeline ) - 1 ) : ?>
					<div class="dh-tk-line" x-bind:class="{ 'is-done': stepDone(<?php echo $idx; ?>) }"></div>
				<?php endif; ?>
			<?php endforeach; ?>
		</div>
		<?php endif; ?>

		<!-- INFO CARDS -->
		<div class="dh-tk-cards">

			<!-- Tipo + endereço/mesa -->
			<div class="dh-tk-card">
				<div class="dh-tk-card-head">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
					<h3><?php echo esc_html( $type_labels[ $type ] ?? 'Entrega' ); ?></h3>
				</div>
				<div class="dh-tk-card-body">
					<?php if ( $type === 'delivery' ) :
						if ( ! empty( $cust['street'] ) ) : ?>
							<p class="dh-tk-address">
								<strong><?php echo esc_html( $cust['street'] . ( ! empty( $cust['number'] ) ? ', ' . $cust['number'] : '' ) ); ?></strong>
								<?php if ( ! empty( $cust['complement'] ) ) echo '<br>' . esc_html( $cust['complement'] ); ?>
								<?php if ( ! empty( $cust['neighborhood'] ) ) echo '<br>' . esc_html( $cust['neighborhood'] ); ?>
								<?php
								$cid = trim( ( $cust['city'] ?? '' ) . ( ! empty( $cust['state'] ) ? '/' . $cust['state'] : '' ) );
								if ( $cid ) echo '<br>' . esc_html( $cid );
								if ( ! empty( $cust['cep'] ) ) echo '<br>CEP ' . esc_html( preg_replace( '/^(\d{5})(\d{3})$/', '$1-$2', $cust['cep'] ) );
								?>
							</p>
							<?php if ( ! empty( $cust['reference'] ) ) : ?>
								<p class="dh-tk-ref">Ref: <?php echo esc_html( $cust['reference'] ); ?></p>
							<?php endif; ?>
						<?php elseif ( ! empty( $cust['address'] ) ) : ?>
							<p><?php echo esc_html( $cust['address'] ); ?></p>
						<?php endif;
					elseif ( $type === 'dinein' && ! empty( $cust['table'] ) ) : ?>
						<p><strong>Mesa <?php echo esc_html( $cust['table'] ); ?></strong></p>
					<?php elseif ( $type === 'pickup' && ! empty( $s['address'] ) ) : ?>
						<p><?php echo esc_html( $s['address'] ); ?></p>
					<?php endif; ?>
				</div>
			</div>

			<!-- Itens + totais -->
			<div class="dh-tk-card">
				<div class="dh-tk-card-head">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
					<h3>Itens <em>(<?php echo count( $items ); ?>)</em></h3>
				</div>
				<div class="dh-tk-card-body">
					<ul class="dh-tk-items">
						<?php foreach ( $items as $it ) : ?>
							<li>
								<span class="dh-tk-qty"><?php echo esc_html( $it['qty'] ); ?></span>
								<div class="dh-tk-item-info">
									<strong><?php echo esc_html( $it['name'] ); ?></strong>
									<?php foreach ( ( $it['extras'] ?? [] ) as $e ) : ?>
										<small><?php echo esc_html( $e['label'] ); ?></small>
									<?php endforeach; ?>
									<?php if ( ! empty( $it['obs'] ) ) : ?>
										<em>Obs: <?php echo esc_html( $it['obs'] ); ?></em>
									<?php endif; ?>
								</div>
								<span class="dh-tk-item-total"><?php echo dh_money( $it['total'] ); ?></span>
							</li>
						<?php endforeach; ?>
					</ul>

					<div class="dh-tk-totals">
						<div><span>Subtotal</span><span><?php echo dh_money( $subtotal ); ?></span></div>
						<?php if ( $fee > 0 ) : ?>
							<div><span>Taxa de entrega</span><span><?php echo dh_money( $fee ); ?></span></div>
						<?php endif; ?>
						<div class="dh-tk-total-row">
							<span>Total</span>
							<strong><?php echo dh_money( $total ); ?></strong>
						</div>
					</div>
				</div>
			</div>

			<!-- Pagamento -->
			<?php if ( ! empty( $cust['payment'] ) ) : ?>
			<div class="dh-tk-card">
				<div class="dh-tk-card-head">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
					<h3>Pagamento</h3>
				</div>
				<div class="dh-tk-card-body dh-tk-payment">
					<strong><?php echo esc_html( $cust['payment'] ); ?></strong>
					<?php if ( ! empty( $cust['change'] ) ) : ?>
						<small>Troco para <?php echo esc_html( $cust['change'] ); ?></small>
					<?php endif; ?>
				</div>
			</div>
			<?php endif; ?>

			<!-- Observação -->
			<?php if ( ! empty( $cust['notes'] ) ) : ?>
			<div class="dh-tk-card">
				<div class="dh-tk-card-head">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
					<h3>Observação</h3>
				</div>
				<div class="dh-tk-card-body">
					<p><?php echo esc_html( $cust['notes'] ); ?></p>
				</div>
			</div>
			<?php endif; ?>
		</div>

		<!-- Ações -->
		<div class="dh-tk-actions">

			<div class="dh-tk-confirm-card" x-show="canConfirm" x-cloak>
				<div class="dh-tk-confirm-icon">
					<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
				</div>
				<div class="dh-tk-confirm-body">
					<strong x-text="confirmPrompt"></strong>
					<small>Se sim, confirme aqui para encerrar o acompanhamento.</small>
					<button type="button"
						class="dh-tk-confirm-btn"
						@click="confirmDelivery()"
						:disabled="confirming">
						<span x-text="confirming ? 'Confirmando…' : confirmLabel"></span>
						<svg x-show="!confirming" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
					</button>
				</div>
			</div>

			<?php if ( $wa_store ) : ?>
				<a href="<?php echo esc_url( $wa_store ); ?>" class="dh-tk-wa" target="_blank" rel="noopener">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/></svg>
					Falar com a loja no WhatsApp
				</a>
			<?php endif; ?>
		</div>

	</main>
</div>

<script>
function orderTracking(orderId) {
	const STEPS = <?php echo wp_json_encode( array_keys( $timeline ) ); ?>;
	const ICONS = <?php echo wp_json_encode( $icons ); ?>;
	const TEXTS = <?php echo wp_json_encode( $hero_texts ); ?>;
	const COLORS = <?php echo wp_json_encode( array_map( fn( $s ) => $s['color'], $statuses_all ) ); ?>;
	const LABELS = <?php echo wp_json_encode( array_map( fn( $s ) => $s['label'], $statuses_all ) ); ?>;
	const ORDER_TYPE = '<?php echo esc_js( $type ); ?>';
	const ORDER_PHONE = '<?php echo esc_js( preg_replace( '/\D/', '', $cust['phone'] ?? '' ) ); ?>';

	return {
		currentStatus: '<?php echo esc_js( $order->post_status ); ?>',
		currentIdx: <?php echo intval( $current_idx ); ?>,
		statusLabel: LABELS['<?php echo esc_js( $order->post_status ); ?>'] || '',
		statusColor: COLORS['<?php echo esc_js( $order->post_status ); ?>'] || '#999',
		statusText: TEXTS['<?php echo esc_js( $order->post_status ); ?>'] || '',
		statusIcon: ICONS['<?php echo esc_js( $order->post_status ); ?>'] || ICONS['dh-novo'],
		isCancelled: '<?php echo esc_js( $order->post_status ); ?>' === 'dh-cancelado',
		lastUpdate: new Date().toLocaleTimeString('pt-BR', {hour:'2-digit', minute:'2-digit'}),
		confirming: false,

		stepDone(i)   { return i < this.currentIdx; },
		stepActive(i) { return i === this.currentIdx; },

		get canConfirm() {
			// Permite confirmar assim que a loja marcar como Pronto (ou Em rota, no delivery)
			return this.currentStatus === 'dh-pronto' || this.currentStatus === 'dh-rota';
		},
		get confirmLabel() {
			if (ORDER_TYPE === 'delivery') return 'Sim, já recebi';
			if (ORDER_TYPE === 'pickup')   return 'Sim, já retirei';
			return 'Sim, já foi entregue';
		},
		get confirmPrompt() {
			if (ORDER_TYPE === 'delivery') return 'Seu pedido já chegou?';
			if (ORDER_TYPE === 'pickup')   return 'Você já retirou o pedido?';
			return 'O pedido já foi servido?';
		},

		async confirmDelivery() {
			if (!confirm('Confirmar que você já recebeu o pedido? Isso finaliza o rastreamento.')) return;
			this.confirming = true;
			try {
				const res = await fetch(`/delivery/wp-json/dh/v1/order/${orderId}/confirm`, {
					method: 'POST',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
					body: new URLSearchParams({ phone: ORDER_PHONE }),
				});
				const data = await res.json();
				if (!res.ok || data.error) {
					alert(data.error || 'Não foi possível confirmar. Tente pelo WhatsApp.');
					this.confirming = false;
					return;
				}
				// atualiza estado localmente sem esperar poll
				this.currentStatus = 'dh-entregue';
				this.statusLabel = LABELS['dh-entregue'] || 'Entregue';
				this.statusColor = COLORS['dh-entregue'] || '#6b7280';
				this.statusText  = TEXTS['dh-entregue'] || '';
				this.statusIcon  = ICONS['dh-entregue'];
				const idx = STEPS.indexOf('dh-entregue');
				if (idx !== -1) this.currentIdx = idx;
			} catch (e) {
				alert('Erro de conexão');
			} finally {
				this.confirming = false;
			}
		},

		async poll() {
			try {
				const res = await fetch(`/delivery/wp-json/dh/v1/order/${orderId}`);
				const data = await res.json();
				if (data.status && data.status !== this.currentStatus) {
					this.currentStatus = data.status;
					this.statusLabel = data.status_label || LABELS[data.status] || '';
					this.statusColor = data.status_color || COLORS[data.status] || '#999';
					this.statusText  = TEXTS[data.status] || '';
					this.statusIcon  = ICONS[data.status] || ICONS['dh-novo'];
					this.isCancelled = data.status === 'dh-cancelado';
					const idx = STEPS.indexOf(data.status);
					if (idx !== -1) this.currentIdx = idx;
				}
				this.lastUpdate = new Date().toLocaleTimeString('pt-BR', {hour:'2-digit', minute:'2-digit'});
			} catch (e) { console.error(e); }
		},
		startPoll() { setInterval(() => this.poll(), 15000); },
	};
}
</script>

<?php get_footer(); ?>
