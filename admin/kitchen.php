<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Modo Cozinha — tela fullscreen otimizada pra TV/tablet montado na cozinha.
 * Rota: /wp-admin/admin.php?page=dh-kitchen
 * Auth: reusa auth do admin (precisa capability edit_posts).
 * Design: sem admin bar, 2 colunas gigantes (Preparando + Pronto), auto-refresh 10s.
 */

add_action( 'admin_menu', function () {
	add_submenu_page(
		'dh-pdv',
		'Modo Cozinha',
		'Modo Cozinha',
		'edit_posts',
		'dh-kitchen',
		'dh_kitchen_page'
	);
} );

// Esconder chrome do WP quando estiver na tela cozinha
add_action( 'admin_init', function () {
	if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'dh-kitchen' ) return;
	if ( ! isset( $_GET['fs'] ) ) return; // ativa fullscreen só com ?fs=1
	add_filter( 'show_admin_bar', '__return_false' );
	remove_action( 'admin_head', 'wp_admin_bar_render', 0 );
} );

function dh_kitchen_page() {
	$statuses = dh_order_statuses();
	$today    = wp_date( 'Y-m-d', time() );
	$date_filter = [ [ 'column' => 'post_date', 'after' => $today . ' 00:00:00', 'inclusive' => true ] ];

	$cols = [ 'dh-novo', 'dh-preparando', 'dh-pronto' ];
	$orders_by_status = [];
	foreach ( $cols as $st ) {
		$orders_by_status[ $st ] = get_posts( [
			'post_type'      => 'dh_order',
			'post_status'    => $st,
			'posts_per_page' => 30,
			'date_query'     => $date_filter,
			'orderby'        => 'date',
			'order'          => 'ASC',
		] );
	}

	$fs = isset( $_GET['fs'] ) ? '1' : '';
	?>
	<div class="dh-kitchen-wrap <?php echo $fs ? 'is-fullscreen' : ''; ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'dh_pdv' ) ); ?>">

		<header class="dh-kitchen-top">
			<h1>
				<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5z"/></svg>
				Cozinha
			</h1>
			<div class="dh-kitchen-clock" id="dh-kitchen-clock"><?php echo esc_html( wp_date( 'H:i', time() ) ); ?></div>
			<div class="dh-kitchen-actions">
				<a href="?page=dh-kitchen<?php echo $fs ? '' : '&fs=1'; ?>" class="dh-kitchen-btn">
					<?php echo $fs ? 'Sair fullscreen' : 'Fullscreen'; ?>
				</a>
				<a href="?page=dh-pdv" class="dh-kitchen-btn dh-kitchen-btn-ghost">Voltar ao PDV</a>
			</div>
		</header>

		<div class="dh-kitchen-grid" data-poll-nonce="<?php echo esc_attr( wp_create_nonce( 'dh_pdv' ) ); ?>">
			<?php foreach ( $cols as $slug ) :
				$cfg = $statuses[ $slug ];
				?>
				<section class="dh-kitchen-col" data-status="<?php echo esc_attr( $slug ); ?>" style="--col-color: <?php echo esc_attr( $cfg['color'] ); ?>">
					<header>
						<h2><?php echo esc_html( $cfg['label'] ); ?></h2>
						<span class="dh-kitchen-count"><?php echo count( $orders_by_status[ $slug ] ); ?></span>
					</header>
					<div class="dh-kitchen-list">
						<?php foreach ( $orders_by_status[ $slug ] as $order ) :
							$customer = get_post_meta( $order->ID, '_dh_customer', true ) ?: [];
							$items    = get_post_meta( $order->ID, '_dh_items', true ) ?: [];
							$stage_secs = dh_current_stage_seconds( $order->ID );
							$stage_sla  = dh_current_stage_sla( $order->ID );

							$next = dh_next_status( $order->post_status, $customer['type'] ?? 'delivery' );
							$next_verb = [
								'dh-preparando' => 'INICIAR PREPARO',
								'dh-pronto'     => 'PRONTO',
								'dh-rota'       => 'SAIU',
								'dh-entregue'   => 'ENTREGUE',
							][ $next ] ?? '→';
							?>
							<article class="dh-kt-card has-timer"
								data-id="<?php echo esc_attr( $order->ID ); ?>"
								data-since="<?php echo esc_attr( time() - $stage_secs ); ?>"
								data-sla="<?php echo esc_attr( $stage_sla ); ?>">
								<header>
									<strong>#<?php echo intval( $order->ID ); ?></strong>
									<time data-timer>—</time>
								</header>

								<h3><?php echo esc_html( $customer['name'] ?? '' ); ?></h3>
								<?php if ( ( $customer['type'] ?? '' ) === 'dinein' && ! empty( $customer['table'] ) ) : ?>
									<div class="dh-kt-tag dh-kt-tag-mesa">MESA <?php echo esc_html( $customer['table'] ); ?></div>
								<?php else : ?>
									<div class="dh-kt-tag"><?php echo esc_html( [ 'delivery' => 'ENTREGA', 'pickup' => 'RETIRADA', 'dinein' => 'NO LOCAL' ][ $customer['type'] ?? 'delivery' ] ); ?></div>
								<?php endif; ?>

								<ul class="dh-kt-items">
									<?php foreach ( $items as $it ) : ?>
										<li>
											<span class="dh-kt-qty"><?php echo intval( $it['qty'] ); ?>x</span>
											<div>
												<strong><?php echo esc_html( $it['name'] ); ?></strong>
												<?php foreach ( ( $it['extras'] ?? [] ) as $e ) : ?>
													<small>+ <?php echo esc_html( $e['label'] ); ?></small>
												<?php endforeach; ?>
												<?php if ( ! empty( $it['obs'] ) ) : ?>
													<em><?php echo esc_html( $it['obs'] ); ?></em>
												<?php endif; ?>
											</div>
										</li>
									<?php endforeach; ?>
								</ul>

								<?php if ( ! empty( $customer['notes'] ) ) : ?>
									<div class="dh-kt-note"><?php echo esc_html( $customer['notes'] ); ?></div>
								<?php endif; ?>

								<?php if ( $next && $next !== 'dh-cancelado' ) : ?>
									<button type="button" class="dh-kt-advance"
										data-status="<?php echo esc_attr( $next ); ?>"
										data-nonce="<?php echo esc_attr( wp_create_nonce( 'dh_order_action_' . $order->ID ) ); ?>">
										<?php echo esc_html( $next_verb ); ?>
									</button>
								<?php endif; ?>
							</article>
						<?php endforeach; ?>

						<?php if ( empty( $orders_by_status[ $slug ] ) ) : ?>
							<div class="dh-kt-empty">Nenhum pedido nesta coluna</div>
						<?php endif; ?>
					</div>
				</section>
			<?php endforeach; ?>
		</div>
	</div>

	<script>
	(function () {
		// Timer live
		function fmt(s) {
			s = Math.max(0, Math.floor(s));
			if (s < 60) return s + 's';
			const m = Math.floor(s / 60);
			return m < 60 ? m + 'min' : Math.floor(m/60) + 'h' + String(m % 60).padStart(2,'0');
		}
		function tick() {
			const now = Math.floor(Date.now() / 1000);
			document.querySelectorAll('.dh-kt-card.has-timer').forEach(card => {
				const since = parseInt(card.dataset.since || 0, 10);
				const sla   = parseInt(card.dataset.sla || 0, 10);
				const secs  = Math.max(0, now - since);
				const t = card.querySelector('[data-timer]');
				if (t) t.textContent = fmt(secs);
				if (sla > 0) {
					const pct = (secs / sla) * 100;
					card.classList.remove('is-warn','is-over');
					if (pct > 100) card.classList.add('is-over');
					else if (pct >= 70) card.classList.add('is-warn');
				}
			});
			// Relógio
			const clock = document.getElementById('dh-kitchen-clock');
			if (clock) {
				const d = new Date();
				clock.textContent = String(d.getHours()).padStart(2,'0') + ':' + String(d.getMinutes()).padStart(2,'0');
			}
		}
		tick();
		setInterval(tick, 1000);

		// Botão de avanço
		document.querySelectorAll('.dh-kt-advance').forEach(btn => btn.addEventListener('click', function () {
			const card = btn.closest('.dh-kt-card');
			const id = card.dataset.id;
			const status = btn.dataset.status;
			const nonce = btn.dataset.nonce;
			btn.disabled = true;
			btn.textContent = '…';
			fetch(ajaxurl, {
				method: 'POST',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: new URLSearchParams({ action: 'dh_order_action', id, status, nonce }),
			}).then(r => r.json()).then(d => {
				if (d.success) card.classList.add('is-going');
				setTimeout(() => window.location.reload(), 400);
			}).catch(() => { btn.disabled = false; alert('Erro'); });
		}));

		// Auto-refresh 10s (só se sem card sendo processado)
		setInterval(() => {
			if (document.visibilityState === 'visible' && !document.querySelector('.dh-kt-card.is-going')) {
				window.location.reload();
			}
		}, 10000);
	})();
	</script>
	<?php
}
