<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Meta boxes do item do cardápio (preço, promo, adicionais, order bump).
 */
add_action( 'add_meta_boxes', function () {
	add_meta_box( 'dh_item_price', 'Preço e Promoção', 'dh_mb_item_price', 'menu_item', 'side', 'high' );
	add_meta_box( 'dh_item_extras', 'Adicionais e Opções', 'dh_mb_item_extras', 'menu_item', 'normal', 'default' );
	add_meta_box( 'dh_item_bump', 'Order Bump', 'dh_mb_item_bump', 'menu_item', 'side', 'default' );
} );

function dh_mb_item_price( $post ) {
	$price   = get_post_meta( $post->ID, '_dh_price', true );
	$promo   = get_post_meta( $post->ID, '_dh_promo_price', true );
	$soldout = get_post_meta( $post->ID, '_dh_soldout', true );
	wp_nonce_field( 'dh_save_item', 'dh_item_nonce' );
	?>
	<p>
		<label><strong>Preço (R$)</strong></label>
		<input type="number" step="0.01" min="0" name="_dh_price" value="<?php echo esc_attr( $price ); ?>" class="widefat" placeholder="0,00">
	</p>
	<p>
		<label><strong>Preço promocional (R$)</strong></label>
		<input type="number" step="0.01" min="0" name="_dh_promo_price" value="<?php echo esc_attr( $promo ); ?>" class="widefat" placeholder="opcional">
	</p>
	<p>
		<label><input type="checkbox" name="_dh_soldout" value="1" <?php checked( $soldout, '1' ); ?>> Esgotado</label>
	</p>
	<?php
}

