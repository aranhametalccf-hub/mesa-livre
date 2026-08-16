<?php
if ( ! defined( 'ABSPATH' ) ) exit;
get_header();
$s = dh_get_settings();
?>

<div class="dh-mo-wrap" x-data="myOrders()" x-init="load()">

	<header class="dh-mo-topbar">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="dh-mo-back" aria-label="Voltar ao cardápio">
			<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5"/><polyline points="12 19 5 12 12 5"/></svg>
		</a>
		<h1>Meus Pedidos</h1>
		<button class="dh-mo-logout" @click="logout()" x-show="phone" x-cloak title="Trocar telefone">
			<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
		</button>
	</header>

	<main class="dh-mo-main">

		<template x-if="!loaded">
			<div class="dh-mo-skeleton">
				<div class="dh-skel dh-skel-header"></div>
				<div class="dh-skel dh-skel-card"></div>
				<div class="dh-skel dh-skel-card"></div>
			</div>
		</template>

		<template x-if="loaded && !phone">
			<div class="dh-mo-login">
				<div class="dh-mo-login-icon">
					<svg width="52" height="52" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
				</div>
				<h2>Consultar meus pedidos</h2>
				<p>Digite seu WhatsApp para ver o histórico e acompanhar pedidos em andamento.</p>
				<input type="tel"
					x-model="phoneInput"
					@input="maskInput()"
					@keydown.enter="checkPhone()"
					placeholder="(11) 99999-9999"
					maxlength="15"
					inputmode="numeric"
					autocomplete="tel">
				<button class="dh-btn dh-btn-primary dh-btn-block"
					@click="checkPhone()"
					:disabled="checkingPhone"
					x-text="checkingPhone ? 'Buscando…' : 'Ver meus pedidos'"></button>
			</div>
		</template>

		<template x-if="loaded && phone && orders.length === 0">
			<div class="dh-mo-empty">
				<div class="dh-mo-empty-icon">
					<svg width="72" height="72" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
				</div>
				<h2>Nenhum pedido ainda</h2>
				<p>Que tal fazer o primeiro? Estamos preparando as coisas boas.</p>
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="dh-btn dh-btn-primary">Ver cardápio</a>
			</div>
		</template>

		<template x-if="loaded && phone && orders.length > 0">
			<div class="dh-mo-content">

				<div class="dh-mo-hero">
					<div class="dh-mo-avatar" :style="`background: ${avatarColor}`" x-text="initials"></div>
					<div class="dh-mo-hero-info">
						<h2 x-text="'Olá, ' + (firstName || 'cliente') + '!'"></h2>
						<small x-text="phoneMasked"></small>
					</div>
					<div class="dh-mo-stats">
						<div><strong x-text="orders.length"></strong><span x-text="orders.length === 1 ? 'pedido' : 'pedidos'"></span></div>
					</div>
				</div>

				<template x-if="openOrders.length > 0">
					<section class="dh-mo-section">
						<h3><span class="dh-mo-badge dh-mo-badge-live">•</span> Em andamento <em x-text="'(' + openOrders.length + ')'"></em></h3>

						<template x-for="o in openOrders" :key="o.id">
							<a class="dh-mo-order dh-mo-order-open" :href="o.tracking_url">
								<div class="dh-mo-order-top">
									<span class="dh-mo-status" :style="`background:${o.status_color}20;color:${o.status_color}`" x-text="o.status_label"></span>
									<time x-text="o.created_hum"></time>
								</div>

								<div class="dh-mo-mini-timeline">
									<template x-for="(step, i) in miniTimeline(o)" :key="i">
										<div class="dh-mo-mini-step"
											:class="{ 'is-done': i < step.currentIdx, 'is-active': i === step.currentIdx }"
											:style="i <= step.currentIdx ? `background:${o.status_color}` : ''">
										</div>
									</template>
								</div>

								<div class="dh-mo-order-body">
									<div class="dh-mo-order-title">Pedido #<span x-text="o.id"></span></div>
									<ul class="dh-mo-items">
										<template x-for="line in o.items_preview" :key="line">
											<li x-text="line"></li>
										</template>
										<li class="dh-mo-more" x-show="o.items_count > 3" x-text="'+ ' + (o.items_count - 3) + ' item(s)'"></li>
									</ul>
								</div>

								<div class="dh-mo-order-foot">
									<strong x-text="o.total_fmt"></strong>
									<span class="dh-mo-cta">Acompanhar
										<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
									</span>
								</div>
							</a>
						</template>
					</section>
				</template>

				<template x-if="pastOrders.length > 0">
					<section class="dh-mo-section">
						<h3>Histórico <em x-text="'(' + pastOrders.length + ')'"></em></h3>

						<template x-for="o in pastOrders" :key="o.id">
							<div class="dh-mo-order dh-mo-order-past">
								<div class="dh-mo-order-top">
									<span class="dh-mo-status" :style="`background:${o.status_color}20;color:${o.status_color}`" x-text="o.status_label"></span>
									<time x-text="o.created_hum"></time>
								</div>
								<div class="dh-mo-order-body">
									<div class="dh-mo-order-title">Pedido #<span x-text="o.id"></span></div>
									<ul class="dh-mo-items">
										<template x-for="line in o.items_preview" :key="line">
											<li x-text="line"></li>
										</template>
										<li class="dh-mo-more" x-show="o.items_count > 3" x-text="'+ ' + (o.items_count - 3) + ' item(s)'"></li>
									</ul>
								</div>
								<div class="dh-mo-order-foot">
									<strong x-text="o.total_fmt"></strong>
									<a :href="'<?php echo esc_url( home_url( '/' ) ); ?>?reorder=' + o.id" class="dh-mo-reorder">
										<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-9-9c2.52 0 4.93 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/></svg>
										Pedir de novo
									</a>
								</div>
							</div>
						</template>
					</section>
				</template>
			</div>
		</template>

	</main>
