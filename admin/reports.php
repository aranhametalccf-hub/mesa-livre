<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Relatórios — insights, comparativos, fechamento de caixa e export CSV.
 * Rota: /wp-admin/admin.php?page=dh-reports
 */

add_action( 'admin_menu', function () {
	add_menu_page(
		'Relatórios',
		'Relatórios',
		'edit_others_posts',
		'dh-reports',
		'dh_reports_page',
		'dashicons-chart-line',
		8
	);
} );

// Export CSV
add_action( 'admin_init', function () {
	if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'dh-reports' ) return;
	if ( ! isset( $_GET['export'] ) || $_GET['export'] !== 'csv' ) return;
	if ( ! current_user_can( 'edit_others_posts' ) ) wp_die( 'Sem permissão' );

	$date = sanitize_text_field( $_GET['date'] ?? wp_date( 'Y-m-d', time() ) );
	dh_export_csv( $date );
	exit;
} );

function dh_export_csv( $date ) {
	$orders = get_posts( [
		'post_type'      => 'dh_order',
		'post_status'    => array_keys( dh_order_statuses() ),
		'posts_per_page' => -1,
		'date_query'     => [ [ 'column' => 'post_date', 'after' => $date . ' 00:00:00', 'before' => $date . ' 23:59:59', 'inclusive' => true ] ],
	] );

	header( 'Content-Type: text/csv; charset=UTF-8' );
	header( 'Content-Disposition: attachment; filename="pedidos-' . $date . '.csv"' );
	echo "\xEF\xBB\xBF"; // BOM UTF-8 pra Excel abrir com acento
	$out = fopen( 'php://output', 'w' );
	fputcsv( $out, [ 'ID', 'Data', 'Status', 'Cliente', 'Telefone', 'Tipo', 'Endereço', 'Itens', 'Subtotal', 'Taxa', 'Total', 'Pagamento', 'Entregador' ] );

	foreach ( $orders as $o ) {
		$c = get_post_meta( $o->ID, '_dh_customer', true ) ?: [];
		$items = get_post_meta( $o->ID, '_dh_items', true ) ?: [];
		$deliv = function_exists( 'dh_order_delivery' ) ? dh_order_delivery( $o->ID ) : null;
		$items_str = implode( ' | ', array_map( fn( $it ) => $it['qty'] . 'x ' . $it['name'], $items ) );

		fputcsv( $out, [
			$o->ID,
			get_the_time( 'd/m/Y H:i', $o ),
			dh_order_statuses()[ $o->post_status ]['label'] ?? $o->post_status,
			$c['name']    ?? '',
			$c['phone']   ?? '',
			$c['type']    ?? '',
			$c['address'] ?? '',
			$items_str,
			get_post_meta( $o->ID, '_dh_subtotal', true ),
			get_post_meta( $o->ID, '_dh_delivery_fee', true ),
			get_post_meta( $o->ID, '_dh_total', true ),
			$c['payment'] ?? '',
			$deliv['name'] ?? '',
		] );
	}
	fclose( $out );
}

