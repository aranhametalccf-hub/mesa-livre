<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Página de configurações da loja no admin.
 */
add_action( 'admin_menu', function () {
	add_menu_page(
		'Configurações da Loja',
		'Config. da Loja',
		'manage_options',
		'dh-settings',
		'dh_settings_page',
		'dashicons-store',
		3
	);
} );

add_action( 'admin_init', function () {
	register_setting( 'dh_settings_group', 'dh_settings', [
		'type'    => 'array',
		'default' => dh_default_settings(),
		'sanitize_callback' => 'dh_sanitize_settings',
	] );
} );

function dh_default_settings() {
	return [
		'store_name'        => get_bloginfo( 'name' ),
		'whatsapp'          => '',
		'cover_image'       => '',
		'logo'              => '',
		'primary_color'     => '#EA1D2C',
		'delivery_fee'      => 0,
		'min_order'         => 0,
		'accept_delivery'   => 1,
		'accept_pickup'     => 1,
		'accept_dinein'     => 0,
		'payment_methods'   => [ 'PIX', 'Dinheiro', 'Cartão de crédito', 'Cartão de débito' ],
		'address'           => '',
		'hours'             => '',
		'greeting'          => 'Olá! Peça pelo cardápio digital.',
		// Metas de SLA em minutos
		'sla_total'         => 45,
		'sla_accept'        => 5,
		'sla_prep'          => 25,
		'sla_dispatch'      => 10,
		'sla_delivery'      => 20,
		'sla_pickup'        => 15,
		'hours_by_day'      => [],
		'hours_exceptions'  => [],
	];
}

function dh_sanitize_settings( $input ) {
	$out = [];
	$out['store_name']      = sanitize_text_field( $input['store_name'] ?? '' );
	$out['whatsapp']        = preg_replace( '/\D/', '', $input['whatsapp'] ?? '' );
	$out['cover_image']     = esc_url_raw( $input['cover_image'] ?? '' );
	$out['logo']            = esc_url_raw( $input['logo'] ?? '' );
	$out['primary_color']   = sanitize_hex_color( $input['primary_color'] ?? '#EA1D2C' );
	$out['delivery_fee']    = floatval( str_replace( ',', '.', $input['delivery_fee'] ?? 0 ) );
	$out['min_order']       = floatval( str_replace( ',', '.', $input['min_order'] ?? 0 ) );
	$out['accept_delivery'] = ! empty( $input['accept_delivery'] ) ? 1 : 0;
	$out['accept_pickup']   = ! empty( $input['accept_pickup'] ) ? 1 : 0;
	$out['accept_dinein']   = ! empty( $input['accept_dinein'] ) ? 1 : 0;
	$out['address']         = sanitize_textarea_field( $input['address'] ?? '' );
	$out['hours']           = sanitize_textarea_field( $input['hours'] ?? '' );
	$out['greeting']        = sanitize_text_field( $input['greeting'] ?? '' );
	$methods = $input['payment_methods'] ?? '';
	if ( is_string( $methods ) ) {
		$methods = array_map( 'trim', explode( ',', $methods ) );
	}
	$out['payment_methods'] = array_values( array_filter( array_map( 'sanitize_text_field', (array) $methods ) ) );

	foreach ( [ 'sla_total', 'sla_accept', 'sla_prep', 'sla_dispatch', 'sla_delivery', 'sla_pickup' ] as $k ) {
		$out[ $k ] = max( 1, intval( $input[ $k ] ?? 0 ) );
	}
	// Delega horários e exceções pro módulo store-hours
	$out = apply_filters( 'dh_sanitize_settings_ext', $out, $input );
	return $out;
}

function dh_get_settings() {
	$s = get_option( 'dh_settings', [] );
	return wp_parse_args( $s, dh_default_settings() );
}

