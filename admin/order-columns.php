<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Faz a listagem "Todos" de dh_order mostrar TODOS os status customizados dh-*.
 * Sem isso, WP filtra por 'publish' e a tela fica vazia mesmo com pedidos existindo.
 */
add_action( 'pre_get_posts', function ( $q ) {
	if ( ! is_admin() || ! $q->is_main_query() ) return;
	if ( ( $q->get( 'post_type' ) ?? '' ) !== 'dh_order' ) return;
	if ( $q->get( 'post_status' ) ) return; // usuário clicou num filtro específico

	$q->set( 'post_status', array_keys( dh_order_statuses() ) );
} );

/**
 * Popular os links de filtro no topo da listagem ("Novo (3)", "Preparando (1)", etc).
 */
add_filter( 'views_edit-dh_order', function ( $views ) {
	$statuses = dh_order_statuses();
	$current = $_GET['post_status'] ?? '';

	// "Todos" — conta todos os customizados
	$total = 0;
	$counts = [];
	foreach ( array_keys( $statuses ) as $slug ) {
		$c = count( get_posts( [
			'post_type'      => 'dh_order',
			'post_status'    => $slug,
			'posts_per_page' => -1,
			'fields'         => 'ids',
		] ) );
		$counts[ $slug ] = $c;
		$total += $c;
	}

	$class = $current === '' ? ' class="current"' : '';
	$views = [
		'all' => sprintf(
			'<a href="%s"%s>Todos <span class="count">(%d)</span></a>',
			esc_url( admin_url( 'edit.php?post_type=dh_order' ) ),
			$class,
			$total
		),
	];

	foreach ( $statuses as $slug => $cfg ) {
		if ( $counts[ $slug ] === 0 ) continue;
		$class = $current === $slug ? ' class="current"' : '';
		$views[ $slug ] = sprintf(
			'<a href="%s"%s>%s <span class="count">(%d)</span></a>',
			esc_url( admin_url( 'edit.php?post_type=dh_order&post_status=' . $slug ) ),
			$class,
			esc_html( $cfg['label'] ),
			$counts[ $slug ]
		);
	}

	return $views;
} );

/**
 * Colunas customizadas na listagem de pedidos no admin.
 */
add_filter( 'manage_dh_order_posts_columns', function ( $cols ) {
	return [
		'cb'         => $cols['cb'] ?? '',
		'dh_id'      => '#',
		'dh_client'  => 'Cliente',
		'dh_type'    => 'Tipo',
		'dh_total'   => 'Total',
		'dh_status'  => 'Status',
		'date'       => 'Data',
	];
} );

add_action( 'manage_dh_order_posts_custom_column', function ( $col, $post_id ) {
	$customer = get_post_meta( $post_id, '_dh_customer', true ) ?: [];
	$total    = get_post_meta( $post_id, '_dh_total', true );
	$status   = get_post_status( $post_id );
	$statuses = dh_order_statuses();

	switch ( $col ) {
		case 'dh_id':
			echo '<strong>#' . intval( $post_id ) . '</strong>';
			break;
		case 'dh_client':
			echo esc_html( $customer['name'] ?? '-' );
			if ( ! empty( $customer['phone'] ) ) echo '<br><small>' . esc_html( $customer['phone'] ) . '</small>';
			break;
		case 'dh_type':
			$map = [ 'delivery' => 'Entrega', 'pickup' => 'Retirada', 'dinein' => 'No local' ];
			echo esc_html( $map[ $customer['type'] ?? '' ] ?? '-' );
			break;
		case 'dh_total':
			echo '<strong>' . dh_money( $total ) . '</strong>';
			break;
		case 'dh_status':
			$cfg = $statuses[ $status ] ?? null;
			if ( $cfg ) {
				echo '<span class="dh-status-pill" style="background:' . esc_attr( $cfg['color'] ) . '">' . esc_html( $cfg['label'] ) . '</span>';
			}
			break;
	}
}, 10, 2 );

/**
 * Metabox de detalhes na tela de edição do pedido.
 */
add_action( 'add_meta_boxes', function () {
	add_meta_box( 'dh_order_detail', 'Detalhes do Pedido', 'dh_mb_order_detail', 'dh_order', 'normal', 'high' );
	add_meta_box( 'dh_order_actions', 'Ações', 'dh_mb_order_actions', 'dh_order', 'side', 'high' );
} );

