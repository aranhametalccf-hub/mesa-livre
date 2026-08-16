/**
 * Delivery Hub — app.js
 * Estado global do carrinho, checkout e integração com AJAX.
 * Exposto como window.deliveryApp() e chamado via x-data no HTML.
 * DEVE carregar ANTES do Alpine.js (registrado como dependência inversa no enqueue.php).
 */

window.deliveryApp = function () {
	return ({
		cart: JSON.parse(localStorage.getItem('dh_cart') || '[]'),
		cartOpen: false,
		checkoutOpen: false,
		successOpen: false,
		submitting: false,
		successOrderId: null,
		successWhatsApp: '',

		modal: {
			open: false,
			item: null,
			qty: 1,
			obs: '',
			selections: {}, // { groupIdx: singleOptIdx OR [optIdx,...] }
		},

		checkout: Object.assign({
			name: '',
			phone: '',
			type: 'delivery',
			cep: '',
			street: '',
			number: '',
			neighborhood: '',
			complement: '',
			reference: '',
			city: '',
			state: '',
			table: '',
			payment: '',
			change: '',
			notes: '',
		}, JSON.parse(localStorage.getItem('dh_checkout') || '{}')),

		cepStatus: '',
		cepError: '',
		savedAddresses: [],
		selectedAddressIdx: null,
		addingNewAddress: false,
		customerFound: false,
		lastLookupPhone: '',
		successTracking: '',

		init() {
			// Se algum tipo padrão não estiver disponível, escolhe o primeiro habilitado
			const store = window.DH?.store || {};
			const order = ['delivery', 'pickup', 'dinein'];
			const enabled = order.filter(t => Number(store['accept_' + t]) === 1);
			if (enabled.length && !enabled.includes(this.checkout.type)) {
				this.checkout.type = enabled[0];
			}
			if (!this.checkout.payment && store.payment_methods?.length) {
				this.checkout.payment = store.payment_methods[0];
			}

			this.$watch('cart', v => localStorage.setItem('dh_cart', JSON.stringify(v)));
			this.$watch('checkout', v => localStorage.setItem('dh_checkout', JSON.stringify(v)), { deep: true });

			// Se já tem CEP salvo e campos preenchidos, marca como ok pra reabrir grid
			if (this.checkout.cep && this.checkout.street) this.cepStatus = 'ok';

			// Se telefone salvo, faz lookup automático
			if (this.checkout.phone && this.checkout.phone.replace(/\D/g,'').length >= 10) {
				this.lookupCustomer();
			}
		},

		maskPhone() {
			let d = (this.checkout.phone || '').replace(/\D/g, '').slice(0, 11);
			if (d.length > 10) {
				this.checkout.phone = `(${d.slice(0,2)}) ${d.slice(2,7)}-${d.slice(7)}`;
			} else if (d.length > 6) {
				this.checkout.phone = `(${d.slice(0,2)}) ${d.slice(2,6)}-${d.slice(6)}`;
			} else if (d.length > 2) {
				this.checkout.phone = `(${d.slice(0,2)}) ${d.slice(2)}`;
			} else {
				this.checkout.phone = d;
			}
		},

		async lookupCustomer() {
			const digits = (this.checkout.phone || '').replace(/\D/g, '');
			if (digits.length < 10 || digits === this.lastLookupPhone) return;
			this.lastLookupPhone = digits;

			try {
				const res = await fetch(`/delivery/wp-json/dh/v1/customer/${digits}`);
				const data = await res.json();
				if (!data.found) {
					this.customerFound = false;
					this.savedAddresses = [];
					return;
				}
				this.customerFound = true;
				if (data.name && !this.checkout.name) this.checkout.name = data.name;

				this.savedAddresses = Array.isArray(data.addresses) ? data.addresses : [];
				localStorage.setItem('dh_phone', digits);

				// Auto-seleciona o endereço mais recente se ainda não tem CEP preenchido
				if (this.savedAddresses.length > 0 && !this.checkout.cep) {
					this.selectedAddressIdx = 0;
					this.applyAddress(0);
				}
			} catch (e) {
				console.warn('lookup customer falhou', e);
			}
		},

		applyAddress(i) {
			const a = this.savedAddresses[i];
			if (!a) return;
			this.checkout.cep = a.cep ? a.cep.replace(/^(\d{5})(\d{3})$/, '$1-$2') : '';
			this.checkout.street = a.street || '';
			this.checkout.number = a.number || '';
			this.checkout.neighborhood = a.neighborhood || '';
			this.checkout.complement = a.complement || '';
			this.checkout.reference = a.reference || '';
			this.checkout.city = a.city || '';
			this.checkout.state = a.state || '';
			this.cepStatus = 'ok';
			this.addingNewAddress = false;
		},

		clearAddressFields() {
			['cep','street','number','neighborhood','complement','reference','city','state'].forEach(k => this.checkout[k] = '');
			this.cepStatus = '';
		},

		get cepDigits() { return (this.checkout.cep || '').replace(/\D/g, ''); },

		maskCep() {
			const d = this.cepDigits.slice(0, 8);
			this.checkout.cep = d.length > 5 ? d.slice(0, 5) + '-' + d.slice(5) : d;
			if (d.length < 8) { this.cepStatus = ''; }
		},

		async lookupCep() {
			const cep = this.cepDigits;
			if (cep.length !== 8) return;
			this.cepStatus = 'loading';
			this.cepError = '';
			try {
				const res = await fetch(`https://viacep.com.br/ws/${cep}/json/`);
				const data = await res.json();
				if (data.erro) {
					this.cepStatus = 'err';
					this.cepError = 'CEP não encontrado';
					return;
				}
				this.checkout.street = data.logradouro || this.checkout.street;
				this.checkout.neighborhood = data.bairro || this.checkout.neighborhood;
				this.checkout.city = data.localidade || '';
				this.checkout.state = data.uf || '';
				this.checkout.complement = this.checkout.complement || data.complemento || '';
				this.cepStatus = 'ok';
				// Foca no número se rua veio preenchida
				this.$nextTick(() => {
					if (this.$refs.numberInput && this.checkout.street) {
						this.$refs.numberInput.focus();
					}
				});
			} catch (e) {
				this.cepStatus = 'err';
				this.cepError = 'Erro na consulta. Verifique sua conexão.';
			}
		},

		get fullAddress() {
			const c = this.checkout;
			const parts = [];
			if (c.street) parts.push(c.street + (c.number ? ', ' + c.number : ''));
			if (c.complement) parts.push(c.complement);
			if (c.neighborhood) parts.push(c.neighborhood);
			if (c.city) parts.push(c.city + (c.state ? '/' + c.state : ''));
			if (c.cep) parts.push('CEP ' + c.cep);
			if (c.reference) parts.push('Ref: ' + c.reference);
			return parts.join(' — ');
		},

		money(v) {
			const n = Number(v || 0).toFixed(2);
			const [int, dec] = n.split('.');
			return 'R$ ' + int.replace(/\B(?=(\d{3})+(?!\d))/g, '.') + ',' + dec;
		},

		// ---------- Item modal ----------
		openItem(itemJson) {
			const item = typeof itemJson === 'string' ? JSON.parse(itemJson) : itemJson;
			this.modal.item = item;
			this.modal.qty = 1;
			this.modal.obs = '';
			this.modal.selections = {};
			this.modal.open = true;
			document.body.style.overflow = 'hidden';
		},
		closeItem() {
			this.modal.open = false;
			document.body.style.overflow = '';
		},
		incQty() { this.modal.qty++; },
		decQty() { if (this.modal.qty > 1) this.modal.qty--; },

		selectSingle(gi, oi) {
			this.modal.selections[gi] = oi;
		},
		toggleMulti(gi, oi, checked) {
			if (!Array.isArray(this.modal.selections[gi])) this.modal.selections[gi] = [];
			const arr = this.modal.selections[gi];
			const idx = arr.indexOf(oi);
			const group = this.modal.item.extras[gi];
			const max = parseInt(group.max) || 0;
			if (checked) {
				if (max > 0 && arr.length >= max) {
					// remove o primeiro pra respeitar o max
					arr.shift();
				}
				if (!arr.includes(oi)) arr.push(oi);
			} else if (idx !== -1) {
				arr.splice(idx, 1);
			}
		},

		get modalExtrasFlat() {
			if (!this.modal.item) return [];
			const out = [];
			(this.modal.item.extras || []).forEach((group, gi) => {
				const sel = this.modal.selections[gi];
				if (sel === undefined) return;
				if (group.type === 'single') {
					const opt = group.options[sel];
					if (opt) out.push({ group: group.name, label: opt.label, price: Number(opt.price || 0) });
				} else {
					(sel || []).forEach(oi => {
						const opt = group.options[oi];
						if (opt) out.push({ group: group.name, label: opt.label, price: Number(opt.price || 0) });
					});
				}
			});
			return out;
		},

		get modalTotal() {
			if (!this.modal.item) return 0;
			const extras = this.modalExtrasFlat.reduce((s, o) => s + Number(o.price || 0), 0);
			return (Number(this.modal.item.price) + extras) * this.modal.qty;
		},

		addToCart() {
			// valida obrigatórios
			for (const [gi, group] of (this.modal.item.extras || []).entries()) {
				if (group.required) {
					const sel = this.modal.selections[gi];
					const ok = group.type === 'single' ? sel !== undefined : (sel && sel.length > 0);
					if (!ok) {
						alert('Escolha uma opção em: ' + group.name);
						return;
					}
				}
			}

			const line = {
				uid: Date.now() + '-' + Math.random().toString(36).slice(2, 8),
				id: this.modal.item.id,
				name: this.modal.item.name,
				qty: this.modal.qty,
				price: Number(this.modal.item.price),
				options: this.modalExtrasFlat,
				obs: this.modal.obs,
				total: this.modalTotal,
			};
			this.cart.push(line);
			this.closeItem();
			this.openCart();
		},

		// ---------- Cart ----------
		openCart() { this.cartOpen = true; document.body.style.overflow = 'hidden'; },
		closeCart() { this.cartOpen = false; document.body.style.overflow = ''; },

		get cartCount() { return this.cart.reduce((s, l) => s + l.qty, 0); },
		get cartTotal() { return this.cart.reduce((s, l) => s + l.total, 0); },

		changeQty(i, delta) {
			const line = this.cart[i];
			const unit = line.total / line.qty;
			line.qty = Math.max(1, line.qty + delta);
			line.total = unit * line.qty;
			this.cart = [...this.cart];
		},
		removeLine(i) {
			this.cart.splice(i, 1);
			this.cart = [...this.cart];
			if (this.cart.length === 0) this.closeCart();
		},

		// ---------- Checkout ----------
		openCheckout() {
			this.cartOpen = false;
			this.checkoutOpen = true;
		},
		closeCheckout() {
			this.checkoutOpen = false;
			this.cartOpen = true;
		},

		get checkoutTotal() {
			const fee = (this.checkout.type === 'delivery') ? Number(window.DH?.store?.delivery_fee || 0) : 0;
			return this.cartTotal + fee;
		},

		get checkoutValid() {
			if (!this.checkout.name?.trim() || !this.checkout.phone?.trim()) return false;
			if (this.checkout.type === 'delivery') {
				if (this.cepDigits.length !== 8) return false;
				if (!this.checkout.street?.trim()) return false;
				if (!this.checkout.number?.trim()) return false;
			}
			if (this.checkout.type === 'dinein' && !this.checkout.table?.trim()) return false;
			if (!this.checkout.payment) return false;
			return true;
		},

		async placeOrder() {
			if (!this.checkoutValid || this.submitting) return;
			this.submitting = true;
			try {
				const payload = {
					items: this.cart.map(l => ({
						id: l.id,
						qty: l.qty,
						options: l.options,
						obs: l.obs,
					})),
					customer: {
						name: this.checkout.name,
						phone: this.checkout.phone,
						type: this.checkout.type,
						address: this.fullAddress,
						cep: this.checkout.cep,
						street: this.checkout.street,
						number: this.checkout.number,
						neighborhood: this.checkout.neighborhood,
						complement: this.checkout.complement,
						reference: this.checkout.reference,
						city: this.checkout.city,
						state: this.checkout.state,
						table: this.checkout.table,
						payment: this.checkout.payment,
						change: this.checkout.change,
						notes: this.checkout.notes,
					},
				};

				const url = window.DH.ajaxUrl + '?action=dh_place_order&nonce=' + window.DH.nonce;
				const res = await fetch(url, {
					method: 'POST',
					headers: { 'Content-Type': 'application/json' },
					body: JSON.stringify(payload),
				});
				const data = await res.json();
				if (!data.success) {
					alert(data.data?.message || 'Erro ao enviar pedido');
					this.submitting = false;
					return;
				}

				this.successOrderId = data.data.order_id;
				this.successWhatsApp = data.data.whatsapp;
				this.successTracking = data.data.tracking_url || '';
				this.checkoutOpen = false;
				this.successOpen = true;

				// Salva telefone pra login persistente
				const phoneDigits = (this.checkout.phone || '').replace(/\D/g, '');
				if (phoneDigits.length >= 10) localStorage.setItem('dh_phone', phoneDigits);

				// Limpa o carrinho AGORA — pedido registrado no servidor com sucesso.
				// Sem esperar o clique em "Novo pedido": user pode fechar aba após abrir WhatsApp.
				this.cart = [];
				this.cartOpen = false;

				// Abre WhatsApp automaticamente
				window.open(data.data.whatsapp, '_blank');
			} catch (e) {
				console.error(e);
				alert('Erro de conexão. Tente novamente.');
			} finally {
				this.submitting = false;
			}
		},

		resetAfterSuccess() {
			// cart já foi limpo no placeOrder — só fecha o modal e libera scroll
			this.successOpen = false;
			document.body.style.overflow = '';
		},
	});
};

/**
 * Categorias nav — destaca link ativo conforme scroll e faz smooth scroll.
 */
window.initCategoriesNav = function (nav) {
	const links = nav.querySelectorAll('a[data-cat]');
	links.forEach(a => {
		a.addEventListener('click', e => {
			e.preventDefault();
			const target = document.querySelector(a.getAttribute('href'));
			if (target) {
				const offset = nav.offsetHeight;
				const top = target.getBoundingClientRect().top + window.scrollY - offset - 8;
				window.scrollTo({ top, behavior: 'smooth' });
			}
		});
	});

	const sections = Array.from(links).map(a => document.querySelector(a.getAttribute('href'))).filter(Boolean);
	const observer = new IntersectionObserver(entries => {
		entries.forEach(entry => {
			if (entry.isIntersecting) {
				const id = entry.target.id;
				links.forEach(a => a.classList.toggle('is-active', a.getAttribute('href') === '#' + id));
			}
		});
	}, { rootMargin: '-40% 0px -55% 0px' });
	sections.forEach(s => observer.observe(s));
};
