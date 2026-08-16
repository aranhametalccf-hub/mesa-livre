<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

	<?php get_template_part( 'templates/item-modal' ); ?>
	<?php get_template_part( 'templates/cart-drawer' ); ?>
	<?php get_template_part( 'templates/checkout-modal' ); ?>
	<?php get_template_part( 'templates/success-modal' ); ?>

	<button class="dh-cart-fab" @click="openCart()" x-show="cart.length > 0" x-transition>
		<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
		<span x-text="cartCount"></span>
		<strong x-text="money(cartTotal)"></strong>
	</button>
</div>

<?php wp_footer(); ?>
</body>
</html>