function dh_mb_item_extras( $post ) {
	$extras = get_post_meta( $post->ID, '_dh_extras', true );
	$extras = is_array( $extras ) ? $extras : [];
	?>
	<p class="dh-ex-hint">Crie grupos como <strong>Tamanho</strong>, <strong>Bordas</strong>, <strong>Ingredientes extras</strong>. O cliente escolhe ao adicionar o item ao carrinho.</p>
	<div id="dh-extras-root" data-extras='<?php echo esc_attr( wp_json_encode( $extras ) ); ?>'></div>
	<script>
	(function () {
		const root = document.getElementById('dh-extras-root');
		let data = [];
		try { data = JSON.parse(root.dataset.extras) || []; } catch (e) { data = []; }

		const icons = {
			drag:   '<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><circle cx="9" cy="6" r="1.5"/><circle cx="9" cy="12" r="1.5"/><circle cx="9" cy="18" r="1.5"/><circle cx="15" cy="6" r="1.5"/><circle cx="15" cy="12" r="1.5"/><circle cx="15" cy="18" r="1.5"/></svg>',
			trash:  '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-2 14a2 2 0 0 1-2 2H9a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2"/></svg>',
			close:  '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>',
			plus:   '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>',
			addGrp: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>',
		};

		function render() {
			root.innerHTML = '';

			if (data.length === 0) {
				const empty = document.createElement('div');
				empty.className = 'dh-ex-empty';
				empty.innerHTML = `
					<svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20"/><path d="M2 12h20"/></svg>
					<strong>Nenhum grupo ainda</strong>
					<span>Adicione um grupo pra deixar o cliente escolher tamanho, borda, extras…</span>
				`;
				root.appendChild(empty);
			}

			data.forEach((group, gi) => {
				const card = document.createElement('div');
				card.className = 'dh-ex-group';
				card.innerHTML = `
					<header class="dh-ex-group-head">
						<span class="dh-ex-drag" title="Arrastar">${icons.drag}</span>
						<input type="text" class="dh-ex-name" placeholder="Nome do grupo (ex: Tamanho)" value="${escape(group.name||'')}" data-k="name">
						<div class="dh-ex-head-actions">
							<button type="button" class="dh-icon-btn dh-ex-rm-group" data-act="rm-group" title="Remover grupo">${icons.trash}</button>
						</div>
					</header>

					<div class="dh-ex-controls">
						<label class="dh-ex-ctrl">
							<span>Tipo</span>
							<select data-k="type">
								<option value="single" ${group.type==='single'?'selected':''}>Escolha única</option>
								<option value="multi" ${group.type==='multi'?'selected':''}>Múltipla escolha</option>
							</select>
						</label>
						<label class="dh-ex-ctrl dh-ex-max ${group.type==='multi'?'':'is-hidden'}">
							<span>Máx. escolhas</span>
							<input type="number" min="0" placeholder="sem limite" value="${group.max||''}" data-k="max">
						</label>
						<label class="dh-ex-toggle">
							<input type="checkbox" data-k="required" ${group.required?'checked':''}>
							<span class="dh-ex-switch"></span>
							<span class="dh-ex-toggle-label">Obrigatório</span>
						</label>
					</div>

					<div class="dh-ex-options-wrap">
						<div class="dh-ex-options-head">
							<span>OPÇÕES</span>
							<small>Cada linha vira uma escolha pro cliente</small>
						</div>
						<div class="dh-options"></div>
						<button type="button" class="dh-btn-add-opt" data-act="add-opt">${icons.plus} Adicionar opção</button>
					</div>
				`;

				const optsBox = card.querySelector('.dh-options');
				if (!group.options || group.options.length === 0) {
					const empty = document.createElement('div');
					empty.className = 'dh-ex-opt-empty';
					empty.textContent = 'Sem opções ainda';
					optsBox.appendChild(empty);
				}

				(group.options||[]).forEach((opt, oi) => {
					const row = document.createElement('div');
					row.className = 'dh-ex-opt-row';
					row.innerHTML = `
						<input type="text" class="dh-ex-opt-label" placeholder="Nome da opção" value="${escape(opt.label||'')}" data-ok="label">
						<div class="dh-ex-opt-price">
							<span class="dh-ex-plus">+</span>
							<span class="dh-ex-currency">R$</span>
							<input type="number" step="0.01" min="0" placeholder="0,00" value="${opt.price||''}" data-ok="price">
						</div>
						<button type="button" class="dh-icon-btn dh-ex-rm-opt" data-act="rm-opt" data-oi="${oi}" title="Remover opção">${icons.close}</button>
					`;
					optsBox.appendChild(row);
				});

				root.appendChild(card);

				// Bindings
				card.querySelectorAll('[data-k]').forEach(el => {
					el.addEventListener('input', () => {
						const k = el.dataset.k;
						data[gi][k] = el.type === 'checkbox' ? el.checked : el.value;
						if (k === 'type') {
							card.querySelector('.dh-ex-max').classList.toggle('is-hidden', el.value !== 'multi');
						}
						sync();
					});
				});
				card.querySelector('[data-act="rm-group"]').addEventListener('click', () => {
					if (!confirm('Remover este grupo inteiro?')) return;
					data.splice(gi,1); render();
				});
				card.querySelector('[data-act="add-opt"]').addEventListener('click', () => {
					data[gi].options = data[gi].options || [];
					data[gi].options.push({label:'', price:''}); render();
				});
				card.querySelectorAll('[data-ok]').forEach(el => {
					el.addEventListener('input', (e) => {
						const row = e.target.closest('.dh-ex-opt-row');
						const oi = Array.from(row.parentNode.children).indexOf(row);
						data[gi].options[oi][el.dataset.ok] = el.value;
						sync();
					});
				});
				card.querySelectorAll('[data-act="rm-opt"]').forEach(btn => {
					btn.addEventListener('click', () => {
						data[gi].options.splice(parseInt(btn.dataset.oi),1); render();
					});
				});
			});

			// Botão adicionar grupo (dropzone tracejada)
			const addBtn = document.createElement('button');
			addBtn.type = 'button';
			addBtn.className = 'dh-btn-add-group';
			addBtn.innerHTML = `${icons.addGrp} <span>Adicionar novo grupo</span>`;
			addBtn.addEventListener('click', () => {
				data.push({name:'', type:'single', required:false, max:'', options:[{label:'',price:''}]});
				render();
			});
			root.appendChild(addBtn);
			sync();
		}

		function sync() {
			let input = document.getElementById('dh-extras-input');
			if (!input) {
				input = document.createElement('input');
				input.type = 'hidden';
				input.name = '_dh_extras';
				input.id = 'dh-extras-input';
				root.parentNode.appendChild(input);
			}
			input.value = JSON.stringify(data);
		}
		function escape(s) { return String(s).replace(/"/g,'&quot;'); }
		render();
	})();
	</script>
	<?php
}

function dh_mb_item_bump( $post ) {
	$is_bump = get_post_meta( $post->ID, '_dh_is_bump', true );
	?>
	<p>
		<label>
			<input type="checkbox" name="_dh_is_bump" value="1" <?php checked( $is_bump, '1' ); ?>>
			Oferecer como <strong>Order Bump</strong> no carrinho
		</label>
	</p>
	<p class="description">Aparece antes do checkout como sugestão ("Que tal adicionar…").</p>
	<?php
}

add_action( 'save_post_menu_item', function ( $post_id ) {
	if ( ! isset( $_POST['dh_item_nonce'] ) || ! wp_verify_nonce( $_POST['dh_item_nonce'], 'dh_save_item' ) ) return;
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
	if ( ! current_user_can( 'edit_post', $post_id ) ) return;

	update_post_meta( $post_id, '_dh_price', floatval( str_replace( ',', '.', $_POST['_dh_price'] ?? 0 ) ) );
	update_post_meta( $post_id, '_dh_promo_price', $_POST['_dh_promo_price'] !== '' ? floatval( str_replace( ',', '.', $_POST['_dh_promo_price'] ) ) : '' );
	update_post_meta( $post_id, '_dh_soldout', isset( $_POST['_dh_soldout'] ) ? '1' : '' );
	update_post_meta( $post_id, '_dh_is_bump', isset( $_POST['_dh_is_bump'] ) ? '1' : '' );

	$extras_raw = wp_unslash( $_POST['_dh_extras'] ?? '[]' );
	$extras = json_decode( $extras_raw, true );
	update_post_meta( $post_id, '_dh_extras', is_array( $extras ) ? $extras : [] );
} );
