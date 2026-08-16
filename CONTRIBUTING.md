# Guia de Contribuição

Obrigado por considerar contribuir com o **Delivery Hub**. Este documento estabelece o padrão único que todas as contribuições devem seguir.

## Filosofia do projeto

O Delivery Hub existe pra dar autonomia digital ao pequeno restaurante brasileiro sem que ele precise pagar mensalidade eterna a plataformas fechadas. Toda decisão de arquitetura passa por:

1. **Roda em qualquer hospedagem barata** (PHP compartilhado, sem Docker, sem Node)
2. **Cliente não precisa aprender nada novo** — quem já usa Anota Ai/CardapioWeb reconhece a UX
3. **Instalação em menos de 10 minutos**
4. **Zero fee de terceiros no fluxo do pedido** — WhatsApp direto

Contribuições que quebram essas premissas serão questionadas, não rejeitadas de cara.

## Como contribuir

### Reportar bug

1. Verifique se o bug já não foi reportado nas [Issues](https://github.com/eusougabrielgadelha/delivery-free/issues)
2. Abra uma nova issue usando o template **Bug report**
3. Inclua versão do WP, PHP, browser e passos claros pra reproduzir

### Sugerir feature

1. Abra uma issue usando o template **Feature request**
2. Descreva o problema real que a feature resolve (não a solução — o problema)
3. Aguarde discussão antes de implementar features grandes. Features pequenas (< 100 linhas) podem ir direto pra PR

### Contribuir com código

1. **Fork** o repositório
2. Crie um branch descritivo: `git checkout -b feat/kanban-filter-by-driver`
3. Faça as alterações seguindo o [padrão de código](#padrão-de-código)
4. Commit seguindo [Conventional Commits](https://www.conventionalcommits.org/pt-br/)
5. Push pro seu fork e abra um **Pull Request** usando o template
6. Aguarde review

### Contribuir sem código

Todas essas contribuições são bem-vindas:

- **Traduções** — arquivos `.po` em `/languages/`
- **Documentação** — melhorias no README, wiki, tutoriais
- **Design** — mockups pra novas features, ícones, ilustrações
- **QA** — testar features novas em restaurantes reais e reportar
- **Divulgação** — falar do projeto pra donos de restaurante

## Setup local

**Pré-requisitos:**
- WordPress 6.0+
- PHP 8.0+
- MySQL 5.7+
- Servidor local (XAMPP, Local, Laragon, MAMP ou Docker)

**Instalação:**
```bash
# 1. Fork + clone
git clone https://github.com/SEU-USUARIO/delivery-free.git

# 2. Mover pra pasta de temas
mv delivery-free /caminho/pro/wordpress/wp-content/themes/delivery-hub

# 3. Ativar tema no admin
# Aparência → Temas → Delivery Hub → Ativar

# 4. Popular dados de exemplo
# Menu lateral → Cardápio → Popular exemplos

# 5. Configurar
# Menu lateral → Config. da Loja
```

Antes de abrir PR, teste a mudança em pelo menos 2 fluxos ponta-a-ponta (cliente + admin).

## Padrão de código

### PHP

Seguimos [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/) com pequenas variações:

- Tabs pra indentação, não espaços
- `<?php` tag de abertura sempre
- Escape output com `esc_html()`, `esc_attr()`, `esc_url()`, `wp_kses_post()`
- Sanitize input com `sanitize_text_field()`, `intval()`, `absint()`, etc
- Nonces em todas as ações que mudam estado (`wp_verify_nonce`, `check_ajax_referer`)
- Prefixe funções/classes/hooks com `dh_` pra evitar colisão
- Docblocks curtos em cima de funções públicas ou não-triviais
- Nada de `require`/`include` direto — sempre pelo bootstrap em `functions.php`

**Nunca use:**
- `current_time( 'timestamp' )` — bugado no WP. Use `time()` + `wp_date()`
- `wp_date( 'D' )` pra dia da semana — retorna em português. Use `wp_date( 'N' )` (ISO 1-7)
- Query pesada em runtime sem cache — use `set_transient()` com TTL

### JavaScript

- Vanilla JS puro, sem jQuery quando possível (usar só onde já existe)
- Alpine.js no frontend público (leve, sem build)
- SortableJS apenas no admin
- Nada de bundler — o projeto tem que rodar direto sem `npm install`
- `const` > `let` > `var`
- Async/await > `.then()` chains

### CSS

- Sem preprocessador — CSS puro moderno (variáveis CSS, `color-mix`, `:has`)
- Prefixe classes com `dh-` pra evitar colisão
- Mobile-first (`min-width` media queries)
- Nada de `!important` a menos que sobrescrevendo estilos WP admin

### Commits

Seguimos [Conventional Commits](https://www.conventionalcommits.org/pt-br/):

```
<tipo>(<escopo opcional>): <descrição curta>

<corpo opcional explicando o porquê>

<footer opcional com breaking changes ou refs a issues>
```

**Tipos aceitos:**
- `feat` — nova funcionalidade
- `fix` — correção de bug
- `refactor` — melhoria de código sem mudar comportamento
- `docs` — mudanças em documentação
- `style` — formatação, whitespace
- `test` — adição/correção de testes
- `chore` — mudanças em build/config/deps
- `perf` — melhoria de performance
- `i18n` — traduções

**Exemplos:**
```
feat(pdv): adiciona filtro por entregador no Kanban

Kanban agora aceita filtro múltiplo por entregador ativo,
persistindo escolha no localStorage.

Closes #42
```

```
fix(store-hours): corrige TZ em next_opening_timestamp

current_time('timestamp') retorna offset dobrado no WP.
Trocado por time() + wp_date().
```

## Pull Request checklist

Antes de marcar seu PR como pronto pra review:

- [ ] Testei manualmente a mudança em fluxo cliente + admin
- [ ] Não introduzi dependências externas (npm/composer) sem discutir
- [ ] Segui padrão de código PHP/JS/CSS
- [ ] Commits seguem Conventional Commits
- [ ] Atualizei README/CHANGELOG se a mudança é visível pro usuário
- [ ] Adicionei/atualizei docblocks em funções novas
- [ ] Testei em pelo menos 1 navegador desktop + 1 mobile
- [ ] Não quebrei o timezone (testei com WP em `America/Sao_Paulo`)

## Fluxo de review

1. Um mantenedor faz review em até **7 dias**
2. Se houver ajustes, você tem **14 dias** pra atender antes do PR ser marcado como stale
3. PRs aprovados são mergeados pela pessoa que revisou
4. Cada release consolida PRs mergeados desde a última versão

## Áreas prioritárias

Se você quer contribuir mas não sabe por onde começar, essas são as **áreas mais impactantes** no momento:

- **Integração PIX** (Mercado Pago / Gerencianet / PagBank) — issue #TBD
- **Cupons de desconto** — issue #TBD
- **PWA instalável** com service worker offline
- **Traduções** — inglês, espanhol (LATAM)
- **Testes automatizados** (PHPUnit)
- **Documentação em vídeo** — tutoriais no YouTube

## Licença das contribuições

Ao enviar um PR, você concorda que suas contribuições serão licenciadas sob a mesma [licença do projeto](LICENSE) (PolyForm Noncommercial 1.0.0). O software não pode ser vendido, mas você pode cobrar por instalação, customização e suporte.

## Créditos

Cada contribuição significativa é reconhecida no [CHANGELOG.md](CHANGELOG.md) e no `Co-Authored-By` do commit de merge. Contribuidores recorrentes podem ser adicionados como mantenedores.

---

Dúvidas? Abra uma issue ou fale com [@eusougabrielgadelha](https://github.com/eusougabrielgadelha).
