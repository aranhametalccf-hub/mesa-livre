<h1 align="center">Delivery Hub</h1>

<p align="center">
  <strong>Alternativa gratuita e open-source ao Anota Ai e CardapioWeb.</strong><br>
  Cardápio digital, carrinho, checkout via WhatsApp e PDV Kanban — direto no seu WordPress.
</p>

<p align="center">
  <a href="https://github.com/eusougabrielgadelha/delivery-free/releases"><img src="https://img.shields.io/github/v/release/eusougabrielgadelha/delivery-free?color=EA1D2C&label=vers%C3%A3o&style=flat-square" alt="Versão"></a>
  <a href="LICENSE"><img src="https://img.shields.io/badge/licen%C3%A7a-PolyForm%20NC%201.0.0-blue?style=flat-square" alt="License"></a>
  <a href="https://wordpress.org/download/"><img src="https://img.shields.io/badge/WordPress-6.0%2B-21759B?style=flat-square&logo=wordpress&logoColor=white" alt="WordPress"></a>
  <a href="https://www.php.net/"><img src="https://img.shields.io/badge/PHP-8.0%2B-777BB4?style=flat-square&logo=php&logoColor=white" alt="PHP"></a>
  <a href="https://github.com/eusougabrielgadelha/delivery-free/stargazers"><img src="https://img.shields.io/github/stars/eusougabrielgadelha/delivery-free?style=flat-square&color=yellow" alt="Stars"></a>
  <a href="CONTRIBUTING.md"><img src="https://img.shields.io/badge/PRs-bem%20vindos-brightgreen?style=flat-square" alt="PRs Welcome"></a>
</p>

<p align="center">
  <a href="#-por-que-existe">Por que existe</a> ·
  <a href="#-funcionalidades">Funcionalidades</a> ·
  <a href="#-instalação-em-10-minutos">Instalação</a> ·
  <a href="#-arquitetura">Arquitetura</a> ·
  <a href="#-roadmap">Roadmap</a> ·
  <a href="#-contribuindo">Contribuir</a>
</p>

---

## 🎯 Por que existe

Todo restaurante pequeno no Brasil está pagando **R$ 90-300/mês** para plataformas fechadas de cardápio digital (Anota Ai, CardapioWeb, Menu Dino, Vittude, Cardápio Web, Pedidos Online etc). Ao longo do ano, isso vira **R$ 1.000-3.600 de aluguel de software** que poderia estar comprando insumo.

O Delivery Hub existe pra dar a esse dono de restaurante:

- **Autonomia total** — o cardápio, os pedidos e a lista de clientes ficam no servidor dele
- **Zero mensalidade eterna** — instala uma vez e roda pra sempre
- **WhatsApp direto** — sem fee de terceiros no fluxo do pedido
- **Rodando em hospedagem barata** — PHP compartilhado + MySQL, sem Docker, sem Node
- **Instalação em 10 minutos** — não precisa contratar desenvolvedor

É um projeto **open-source, gratuito, sem venda**. Comercialização proibida por licença ([PolyForm Noncommercial 1.0.0](LICENSE)). Serviços de instalação, customização e suporte são permitidos.

## 📸 Como se parece

> **Screenshots serão adicionados na próxima release.** Enquanto isso, o design é fortemente inspirado no CardapioWeb (frontend) e no iFood Painel do Parceiro (backend/PDV) — se você conhece qualquer um dos dois, vai se sentir em casa.

## ⚡ Funcionalidades

<table>
<tr>
<td width="50%" valign="top">

### 👨‍🍳 Para o restaurante (Admin)

- **PDV Kanban** com 6 status (Novo → Preparando → Pronto → Em rota → Entregue) + drag-drop entre colunas
- **Timer live por card** com metas configuráveis (SLA). Cards ficam laranja/vermelhos quando estouram
- **Modo Cozinha** fullscreen otimizado pra TV/tablet montado na cozinha
- **Loja aberta/fechada automática** por horário do dia + exceções (feriados)
- **Pausas temporárias** (15/30/60min ou até fim do dia)
- **Busca em tempo real** por nome, telefone, número do pedido ou item
- **Filtros** por tipo (entrega/retirada/no local) e período (hoje/ontem/7d)
- **Editor completo do pedido** — mudar itens, adicionais, taxa, endereço com audit log
- **Histórico completo** de alterações (quem, o quê, quando)
- **Ações rápidas por card** — templates WhatsApp, marcar item esgotado, duplicar pedido
- **Impressão térmica 80mm** — cupom cliente, comanda cozinha, etiqueta pra sacola
- **Auto-imprimir** ao cair pedido novo
- **Notificações desktop** com sons distintos (novo/atraso/confirmado)
- **Cadastro de entregadores** com foto + atribuição no card
- **Relatórios** — faturamento por hora, top itens, breakdown pagamento, fechamento de caixa, export CSV
- **3 perfis de acesso** — Cozinha, Atendente, Gerente

