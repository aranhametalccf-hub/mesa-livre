# Delivery Hub

Tema WordPress open-source de delivery inspirado em **CardapioWeb** e **Anota Ai**. Cardápio digital, carrinho reativo, checkout com envio para WhatsApp e PDV Kanban no admin.

## Stack

- WordPress 6+ / PHP 8+
- Alpine.js (via CDN) para reatividade do frontend
- SortableJS (via CDN) para drag-drop do Kanban admin
- CSS puro (sem build step)
- ViaCEP (autocomplete de endereço grátis)

## Instalação rápida

1. Copie a pasta `delivery-hub/` para `wp-content/themes/` da sua instalação WordPress
2. Ative o tema em **Aparência → Temas**
3. Menu lateral → **Cardápio → Popular exemplos** → clique "Popular agora" (12 itens de teste)
4. Menu **Config. da Loja** → preencha WhatsApp com DDI (`5511987654321`), endereço, horário, cor primária, formas de pagamento
5. Abra `/` do site pra ver o cardápio funcionando

## Estrutura

```
delivery-hub/
├── style.css                       Header do tema (obrigatório WP)
├── functions.php                   Bootstrap + includes
├── header.php · footer.php         Wrapper com Alpine + modais
├── index.php · front-page.php      Home = cardápio
├── inc/
│   ├── setup.php                   Theme supports, image sizes
│   ├── cpt.php                     CPTs menu_item, dh_order + status Kanban + flow
│   ├── meta.php                    Meta boxes: preço, extras, order bump
│   ├── settings.php                Config da loja + SLA + horários
│   ├── enqueue.php                 CSS + JS + localize DH global
│   ├── helpers.php                 money(), dh_log_change(), recalc_totals()
│   ├── ajax.php                    place_order, order_action, edit_items, toggle_soldout, reorder
│   ├── whatsapp.php                Monta URL wa.me com mensagem formatada
│   ├── customer.php                CPT dh_customer, addresses, orders history
│   ├── rest.php                    REST endpoints /dh/v1/*
│   ├── routes.php                  Rewrite rules /meus-pedidos e /pedido/{id}
│   ├── metrics.php                 Cálculo de tempo por etapa + médias do dia
│   ├── store-hours.php             Horários + fechado manual + pausa
│   └── seed.php                    Botão pra popular cardápio de exemplo
├── admin/
│   ├── pdv.php                     Página Kanban do PDV
│   ├── order-columns.php           Colunas + metaboxes da edição do pedido
│   ├── order-page.php              Customização visual da tela de pedido
│   └── menu-item-page.php          Customização visual da tela de item do cardápio
├── templates/
│   ├── item-modal.php              Modal ao clicar num item
│   ├── cart-drawer.php             Drawer do carrinho + order bumps
│   ├── checkout-modal.php          Formulário de finalização com CEP + login
│   ├── success-modal.php           Confirmação após envio
│   ├── my-orders.php               Página /meus-pedidos
│   └── order-tracking.php          Página /pedido/{id} com timeline
└── assets/
    ├── css/theme.css               CSS do frontend
    ├── css/admin.css               CSS do admin/PDV
    ├── js/app.js                   Alpine store (cardápio + carrinho + checkout)
    └── js/pdv.js                   Kanban interativo (drag-drop, timers, filtros, ações)
```

## Funcionalidades entregues

### Cardápio (cliente)
- ✅ Header com capa/logo/nome/horário/taxa
- ✅ Categorias sticky com scroll automático
- ✅ Cards de item com foto, descrição, preço + promo
- ✅ Modal de customização (adicionais única/múltipla, obrigatórios, extras com preço)
- ✅ Observação por item
- ✅ Item esgotado (bloqueia click)
- ✅ Carrinho drawer com editar quantidade / remover
- ✅ Order bumps (sugestões antes do checkout)
- ✅ Checkout: nome, WhatsApp com máscara BR, 3 tipos (entrega/retirada/no local)
- ✅ **Autocomplete de endereço via CEP** (ViaCEP, sem key)
- ✅ Endereço estruturado (rua, número, bairro, complemento, referência, cidade, UF)
- ✅ Formas de pagamento configuráveis + troco pra dinheiro
- ✅ Envio automático pra WhatsApp com pedido formatado
- ✅ Bloqueio de checkout quando loja fechada

### Área do cliente
- ✅ Página `/meus-pedidos` com "login" via telefone
- ✅ Avatar circular colorido gerado pelas iniciais (cor consistente por telefone)
- ✅ Cards de pedidos em andamento com mini-timeline
- ✅ Histórico de pedidos com "Pedir de novo"
- ✅ Página `/pedido/{id}` com tracking em tempo real
- ✅ Timeline horizontal com ícones SVG por etapa (checkmark, chef, caixa, moto, check-duplo)
- ✅ Card de status hero com cor dinâmica
- ✅ Poll de 15s pra atualizar status sem reload
- ✅ Botão de confirmação do recebimento pelo cliente
- ✅ Cards de endereço, itens, pagamento, observação
- ✅ Botão WhatsApp da loja

