<?php
if ( ! defined( 'ABSPATH' ) ) exit;
$bumps = dh_get_order_bumps();
?>

<div class="dh-drawer-overlay" x-show="cartOpen" x-transition.opacity @click.self="closeCart()"></div>

<aside class="dh-drawer" :class="{ 'is-open': cartOpen }" role="dialog" aria-label="Carrinho">
	<header class="dh-drawer-head">
		<h2>Seu pedido</h2>
		<button @click="closeCart()" aria-label="Fechar">×</button>
	</header>

	<div class="dh-drawer-body">
		<template x-if="cart.length === 0">
			<div class="dh-cart-empty">
				<p>Seu carrinho está vazio.</p>
			</div>
		</template>

		<template x-for="(line, i) in cart" :key="line.uid">
			<div class="dh-cart-line">
				<div class="dh-cart-line-info">
					<div class="dh-cart-line-name">
						<strong x-text="line.qty + 'x ' + line.name"></strong>
					</div>
					<template x-if="line.options && line.options.length">
						<ul class="dh-cart-line-opts">
							<template x-for="opt in line.options" :key="opt.label">
								<li x-text="opt.label"></li>
							</template>
						</ul>
					</template>
					<p class="dh-cart-line-obs" x-show="line.obs" x-text="'Obs: ' + line.obs"></p>
					<div class="dh-cart-line-actions">
						<button @click="changeQty(i, -1)">−</button>
						<span x-text="line.qty"></span>
						<button @click="changeQty(i, 1)">+</button>
						<button class="dh-cart-remove" @click="removeLine(i)">Remover</button>
					</div>
				</div>
				<div class="dh-cart-line-price" x-text="money(line.total)"></div>
			</div>
		</template>

		<?php if ( $bumps ) : ?>
			<div class="dh-bumps" x-show="cart.length > 0">
				<h3>Que tal adicionar?</h3>
				<div class="dh-bumps-list">
					<?php foreach ( $bumps as $bump ) :
						$data = dh_item_to_array( $bump ); ?>
						<button class="dh-bump" @click='openItem(<?php echo esc_attr( wp_json_encode( $data ) ); ?>)'>
							<?php if ( $data['image'] ) : ?>
								<div class="dh-bump-img" style="background-image:url('<?php echo esc_url( $data['image'] ); ?>');"></div>
							<?php endif; ?>
							<div class="dh-bump-info">
								<strong><?php echo esc_html( $data['name'] ); ?></strong>
								<span><?php echo dh_money( $data['price'] ); ?></span>
							</div>
							<span class="dh-bump-add">+</span>
						</button>
					<?php endforeach; ?>
				</div>
			</div>
		<?php endif; ?>
	</div>

	<footer class="dh-drawer-foot" x-show="cart.length > 0">
		<div class="dh-cart-subtotal">
			<span>Subtotal</span>
			<strong x-text="money(cartTotal)"></strong>
		</div>
		<button class="dh-btn dh-btn-primary dh-btn-block" @click="openCheckout()">
			Ir para o pagamento
		</button>
	</footer>
</aside>