</td>
<td width="50%" valign="top">

### 🛒 Para o cliente

- **Cardápio digital** responsivo com fotos, categorias sticky e busca por scroll
- **Modal de customização** com adicionais únicos/múltiplos, obrigatórios, extras com preço
- **Observação por item** ("sem cebola", "bem passado")
- **Carrinho reativo** com edição de quantidade e remoção
- **Order bumps** (sugestões antes do checkout)
- **Autocomplete de endereço via CEP** (ViaCEP, sem key, grátis)
- **Login por telefone** — endereços salvos, histórico completo
- **3 formas de recebimento** — Entrega, Retirada, Consumir no local
- **Pagamento configurável** — PIX, dinheiro (com troco), cartões
- **Envio automático pro WhatsApp** com pedido formatado
- **Página de tracking** — timeline em tempo real do status
- **Confirmação de recebimento pelo cliente**
- **Página "Meus Pedidos"** com histórico + botão "Pedir de novo"

</td>
</tr>
</table>

### Diferenças pras plataformas pagas

| | Delivery Hub | Anota Ai | CardapioWeb |
|---|---|---|---|
| Mensalidade | **R$ 0** | R$ 89-249/mês | R$ 97-249/mês |
| Fee por pedido | **R$ 0** | R$ 0-2 | R$ 0-3 |
| Dados do cliente | **Você é o dono** | Deles | Deles |
| Personalização visual | **Total (é WordPress)** | Templates fechados | Templates fechados |
| Roda offline | Depende só do seu servidor | ❌ | ❌ |
| Multi-loja | 🔨 Fase futura (Multisite) | ✅ Pago | ✅ Pago |
| PIX integrado | 🔨 Fase futura | ✅ | ✅ |
| App próprio na loja | 🔨 PWA na fase futura | ❌ | ❌ |

## 🚀 Instalação em 10 minutos

### Pré-requisitos

- WordPress 6.0+ (com PHP 8.0+ e MySQL 5.7+)
- Hospedagem qualquer — Hostgator, Hostinger, KingHost, LocaWeb, Bluehost, WPX, Kinsta

### Passo a passo

**1. Baixe a release**

```bash
# Via Git
git clone https://github.com/eusougabrielgadelha/delivery-free.git delivery-hub

# Ou baixe o ZIP em Releases e descompacte
```

**2. Coloque na pasta de temas**

```
wp-content/
└── themes/
    └── delivery-hub/    ← aqui
```

**3. Ative o tema**

`Aparência → Temas → Delivery Hub → Ativar`

**4. Popule com exemplos** (opcional mas recomendado pra explorar)

`Cardápio → Popular exemplos → Popular agora`

Cria 4 categorias e 12 itens de exemplo (pizzas, hambúrgueres, bebidas, sobremesas) com adicionais configurados.

**5. Configure sua loja**

`Config. da Loja` → preencha:
- Nome, WhatsApp (com DDI: `5511987654321`)
- Endereço, horário de funcionamento
- Cor primária, formas de pagamento
- Metas de tempo (SLAs)

**6. Abra `/` do seu site**

Cardápio funcionando. Faça um pedido de teste e veja aparecer no PDV.

## 🏗️ Arquitetura

### Stack

- **WordPress 6+** — a base
- **PHP 8+** — nada de dependências externas via Composer
- **Alpine.js** (via CDN) — reatividade no frontend público
- **SortableJS** (via CDN) — drag-drop do Kanban admin
- **CSS puro moderno** — variáveis CSS, `color-mix()`, sem preprocessador
- **ViaCEP** — autocomplete de endereço grátis
- **Canvas API** — gráficos e favicon dinâmico

**Zero bundler. Zero `npm install`. Zero build step.** O tema roda direto após copiar os arquivos.

### Estrutura

