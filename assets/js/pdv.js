/**
 * Delivery Hub — pdv.js
 * Drag-drop entre colunas do Kanban + auto-refresh.
 */

(function ($) {
	document.addEventListener('DOMContentLoaded', function () {
		const lists = document.querySelectorAll('.dh-kanban-list');
		if (!lists.length || typeof Sortable === 'undefined') return;

		// Lista de entregadores ativos (do JSON impresso pelo PHP)
		let ACTIVE_DELIVERIES = [];
		const drvJson = document.getElementById('dh-active-deliveries');
		if (drvJson) { try { ACTIVE_DELIVERIES = JSON.parse(drvJson.textContent); } catch (e) {} }

		lists.forEach(list => {
			new Sortable(list, {
				group: 'dh-orders',
				animation: 180,
				ghostClass: 'dh-drop-ghost',
				onEnd: function (evt) {
					const id = evt.item.dataset.id;
					const status = evt.to.dataset.status;
					const originStatus = evt.from.dataset.status;

					if (status === originStatus) return;

					// Se movendo pra 'em rota' e temos entregadores cadastrados, pede pra escolher.
					if (status === 'dh-rota' && ACTIVE_DELIVERIES.length > 0) {
						chooseDelivery(id, (deliveryId) => {
							if (deliveryId === null) {
								// cancelou → volta o card
								evt.from.appendChild(evt.item);
								return;
							}
							fetch(DH_PDV.ajaxUrl, {
								method: 'POST',
								headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
								body: new URLSearchParams({
									action: 'dh_assign_delivery',
									nonce: DH_PDV.nonce,
									order_id: id,
									delivery_id: deliveryId,
									set_status: 'dh-rota',
								}),
							})
							.then(r => r.json())
							.then(data => {
								if (data.success) window.location.reload();
								else { alert('Falha: ' + (data.data || 'erro')); evt.from.appendChild(evt.item); }
							})
							.catch(() => { alert('Erro de conexão.'); evt.from.appendChild(evt.item); });
						});
						return;
					}

					fetch(DH_PDV.ajaxUrl, {
						method: 'POST',
						headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
						body: new URLSearchParams({
							action: 'dh_move_order',
							nonce: DH_PDV.nonce,
							id, status,
						}),
					})
					.then(r => r.json())
					.then(data => {
						if (!data.success) {
							alert('Não deu pra mover: ' + (data.data || ''));
							evt.from.appendChild(evt.item);
						}
						updateCounts();
					})
					.catch(() => {
						alert('Erro de conexão.');
						evt.from.appendChild(evt.item);
					});
				},
			});
		});

		// Modal de escolha de entregador
		function chooseDelivery(orderId, callback) {
			const overlay = document.createElement('div');
			overlay.className = 'dh-driver-overlay';
			overlay.innerHTML = `
				<div class="dh-driver-modal">
					<header>
						<h3>Quem vai entregar?</h3>
						<button class="dh-driver-close" aria-label="Cancelar">×</button>
					</header>
					<div class="dh-driver-list">
						${ACTIVE_DELIVERIES.map(d => `
							<button type="button" class="dh-driver-choice" data-id="${d.id}">
								${d.photo ? `<img src="${d.photo}" alt="">` : `<span class="dh-driver-initial">${escapeHtmlLocal(d.name).charAt(0)}</span>`}
								<strong>${escapeHtmlLocal(d.name)}</strong>
							</button>
						`).join('')}
					</div>
					<footer>
						<button type="button" class="dh-driver-cancel">Cancelar</button>
					</footer>
				</div>
			`;
			document.body.appendChild(overlay);

			function done(result) { overlay.remove(); callback(result); }
			overlay.querySelectorAll('.dh-driver-choice').forEach(b => b.addEventListener('click', () => done(parseInt(b.dataset.id, 10))));
			overlay.querySelector('.dh-driver-close').addEventListener('click', () => done(null));
			overlay.querySelector('.dh-driver-cancel').addEventListener('click', () => done(null));
			overlay.addEventListener('click', (e) => { if (e.target === overlay) done(null); });
		}

		function escapeHtmlLocal(s) {
			return String(s || '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
		}

		function updateCounts() {
			document.querySelectorAll('.dh-kanban-col').forEach(col => {
				const n = col.querySelectorAll('.dh-order-card').length;
				const badge = col.querySelector('.dh-kanban-count');
				if (badge) badge.textContent = n;
			});
		}

		// Timer live nos cards — tick 1s, atualiza label + barra + classe de estado
		function fmtSecs(s) {
			s = Math.max(0, Math.floor(s));
			if (s < 60) return s + 's';
			const m = Math.floor(s / 60);
			if (m < 60) return m + 'min';
			const h = Math.floor(m / 60);
			const rem = m % 60;
			return h + 'h' + (rem > 0 ? String(rem).padStart(2,'0') : '');
		}
		function tickTimers() {
			const now = Math.floor(Date.now() / 1000);
			document.querySelectorAll('.dh-order-card.has-timer').forEach(card => {
				const since = parseInt(card.dataset.since || 0, 10);
				const sla   = parseInt(card.dataset.sla || 0, 10);
				const secs  = Math.max(0, now - since);
				const value = card.querySelector('[data-timer]');
				const fill  = card.querySelector('[data-fill]');
				if (value) value.textContent = fmtSecs(secs);
				if (fill && sla > 0) {
					const pct = Math.min(100, (secs / sla) * 100);
					fill.style.width = pct + '%';
					let state = 'ok';
					if (pct > 100 || secs > sla) state = 'over';
					else if (pct >= 70) state = 'warn';
					card.classList.remove('is-timer-ok','is-timer-warn','is-timer-over');
					card.classList.add('is-timer-' + state);
				}
			});
		}
		tickTimers();
		setInterval(tickTimers, 1000);

		// Toggle "Loja aberta/fechada" + pausar
		const wrap = document.querySelector('.dh-pdv-wrap');
		const wrapNonce = wrap ? wrap.dataset.nonce : null;
		function storeReq(action, extra) {
			const body = new URLSearchParams(Object.assign({ action, nonce: wrapNonce || DH_PDV.nonce }, extra || {}));
			return fetch(DH_PDV.ajaxUrl, { method: 'POST', body }).then(r => r.json());
		}
		document.querySelectorAll('.dh-store-close').forEach(b => b.addEventListener('click', () => {
			if (!confirm('Fechar a loja e bloquear novos pedidos?')) return;
			storeReq('dh_store_toggle').then(() => window.location.reload());
		}));
		document.querySelectorAll('.dh-store-resume').forEach(b => b.addEventListener('click', () => {
			storeReq('dh_store_resume').then(() => window.location.reload());
		}));
		document.querySelectorAll('.dh-store-pause').forEach(b => b.addEventListener('click', () => {
			const mode = b.dataset.mode || 'minutes';
			const mins = b.dataset.mins || 0;
			storeReq('dh_store_pause', { mode, minutes: mins }).then(() => window.location.reload());
		}));

		// Toolbar: busca + filtro tipo + ordenação + modo compacto
		const search  = document.getElementById('dh-pdv-search');
		const clearBtn = document.getElementById('dh-pdv-search-clear');
		const sortSel = document.getElementById('dh-pdv-sort');
		const compact = document.getElementById('dh-pdv-compact');
		const typeChips = document.querySelectorAll('[data-group="type"] .dh-chip');
		const emptyHint = document.getElementById('dh-pdv-empty-hint');
		const resetBtn  = document.getElementById('dh-pdv-reset-filters');

		if (!search) return; // segurança se o toolbar não montou

		// Estado persistido
		const LS_KEY = 'dh_pdv_filters_v1';
		let state = { q: '', type: 'all', sort: 'oldest', compact: false };
		try { state = Object.assign(state, JSON.parse(localStorage.getItem(LS_KEY) || '{}')); } catch (e) {}

		function saveState() { localStorage.setItem(LS_KEY, JSON.stringify(state)); }

		function normalize(s) {
			return (s || '').toString().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
		}

		function applyFilters() {
			const q = normalize(state.q.trim());
			const type = state.type;
			let anyVisible = false;

			document.querySelectorAll('.dh-kanban-col').forEach(col => {
				const list = col.querySelector('.dh-kanban-list');
				const cards = Array.from(list.querySelectorAll('.dh-order-card'));
				let visibleInCol = 0;

				cards.forEach(card => {
					const hay = normalize(card.dataset.search || '');
					const t = card.dataset.type || 'delivery';
					const matchQ = !q || hay.includes(q);
					const matchType = type === 'all' || t === type;
					const show = matchQ && matchType;
					card.style.display = show ? '' : 'none';
					if (show) { visibleInCol++; anyVisible = true; }
				});

				// Ordenação
				const sorted = cards.slice().sort((a,b) => {
					const av = parseFloat(a.dataset.total) || 0;
					const bv = parseFloat(b.dataset.total) || 0;
					const at = parseInt(a.dataset.created) || 0;
					const bt = parseInt(b.dataset.created) || 0;
					switch (state.sort) {
						case 'newest':     return bt - at;
						case 'value_desc': return bv - av;
						case 'value_asc':  return av - bv;
						case 'oldest':
						default:           return at - bt;
					}
				});
				sorted.forEach(c => list.appendChild(c));

				// Atualiza contador da coluna
				const badge = col.querySelector('.dh-kanban-count');
				if (badge) badge.textContent = visibleInCol;
			});

			// Modo compacto
			document.querySelector('.dh-kanban')?.classList.toggle('is-compact', !!state.compact);

			// Aviso de vazio
			if (emptyHint) emptyHint.hidden = anyVisible || (q === '' && type === 'all');

			// Botão limpar da busca
			if (clearBtn) clearBtn.hidden = !state.q;
		}

		function setChipActive(group, value) {
			document.querySelectorAll(`[data-group="${group}"] .dh-chip`).forEach(c => {
				c.classList.toggle('is-active', c.dataset[group] === value);
			});
		}

		// Restaurar estado no DOM
		search.value = state.q;
		sortSel.value = state.sort;
		compact.checked = !!state.compact;
		setChipActive('type', state.type);

		search.addEventListener('input', () => { state.q = search.value; saveState(); applyFilters(); });
		clearBtn?.addEventListener('click', () => { state.q = ''; search.value = ''; saveState(); applyFilters(); search.focus(); });
		sortSel.addEventListener('change', () => { state.sort = sortSel.value; saveState(); applyFilters(); });
		compact.addEventListener('change', () => { state.compact = compact.checked; saveState(); applyFilters(); });
		typeChips.forEach(chip => chip.addEventListener('click', () => {
			state.type = chip.dataset.type;
			setChipActive('type', state.type);
			saveState(); applyFilters();
		}));
		resetBtn?.addEventListener('click', () => {
			state = { q: '', type: 'all', sort: 'oldest', compact: state.compact };
			search.value = ''; sortSel.value = 'oldest'; setChipActive('type', 'all');
			saveState(); applyFilters();
		});

		applyFilters();

		// ============================================
		// Ações rápidas no card (popover "⋯")
		// ============================================
		const WA_TEMPLATES = {
			delay:      'Olá! Seu pedido está com um pequeno atraso, deve chegar em cerca de 15 minutos. Obrigado pela paciência!',
			dispatched: 'Seu pedido saiu para entrega! Chega em instantes.',
			ready:      'Seu pedido está pronto pra retirada no balcão!',
			address:    'Olá! Precisamos confirmar o endereço de entrega do seu pedido. Pode nos passar por aqui?',
			custom:     'Olá! Sobre o seu pedido: ',
		};
		const TEMPLATE_LABELS = {
			delay:      'Está atrasando ~15min',
			dispatched: 'Saiu pra entrega',
			ready:      'Pronto pra retirar',
			address:    'Confirmar endereço',
			custom:     'Mensagem em branco',
		};

		let openPopover = null;
		function closePopover() {
			if (openPopover) { openPopover.remove(); openPopover = null; }
		}
		document.addEventListener('click', (e) => {
			if (openPopover && !e.target.closest('.dh-card-popover') && !e.target.closest('.dh-card-menu-btn')) {
				closePopover();
			}
		});

		function toast(msg, ok) {
			const t = document.createElement('div');
			t.className = 'dh-toast' + (ok === false ? ' is-err' : '');
			t.textContent = msg;
			document.body.appendChild(t);
			setTimeout(() => t.classList.add('is-in'), 10);
			setTimeout(() => { t.classList.remove('is-in'); setTimeout(() => t.remove(), 250); }, 3000);
		}

		function openMenu(btn) {
			closePopover();
			const card = btn.closest('.dh-order-card');
			const orderId = card.dataset.id;
			const phone = btn.dataset.phone;
			const name  = (btn.dataset.name || 'cliente').split(' ')[0];
			const type  = btn.dataset.type || 'delivery';
			let items = [];
			try { items = JSON.parse(btn.dataset.items || '[]'); } catch (e) {}

			const pop = document.createElement('div');
			pop.className = 'dh-card-popover';
			pop.innerHTML = `
				<div class="dh-pop-section">
					<div class="dh-pop-title">Avisar ${name}${phone ? '' : ' <em>(sem telefone)</em>'}</div>
					${phone ? Object.keys(TEMPLATE_LABELS).map(key => `
						<button class="dh-pop-item" data-act="wa" data-key="${key}">
							<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347"/></svg>
							${TEMPLATE_LABELS[key]}
						</button>
					`).join('') : '<div class="dh-pop-empty">Sem telefone cadastrado</div>'}
				</div>

				${items.length > 0 ? `
					<div class="dh-pop-section">
						<div class="dh-pop-title">Marcar item esgotado</div>
						${items.map(it => `
							<button class="dh-pop-item" data-act="soldout" data-item-id="${it.id}" title="Afeta o cardápio pra próximos pedidos">
								<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
								${escapeHtml(it.name)}
							</button>
						`).join('')}
					</div>
				` : ''}

				<div class="dh-pop-section">
					<div class="dh-pop-title">Imprimir</div>
					<button class="dh-pop-item" data-act="print" data-type="cliente">
						<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
						Cupom do cliente
					</button>
					<button class="dh-pop-item" data-act="print" data-type="cozinha">
						<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5z"/></svg>
						Comanda cozinha
					</button>
					<button class="dh-pop-item" data-act="print" data-type="etiqueta">
						<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
						Etiqueta pra sacola
					</button>
				</div>

				<div class="dh-pop-section">
					<button class="dh-pop-item" data-act="reorder">
						<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-9-9c2.52 0 4.93 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/></svg>
						Repetir pedido (novo)
					</button>
				</div>
			`;

			// posicionar
			document.body.appendChild(pop);
			const r = btn.getBoundingClientRect();
			pop.style.top  = (window.scrollY + r.bottom + 6) + 'px';
			pop.style.left = Math.max(8, Math.min(window.innerWidth - pop.offsetWidth - 8, window.scrollX + r.right - pop.offsetWidth)) + 'px';
			openPopover = pop;

			// bindings
			pop.querySelectorAll('[data-act]').forEach(b => b.addEventListener('click', () => {
				const act = b.dataset.act;
				if (act === 'wa') {
					const msg = WA_TEMPLATES[b.dataset.key];
					if (phone) window.open('https://wa.me/' + phone + '?text=' + encodeURIComponent(msg), '_blank');
					closePopover();
				} else if (act === 'soldout') {
					b.disabled = true;
					storeReq('dh_toggle_soldout', { item_id: b.dataset.itemId }).then(res => {
						if (res.success) toast(res.data.label);
						else toast('Falha ao atualizar item', false);
						closePopover();
					});
				} else if (act === 'print') {
					const t = b.dataset.type || 'cliente';
					window.open(`/delivery/print/${orderId}/${t}?auto=1`, `dh-print-${orderId}-${t}`, 'width=400,height=600');
					closePopover();
				} else if (act === 'reorder') {
					if (!confirm('Duplicar este pedido? Vai criar um novo pedido com status "Novo".')) return;
					b.disabled = true;
					storeReq('dh_reorder', { order_id: orderId }).then(res => {
						if (res.success) {
							toast('Pedido duplicado! Abrindo…');
							setTimeout(() => window.location.href = res.data.edit_url, 700);
						} else toast('Falha ao duplicar', false);
					});
				}
			}));
		}

		function escapeHtml(s) {
			return String(s || '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
		}

		document.querySelectorAll('.dh-card-menu-btn').forEach(btn => {
			btn.addEventListener('click', (e) => { e.stopPropagation(); openMenu(btn); });
		});

		// ============================================
		// Notificações desktop + badge no title + som
		// ============================================
		const baseTitle = 'PDV — Delivery Hub';
		const notifyBtn = document.getElementById('dh-notify-btn');
		const notifyBtnLabel = notifyBtn?.querySelector('span');

		function refreshNotifyBtn() {
			if (!notifyBtn) return;
			if (!('Notification' in window)) return;
			if (Notification.permission === 'granted') {
				notifyBtn.hidden = true;
			} else if (Notification.permission === 'denied') {
				notifyBtn.hidden = false;
				notifyBtn.classList.add('is-denied');
				if (notifyBtnLabel) notifyBtnLabel.textContent = 'Notificações bloqueadas';
				notifyBtn.disabled = true;
			} else {
				notifyBtn.hidden = false;
			}
		}
		refreshNotifyBtn();
		notifyBtn?.addEventListener('click', async () => {
			if (!('Notification' in window)) return;
			const p = await Notification.requestPermission();
			refreshNotifyBtn();
			if (p === 'granted') sendNotification('Notificações ativadas!', 'Você vai receber alertas quando cair pedido novo.', 'ok');
		});

		function sendNotification(title, body, kind) {
			if (!('Notification' in window) || Notification.permission !== 'granted') return;
			try {
				new Notification(title, {
					body,
					icon: '/delivery/wp-content/themes/delivery-hub/assets/images/icon-192.png',
					tag: 'dh-' + (kind || 'info'),
					silent: false,
				});
			} catch (e) { /* silencia */ }
		}

		// Favicon dinâmico com badge
		let baseFavicon = null;
		function ensureFavicon() {
			if (baseFavicon) return;
			let link = document.querySelector('link[rel~="icon"]');
			if (!link) {
				link = document.createElement('link');
				link.rel = 'icon';
				document.head.appendChild(link);
			}
			baseFavicon = link;
		}
		function setFaviconBadge(count) {
			ensureFavicon();
			const size = 32;
			const canvas = document.createElement('canvas');
			canvas.width = canvas.height = size;
			const ctx = canvas.getContext('2d');
			// círculo base vermelho da marca
			ctx.fillStyle = '#EA1D2C';
			ctx.beginPath();
			ctx.arc(size/2, size/2, size/2, 0, Math.PI*2);
			ctx.fill();
			// letra "D"
			ctx.fillStyle = 'white';
			ctx.font = 'bold 18px -apple-system, sans-serif';
			ctx.textAlign = 'center';
			ctx.textBaseline = 'middle';
			ctx.fillText('D', size/2, size/2 + 1);
			// badge se count > 0
			if (count > 0) {
				const r = 10;
				ctx.fillStyle = '#dc2626';
				ctx.beginPath();
				ctx.arc(size - r, r, r, 0, Math.PI*2);
				ctx.fill();
				ctx.fillStyle = 'white';
				ctx.font = 'bold 13px -apple-system, sans-serif';
				ctx.fillText(count > 9 ? '9+' : String(count), size - r, r + 1);
			}
			baseFavicon.href = canvas.toDataURL('image/png');
		}

		// Título dinâmico com badge + piscar quando novo
		let blinkTimer = null;
		function setTabBadge(count) {
			if (blinkTimer) { clearInterval(blinkTimer); blinkTimer = null; }
			if (count > 0) {
				const badge = count > 9 ? '(9+)' : `(${count})`;
				let alt = true;
				document.title = `${badge} ${baseTitle}`;
				blinkTimer = setInterval(() => {
					document.title = alt ? `${badge} ${baseTitle}` : `● Novo pedido — ${baseTitle}`;
					alt = !alt;
				}, 1500);
			} else {
				document.title = baseTitle;
			}
			setFaviconBadge(count);
		}

		// Sons distintos por tipo
		function playSound(kind) {
			try {
				const ctx = new (window.AudioContext || window.webkitAudioContext)();
				let notes;
				switch (kind) {
					case 'overdue':  notes = [ 392, 349 ];        // G4-F4 (grave/alerta)
					                 break;
					case 'confirmed': notes = [ 1046, 1318 ];      // C6-E6 (suave/positivo)
					                 break;
					case 'ok':       notes = [ 659, 784 ];        // E5-G5 (curto)
					                 break;
					case 'new':
					default:         notes = [ 880, 1174, 1568 ]; // A5-D6-G6 (agudo/atenção)
				}
				notes.forEach((freq, i) => {
					const osc = ctx.createOscillator();
					const gain = ctx.createGain();
					osc.type = 'sine';
					osc.frequency.value = freq;
					const t = ctx.currentTime + i * 0.14;
					gain.gain.setValueAtTime(0, t);
					gain.gain.linearRampToValueAtTime(0.25, t + 0.02);
					gain.gain.linearRampToValueAtTime(0, t + 0.22);
					osc.connect(gain).connect(ctx.destination);
					osc.start(t);
					osc.stop(t + 0.25);
				});
			} catch (e) { /* browsers requerem interação */ }
		}

		// Toggle auto-imprimir (persistido no device)
		const autoPrintChk = document.getElementById('dh-autoprint');
		if (autoPrintChk) {
			autoPrintChk.checked = localStorage.getItem('dh_pdv_autoprint') === '1';
			autoPrintChk.addEventListener('change', () => {
				localStorage.setItem('dh_pdv_autoprint', autoPrintChk.checked ? '1' : '0');
			});
		}
		function autoPrintOrder(id) {
			if (!autoPrintChk || !autoPrintChk.checked) return;
			// Cliente + Cozinha em janelas separadas (etiqueta é opcional/manual)
			['cliente', 'cozinha'].forEach((t, i) => {
				setTimeout(() => {
					const w = window.open(`/delivery/print/${id}/${t}?auto=1`, `dh-print-${id}-${t}`, 'width=400,height=600');
					if (!w) console.warn('Popup bloqueado — habilite popups pra este site.');
				}, i * 300);
			});
		}

		// Poll de estado — detecta novos pedidos, atrasos e confirmações
		const initialNew = document.querySelectorAll('.dh-kanban-col[data-status="dh-novo"] .dh-order-card').length;
		const state = {
			lastCount: initialNew,
			lastOverdue: null,
			lastConfirmed: null,
			lastNovoIds: Array.from(document.querySelectorAll('.dh-kanban-col[data-status="dh-novo"] .dh-order-card')).map(c => parseInt(c.dataset.id)),
		};
		setTabBadge(initialNew);

		setInterval(async () => {
			if (document.visibilityState !== 'visible' && !('Notification' in window && Notification.permission === 'granted')) {
				// só pula se aba oculta E sem permissão de notif
				return;
			}
			try {
				const res = await fetch(DH_PDV.ajaxUrl + '?action=dh_count_new&nonce=' + DH_PDV.nonce);
				const data = await res.json();
				if (!data.success) return;
				const d = data.data;

				// 1. Novo pedido
				if (d.count > state.lastCount) {
					playSound('new');
					sendNotification('Novo pedido!', `${d.count - state.lastCount} pedido(s) aguardando aceite.`, 'new');
					setTabBadge(d.count);
					// Detecta quais pedidos são novos (não estavam na lista anterior) e auto-imprime
					const newIds = (d.novo_ids || []).filter(id => !state.lastNovoIds.includes(id));
					newIds.forEach(id => autoPrintOrder(id));
					state.lastNovoIds = d.novo_ids || [];
					setTimeout(() => window.location.reload(), 1500);
				} else if (d.count !== state.lastCount) {
					setTabBadge(d.count);
					state.lastNovoIds = d.novo_ids || [];
				}

				// 2. Pedido em atraso (novo estouro de SLA)
				if (state.lastOverdue !== null && d.overdue > state.lastOverdue) {
					playSound('overdue');
					sendNotification('Pedido em atraso', `${d.overdue} pedido(s) passaram da meta de tempo.`, 'overdue');
				}

				// 3. Cliente confirmou recebimento
				if (state.lastConfirmed !== null && d.last_confirmed && d.last_confirmed !== state.lastConfirmed) {
					playSound('confirmed');
					sendNotification('Cliente confirmou entrega', `Pedido #${d.last_confirmed} finalizado.`, 'confirmed');
				}

				state.lastCount = d.count;
				state.lastOverdue = d.overdue;
				state.lastConfirmed = d.last_confirmed;
			} catch (e) { /* silencia */ }
		}, 15000);
	});
})(jQuery);
