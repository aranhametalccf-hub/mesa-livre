<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<div class="dh-modal-overlay" x-show="modal.open" x-transition.opacity @click.self="closeItem()" role="dialog" aria-modal="true">
	<div class="dh-modal">
		<button class="dh-modal-close" @click="closeItem()" aria-label="Fechar">×</button>

		<template x-if="modal.item">
			<div>
				<div class="dh-modal-hero" x-show="modal.item.image" :style="`background-image: url(${modal.item.image})`"></div>

				<div class="dh-modal-body">
					<h2 x-text="modal.item.name"></h2>
					<p class="dh-modal-desc" x-text="modal.item.description" x-show="modal.item.description"></p>

					<template x-for="(group, gi) in (modal.item.extras || [])" :key="gi">
						<div class="dh-extra-group">
							<div class="dh-extra-head">
								<h3 x-text="group.name"></h3>
								<span class="dh-extra-req" x-show="group.required">Obrigatório</span>
							</div>
							<template x-for="(opt, oi) in group.options" :key="oi">
								<label class="dh-extra-opt">
									<template x-if="group.type === 'single'">
										<input type="radio" :name="`grp-${gi}`" @change="selectSingle(gi, oi)">
									</template>
									<template x-if="group.type === 'multi'">
										<input type="checkbox" @change="toggleMulti(gi, oi, $event.target.checked)">
									</template>
									<span class="dh-extra-label" x-text="opt.label"></span>
									<span class="dh-extra-price" x-show="Number(opt.price) > 0" x-text="'+ ' + money(Number(opt.price))"></span>
								</label>
							</template>
						</div>
					</template>

					<div class="dh-obs">
						<label>Alguma observação?</label>
						<textarea x-model="modal.obs" rows="2" placeholder="Ex: sem cebola, ponto da carne, etc."></textarea>
					</div>
				</div>

				<footer class="dh-modal-footer">
					<div class="dh-qty">
						<button @click="decQty()" aria-label="menos">−</button>
						<span x-text="modal.qty"></span>
						<button @click="incQty()" aria-label="mais">+</button>
					</div>
					<button class="dh-btn dh-btn-primary" @click="addToCart()">
						<span>Adicionar</span>
						<strong x-text="money(modalTotal)"></strong>
					</button>
				</footer>
			</div>
		</template>
	</div>
</div>