```
delivery-hub/
├── style.css                       Header do tema (WP)
├── functions.php                   Bootstrap + includes
├── header.php · footer.php         Wrapper com Alpine + modais
├── index.php · front-page.php      Home = cardápio
├── inc/
│   ├── setup.php                   Theme supports, image sizes
│   ├── cpt.php                     CPTs menu_item, dh_order + status Kanban
│   ├── meta.php                    Meta boxes: preço, extras, order bump
│   ├── settings.php                Config da loja + SLA + horários
│   ├── enqueue.php                 CSS + JS + localize DH global
│   ├── helpers.php                 money(), log_change(), recalc_totals()
│   ├── ajax.php                    place_order, edit_items, toggle_soldout etc
│   ├── whatsapp.php                Monta URL wa.me formatada
│   ├── customer.php                CPT dh_customer, addresses, history
│   ├── delivery.php                CPT dh_delivery + assign no PDV
│   ├── rest.php                    REST endpoints /dh/v1/*
│   ├── routes.php                  Rewrite rules públicas
│   ├── metrics.php                 Cálculo de tempo por etapa + médias
│   ├── store-hours.php             Horários + fechado manual + pausa
│   ├── roles.php                   dh_cook, dh_attendant, dh_manager
│   └── seed.php                    Popular cardápio de exemplo
├── admin/
│   ├── pdv.php                     PDV Kanban
│   ├── kitchen.php                 Modo Cozinha (fullscreen TV)
│   ├── reports.php                 Relatórios + fechamento + CSV
│   ├── order-columns.php           Metaboxes da edição do pedido
│   ├── order-page.php              Customização visual da tela de pedido
│   └── menu-item-page.php          Customização visual da tela de item
├── templates/
│   ├── item-modal.php              Modal ao clicar num item
│   ├── cart-drawer.php             Drawer do carrinho + order bumps
│   ├── checkout-modal.php          Checkout com CEP + login por telefone
│   ├── success-modal.php           Confirmação após envio
│   ├── my-orders.php               /meus-pedidos (área do cliente)
│   ├── order-tracking.php          /pedido/{id} (tracking em tempo real)
│   └── print-order.php             /print/{id}/{tipo} (impressão térmica)
└── assets/
    ├── css/theme.css               CSS do frontend
    ├── css/admin.css               CSS do admin/PDV
    ├── js/app.js                   Alpine store (cardápio + carrinho + checkout)
    └── js/pdv.js                   Kanban interativo (drag, timers, filtros, ações)
```

### Modelo de dados

- **`menu_item`** — CPT dos itens do cardápio. Meta `_dh_price`, `_dh_promo_price`, `_dh_extras` (grupos com opções JSON), `_dh_is_bump`, `_dh_soldout`. Taxonomia `menu_category`
- **`dh_order`** — CPT dos pedidos. Meta `_dh_customer` (nome, telefone, endereço estruturado), `_dh_items`, `_dh_subtotal`, `_dh_delivery_fee`, `_dh_total`, `_dh_delivery_id`, `_dh_status_history`, `_dh_change_log`
- **`dh_customer`** — CPT dos clientes, indexado por `post_name` = telefone. Meta `_dh_addresses` (array de endereços salvos)
- **`dh_delivery`** — CPT dos entregadores. Meta `_dh_deliv_phone`, `_dh_deliv_active`
- **Status customizados** — `dh-novo`, `dh-preparando`, `dh-pronto`, `dh-rota`, `dh-entregue`, `dh-cancelado`
- **Options** — `dh_settings` (config da loja), `dh_store_state` (fechado/pausado)

### Regras de negócio críticas

- **Prioridade do status da loja**: `manual_closed` > `paused_until` > `manual_open_until` > exceção do dia > horário do dia da semana
- **Timezone**: sempre `time()` + `wp_date()`, nunca `current_time( 'timestamp' )` (bugado no WP)
- **Dia da semana**: `wp_date( 'N' )` (ISO 1-7) — `wp_date( 'D' )` retorna em português (`sáb`, `dom`) e quebra lookups
- **Recálculo de totais**: sempre server-side em `dh_recalc_order_totals()` — cliente nunca dita preço
- **Nonces por-pedido**: `dh_order_edit_{ID}`, `dh_order_action_{ID}` — não dá pra reusar de outro pedido
- **Pedidos entregues/cancelados**: bloqueados pra edição e avanço

## 🗺️ Roadmap

### ✅ Entregue (v0.2.0)

- [x] Cardápio digital com carrinho e checkout via WhatsApp
- [x] Autocomplete de endereço via ViaCEP
- [x] Login por telefone com endereços salvos e histórico
- [x] PDV Kanban com drag-drop, timer live, SLAs configuráveis
- [x] Loja aberta/fechada com horário automático e exceções
- [x] Busca, filtros, ordenação e modo compacto
- [x] Editor completo do pedido com audit log
- [x] Impressão térmica (cliente, cozinha, etiqueta)
- [x] Modo Cozinha fullscreen pra TV
- [x] Notificações desktop com sons distintos
- [x] Cadastro de entregadores com atribuição
- [x] Relatórios (faturamento/hora, top itens, fechamento de caixa, CSV)
- [x] 3 perfis de acesso (cozinha, atendente, gerente)

### 🔨 Próximas fases (contribuições bem-vindas)