function dh_settings_page() {
	$s = dh_get_settings();
	?>
	<div class="wrap">
		<h1>Configurações da Loja</h1>
		<form method="post" action="options.php">
			<?php settings_fields( 'dh_settings_group' ); ?>
			<table class="form-table">
				<tr>
					<th><label>Nome da Loja</label></th>
					<td><input type="text" name="dh_settings[store_name]" value="<?php echo esc_attr( $s['store_name'] ); ?>" class="regular-text"></td>
				</tr>
				<tr>
					<th><label>WhatsApp (só números, com DDI+DDD)</label></th>
					<td>
						<input type="text" name="dh_settings[whatsapp]" value="<?php echo esc_attr( $s['whatsapp'] ); ?>" placeholder="5511999999999" class="regular-text">
						<p class="description">Número que recebe os pedidos. Ex: 5511987654321</p>
					</td>
				</tr>
				<tr>
					<th><label>Logo (URL)</label></th>
					<td><input type="text" name="dh_settings[logo]" value="<?php echo esc_attr( $s['logo'] ); ?>" class="large-text"></td>
				</tr>
				<tr>
					<th><label>Imagem de capa (URL)</label></th>
					<td><input type="text" name="dh_settings[cover_image]" value="<?php echo esc_attr( $s['cover_image'] ); ?>" class="large-text"></td>
				</tr>
				<tr>
					<th><label>Cor primária</label></th>
					<td><input type="color" name="dh_settings[primary_color]" value="<?php echo esc_attr( $s['primary_color'] ); ?>"></td>
				</tr>
				<tr>
					<th><label>Endereço</label></th>
					<td><textarea name="dh_settings[address]" rows="2" class="large-text"><?php echo esc_textarea( $s['address'] ); ?></textarea></td>
				</tr>
				<tr>
					<th><label>Horário de funcionamento</label></th>
					<td><textarea name="dh_settings[hours]" rows="2" class="large-text" placeholder="Seg a Sex: 18h às 23h"><?php echo esc_textarea( $s['hours'] ); ?></textarea></td>
				</tr>
				<tr>
					<th><label>Taxa de entrega padrão (R$)</label></th>
					<td><input type="number" step="0.01" min="0" name="dh_settings[delivery_fee]" value="<?php echo esc_attr( $s['delivery_fee'] ); ?>"></td>
				</tr>
				<tr>
					<th><label>Pedido mínimo (R$)</label></th>
					<td><input type="number" step="0.01" min="0" name="dh_settings[min_order]" value="<?php echo esc_attr( $s['min_order'] ); ?>"></td>
				</tr>
				<tr>
					<th>Formas de recebimento</th>
					<td>
						<label><input type="checkbox" name="dh_settings[accept_delivery]" value="1" <?php checked( $s['accept_delivery'], 1 ); ?>> Entrega</label><br>
						<label><input type="checkbox" name="dh_settings[accept_pickup]" value="1" <?php checked( $s['accept_pickup'], 1 ); ?>> Retirada no balcão</label><br>
						<label><input type="checkbox" name="dh_settings[accept_dinein]" value="1" <?php checked( $s['accept_dinein'], 1 ); ?>> Consumir no local</label>
					</td>
				</tr>
				<tr>
					<th><label>Formas de pagamento</label></th>
					<td>
						<input type="text" name="dh_settings[payment_methods]" value="<?php echo esc_attr( implode( ', ', $s['payment_methods'] ) ); ?>" class="large-text">
						<p class="description">Separe por vírgula. Ex: PIX, Dinheiro, Cartão de crédito</p>
					</td>
				</tr>
				<tr>
					<th><label>Saudação no WhatsApp</label></th>
					<td><input type="text" name="dh_settings[greeting]" value="<?php echo esc_attr( $s['greeting'] ); ?>" class="large-text"></td>
				</tr>
			</table>

			<h2 style="margin-top:40px">Horário de funcionamento</h2>
			<p class="description">
				Defina os horários por dia da semana. Você pode ter múltiplas faixas (ex: almoço 11h-14h + noite 18h-23h).
				Fora desses horários o cardápio bloqueia novos pedidos automaticamente.
			</p>
			<div class="dh-hours-editor" data-hours='<?php echo esc_attr( wp_json_encode( dh_get_hours() ) ); ?>'></div>

			<h2 style="margin-top:30px">Exceções (feriados e datas especiais)</h2>
			<p class="description">Fechar em um dia específico ou aplicar horário diferente (ex: Natal fechado, 31/12 até 20h).</p>
			<div class="dh-exceptions-editor" data-exc='<?php echo esc_attr( wp_json_encode( dh_get_hours_exceptions() ) ); ?>'></div>

			<h2 style="margin-top:40px">Metas de tempo (SLA)</h2>
			<p class="description">
				Meta em minutos por etapa. Cards do PDV ganham cor amarela quando passam de 70% da meta e vermelha quando estouram.
				As médias reais do dia são calculadas automaticamente e comparadas com essas metas.
			</p>
			<table class="form-table">
				<tr>
					<th><label>Ciclo total (Novo → Entregue)</label></th>
					<td><input type="number" min="1" name="dh_settings[sla_total]" value="<?php echo esc_attr( $s['sla_total'] ); ?>" style="width:100px"> min</td>
				</tr>
				<tr>
					<th><label>Aceite (Novo → Preparando)</label></th>
					<td><input type="number" min="1" name="dh_settings[sla_accept]" value="<?php echo esc_attr( $s['sla_accept'] ); ?>" style="width:100px"> min</td>
				</tr>
				<tr>
					<th><label>Preparo (Preparando → Pronto)</label></th>
					<td><input type="number" min="1" name="dh_settings[sla_prep]" value="<?php echo esc_attr( $s['sla_prep'] ); ?>" style="width:100px"> min</td>
				</tr>
				<tr>
					<th><label>Expedição (Pronto → Em rota)</label></th>
					<td><input type="number" min="1" name="dh_settings[sla_dispatch]" value="<?php echo esc_attr( $s['sla_dispatch'] ); ?>" style="width:100px"> min</td>
				</tr>
				<tr>
					<th><label>Entrega (Em rota → Entregue)</label></th>
					<td><input type="number" min="1" name="dh_settings[sla_delivery]" value="<?php echo esc_attr( $s['sla_delivery'] ); ?>" style="width:100px"> min</td>
				</tr>
				<tr>
					<th><label>Retirada (Pronto → Entregue, pickup/local)</label></th>
					<td><input type="number" min="1" name="dh_settings[sla_pickup]" value="<?php echo esc_attr( $s['sla_pickup'] ); ?>" style="width:100px"> min</td>
				</tr>
			</table>
			<?php submit_button( 'Salvar' ); ?>
		</form>
	</div>

	<script>
	(function () {
		const DAYS = <?php echo wp_json_encode( dh_days_of_week() ); ?>;

		function initHours() {
			const root = document.querySelector('.dh-hours-editor');
			if (!root) return;
			let data = {};
			try { data = JSON.parse(root.dataset.hours) || {}; } catch (e) {}

			function render() {
				root.innerHTML = '';
				Object.entries(DAYS).forEach(([key, label]) => {
					const slots = data[key] || [];
					const row = document.createElement('div');
					row.className = 'dh-day-row';
					row.innerHTML = `
						<div class="dh-day-label">${label}</div>
						<div class="dh-day-slots"></div>
						<button type="button" class="dh-add-slot button">+ Adicionar faixa</button>
					`;
					const slotsBox = row.querySelector('.dh-day-slots');
					if (slots.length === 0) {
						const empty = document.createElement('span');
						empty.className = 'dh-day-empty';
						empty.textContent = 'Fechado';
						slotsBox.appendChild(empty);
					}
					slots.forEach((slot, i) => {
						const s = document.createElement('div');
						s.className = 'dh-slot';
						s.innerHTML = `
							<input type="time" name="dh_settings[hours_by_day][${key}][${i}][open]" value="${slot.open || ''}">
							<span>até</span>
							<input type="time" name="dh_settings[hours_by_day][${key}][${i}][close]" value="${slot.close || ''}">
							<button type="button" class="dh-rm-slot button-link">Remover</button>
						`;
						s.querySelector('.dh-rm-slot').addEventListener('click', () => {
							data[key].splice(i, 1); render();
						});
						slotsBox.appendChild(s);
					});
					row.querySelector('.dh-add-slot').addEventListener('click', () => {
						data[key] = data[key] || [];
						data[key].push({ open: '18:00', close: '23:00' });
						render();
					});
					root.appendChild(row);
				});
			}
			render();
		}

		function initExceptions() {
			const root = document.querySelector('.dh-exceptions-editor');
			if (!root) return;
			let raw = {};
			try { raw = JSON.parse(root.dataset.exc) || {}; } catch (e) {}
			let data = Object.entries(raw).map(([date, cfg]) => Object.assign({ date }, cfg));

			function render() {
				root.innerHTML = '';
				if (data.length === 0) {
					const empty = document.createElement('p');
					empty.className = 'description';
					empty.style.margin = '8px 0';
					empty.textContent = 'Nenhuma exceção cadastrada.';
					root.appendChild(empty);
				}
				data.forEach((row, i) => {
					const r = document.createElement('div');
					r.className = 'dh-exc-row';
					r.innerHTML = `
						<input type="date" name="dh_settings[hours_exceptions][${i}][date]" value="${row.date || ''}">
						<label class="dh-exc-closed">
							<input type="checkbox" name="dh_settings[hours_exceptions][${i}][closed]" value="1" ${row.closed ? 'checked' : ''}>
							Fechado o dia todo
						</label>
						<div class="dh-exc-hours" style="${row.closed ? 'opacity:.4' : ''}">
							<input type="time" name="dh_settings[hours_exceptions][${i}][open]" value="${row.open || ''}" ${row.closed ? 'disabled' : ''}>
							<span>até</span>
							<input type="time" name="dh_settings[hours_exceptions][${i}][close]" value="${row.close || ''}" ${row.closed ? 'disabled' : ''}>
						</div>
						<input type="text" name="dh_settings[hours_exceptions][${i}][note]" value="${(row.note || '').replace(/"/g,'&quot;')}" placeholder="Nota (opcional)" style="flex:1">
						<button type="button" class="dh-rm-exc button-link">×</button>
					`;
					r.querySelector('.dh-exc-closed input').addEventListener('change', (e) => {
						data[i].closed = e.target.checked;
						render();
					});
					r.querySelector('.dh-rm-exc').addEventListener('click', () => {
						data.splice(i, 1); render();
					});
					root.appendChild(r);
				});
				const addBtn = document.createElement('button');
				addBtn.type = 'button';
				addBtn.className = 'button dh-add-exc';
				addBtn.textContent = '+ Adicionar exceção';
				addBtn.addEventListener('click', () => {
					data.push({ date: '', closed: false, open: '', close: '', note: '' });
					render();
				});
				root.appendChild(addBtn);
			}
			render();
		}

		document.addEventListener('DOMContentLoaded', () => { initHours(); initExceptions(); });
	})();
	</script>

	<style>
		.dh-hours-editor { background: white; border: 1px solid #e5e7eb; border-radius: 8px; padding: 6px 12px; }
		.dh-day-row { display: flex; align-items: center; gap: 12px; padding: 10px 4px; border-bottom: 1px solid #f3f4f6; }
		.dh-day-row:last-child { border-bottom: none; }
		.dh-day-label { width: 90px; font-weight: 700; color: #1f2937; }
		.dh-day-slots { flex: 1; display: flex; flex-wrap: wrap; gap: 6px; align-items: center; }
		.dh-slot { display: inline-flex; gap: 6px; align-items: center; background: #f9fafb; padding: 4px 8px; border-radius: 6px; }
		.dh-slot input[type=time] { padding: 2px 6px; border: 1px solid #d1d5db; border-radius: 4px; font-size: 13px; }
		.dh-slot span { color: #6b7280; font-size: 12px; }
		.dh-rm-slot { color: #b91c1c !important; font-size: 12px !important; }
		.dh-day-empty { color: #9ca3af; font-style: italic; font-size: 13px; }
		.dh-add-slot { padding: 3px 10px !important; height: auto !important; font-size: 12px !important; }

		.dh-exceptions-editor { background: white; border: 1px solid #e5e7eb; border-radius: 8px; padding: 6px 12px; }
		.dh-exc-row { display: flex; gap: 10px; align-items: center; padding: 10px 4px; border-bottom: 1px solid #f3f4f6; flex-wrap: wrap; }
		.dh-exc-row:last-child { border-bottom: none; }
		.dh-exc-row input[type=date], .dh-exc-row input[type=time] { padding: 4px 8px; border: 1px solid #d1d5db; border-radius: 4px; font-size: 13px; }
		.dh-exc-row input[type=text] { padding: 4px 8px; border: 1px solid #d1d5db; border-radius: 4px; font-size: 13px; }
		.dh-exc-closed { display: inline-flex; gap: 4px; align-items: center; font-size: 12px; color: #4b5563; }
		.dh-exc-hours { display: inline-flex; gap: 6px; align-items: center; }
		.dh-exc-hours span { color: #6b7280; font-size: 12px; }
		.dh-rm-exc { color: #b91c1c !important; font-size: 16px !important; font-weight: 700 !important; padding: 4px 8px !important; }
		.dh-add-exc { margin: 8px 0 !important; padding: 6px 14px !important; height: auto !important; font-size: 12px !important; }
	</style>
	<?php
}
