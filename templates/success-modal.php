<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<div class="dh-modal-overlay" x-show="successOpen" x-transition.opacity>
	<div class="dh-modal dh-modal-success">
		<div class="dh-success-icon">
			<svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
		</div>
		<h2>Pedido enviado!</h2>
		<p>Seu pedido <strong x-text="'#' + successOrderId"></strong> foi registrado. Estamos abrindo o WhatsApp da loja pra você confirmar.</p>
		<a :href="successWhatsApp" class="dh-btn dh-btn-primary dh-btn-block" target="_blank" rel="noopener">
			Abrir WhatsApp
		</a>
		<a :href="successTracking" class="dh-btn dh-btn-ghost dh-btn-block">
			Acompanhar pedido
		</a>
		<button class="dh-btn dh-btn-ghost dh-btn-block" @click="resetAfterSuccess()">Fazer novo pedido</button>
	</div>
</div>