</div>

<script>
function myOrders() {
	const AVATAR_COLORS = ['#EA1D2C','#F59E0B','#10B981','#3B82F6','#8B5CF6','#EC4899','#14B8A6'];
	const FLOW_DELIVERY = ['dh-novo','dh-preparando','dh-pronto','dh-rota','dh-entregue'];
	const FLOW_OTHER    = ['dh-novo','dh-preparando','dh-pronto','dh-entregue'];

	return {
		loaded: false,
		phone: '',
		phoneInput: '',
		checkingPhone: false,
		customerName: '',
		orders: [],

		async load() {
			const saved = localStorage.getItem('dh_phone') || '';
			if (saved) {
				this.phone = saved.replace(/\D/g,'');
				await this.fetchAll();
			}
			this.loaded = true;
		},
		maskInput() {
			let d = (this.phoneInput || '').replace(/\D/g,'').slice(0,11);
			if (d.length > 10) this.phoneInput = `(${d.slice(0,2)}) ${d.slice(2,7)}-${d.slice(7)}`;
			else if (d.length > 6) this.phoneInput = `(${d.slice(0,2)}) ${d.slice(2,6)}-${d.slice(6)}`;
			else if (d.length > 2) this.phoneInput = `(${d.slice(0,2)}) ${d.slice(2)}`;
			else this.phoneInput = d;
		},
		async checkPhone() {
			const p = (this.phoneInput || '').replace(/\D/g,'');
			if (p.length < 10) { alert('Telefone inválido — digite DDD + número'); return; }
			this.checkingPhone = true;
			this.phone = p;
			localStorage.setItem('dh_phone', p);
			await this.fetchAll();
			this.checkingPhone = false;
		},
		async fetchAll() {
			try {
				const [cust, orders] = await Promise.all([
					fetch(`/delivery/wp-json/dh/v1/customer/${this.phone}`).then(r => r.json()),
					fetch(`/delivery/wp-json/dh/v1/orders/${this.phone}`).then(r => r.json()),
				]);
				this.customerName = cust.found ? cust.name : '';
				this.orders = Array.isArray(orders) ? orders : [];
			} catch (e) {
				console.error(e);
				alert('Erro ao carregar pedidos');
			}
		},
		logout() {
			localStorage.removeItem('dh_phone');
			this.phone = ''; this.phoneInput = ''; this.orders = []; this.customerName = '';
		},

		get openOrders() { return this.orders.filter(o => o.is_open); },
		get pastOrders() { return this.orders.filter(o => !o.is_open); },

		get firstName() {
			return (this.customerName || '').split(' ')[0];
		},
		get initials() {
			const n = (this.customerName || 'C').trim();
			const parts = n.split(/\s+/);
			if (parts.length >= 2) return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
			return n.slice(0,2).toUpperCase();
		},
		get avatarColor() {
			// hash simples baseado no telefone
			let h = 0;
			for (const c of this.phone) h = (h * 31 + c.charCodeAt(0)) | 0;
			return AVATAR_COLORS[Math.abs(h) % AVATAR_COLORS.length];
		},
		get phoneMasked() {
			const p = this.phone;
			if (p.length === 11) return `(${p.slice(0,2)}) ${p.slice(2,7)}-${p.slice(7)}`;
			if (p.length === 10) return `(${p.slice(0,2)}) ${p.slice(2,6)}-${p.slice(6)}`;
			return p;
		},
		miniTimeline(o) {
			// heurística: se o pedido tinha rota como status válido (delivery), usa fluxo completo
			const flow = o.status === 'dh-rota' ? FLOW_DELIVERY : FLOW_OTHER;
			const idx = flow.indexOf(o.status);
			return flow.map((_, i) => ({ i, currentIdx: idx === -1 ? 0 : idx }));
		},
	};
}
</script>

<?php get_footer(); ?>
