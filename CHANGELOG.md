# Changelog

Formato baseado em [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/) e este projeto adere ao [Semantic Versioning](https://semver.org/lang/pt-BR/).

## [Unreleased]

### Adicionado
- Padrão de contribuição pública (`CONTRIBUTING.md`, `CODE_OF_CONDUCT.md`, templates de PR e Issue)
- Licença PolyForm Noncommercial 1.0.0

## [0.2.0] — 2026-08-16

### Adicionado — Fases 5 a 10 do roadmap
- **Entregadores**: CPT `dh_delivery` com foto, WhatsApp, ativo/inativo. Modal de escolha ao arrastar pedido pra "Em rota". Barra de entregadores ativos no PDV com contadores
- **Notificações desktop**: Notification API, 3 sons distintos, badge no title da aba piscando, favicon dinâmico via canvas
- **Impressão térmica**: rota `/print/{id}/{tipo}` com 3 templates (cliente 80mm, cozinha, etiqueta). Toggle auto-imprimir persistido no device
- **Modo Cozinha**: sub-page fullscreen otimizada pra TV/tablet. 3 colunas gigantes, timer live, botão grande de avanço, relógio, auto-refresh 10s
- **Relatórios**: página nova com cards comparativos delta % vs. ontem, gráfico faturamento/hora em canvas puro, breakdown de pagamento, top itens, fechamento de caixa, export CSV com BOM UTF-8
- **Roles multi-atendente**: `dh_cook`, `dh_attendant`, `dh_manager` com permissões diferenciadas. Cozinha redireciona automático pro modo cozinha ao logar

### Corrigido
- `#wpfooter` sobrepondo conteúdo em páginas customizadas do admin (position: static via body class `dh-page`)
- Bug de máscara de telefone do entregador que re-injetava `+55` ao apagar

## [0.1.0] — 2026-08-15

### Adicionado — MVP funcional
- **Cardápio digital** reativo com carrinho, adicionais e observação por item
- **Checkout** com autocomplete de endereço via ViaCEP e envio pro WhatsApp
- **Área do cliente** com "login" por telefone, histórico de pedidos, tracking em tempo real
- **PDV Kanban** com drag-drop entre 6 status, timer live, SLAs configuráveis
- **Loja aberta/fechada** com horário por dia da semana, exceções e pausas temporárias
- **Busca, filtros e ordenação** no Kanban persistidos em localStorage
- **Ações rápidas por card**: templates WhatsApp, marcar item esgotado, duplicar pedido
- **Editor completo do pedido** (itens, adicionais, quantidade, taxa, observação, cliente)
- **Timeline horizontal** de status por pedido no admin
- **Audit log** completo (quem, o quê, quando) com badges coloridas
- CPTs `menu_item`, `dh_order`, `dh_customer` + taxonomia `menu_category`
- REST endpoints `/wp-json/dh/v1/*`
- Rotas frontend `/meus-pedidos` e `/pedido/{id}`

[Unreleased]: https://github.com/eusougabrielgadelha/delivery-free/compare/v0.2.0...HEAD
[0.2.0]: https://github.com/eusougabrielgadelha/delivery-free/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/eusougabrielgadelha/delivery-free/releases/tag/v0.1.0