### Loja (admin)
- ✅ **PDV Kanban 6 colunas** (Novo → Preparando → Pronto → Em rota → Entregue → Cancelado)
- ✅ Drag-drop entre colunas
- ✅ Auto-refresh a cada 15s
- ✅ Som ao cair pedido novo
- ✅ Badge no menu com contagem de "Novo"
- ✅ **Timer live por card** com barra de progresso colorida (verde/amarelo/vermelho)
- ✅ **Métricas do dia** — pedidos, faturamento, ticket médio, em atraso
- ✅ **SLAs configuráveis** com médias reais por etapa (aceite/preparo/expedição/entrega/retirada/ciclo)
- ✅ **Horário de funcionamento** por dia da semana com múltiplas faixas
- ✅ Exceções (feriados e datas especiais)
- ✅ Fechamento manual + pausas temporárias (15/30/60min ou até fim do dia)
- ✅ Reabertura força override quando fora do horário
- ✅ **Busca em tempo real** (nome/telefone/#/item, normaliza acento)
- ✅ Filtros por tipo (entrega/retirada/no local) + período (hoje/ontem/7d)
- ✅ Ordenação (mais antigo/recente, maior/menor valor)
- ✅ Modo compacto
- ✅ Filtros persistidos em localStorage
- ✅ **Ações rápidas no card** (popover):
  - 5 templates de WhatsApp (atraso, saiu, pronto, endereço, custom)
  - Marcar item esgotado direto do card (afeta cardápio)
  - Duplicar pedido em 1 clique
- ✅ Toast de confirmação
- ✅ Edição completa do pedido (itens, adicionais, quantidade, taxa, observação, cliente)
- ✅ **Timeline horizontal** de status por pedido
- ✅ **Audit log** completo (quem, o quê, quando) com badges coloridas
- ✅ Combobox de busca (com autocomplete) pra adicionar item
- ✅ Botões estilizados avançar/voltar/cancelar por pedido
- ✅ CPT `dh_customer` com endereços salvos e histórico
- ✅ Roteamento REST `/wp-json/dh/v1/*` (customer, orders, order, menu-item)

### Frontend UX
- ✅ Layout responsivo mobile-first
- ✅ Máscara de telefone/CEP em tempo real
- ✅ Skeleton loader
- ✅ Persistência localStorage (carrinho, checkout, dados do cliente)

## Roadmap — fases entregues

### ✅ Fase 5 — Entregadores
- CPT `dh_delivery` (cadastro completo com foto, telefone com máscara, ativo/inativo)
- Modal de escolha ao arrastar pra "Em rota"
- Contagem por entregador no header do Kanban
- Nome + foto visíveis no tracking do cliente
- Audit log grava atribuição

### ✅ Fase 6 — Notificações e alertas
- Notification API com permissão explícita
- 3 sons distintos (novo agudo, atraso grave, confirmado suave)
- Badge no title da aba piscando `(N) PDV`
- Favicon dinâmico gerado via canvas com dot vermelho
- Alertas: pedido novo, atraso SLA, cliente confirmou

### ✅ Fase 7 — Impressão térmica
- Rota `/print/{id}/{tipo}` — cliente, cozinha, etiqueta
- CSS 80mm otimizado com preview + botão imprimir
- Toggle "Auto-imprimir" persistido no device
- Ao cair pedido novo, abre popups de Cliente + Cozinha
- Botões no popover do card + na sidebar de edição do pedido

### ✅ Fase 8 — Modo Cozinha (TV fullscreen)
- Rota `/wp-admin/admin.php?page=dh-kitchen&fs=1`
- 3 colunas gigantes (Novo, Preparando, Pronto) fundo escuro pra TV
- Cards com fonte 20px+, timer live, obs em amarelo destacado
- Botão grande de avanço por card (INICIAR PREPARO / PRONTO)
- Relógio no header + auto-refresh 10s
- Fullscreen esconde admin bar

### ✅ Fase 9 — Insights, comparativos e fechamento
- Página **Relatórios** no menu lateral
- 4 cards comparativos com delta % vs. ontem (faturamento, pedidos, ticket, atraso)
- Gráfico de barras faturamento por hora (canvas puro)
- Breakdown de pagamento com barras percentuais
- Top 8 itens mais vendidos ranqueados
- Fechamento de caixa (dinheiro esperado vs contado)
- Export CSV do dia com BOM UTF-8 pra Excel

### ✅ Fase 10 — Multi-atendente e roles
- 3 roles WP: `dh_cook`, `dh_attendant`, `dh_manager`
- **Cozinha**: só vê Modo Cozinha, redirect automático no login
- **Atendente**: PDV + pedidos + entregadores + clientes (sem cardápio/settings/relatórios)
- **Gerente**: tudo exceto settings core do WP
- Log de quem fez cada ação já vinha do audit log (`user_id`)
- Admin notice em Config. da Loja pra criar novos usuários

### 💡 Ideias futuras (não priorizadas)
- Pagamento PIX integrado (Mercado Pago / Gerencianet)
- Cupons de desconto
- Programa de fidelidade
- Multi-loja (WP Multisite)
- App PWA instalável
- Chatbot no WhatsApp
- OTP via SMS (segurança do login por telefone)

## Regras de negócio importantes

- **Prioridade do status da loja:** manual_closed > paused_until > manual_open_until > exceção do dia > horário do dia
- **Timezone:** sempre usar `time()` + `wp_date()` — `current_time('timestamp')` é bugado no WP e retorna offset local dobrado
- **Dia da semana:** usar `wp_date('N')` (ISO 1-7), não `wp_date('D')` que retorna português (`sáb`, `dom`) e quebra lookup
- **Pedidos entregues/cancelados:** não podem ser editados nem avançados
- **Recálculo de totais:** sempre server-side após edição (fonte da verdade em `_dh_items` + `_dh_delivery_fee`)
- **Nonces por-pedido:** ações de edição usam `dh_order_edit_{ID}` e `dh_order_action_{ID}`

## Contribuindo

Este projeto foi construído em uma série de fases atômicas via [Claude Code](https://claude.com/claude-code). Cada fase deixa o produto operável — dá pra parar em qualquer ponto e ter algo útil.

## Licença

GPL v2 or later
