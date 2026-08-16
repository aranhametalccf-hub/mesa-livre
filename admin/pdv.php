<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'admin_menu', function () {
	$novos = count( get_posts( [
		'post_type'      => 'dh_order',
		'post_status'    => 'dh-novo',
		'posts_per_page' => 100,
		'fields'         => 'ids',
	] ) );
	$label = 'PDV';
	if ( $novos > 0 ) {
		$label .= ' <span class="awaiting-mod update-plugins count-' . $novos . '"><span class="pending-count">' . $novos . '</span></span>';
	}
	add_menu_page(
		'PDV — Pedidos',
		$label,
		'edit_posts',
		'dh-pdv',
		'dh_pdv_page',
		'dashicons-schedule',
		2
	);
} );

function dh_pdv_page() {
	$statuses = dh_order_statuses();
	$slas     = dh_get_slas();

	// Filtro de período (server-side): today | yesterday | last7
	$period = sanitize_text_field( $_GET['period'] ?? 'today' );
	$today  = wp_date( 'Y-m-d', time() );
	switch ( $period ) {
		case 'yesterday':
			$from_date = wp_date( 'Y-m-d', time() - 86400 );
			$to_date   = $from_date;
			$period_label = 'Ontem';
			break;
		case 'last7':
			$from_date = wp_date( 'Y-m-d', time() - 6 * 86400 );
			$to_date   = $today;
			$period_label = 'Últimos 7 dias';
			break;
		default:
			$period = 'today';
			$from_date = $today;
			$to_date   = $today;
			$period_label = 'Hoje';
	}
	$date_filter = [ [
		'column'    => 'post_date',
		'after'     => $from_date . ' 00:00:00',
		'before'    => $to_date . ' 23:59:59',
		'inclusive' => true,
	] ];

	$orders_by_status = [];
	foreach ( array_keys( $statuses ) as $st ) {
		$orders_by_status[ $st ] = get_posts( [
			'post_type'      => 'dh_order',
			'post_status'    => $st,
			'posts_per_page' => 50,
			'date_query'     => $date_filter,
		] );
	}

	$m = dh_daily_metrics( $today ); // métricas SEMPRE do dia atual (independente do filtro)

	$stage_labels = [
		'accept'   => [ 'Aceite',    'sla_accept'   ],
		'prep'     => [ 'Preparo',   'sla_prep'     ],
		'dispatch' => [ 'Expedição', 'sla_dispatch' ],
		'delivery' => [ 'Entrega',   'sla_delivery' ],
		'pickup'   => [ 'Retirada',  'sla_pickup'   ],
		'total'    => [ 'Ciclo total', 'sla_total'  ],
	];
	?>
	<?php $store = dh_store_status(); ?>
	<div class="wrap dh-pdv-wrap" data-nonce="<?php echo esc_attr( wp_create_nonce( 'dh_pdv' ) ); ?>">
		<div class="dh-pdv-topbar">
			<div>
				<h1>PDV — Pedidos do dia</h1>
				<small><?php echo esc_html( date_i18n( 'l, d \d\e F', current_time( 'timestamp' ) ) ); ?></small>
			</div>

			<div class="dh-store-toggle" data-open="<?php echo $store['open'] ? '1' : '0'; ?>">
				<div class="dh-store-status is-<?php echo $store['open'] ? 'open' : 'closed'; ?>">
					<span class="dh-store-dot"></span>
					<div>
						<strong><?php echo $store['open'] ? 'Loja aberta' : 'Loja fechada'; ?></strong>
						<small><?php echo esc_html( $store['message'] ); ?></small>
					</div>
				</div>
				<div class="dh-store-actions">
					<?php if ( $store['open'] ) : ?>
						<button type="button" class="dh-store-btn dh-store-pause" data-mins="15">Pausar 15min</button>
						<button type="button" class="dh-store-btn dh-store-pause" data-mins="30">30min</button>
						<button type="button" class="dh-store-btn dh-store-pause" data-mins="60">1h</button>
						<button type="button" class="dh-store-btn dh-store-pause" data-mode="end_of_day">Até fechar</button>
						<button type="button" class="dh-store-btn dh-store-close" title="Fechar loja">Fechar</button>
					<?php else : ?>
						<button type="button" class="dh-store-btn dh-store-resume">Reabrir agora</button>
					<?php endif; ?>
				</div>
			</div>
		</div>
		</div>

		<div class="dh-pdv-summary-row">
			<div class="dh-sum-box">
				<small>Pedidos</small>
				<strong><?php echo intval( $m['total_orders'] ); ?></strong>
			</div>
			<div class="dh-sum-box">
				<small>Faturamento</small>
				<strong><?php echo dh_money( $m['total_rev'] ); ?></strong>
			</div>
			<div class="dh-sum-box">
				<small>Ticket médio</small>
				<strong><?php echo dh_money( $m['ticket_avg'] ); ?></strong>
			</div>
			<div class="dh-sum-box <?php echo $m['overdue'] > 0 ? 'is-alert' : ''; ?>">
				<small>Em atraso</small>
				<strong><?php echo intval( $m['overdue'] ); ?></strong>
			</div>
		</div>

		<div class="dh-pdv-slabar">
			<span class="dh-slabar-title">Tempos médios do dia</span>
			<?php foreach ( $stage_labels as $key => $meta ) :
				list( $label, $sla_key ) = $meta;
				$avg  = $m['averages'][ $key ] ?? null;
				$cnt  = $m['counts'][ $key ] ?? 0;
				$sla_min = $slas[ $sla_key ] ?? 0;
				$sla_secs = $sla_min * 60;
				if ( $avg === null ) {
					$state = 'empty';
					$pct = 0;
				} else {
					$pct = $sla_secs > 0 ? min( 200, ( $avg / $sla_secs ) * 100 ) : 0;
					$state = $pct <= 70 ? 'ok' : ( $pct <= 100 ? 'warn' : 'over' );
				}
				?>
				<div class="dh-slabar-item is-<?php echo esc_attr( $state ); ?>">
					<div class="dh-slabar-head">
						<span><?php echo esc_html( $label ); ?></span>
						<strong><?php echo $avg === null ? '—' : dh_fmt_seconds( $avg ); ?></strong>
					</div>
					<div class="dh-slabar-track">
						<div class="dh-slabar-fill" style="width: <?php echo min( 100, $pct ); ?>%"></div>
					</div>
					<small>meta <?php echo intval( $sla_min ); ?>min · <?php echo $cnt; ?> amostra<?php echo $cnt === 1 ? '' : 's'; ?></small>
				</div>
			<?php endforeach; ?>
		</div>

		<div class="dh-pdv-toolbar">
			<div class="dh-toolbar-search">
				<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
				<input type="text" id="dh-pdv-search" placeholder="Buscar por nome, telefone, #pedido ou item…" autocomplete="off">
				<button type="button" id="dh-pdv-search-clear" title="Limpar" hidden>×</button>
			</div>

			<div class="dh-toolbar-chips" data-group="type">
				<span class="dh-chip-label">Tipo:</span>
				<button type="button" class="dh-chip is-active" data-type="all">Todos</button>
				<button type="button" class="dh-chip" data-type="delivery">Entrega</button>
				<button type="button" class="dh-chip" data-type="pickup">Retirada</button>
				<button type="button" class="dh-chip" data-type="dinein">No local</button>
			</div>

			<div class="dh-toolbar-chips" data-group="period">
				<span class="dh-chip-label">Período:</span>
				<a href="?page=dh-pdv&period=today"     class="dh-chip <?php echo $period === 'today'     ? 'is-active' : ''; ?>">Hoje</a>
				<a href="?page=dh-pdv&period=yesterday" class="dh-chip <?php echo $period === 'yesterday' ? 'is-active' : ''; ?>">Ontem</a>
				<a href="?page=dh-pdv&period=last7"     class="dh-chip <?php echo $period === 'last7'     ? 'is-active' : ''; ?>">7 dias</a>
			</div>

			<div class="dh-toolbar-right">
				<label class="dh-toolbar-select">
					Ordenar:
					<select id="dh-pdv-sort">
						<option value="oldest">Mais antigo</option>
						<option value="newest">Mais recente</option>
						<option value="value_desc">Maior valor</option>
						<option value="value_asc">Menor valor</option>
					</select>
				</label>
				<label class="dh-toolbar-toggle" title="Modo compacto">
					<input type="checkbox" id="dh-pdv-compact">
					<span>Compacto</span>
				</label>
			</div>
		</div>

		<div class="dh-pdv-empty-hint" id="dh-pdv-empty-hint" hidden>
			Nenhum pedido corresponde aos filtros. <button type="button" id="dh-pdv-reset-filters" class="button-link">Limpar filtros</button>
		</div>

		<div class="dh-kanban">
			<?php foreach ( $statuses as $slug => $cfg ) : ?>
				<div class="dh-kanban-col" data-status="<?php echo esc_attr( $slug ); ?>">
					<header style="border-top: 4px solid <?php echo esc_attr( $cfg['color'] ); ?>">
						<h2><?php echo esc_html( $cfg['label'] ); ?></h2>
						<span class="dh-kanban-count"><?php echo count( $orders_by_status[ $slug ] ); ?></span>
					</header>
					<div class="dh-kanban-list" data-status="<?php echo esc_attr( $slug ); ?>">
						<?php foreach ( $orders_by_status[ $slug ] as $order ) :
							$customer = get_post_meta( $order->ID, '_dh_customer', true ) ?: [];
							$items    = get_post_meta( $order->ID, '_dh_items', true ) ?: [];
							$total    = get_post_meta( $order->ID, '_dh_total', true );
							$type_map = [ 'delivery' => 'Entrega', 'pickup' => 'Retirada', 'dinein' => 'No local' ];
							$stage_secs = dh_current_stage_seconds( $order->ID );
							$stage_sla  = dh_current_stage_sla( $order->ID );
							$has_timer  = ! in_array( $slug, [ 'dh-entregue', 'dh-cancelado' ], true );
							// Payload pra filtro client-side
							$search_haystack = strtolower( trim(
								( $customer['name'] ?? '' ) . ' ' .
								( $customer['phone'] ?? '' ) . ' ' .
								'#' . $order->ID . ' ' .
								implode( ' ', array_map( fn( $it ) => $it['name'] ?? '', $items ) )
							) );
							?>
							<article class="dh-order-card <?php echo $has_timer ? 'has-timer' : ''; ?>"
								data-id="<?php echo esc_attr( $order->ID ); ?>"
								data-since="<?php echo esc_attr( time() - $stage_secs ); ?>"
								data-sla="<?php echo esc_attr( $stage_sla ); ?>"
								data-type="<?php echo esc_attr( $customer['type'] ?? 'delivery' ); ?>"
								data-search="<?php echo esc_attr( $search_haystack ); ?>"
								data-total="<?php echo esc_attr( (float) $total ); ?>"
								data-created="<?php echo esc_attr( get_post_time( 'U', true, $order ) ); ?>">
								<header>
									<strong>#<?php echo $order->ID; ?></strong>
									<time><?php echo esc_html( get_the_time( 'H:i', $order ) ); ?></time>
								</header>
								<h3><?php echo esc_html( $customer['name'] ?? '-' ); ?></h3>
								<p class="dh-order-type">
									<?php echo esc_html( $type_map[ $customer['type'] ?? 'delivery' ] ?? '' ); ?>
									<?php if ( ! empty( $customer['phone'] ) ) : ?>
										• <?php echo esc_html( $customer['phone'] ); ?>
									<?php endif; ?>
								</p>
								<ul class="dh-order-items">
									<?php foreach ( array_slice( $items, 0, 4 ) as $it ) : ?>
										<li><?php echo esc_html( $it['qty'] . 'x ' . $it['name'] ); ?></li>
									<?php endforeach; ?>
									<?php if ( count( $items ) > 4 ) : ?>
										<li class="dh-more">+<?php echo count( $items ) - 4; ?> item(ns)</li>
									<?php endif; ?>
								</ul>

								<?php if ( $has_timer ) : ?>
									<div class="dh-card-timer">
										<div class="dh-timer-row">
											<span class="dh-timer-label">Nesta etapa</span>
											<span class="dh-timer-value" data-timer>—</span>
										</div>
										<?php if ( $stage_sla > 0 ) : ?>
											<div class="dh-timer-track">
												<div class="dh-timer-fill" data-fill></div>
											</div>
										<?php endif; ?>
									</div>
								<?php endif; ?>

								<footer>
									<strong><?php echo dh_money( $total ); ?></strong>
									<div class="dh-card-actions">
										<button type="button" class="dh-card-menu-btn"
											data-phone="<?php echo esc_attr( preg_replace( '/\D/', '', $customer['phone'] ?? '' ) ); ?>"
											data-name="<?php echo esc_attr( $customer['name'] ?? '' ); ?>"
											data-type="<?php echo esc_attr( $customer['type'] ?? 'delivery' ); ?>"
											data-items='<?php echo esc_attr( wp_json_encode( array_map( fn( $it ) => [ 'id' => $it['id'], 'name' => $it['name'] ], $items ) ) ); ?>'
											title="Ações rápidas">
											<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><circle cx="5" cy="12" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="19" cy="12" r="2"/></svg>
										</button>
										<a href="<?php echo esc_url( get_edit_post_link( $order->ID ) ); ?>" class="button button-small">Ver</a>
									</div>
								</footer>
							</article>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endforeach; ?>
		</div>

		<p class="dh-pdv-hint">
			Visualizando <strong><?php echo esc_html( $period_label ); ?></strong>.
			Arraste os cards entre colunas pra mudar o status. Timer atualiza a cada segundo · recarrega a cada 15s se cair pedido novo.
		</p>
	</div>
	<?php
}