- [ ] **Pagamento PIX integrado** (Mercado Pago / Gerencianet / PagBank)
- [ ] **Cupons de desconto** — % ou valor fixo, com validade
- [ ] **Programa de fidelidade** — pontos por pedido, resgate
- [ ] **Multi-loja** via WP Multisite
- [ ] **PWA instalável** com service worker offline
- [ ] **App nativo** (React Native / Flutter) — read-only pro cliente
- [ ] **Chatbot no WhatsApp** que aceita pedido
- [ ] **OTP via SMS** pro login por telefone (mais seguro)
- [ ] **Integração iFood / Rappi** — importar pedidos no mesmo PDV
- [ ] **Impressão via API PrintNode** — dispensa driver local
- [ ] **Analytics 30/90 dias** — LTV, cohort, churn
- [ ] **Suporte multi-idioma** (i18n com `.po/.mo`)
- [ ] **Traduções** — inglês, espanhol LATAM, francês

Veja o [ROADMAP completo](CHANGELOG.md#unreleased) e [issues abertas](https://github.com/eusougabrielgadelha/delivery-free/issues).

## 🤝 Contribuindo

Contribuições de qualquer tipo são bem-vindas — código, docs, tradução, design, teste, divulgação. Existe um **padrão único** de contribuição documentado em [CONTRIBUTING.md](CONTRIBUTING.md).

**Onde começar:**

- 🐛 [Reportar bug](https://github.com/eusougabrielgadelha/delivery-free/issues/new?template=bug_report.yml)
- 💡 [Sugerir feature](https://github.com/eusougabrielgadelha/delivery-free/issues/new?template=feature_request.yml)
- ❓ [Fazer pergunta](https://github.com/eusougabrielgadelha/delivery-free/discussions)
- 🌍 Traduzir pra inglês/espanhol
- 📚 Escrever tutorial em vídeo

## ⚖️ Licença

**PolyForm Noncommercial License 1.0.0** — veja [LICENSE](LICENSE).

**Você PODE:**
- ✅ Baixar, usar, modificar e redistribuir
- ✅ Instalar em quantos restaurantes/lojas próprias quiser
- ✅ Cobrar por instalação, customização e suporte técnico
- ✅ Contribuir e fazer fork

**Você NÃO PODE:**
- ❌ Vender o Delivery Hub como produto (SaaS pago, licenciamento, revenda)
- ❌ Cobrar assinatura pra usar o software em si
- ❌ Reempacotar e vender como serviço

Uso comercial que envolva venda do software requer licença separada. Entre em contato via [@eusougabrielgadelha](https://github.com/eusougabrielgadelha).

## 👤 Autor

**Gabriel Gadelha** — [@eusougabrielgadelha](https://github.com/eusougabrielgadelha)

- Instagram: [@eusougabrielgadelha](https://www.instagram.com/eusougabrielgadelha)
- YouTube: [@eusougabrielgadelha](https://www.youtube.com/@eusougabrielgadelha)

Se este projeto te economizou dinheiro ou tempo, considere:

- ⭐ Dar uma estrela no repositório
- 💬 Contar pra outros donos de restaurante
- 🤝 [Contribuir](CONTRIBUTING.md) com código, docs ou tradução
- 💰 [Patrocinar](.github/FUNDING.yml) o desenvolvimento

## 🙏 Créditos e inspirações

- Design do frontend inspirado no [CardapioWeb](https://cardapioweb.com) e [Anota Ai](https://anota.ai)
- UX do PDV inspirada no iFood Painel do Parceiro
- Ícones: [Feather Icons](https://feathericons.com) (MIT)
- Fonte de dados de CEP: [ViaCEP](https://viacep.com.br) (gratuito, sem key)

Delivery Hub **não é afiliado** ao Anota Ai, CardapioWeb, iFood ou qualquer plataforma citada. É um projeto independente construído pra oferecer uma alternativa gratuita a donos de restaurantes que não querem pagar mensalidade.

---

<p align="center">
  <sub>
    Feito com 💜 no Brasil por
    <a href="https://github.com/eusougabrielgadelha"><strong>@eusougabrielgadelha</strong></a>
    pra dar autonomia digital ao pequeno restaurante brasileiro.
  </sub>
</p>

<p align="center">
  <sub>
    <strong>Keywords:</strong>
    anota ai gratuito · cardapioweb gratuito · anota ai alternativa · cardapioweb alternativa ·
    sistema delivery gratuito · cardápio digital wordpress · pdv delivery open source ·
    delivery whatsapp brasil · plugin cardápio wordpress · sistema pedido online grátis ·
    ifood alternativa gratuita · menu dino alternativa · restaurante wordpress theme ·
    delivery restaurante grátis · pdv kanban restaurante · brasil restaurante software free
  </sub>
</p>