function dh_mb_order_detail( $post ) {
	$customer = get_post_meta( $post->ID, '_dh_customer', true ) ?: [];
	$items    = get_post_meta( $post->ID, '_dh_items', true ) ?: [];
	$subtotal = get_post_meta( $post->ID, '_dh_subtotal', true );
	$fee      = get_post_meta( $post->ID, '_dh_delivery_fee', true );
	$total    = get_post_meta( $post->ID, '_dh_total', true );
	$type_map = [ 'delivery' => 'Entrega', 'pickup' => 'Retirada no balcão', 'dinein' => 'Consumir no local' ];

	$nonce = wp_create_nonce( 'dh_order_edit_' . $post->ID );
	$is_editable = ! in_array( $post->post_status, [ 'dh-entregue', 'dh-cancelado' ], true );

	$menu_items = get_posts( [
		'post_type'      => 'menu_item',
		'posts_per_page' => -1,
		'orderby'        => 'title',
		'order'          => 'ASC',
	] );
	?>
	<div class="dh-order-detail" data-order-id="<?php echo esc_attr( $post->ID ); ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>">

		<h3 class="dh-detail-heading">
			Cliente
			<?php if ( $is_editable ) : ?>
				<button type="button" class="button-link dh-edit-customer">editar</button>
			<?php endif; ?>
		</h3>
		<div class="dh-customer-view">
			<p>
				<strong><?php echo esc_html( $customer['name'] ?? '-' ); ?></strong><br>
				WhatsApp: <?php echo esc_html( $customer['phone'] ?? '-' ); ?><br>
				Tipo: <?php echo esc_html( $type_map[ $customer['type'] ?? '' ] ?? '-' ); ?>
				<?php
				if ( ! empty( $customer['street'] ) ) {
					$linha = $customer['street'] . ( ! empty( $customer['number'] ) ? ', ' . $customer['number'] : '' );
					if ( ! empty( $customer['complement'] ) ) $linha .= ' — ' . $customer['complement'];
					echo '<br>Endereço: ' . esc_html( $linha );
					if ( ! empty( $customer['neighborhood'] ) ) echo '<br>Bairro: ' . esc_html( $customer['neighborhood'] );
					$cid = trim( ( $customer['city'] ?? '' ) . ( ! empty( $customer['state'] ) ? '/' . $customer['state'] : '' ) );
					if ( $cid ) echo '<br>' . esc_html( $cid );
					if ( ! empty( $customer['cep'] ) ) echo '<br>CEP: ' . esc_html( preg_replace( '/^(\d{5})(\d{3})$/', '$1-$2', $customer['cep'] ) );
					if ( ! empty( $customer['reference'] ) ) echo '<br>Ref: ' . esc_html( $customer['reference'] );
				} elseif ( ! empty( $customer['address'] ) ) {
					echo '<br>Endereço: ' . esc_html( $customer['address'] );
				}
				?>
				<?php if ( ! empty( $customer['table'] ) ) : ?><br>Mesa: <?php echo esc_html( $customer['table'] ); ?><?php endif; ?>
			</p>
		</div>
		<div class="dh-customer-edit" style="display:none">
			<p><label>Nome<br><input type="text" name="edit_name" value="<?php echo esc_attr( $customer['name'] ?? '' ); ?>" class="widefat"></label></p>
			<p><label>WhatsApp<br><input type="text" name="edit_phone" value="<?php echo esc_attr( $customer['phone'] ?? '' ); ?>" class="widefat"></label></p>
			<p><label>Endereço completo<br><textarea name="edit_address" rows="2" class="widefat"><?php echo esc_textarea( $customer['address'] ?? '' ); ?></textarea></label></p>
			<p>
				<button type="button" class="button button-primary dh-save-customer">Salvar</button>
				<button type="button" class="button dh-cancel-customer">Cancelar</button>
			</p>
		</div>

		<h3 class="dh-detail-heading">Itens do pedido</h3>
		<table class="widefat striped dh-items-table">
			<thead>
				<tr>
					<th style="width:110px">Qtd</th>
					<th>Item</th>
					<th style="text-align:right;width:100px">Total</th>
					<?php if ( $is_editable ) : ?><th style="width:40px"></th><?php endif; ?>
				</tr>
			</thead>
			<tbody class="dh-items-body">
			<?php foreach ( $items as $idx => $it ) : ?>
				<tr data-idx="<?php echo esc_attr( $idx ); ?>">
					<td>
						<?php if ( $is_editable ) : ?>
							<span class="dh-qty-ctrl">
								<button type="button" class="dh-qty-btn" data-delta="-1">−</button>
								<span class="dh-qty-num"><?php echo esc_html( $it['qty'] ); ?></span>
								<button type="button" class="dh-qty-btn" data-delta="1">+</button>
							</span>
						<?php else : ?>
							<?php echo esc_html( $it['qty'] ); ?>x
						<?php endif; ?>
					</td>
					<td>
						<strong><?php echo esc_html( $it['name'] ); ?></strong>
						<?php foreach ( ( $it['extras'] ?? [] ) as $e ) : ?>
							<div class="dh-item-extra"><?php echo esc_html( $e['label'] ); ?><?php if ( ! empty( $e['price'] ) ) echo ' (+' . dh_money( $e['price'] ) . ')'; ?></div>
						<?php endforeach; ?>
						<?php if ( ! empty( $it['obs'] ) ) : ?>
							<div class="dh-item-obs">Obs: <?php echo esc_html( $it['obs'] ); ?></div>
						<?php endif; ?>
					</td>
					<td style="text-align:right"><strong class="dh-line-total"><?php echo dh_money( $it['total'] ); ?></strong></td>
					<?php if ( $is_editable ) : ?>
					<td class="dh-line-actions">
						<div class="dh-line-actions-inner">
							<button type="button" class="dh-icon-btn dh-edit-line" title="Editar extras e observação" data-item-id="<?php echo esc_attr( $it['id'] ); ?>">
								<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
							</button>
							<button type="button" class="dh-icon-btn dh-remove-line" title="Remover">
								<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-2 14a2 2 0 0 1-2 2H9a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2"/></svg>
							</button>
						</div>
					</td>
					<?php endif; ?>
				</tr>
				<?php if ( $is_editable ) : ?>
				<tr class="dh-line-edit-row" data-idx="<?php echo esc_attr( $idx ); ?>" style="display:none">
					<td colspan="4">
						<div class="dh-line-editor">
							<div class="dh-line-editor-loading">Carregando opções…</div>
						</div>
					</td>
				</tr>
				<?php endif; ?>
			<?php endforeach; ?>
			<?php if ( empty( $items ) ) : ?>
				<tr class="dh-empty-row"><td colspan="4"><em>Nenhum item.</em></td></tr>
			<?php endif; ?>
			</tbody>
		</table>

		<?php if ( $is_editable ) :
			$combobox_items = array_map( function ( $mi ) {
				return [
					'id'    => $mi->ID,
					'name'  => $mi->post_title,
					'price' => dh_item_price( $mi->ID ),
					'price_fmt' => dh_money( dh_item_price( $mi->ID ) ),
				];
			}, $menu_items );
			?>
			<div class="dh-add-item-row">
				<div class="dh-combobox" data-items='<?php echo esc_attr( wp_json_encode( $combobox_items ) ); ?>'>
					<div class="dh-combobox-field">
						<svg class="dh-combobox-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
						<input type="text" class="dh-combobox-input" placeholder="Digite pra buscar um item…" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false">
						<button type="button" class="dh-combobox-toggle" tabindex="-1" aria-label="Ver todos os itens">
							<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
						</button>
					</div>
					<ul class="dh-combobox-list" role="listbox" hidden></ul>
				</div>
				<button type="button" class="button button-primary dh-add-item-btn" disabled>Adicionar</button>
			</div>
		<?php endif; ?>

		<h3 class="dh-detail-heading">Pagamento e totais</h3>
		<div class="dh-payment-row">
			<div>
				<p>
					Forma: <strong><?php echo esc_html( $customer['payment'] ?? '-' ); ?></strong>
					<?php if ( ! empty( $customer['change'] ) ) : ?><br>Troco para: <?php echo esc_html( $customer['change'] ); ?><?php endif; ?>
				</p>
			</div>
			<div class="dh-totals-box">
				<div>Subtotal: <strong class="dh-sum-subtotal"><?php echo dh_money( $subtotal ); ?></strong></div>
				<div class="dh-fee-row">
					<span class="dh-fee-label">Taxa de entrega:</span>
					<?php if ( $is_editable ) : ?>
						<div class="dh-fee-view">
							<strong class="dh-fee-value"><?php echo dh_money( $fee ); ?></strong>
							<button type="button" class="dh-icon-btn dh-fee-edit-btn" title="Editar taxa">
								<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
							</button>
						</div>
						<div class="dh-fee-edit" style="display:none">
							<input type="number" step="0.01" min="0" class="dh-fee-input" value="<?php echo esc_attr( $fee ); ?>">
							<button type="button" class="button button-small dh-save-fee">Salvar</button>
							<button type="button" class="button button-small dh-cancel-fee" title="Cancelar">×</button>
						</div>
					<?php else : ?>
						<strong><?php echo dh_money( $fee ); ?></strong>
					<?php endif; ?>
				</div>
				<p class="dh-fee-warn" style="display:none">O cliente já viu a taxa anterior. Confirme com ele antes de alterar.</p>
				<div class="dh-sum-total-row">TOTAL: <strong class="dh-sum-total"><?php echo dh_money( $total ); ?></strong></div>
			</div>
		</div>

		<h3 class="dh-detail-heading">Observação do pedido</h3>
		<?php if ( $is_editable ) : ?>
			<textarea class="dh-order-notes widefat" rows="2" placeholder="Observação (visível no PDV)"><?php echo esc_textarea( $customer['notes'] ?? '' ); ?></textarea>
			<p><button type="button" class="button button-small dh-save-notes">Salvar observação</button></p>
		<?php else : ?>
			<p><?php echo esc_html( $customer['notes'] ?? '(nenhuma)' ); ?></p>
		<?php endif; ?>

	</div>

	<script>
	(function () {
		const wrap = document.getElementById('dh_order_detail').querySelector('.dh-order-detail');
		if (!wrap) return;
		const orderId = wrap.dataset.orderId;
		const nonce = wrap.dataset.nonce;

		function post(edit_action, data) {
			const body = new URLSearchParams(Object.assign({
				action: 'dh_order_edit_items',
				order_id: orderId,
				nonce,
				edit_action,
			}, data));
			return fetch(ajaxurl, { method: 'POST', body }).then(r => r.json());
		}
		function refreshTotals(t) {
			wrap.querySelector('.dh-sum-subtotal').textContent = t.subtotal_fmt;
			wrap.querySelector('.dh-sum-total').textContent = t.total_fmt;
		}
		function reload() { setTimeout(() => window.location.reload(), 300); }
		function handleErr(res) { alert('Falha: ' + (res.data?.message || 'erro')); }

		wrap.querySelectorAll('.dh-qty-btn').forEach(b => b.addEventListener('click', function () {
			const tr = b.closest('tr');
			const idx = tr.dataset.idx;
			const delta = parseInt(b.dataset.delta, 10);
			const curr = parseInt(tr.querySelector('.dh-qty-num').textContent, 10) || 1;
			const next = Math.max(1, curr + delta);
			if (next === curr) return;
			b.disabled = true;
			post('set_qty', { item_index: idx, qty: next }).then(res => {
				if (!res.success) { handleErr(res); b.disabled = false; return; }
				tr.querySelector('.dh-qty-num').textContent = next;
				tr.querySelector('.dh-line-total').textContent = res.data.line_total_fmt;
				refreshTotals(res.data.totals);
				b.disabled = false;
			});
		}));

		wrap.querySelectorAll('.dh-remove-line').forEach(b => b.addEventListener('click', function () {
			if (!confirm('Remover este item do pedido?')) return;
			post('remove_line', { item_index: b.closest('tr').dataset.idx }).then(res => {
				if (!res.success) return handleErr(res);
				reload();
			});
		}));

		// Editar extras e observação de uma linha
		wrap.querySelectorAll('.dh-edit-line').forEach(b => b.addEventListener('click', async function () {
			const tr = b.closest('tr');
			const idx = tr.dataset.idx;
			const itemId = b.dataset.itemId;
			const editRow = wrap.querySelector(`.dh-line-edit-row[data-idx="${idx}"]`);
			const container = editRow.querySelector('.dh-line-editor');

			// Toggle: se já está aberto, fecha
			if (editRow.style.display !== 'none') {
				editRow.style.display = 'none';
				return;
			}
			// Fecha os outros
			wrap.querySelectorAll('.dh-line-edit-row').forEach(r => r.style.display = 'none');

			editRow.style.display = '';
			container.innerHTML = '<div class="dh-line-editor-loading">Carregando opções…</div>';

			try {
				const [itemData, lineData] = await Promise.all([
					fetch('/delivery/wp-json/dh/v1/menu-item/' + itemId).then(r => r.json()),
					post('get_line', { item_index: idx }).then(r => r.data),
				]);
				renderLineEditor(container, idx, itemData, lineData);
			} catch (e) {
				container.innerHTML = '<div class="dh-line-editor-error">Erro ao carregar. Tente de novo.</div>';
			}
		}));

		function renderLineEditor(container, idx, item, line) {
			// Marca os já selecionados baseado em label+group
			const selectedSet = new Set((line.extras || []).map(e => e.group + '||' + e.label));

			let html = '<div class="dh-line-editor-inner">';
			if (!item.extras || item.extras.length === 0) {
				html += '<p class="description">Este item não tem grupos de extras.</p>';
			} else {
				item.extras.forEach((group, gi) => {
					html += `<div class="dh-line-group"><h4>${escapeHtml(group.name)} <small>${group.type === 'single' ? '(escolha única)' : '(múltipla)'}${group.required ? ' — obrigatório' : ''}</small></h4>`;
					(group.options || []).forEach((opt, oi) => {
						const key = group.name + '||' + opt.label;
						const checked = selectedSet.has(key) ? 'checked' : '';
						const type = group.type === 'single' ? 'radio' : 'checkbox';
						const name = group.type === 'single' ? `grp_${gi}` : `grp_${gi}_${oi}`;
						html += `<label class="dh-line-opt">
							<input type="${type}" name="${name}" ${checked}
								data-group="${escapeHtml(group.name)}"
								data-label="${escapeHtml(opt.label)}"
								data-price="${opt.price || 0}">
							<span>${escapeHtml(opt.label)}</span>
							${Number(opt.price) > 0 ? `<span class="dh-opt-price">+ ${money(opt.price)}</span>` : ''}
						</label>`;
					});
					html += '</div>';
				});
			}
			html += `<div class="dh-line-obs-row">
				<label><strong>Observação da linha</strong></label>
				<textarea class="dh-line-obs" rows="2" placeholder="Ex: sem cebola, bem passado">${escapeHtml(line.obs || '')}</textarea>
			</div>`;
			html += `<div class="dh-line-actions-row">
				<button type="button" class="button button-primary dh-save-line" data-idx="${idx}">Salvar alterações</button>
				<button type="button" class="button dh-cancel-line" data-idx="${idx}">Cancelar</button>
			</div>`;
			html += '</div>';
			container.innerHTML = html;

			container.querySelector('.dh-save-line').addEventListener('click', function () {
				const inputs = container.querySelectorAll('input[data-group]:checked');
				const extras = Array.from(inputs).map(i => ({
					group: i.dataset.group,
					label: i.dataset.label,
					price: parseFloat(i.dataset.price) || 0,
				}));
				const obs = container.querySelector('.dh-line-obs').value;
				this.disabled = true;
				post('set_line', { item_index: idx, extras: JSON.stringify(extras), obs })
					.then(res => { if (!res.success) return handleErr(res); reload(); });
			});
			container.querySelector('.dh-cancel-line').addEventListener('click', function () {
				container.closest('.dh-line-edit-row').style.display = 'none';
			});
		}

		function escapeHtml(s) {
			return String(s || '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
		}
		function money(v) {
			const n = Number(v || 0).toFixed(2);
			const [i, d] = n.split('.');
			return 'R$ ' + i.replace(/\B(?=(\d{3})+(?!\d))/g, '.') + ',' + d;
		}

		// Combobox de adicionar item — busca em tempo real + navegação por teclado
		const combo   = wrap.querySelector('.dh-combobox');
		const addBtn  = wrap.querySelector('.dh-add-item-btn');
		if (combo) {
			const items   = JSON.parse(combo.dataset.items || '[]');
			const input   = combo.querySelector('.dh-combobox-input');
			const list    = combo.querySelector('.dh-combobox-list');
			const toggle  = combo.querySelector('.dh-combobox-toggle');
			let selectedId = null;
			let hi = -1;      // highlighted index
			let filtered = items;

			const norm = s => (s || '').toString().toLowerCase()
				.normalize('NFD').replace(/[\u0300-\u036f]/g, '');

			function render(list_query) {
				const q = norm(list_query || '');
				filtered = q
					? items.filter(i => norm(i.name).includes(q))
					: items;

				list.innerHTML = '';
				if (filtered.length === 0) {
					list.innerHTML = '<li class="dh-combobox-empty">Nenhum item encontrado</li>';
				} else {
					filtered.forEach((it, i) => {
						const li = document.createElement('li');
						li.setAttribute('role', 'option');
						li.dataset.id = it.id;
						li.dataset.idx = i;
						if (i === hi) li.classList.add('is-active');
						li.innerHTML = `<span>${highlight(it.name, q)}</span><small>${it.price_fmt}</small>`;
						li.addEventListener('mousedown', e => {
							e.preventDefault();
							select(it);
						});
						list.appendChild(li);
					});
				}
			}
			function highlight(text, q) {
				if (!q) return escapeHtml(text);
				const ntext = norm(text);
				const idx = ntext.indexOf(q);
				if (idx === -1) return escapeHtml(text);
				return escapeHtml(text.slice(0, idx))
					+ '<mark>' + escapeHtml(text.slice(idx, idx + q.length)) + '</mark>'
					+ escapeHtml(text.slice(idx + q.length));
			}
			function open() {
				render(input.value);
				list.hidden = false;
				input.setAttribute('aria-expanded', 'true');
				combo.classList.add('is-open');
			}
			function close() {
				list.hidden = true;
				input.setAttribute('aria-expanded', 'false');
				combo.classList.remove('is-open');
				hi = -1;
			}
			function select(item) {
				selectedId = item.id;
				input.value = item.name;
				addBtn.disabled = false;
				close();
			}
			function clearSelection() {
				selectedId = null;
				addBtn.disabled = true;
			}

			input.addEventListener('input', () => {
				clearSelection();
				hi = -1;
				open();
			});
			input.addEventListener('focus', open);
			input.addEventListener('keydown', e => {
				if (e.key === 'ArrowDown') {
					e.preventDefault();
					if (list.hidden) open();
					hi = Math.min(filtered.length - 1, hi + 1);
					render(input.value);
					list.querySelector('.is-active')?.scrollIntoView({ block: 'nearest' });
				} else if (e.key === 'ArrowUp') {
					e.preventDefault();
					hi = Math.max(0, hi - 1);
					render(input.value);
					list.querySelector('.is-active')?.scrollIntoView({ block: 'nearest' });
				} else if (e.key === 'Enter') {
					e.preventDefault();
					if (hi >= 0 && filtered[hi]) select(filtered[hi]);
					else if (filtered.length === 1) select(filtered[0]);
				} else if (e.key === 'Escape') {
					close();
				}
			});
			toggle.addEventListener('click', e => {
				e.preventDefault();
				if (list.hidden) { input.focus(); open(); }
				else close();
			});
			document.addEventListener('click', e => {
				if (!combo.contains(e.target)) close();
			});

			addBtn.addEventListener('click', function () {
				if (!selectedId) return;
				addBtn.disabled = true;
				post('add_item', { item_id: selectedId }).then(res => {
					if (!res.success) { handleErr(res); addBtn.disabled = false; return; }
					reload();
				});
			});
		}

		// Taxa: toggle view/edit + salvar/cancelar
		const feeView = wrap.querySelector('.dh-fee-view');
		const feeEdit = wrap.querySelector('.dh-fee-edit');
		const feeWarn = wrap.querySelector('.dh-fee-warn');
		const feeEditBtn = wrap.querySelector('.dh-fee-edit-btn');
		const feeCancelBtn = wrap.querySelector('.dh-cancel-fee');
		const feeSaveBtn = wrap.querySelector('.dh-save-fee');
		const feeInput = wrap.querySelector('.dh-fee-input');
		const feeValueEl = wrap.querySelector('.dh-fee-value');

		let originalFee = feeInput ? feeInput.value : null;

		if (feeEditBtn) {
			feeEditBtn.addEventListener('click', () => {
				feeView.style.display = 'none';
				feeEdit.style.display = 'inline-flex';
				feeWarn.style.display = 'block';
				feeInput.focus();
				feeInput.select();
			});
		}
		if (feeCancelBtn) {
			feeCancelBtn.addEventListener('click', () => {
				feeInput.value = originalFee;
				feeEdit.style.display = 'none';
				feeWarn.style.display = 'none';
				feeView.style.display = 'inline-flex';
			});
		}
		if (feeSaveBtn) feeSaveBtn.addEventListener('click', function () {
			if (feeInput.value === originalFee) {
				feeCancelBtn.click();
				return;
			}
			feeSaveBtn.disabled = true;
			post('set_fee', { fee: feeInput.value }).then(res => {
				if (!res.success) { handleErr(res); feeSaveBtn.disabled = false; return; }
				refreshTotals(res.data.totals);
				originalFee = feeInput.value;
				feeValueEl.textContent = res.data.totals.total_fmt.startsWith('R$') ? formatFee(feeInput.value) : feeInput.value;
				feeSaveBtn.disabled = false;
				feeEdit.style.display = 'none';
				feeWarn.style.display = 'none';
				feeView.style.display = 'inline-flex';
			});
		});
		function formatFee(v) {
			const n = Number(v || 0).toFixed(2);
			const [i, d] = n.split('.');
			return 'R$ ' + i.replace(/\B(?=(\d{3})+(?!\d))/g, '.') + ',' + d;
		}

		const notesBtn = wrap.querySelector('.dh-save-notes');
		if (notesBtn) notesBtn.addEventListener('click', function () {
			notesBtn.disabled = true;
			post('set_notes', { notes: wrap.querySelector('.dh-order-notes').value }).then(res => {
				if (!res.success) { handleErr(res); notesBtn.disabled = false; return; }
				notesBtn.textContent = 'Salvo ✓';
				setTimeout(() => { notesBtn.textContent = 'Salvar observação'; notesBtn.disabled = false; }, 1500);
			});
		});

		const editCustBtn = wrap.querySelector('.dh-edit-customer');
		if (editCustBtn) {
			const custView = wrap.querySelector('.dh-customer-view');
			const custEdit = wrap.querySelector('.dh-customer-edit');
			editCustBtn.addEventListener('click', () => { custView.style.display = 'none'; custEdit.style.display = 'block'; });
			wrap.querySelector('.dh-cancel-customer').addEventListener('click', () => { custView.style.display = 'block'; custEdit.style.display = 'none'; });
			wrap.querySelector('.dh-save-customer').addEventListener('click', function () {
				this.disabled = true;
				post('set_customer', {
					name: wrap.querySelector('[name=edit_name]').value,
					phone: wrap.querySelector('[name=edit_phone]').value,
					address: wrap.querySelector('[name=edit_address]').value,
				}).then(res => { if (!res.success) return handleErr(res); reload(); });
			});
		}
	})();
	</script>
	<?php
}

/**
 * Metabox de Histórico de Alterações (audit log).
 */
add_action( 'add_meta_boxes', function () {
	add_meta_box( 'dh_order_history', 'Histórico de Alterações', 'dh_mb_order_history', 'dh_order', 'normal', 'low' );
} );

function dh_mb_order_history( $post ) {
	$log = get_post_meta( $post->ID, '_dh_change_log', true );
	if ( ! is_array( $log ) || empty( $log ) ) {
		echo '<p class="description">Nenhuma alteração além do pedido original.</p>';
		return;
	}
	$log = array_reverse( $log );
	?>
	<ul class="dh-audit-log">
		<?php foreach ( $log as $entry ) : ?>
			<li>
				<div class="dh-audit-time">
					<time><?php echo esc_html( date_i18n( 'd/m H:i', strtotime( $entry['at'] ) ) ); ?></time>
					<small>por <?php echo esc_html( $entry['user_name'] ); ?></small>
				</div>
				<div class="dh-audit-body">
					<span class="dh-audit-badge dh-audit-<?php echo esc_attr( $entry['action'] ); ?>"><?php echo esc_html( dh_audit_action_label( $entry['action'] ) ); ?></span>
					<span class="dh-audit-details"><?php echo esc_html( $entry['details'] ); ?></span>
				</div>
			</li>
		<?php endforeach; ?>
	</ul>
	<?php
}

function dh_audit_action_label( $action ) {
	$map = [
		'status_changed'        => 'Status',
		'qty_changed'           => 'Quantidade',
		'item_added'            => 'Item +',
		'item_removed'          => 'Item −',
		'item_edited'           => 'Item editado',
		'fee_changed'           => 'Taxa',
		'notes_changed'         => 'Obs',
		'customer_changed'      => 'Cliente',
		'confirmed_by_customer' => 'Confirmado pelo cliente',
		'reordered_from'        => 'Duplicado',
	];
	return $map[ $action ] ?? $action;
}

function dh_mb_order_actions( $post ) {
	$statuses = dh_order_statuses();
	$current  = $post->post_status;
	$customer = get_post_meta( $post->ID, '_dh_customer', true ) ?: [];
	$type     = $customer['type'] ?? 'delivery';
	$phone    = preg_replace( '/\D/', '', $customer['phone'] ?? '' );

	$next = dh_next_status( $current, $type );
	$prev = dh_prev_status( $current );

	$wa_reenvio = dh_build_whatsapp_url( $post->ID );
	$wa_cliente = $phone
		? 'https://wa.me/' . $phone . '?text=' . rawurlencode( 'Olá! Sobre o seu pedido #' . $post->ID . ': ' )
		: '';

	$nonce = wp_create_nonce( 'dh_order_action_' . $post->ID );

	// Ícones SVG por status (usado no botão principal)
	$next_icons = [
		'dh-preparando' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5z"/></svg>',
		'dh-pronto'     => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v3"/><path d="M3 8h18v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8z"/><line x1="10" y1="12" x2="14" y2="12"/></svg>',
		'dh-rota'       => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="5.5" cy="17.5" r="3.5"/><circle cx="18.5" cy="17.5" r="3.5"/><path d="M15 6h4l3 6h-3"/><path d="M9 17.5V13.5a2 2 0 0 1 2-2h4l3 6"/><path d="M9 17.5H5.5"/></svg>',
		'dh-entregue'   => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>',
	];
	$verbs = [
		'dh-preparando' => 'Começar preparo',
		'dh-pronto'     => 'Marcar como pronto',
		'dh-rota'       => 'Saiu para entrega',
		'dh-entregue'   => 'Marcar como entregue',
	];
	?>

	<div class="dh-actions-panel">

		<?php if ( $current === 'dh-cancelado' ) : ?>
			<div class="dh-status-state dh-state-cancelled">
				<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
				<span>Pedido cancelado</span>
			</div>
		<?php elseif ( $current === 'dh-entregue' ) : ?>
			<div class="dh-status-state dh-state-done">
				<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
				<span>Pedido concluído</span>
			</div>
		<?php elseif ( $next ) :
			$next_cfg = $statuses[ $next ];
			?>
			<div class="dh-actions-block">
				<label class="dh-actions-label">Próxima etapa</label>
				<button type="button" class="dh-btn-advance dh-btn-next"
					data-id="<?php echo esc_attr( $post->ID ); ?>"
					data-status="<?php echo esc_attr( $next ); ?>"
					data-nonce="<?php echo esc_attr( $nonce ); ?>"
					style="--action-color: <?php echo esc_attr( $next_cfg['color'] ); ?>">
					<span class="dh-btn-icon"><?php echo $next_icons[ $next ] ?? ''; ?></span>
					<span class="dh-btn-txt"><?php echo esc_html( $verbs[ $next ] ?? ( 'Marcar como ' . $next_cfg['label'] ) ); ?></span>
					<svg class="dh-btn-arrow" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
				</button>
			</div>
		<?php endif; ?>

		<?php if ( $current !== 'dh-cancelado' && $current !== 'dh-entregue' && ( $prev || true ) ) : ?>
			<div class="dh-actions-block">
				<label class="dh-actions-label">Corrigir</label>
				<div class="dh-actions-row">
					<?php if ( $prev ) : ?>
						<button type="button" class="dh-btn-mini dh-btn-back dh-btn-advance"
							data-id="<?php echo esc_attr( $post->ID ); ?>"
							data-status="<?php echo esc_attr( $prev ); ?>"
							data-nonce="<?php echo esc_attr( $nonce ); ?>"
							title="Voltar para <?php echo esc_attr( $statuses[ $prev ]['label'] ); ?>">
							<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5"/><polyline points="12 19 5 12 12 5"/></svg>
							<?php echo esc_html( $statuses[ $prev ]['label'] ); ?>
						</button>
					<?php endif; ?>
					<button type="button" class="dh-btn-mini dh-btn-cancel"
						data-id="<?php echo esc_attr( $post->ID ); ?>"
						data-status="dh-cancelado"
						data-nonce="<?php echo esc_attr( $nonce ); ?>">
						<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
					 	Cancelar
					</button>
				</div>
			</div>
		<?php endif; ?>

		<div class="dh-actions-divider"></div>

		<div class="dh-actions-block">
			<label class="dh-actions-label">Contato</label>

			<?php if ( $wa_cliente ) : ?>
				<a href="<?php echo esc_url( $wa_cliente ); ?>" target="_blank" class="dh-contact-btn dh-contact-primary">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/></svg>
					<div>
						<strong>Falar com <?php echo esc_html( explode( ' ', trim( $customer['name'] ?? 'cliente' ) )[0] ); ?></strong>
						<small><?php echo esc_html( preg_replace( '/(\d{2})(\d{5})(\d{4})/', '($1) $2-$3', $phone ) ); ?></small>
					</div>
				</a>
			<?php endif; ?>

			<a href="<?php echo esc_url( $wa_reenvio ); ?>" target="_blank" class="dh-contact-btn dh-contact-secondary">
				<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/></svg>
				Reenviar pedido pro WhatsApp da loja
			</a>
		</div>

	</div>

	<script>
	(function () {
		document.querySelectorAll('.dh-btn-advance, .dh-btn-cancel').forEach(btn => {
			btn.addEventListener('click', function () {
				const id = btn.dataset.id;
				const status = btn.dataset.status;
				const nonce = btn.dataset.nonce;
				if (btn.classList.contains('dh-btn-cancel')) {
					if (!confirm('Cancelar este pedido? Essa ação avisa o cliente e não pode ser desfeita facilmente.')) return;
				}
				btn.disabled = true;
				btn.style.opacity = '.6';
				fetch(ajaxurl, {
					method: 'POST',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
					body: new URLSearchParams({ action: 'dh_order_action', id, status, nonce }),
				})
				.then(r => r.json())
				.then(data => {
					if (data.success) window.location.reload();
					else { alert('Falha: ' + (data.data?.message || 'erro')); btn.disabled = false; btn.style.opacity = ''; }
				})
				.catch(() => { alert('Erro de conexão'); btn.disabled = false; btn.style.opacity = ''; });
			});
		});
	})();
	</script>
	<?php
}

/**
 * Metabox de timeline (histórico de status com horário) — coluna principal, no topo.
 */
add_action( 'add_meta_boxes', function () {
	add_meta_box( 'dh_order_timeline', 'Linha do Tempo', 'dh_mb_order_timeline', 'dh_order', 'normal', 'high' );
} );

function dh_mb_order_timeline( $post ) {
	$statuses = dh_order_statuses();
	$customer = get_post_meta( $post->ID, '_dh_customer', true ) ?: [];
	$type     = $customer['type'] ?? 'delivery';

	$history  = get_post_meta( $post->ID, '_dh_status_history', true );
	if ( ! is_array( $history ) ) $history = [];

	// Pedidos antigos sem histórico — sintetiza o marco inicial pelo post_date
	if ( empty( $history ) ) {
		$history[] = [ 'status' => $post->post_status, 'at' => $post->post_date ];
	}

	// Mapa {status => timestamp} — se um status aparecer múltiplas vezes, guarda o mais recente
	$reached = [];
	foreach ( $history as $h ) {
		$reached[ $h['status'] ] = $h['at'];
	}

	$is_cancelled = $post->post_status === 'dh-cancelado';
	$current      = $post->post_status;

	// Steps a exibir: fluxo linear filtrado por tipo (retirada/local não passa por "em rota")
	$steps = dh_status_flow();
	if ( $type !== 'delivery' ) {
		$steps = array_values( array_diff( $steps, [ 'dh-rota' ] ) );
	}

	$current_idx = array_search( $current, $steps, true );
	?>

	<?php if ( $is_cancelled ) : ?>
		<div class="dh-tl-cancelled">
			<strong>Pedido cancelado</strong>
			<?php if ( isset( $reached['dh-cancelado'] ) ) : ?>
				<small><?php echo esc_html( date_i18n( 'd/m \à\s H:i', strtotime( $reached['dh-cancelado'] ) ) ); ?></small>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<ol class="dh-adm-timeline">
		<?php foreach ( $steps as $i => $slug ) :
			$cfg  = $statuses[ $slug ];
			$done = isset( $reached[ $slug ] ) || ( $current_idx !== false && $i < $current_idx );
			$active = ! $is_cancelled && $slug === $current;
			$state = $active ? 'active' : ( $done ? 'done' : 'pending' );
			$ts   = $reached[ $slug ] ?? '';
			?>
			<li class="dh-tl-step is-<?php echo esc_attr( $state ); ?>" data-color="<?php echo esc_attr( $cfg['color'] ); ?>">
				<span class="dh-tl-dot" <?php if ( $done || $active ) : ?>style="background: <?php echo esc_attr( $cfg['color'] ); ?>; box-shadow: 0 0 0 3px #fff, 0 0 0 4px <?php echo esc_attr( $cfg['color'] ); ?>"<?php endif; ?>>
					<?php if ( $done && ! $active ) echo '<span class="dh-tl-check">✓</span>'; ?>
				</span>
				<div class="dh-tl-body">
					<strong><?php echo esc_html( $cfg['label'] ); ?></strong>
					<small>
						<?php if ( $ts ) : ?>
							<?php echo esc_html( date_i18n( 'd/m \à\s H:i', strtotime( $ts ) ) ); ?>
						<?php elseif ( $active ) : ?>
							<em>em andamento…</em>
						<?php else : ?>
							<em>aguardando</em>
						<?php endif; ?>
					</small>
				</div>
			</li>
		<?php endforeach; ?>
	</ol>
	<?php
}