function dh_reports_page() {
	$date_selected = sanitize_text_field( $_GET['date'] ?? wp_date( 'Y-m-d', time() ) );
	$compare_date  = wp_date( 'Y-m-d', strtotime( $date_selected ) - 86400 );

	$m_today = dh_daily_metrics( $date_selected );
	$m_prev  = dh_daily_metrics( $compare_date );

	// Faturamento por hora
	$orders = get_posts( [
		'post_type'      => 'dh_order',
		'post_status'    => array_keys( dh_order_statuses() ),
		'posts_per_page' => -1,
		'date_query'     => [ [ 'column' => 'post_date', 'after' => $date_selected . ' 00:00:00', 'before' => $date_selected . ' 23:59:59', 'inclusive' => true ] ],
	] );

	$by_hour = array_fill( 0, 24, 0 );
	$by_payment = [];
	$top_items = [];
	$cash_total = 0;
	foreach ( $orders as $o ) {
		if ( $o->post_status === 'dh-cancelado' ) continue;
		$h = intval( get_post_time( 'G', false, $o ) );
		$total = (float) get_post_meta( $o->ID, '_dh_total', true );
		$by_hour[ $h ] += $total;

		$c = get_post_meta( $o->ID, '_dh_customer', true ) ?: [];
		$pay = $c['payment'] ?? '—';
		$by_payment[ $pay ] = ( $by_payment[ $pay ] ?? 0 ) + $total;
		if ( strcasecmp( $pay, 'Dinheiro' ) === 0 ) $cash_total += $total;

		foreach ( ( get_post_meta( $o->ID, '_dh_items', true ) ?: [] ) as $it ) {
			$k = $it['name'];
			if ( ! isset( $top_items[ $k ] ) ) $top_items[ $k ] = 0;
			$top_items[ $k ] += intval( $it['qty'] );
		}
	}
	arsort( $top_items );
	$top_items = array_slice( $top_items, 0, 8, true );
	arsort( $by_payment );

	$delta = function ( $curr, $prev ) {
		if ( ! $prev ) return null;
		$d = ( ( $curr - $prev ) / $prev ) * 100;
		return round( $d, 1 );
	};
	$delta_rev  = $delta( $m_today['total_rev'], $m_prev['total_rev'] );
	$delta_cnt  = $delta( $m_today['total_orders'], $m_prev['total_orders'] );
	$delta_avg  = $delta( $m_today['ticket_avg'], $m_prev['ticket_avg'] );
	?>
	<div class="wrap dh-page">
		<div class="dh-rep-topbar">
			<div>
				<h1 style="margin:0">Relatórios</h1>
				<small><?php echo esc_html( date_i18n( 'l, d \d\e F', strtotime( $date_selected ) ) ); ?></small>
			</div>
			<form method="get" class="dh-rep-date">
				<input type="hidden" name="page" value="dh-reports">
				<label>Data:
					<input type="date" name="date" value="<?php echo esc_attr( $date_selected ); ?>" onchange="this.form.submit()">
				</label>
				<a href="<?php echo esc_url( add_query_arg( [ 'page' => 'dh-reports', 'date' => $date_selected, 'export' => 'csv' ] ) ); ?>" class="button button-primary">
					<span class="dashicons dashicons-download" style="vertical-align:middle"></span> Exportar CSV
				</a>
			</form>
		</div>

		<!-- Cards comparativos -->
		<div class="dh-rep-cards">
			<?php
			$stats = [
				[ 'Faturamento', dh_money( $m_today['total_rev'] ), $delta_rev, dh_money( $m_prev['total_rev'] ) ],
				[ 'Pedidos', intval( $m_today['total_orders'] ), $delta_cnt, intval( $m_prev['total_orders'] ) ],
				[ 'Ticket médio', dh_money( $m_today['ticket_avg'] ), $delta_avg, dh_money( $m_prev['ticket_avg'] ) ],
				[ 'Em atraso agora', intval( $m_today['overdue'] ), null, null ],
			];
			foreach ( $stats as $s ) : ?>
				<div class="dh-rep-card">
					<small><?php echo esc_html( $s[0] ); ?></small>
					<strong><?php echo esc_html( is_numeric( $s[1] ) ? number_format( $s[1], 0, ',', '.' ) : $s[1] ); ?></strong>
					<?php if ( $s[2] !== null ) : ?>
						<div class="dh-rep-delta <?php echo $s[2] >= 0 ? 'is-up' : 'is-down'; ?>">
							<?php echo $s[2] > 0 ? '+' : ''; ?><?php echo esc_html( $s[2] ); ?>% vs ontem
							<em>(<?php echo esc_html( is_numeric( $s[3] ) ? number_format( $s[3], 0, ',', '.' ) : $s[3] ); ?>)</em>
						</div>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>

		<div class="dh-rep-grid">
			<!-- Gráfico faturamento por hora -->
			<div class="dh-rep-block">
				<h2>Faturamento por hora</h2>
				<canvas id="dh-chart-hours" width="800" height="240"></canvas>
			</div>

			<!-- Breakdown pagamento -->
			<div class="dh-rep-block">
				<h2>Formas de pagamento</h2>
				<div class="dh-rep-payments">
					<?php if ( empty( $by_payment ) ) : ?>
						<p class="description">Sem dados.</p>
					<?php else :
						$total_pay = array_sum( $by_payment );
						foreach ( $by_payment as $method => $val ) :
							$pct = $total_pay > 0 ? ( $val / $total_pay ) * 100 : 0;
							?>
							<div class="dh-rep-pay-row">
								<div class="dh-rep-pay-head">
									<span><?php echo esc_html( $method ); ?></span>
									<strong><?php echo dh_money( $val ); ?> · <?php echo round( $pct, 1 ); ?>%</strong>
								</div>
								<div class="dh-rep-pay-bar"><span style="width:<?php echo $pct; ?>%"></span></div>
							</div>
						<?php endforeach;
					endif; ?>
				</div>
			</div>

			<!-- Top itens -->
			<div class="dh-rep-block">
				<h2>Itens mais vendidos</h2>
				<?php if ( empty( $top_items ) ) : ?>
					<p class="description">Sem itens vendidos hoje.</p>
				<?php else : ?>
					<ol class="dh-rep-top">
						<?php foreach ( $top_items as $name => $qty ) : ?>
							<li>
								<span><?php echo esc_html( $name ); ?></span>
								<strong><?php echo intval( $qty ); ?>x</strong>
							</li>
						<?php endforeach; ?>
					</ol>
				<?php endif; ?>
			</div>

			<!-- Fechamento de caixa -->
			<div class="dh-rep-block dh-rep-cashclose">
				<h2>Fechamento de caixa</h2>
				<p class="description">
					Confira o dinheiro em espécie recebido contra o esperado.
					<strong>Cálculo local, não persiste — só pra conferência do turno.</strong>
				</p>
				<div class="dh-rep-cash-row">
					<label>Vendas em dinheiro (esperado)</label>
					<strong><?php echo dh_money( $cash_total ); ?></strong>
				</div>
				<div class="dh-rep-cash-row">
					<label for="dh-cash-counted">Dinheiro contado</label>
					<input type="number" step="0.01" min="0" id="dh-cash-counted" placeholder="0,00">
				</div>
				<div class="dh-rep-cash-row dh-rep-cash-diff">
					<label>Diferença</label>
					<strong id="dh-cash-diff" data-expected="<?php echo esc_attr( $cash_total ); ?>">—</strong>
				</div>
			</div>
		</div>
	</div>

	<script>
	(function () {
		// Chart faturamento por hora (canvas puro, sem lib)
		const data = <?php echo wp_json_encode( array_values( $by_hour ) ); ?>;
		const canvas = document.getElementById('dh-chart-hours');
		if (canvas) drawBars(canvas, data);
		function drawBars(cv, values) {
			const ctx = cv.getContext('2d');
			const W = cv.width, H = cv.height, pad = 30;
			ctx.clearRect(0, 0, W, H);
			const max = Math.max(...values, 1);
			const bw = (W - pad*2) / values.length;
			// Eixo Y — 3 linhas guia
			ctx.strokeStyle = '#e5e7eb';
			ctx.fillStyle = '#9ca3af';
			ctx.font = '10px -apple-system, sans-serif';
			for (let i = 0; i <= 4; i++) {
				const y = pad + ((H - pad*2) * i / 4);
				ctx.beginPath(); ctx.moveTo(pad, y); ctx.lineTo(W - pad, y); ctx.stroke();
				const val = max * (1 - i/4);
				ctx.fillText('R$ ' + Math.round(val), 2, y + 3);
			}
			// Barras
			values.forEach((v, i) => {
				const h = ((v / max) * (H - pad*2));
				const x = pad + i * bw + 2;
				const y = H - pad - h;
				ctx.fillStyle = v > 0 ? '#ea1d2c' : '#f3f4f6';
				ctx.fillRect(x, y, bw - 4, h);
				// labels a cada 3h
				if (i % 3 === 0) {
					ctx.fillStyle = '#6b7280';
					ctx.fillText(String(i).padStart(2, '0') + 'h', x, H - pad + 12);
				}
			});
		}

		// Fechamento de caixa
		const counted = document.getElementById('dh-cash-counted');
		const diff = document.getElementById('dh-cash-diff');
		function fmt(v) { return 'R$ ' + Number(v).toFixed(2).replace('.',','); }
		if (counted && diff) {
			const expected = parseFloat(diff.dataset.expected) || 0;
			counted.addEventListener('input', () => {
				const c = parseFloat(counted.value) || 0;
				const d = c - expected;
				diff.textContent = (d > 0 ? '+' : '') + fmt(d);
				diff.className = d === 0 ? 'is-ok' : (d > 0 ? 'is-up' : 'is-down');
			});
		}
	})();
	</script>

	<style>
		.dh-rep-topbar { display: flex; align-items: center; justify-content: space-between; gap: 20px; background: white; padding: 18px 22px; border-radius: 12px; margin: 20px 20px 16px 0; box-shadow: 0 1px 3px rgba(0,0,0,.05); flex-wrap: wrap; }
		.dh-rep-topbar small { color: #9ca3af; text-transform: capitalize; }
		.dh-rep-date { display: flex; gap: 10px; align-items: center; }
		.dh-rep-date input[type=date] { padding: 6px 10px; border: 1px solid #d1d5db; border-radius: 6px; }

		.dh-rep-cards { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin: 0 20px 16px 0; }
		.dh-rep-card { background: white; padding: 16px 20px; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,.05); }
		.dh-rep-card small { display: block; font-size: 11px; text-transform: uppercase; color: #9ca3af; letter-spacing: .5px; font-weight: 700; }
		.dh-rep-card strong { display: block; font-size: 22px; color: #1f2937; font-weight: 800; margin: 4px 0; }
		.dh-rep-delta { font-size: 11px; font-weight: 700; }
		.dh-rep-delta.is-up { color: #059669; }
		.dh-rep-delta.is-down { color: #dc2626; }
		.dh-rep-delta em { font-style: normal; color: #9ca3af; font-weight: 500; margin-left: 4px; }

		.dh-rep-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 16px; margin-right: 20px; }
		.dh-rep-block { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,.05); }
		.dh-rep-block h2 { margin: 0 0 12px; font-size: 14px; text-transform: uppercase; letter-spacing: .5px; color: #4b5563; }

		#dh-chart-hours { width: 100%; height: 240px; }

		.dh-rep-payments { display: flex; flex-direction: column; gap: 10px; }
		.dh-rep-pay-head { display: flex; justify-content: space-between; font-size: 13px; margin-bottom: 4px; }
		.dh-rep-pay-head strong { color: #1f2937; }
		.dh-rep-pay-bar { height: 6px; background: #f3f4f6; border-radius: 3px; overflow: hidden; }
		.dh-rep-pay-bar span { display: block; height: 100%; background: #ea1d2c; }

		.dh-rep-top { list-style: none; padding: 0; margin: 0; counter-reset: dh-top; }
		.dh-rep-top li { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #f3f4f6; font-size: 13px; }
		.dh-rep-top li::before { content: counter(dh-top) '.'; counter-increment: dh-top; color: #9ca3af; font-weight: 800; margin-right: 8px; }
		.dh-rep-top li:last-child { border-bottom: none; }
		.dh-rep-top strong { color: #ea1d2c; }

		.dh-rep-cashclose { grid-column: 1 / -1; }
		.dh-rep-cash-row { display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid #f3f4f6; }
		.dh-rep-cash-row:last-child { border-bottom: none; }
		.dh-rep-cash-row label { font-size: 13px; color: #4b5563; }
		.dh-rep-cash-row input { padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; width: 140px; text-align: right; }
		.dh-rep-cash-diff strong { font-size: 18px; }
		.dh-rep-cash-diff strong.is-ok { color: #059669; }
		.dh-rep-cash-diff strong.is-up { color: #d97706; }
		.dh-rep-cash-diff strong.is-down { color: #dc2626; }

		@media (max-width: 1100px) {
			.dh-rep-cards { grid-template-columns: repeat(2, 1fr); }
			.dh-rep-grid { grid-template-columns: 1fr; }
		}
	</style>
	<?php
}
