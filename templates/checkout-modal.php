<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<div class="dh-modal-overlay" x-show="checkoutOpen" x-transition.opacity @click.self="closeCheckout()">
	<div class="dh-modal dh-modal-checkout">
		<header class="dh-checkout-head">
			<button class="dh-back" @click="closeCheckout()" aria-label="Voltar">←</button>
			<h2>Finalizar pedido</h2>
		</header>

		<div class="dh-checkout-body">

			<section class="dh-field-group">
				<h3>Seus dados</h3>
				<label>WhatsApp</label>
				<input type="tel"
					x-model="checkout.phone"
					@input="maskPhone()"
					@blur="lookupCustomer()"
					placeholder="(11) 99999-9999"
					autocomplete="tel"
					required>
				<div class="dh-customer-hint" x-show="customerFound" x-transition>
					<span>Bem-vindo de volta!</span>
					<a href="<?php echo esc_url( home_url( '/meus-pedidos' ) ); ?>">Ver meus pedidos →</a>
				</div>

				<label>Nome</label>
				<input type="text" x-model="checkout.name" placeholder="Seu nome completo" autocomplete="name" required>
			</section>

			<section class="dh-field-group">
				<h3>Como você quer receber?</h3>
				<div class="dh-type-picker">
					<template x-if="DH.store.accept_delivery == 1">
						<label :class="{ 'is-active': checkout.type === 'delivery' }">
							<input type="radio" x-model="checkout.type" value="delivery">
							<div>
								<strong>Entrega</strong>
								<small>Receber no endereço</small>
							</div>
						</label>
					</template>
					<template x-if="DH.store.accept_pickup == 1">
						<label :class="{ 'is-active': checkout.type === 'pickup' }">
							<input type="radio" x-model="checkout.type" value="pickup">
							<div>
								<strong>Retirada</strong>
								<small>Buscar na loja</small>
							</div>
						</label>
					</template>
					<template x-if="DH.store.accept_dinein == 1">
						<label :class="{ 'is-active': checkout.type === 'dinein' }">
							<input type="radio" x-model="checkout.type" value="dinein">
							<div>
								<strong>No local</strong>
								<small>Consumir aqui</small>
							</div>
						</label>
					</template>
				</div>
			</section>

			<section class="dh-field-group" x-show="checkout.type === 'delivery'">
				<h3>Endereço de entrega</h3>

				<div class="dh-saved-addresses" x-show="savedAddresses.length > 0 && !addingNewAddress">
					<label>Endereços salvos</label>
					<template x-for="(addr, i) in savedAddresses" :key="addr.sig">
						<label class="dh-saved-addr" :class="{ 'is-active': selectedAddressIdx === i }">
							<input type="radio" :value="i" x-model.number="selectedAddressIdx" @change="applyAddress(i)">
							<div>
								<strong x-text="addr.label"></strong>
								<span x-text="addr.street + (addr.number ? ', ' + addr.number : '') + (addr.complement ? ' — ' + addr.complement : '')"></span>
								<small x-text="addr.neighborhood + ' — ' + addr.city + '/' + addr.state"></small>
							</div>
						</label>
					</template>
					<button type="button" class="dh-btn dh-btn-ghost dh-btn-block" @click="addingNewAddress = true; selectedAddressIdx = null; clearAddressFields()">+ Adicionar novo endereço</button>
				</div>

				<template x-if="addingNewAddress || savedAddresses.length === 0">
					<div>
						<button type="button" class="dh-link-back" @click="addingNewAddress = false" x-show="savedAddresses.length > 0">← Usar endereço salvo</button>
				<label>CEP</label>
				<div class="dh-cep-row">
					<input type="text"
						x-model="checkout.cep"
						@input="maskCep(); if (cepDigits.length === 8) lookupCep()"
						placeholder="00000-000"
						maxlength="9"
						inputmode="numeric"
						autocomplete="postal-code">
					<span class="dh-cep-status" x-show="cepStatus">
						<span x-show="cepStatus === 'loading'">Buscando…</span>
						<span x-show="cepStatus === 'ok'" class="dh-cep-ok">Encontrado</span>
						<span x-show="cepStatus === 'err'" class="dh-cep-err" x-text="cepError"></span>
					</span>
				</div>

				<div class="dh-address-grid" x-show="cepStatus === 'ok'" x-transition>
					<div class="dh-grid-street">
						<label>Rua</label>
						<input type="text" x-model="checkout.street" placeholder="Rua/Avenida">
					</div>
					<div class="dh-grid-number">
						<label>Número</label>
						<input type="text" x-model="checkout.number" placeholder="123" inputmode="numeric" x-ref="numberInput">
					</div>
					<div class="dh-grid-full">
						<label>Bairro</label>
						<input type="text" x-model="checkout.neighborhood" placeholder="Bairro">
					</div>
					<div class="dh-grid-full">
						<label>Complemento (opcional)</label>
						<input type="text" x-model="checkout.complement" placeholder="Apto, bloco, casa">
					</div>
					<div class="dh-grid-full">
						<label>Ponto de referência (opcional)</label>
						<input type="text" x-model="checkout.reference" placeholder="Ex: próximo ao mercado">
					</div>
					<div class="dh-grid-city">
						<label>Cidade / UF</label>
						<input type="text" :value="checkout.city + (checkout.state ? ' — ' + checkout.state : '')" readonly>
					</div>
				</div>
					</div>
				</template>
			</section>

			<section class="dh-field-group" x-show="checkout.type === 'dinein'">
				<label>Número da mesa</label>
				<input type="text" x-model="checkout.table" placeholder="Ex: 12">
			</section>

			<section class="dh-field-group">
				<h3>Pagamento</h3>
				<div class="dh-payment-picker">
					<template x-for="method in DH.store.payment_methods" :key="method">
						<label :class="{ 'is-active': checkout.payment === method }">
							<input type="radio" x-model="checkout.payment" :value="method">
							<span x-text="method"></span>
						</label>
					</template>
				</div>
				<template x-if="checkout.payment === 'Dinheiro'">
					<div>
						<label>Troco para quanto? (opcional)</label>
						<input type="text" x-model="checkout.change" placeholder="Ex: R$ 50,00">
					</div>
				</template>
			</section>

			<section class="dh-field-group">
				<label>Observação geral do pedido (opcional)</label>
				<textarea x-model="checkout.notes" rows="2" placeholder="Alguma observação para toda a loja?"></textarea>
			</section>

			<section class="dh-checkout-summary">
				<div><span>Subtotal</span><strong x-text="money(cartTotal)"></strong></div>
				<div x-show="checkout.type === 'delivery' && DH.store.delivery_fee > 0">
					<span>Taxa de entrega</span>
					<strong x-text="money(DH.store.delivery_fee)"></strong>
				</div>
				<div class="dh-total">
					<span>Total</span>
					<strong x-text="money(checkoutTotal)"></strong>
				</div>
			</section>
		</div>

		<footer class="dh-modal-footer">
			<div class="dh-closed-warning" x-show="DH.store.is_open == 0" x-cloak>
				<strong>Loja fechada agora</strong>
				<span x-text="DH.store.closed_message"></span>
			</div>
			<button class="dh-btn dh-btn-primary dh-btn-block"
				:disabled="submitting || !checkoutValid || DH.store.is_open == 0"
				@click="placeOrder()">
				<span x-show="!submitting && DH.store.is_open == 1">Fazer pedido pelo WhatsApp</span>
				<span x-show="!submitting && DH.store.is_open == 0">Loja fechada</span>
				<span x-show="submitting">Enviando…</span>
			</button>
		</footer>
	</div>
</div>
