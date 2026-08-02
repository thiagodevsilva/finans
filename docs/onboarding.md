# Onboarding Levita — manual de tours (tutoriais)

Fonte da verdade para copy, seletores `data-tour` e fluxo sugerido.
Espelhado em `resources/js/tours/*.js` + registry em `resources/js/tours/registry.js`.

Na UI do produto o termo é **Tutorial** (ex.: Refazer tutorial). Internamente o código continua usando `tour` / Shepherd.

## Regras gerais

- Tour **sugerido**, nunca bloqueante.
- Cadastro durante o tutorial é **opcional** — o tom do copy deixa isso explícito.
- Tours de tela podem usar **dados fictícios** só no cliente (`useTourDemo`) — nunca persistem.
- Texto leve: 1–2 frases por step.
- Âncoras sempre via `data-tour="..."`, nunca classes CSS frágeis.
- Progresso ativo: `sessionStorage` (`levita.tour.active`, `levita.tour.step`).
- `first-setup` grava `users.onboarding_status` (`completed` / `skipped` / `null`).
- Tours de tela (`dashboard`, `transactions`, `bank-accounts`, `payment-cards`) **não** alteram `onboarding_status`.
- Entrada por tela: botão **Ajuda** no header mobile do `AppLayout` (à direita do nome Levita); resolve o tour pela rota atual via `pageTourByRoute`.
- **Tutorial completo** (`first-setup`): só via Sidebar / Perfil → **Refazer tutorial** (ou modal de boas-vindas no primeiro acesso). **Nunca** via botão Ajuda.

## Tours disponíveis

| id | Escopo | Demo | Persistência | Entrada |
|----|--------|------|--------------|---------|
| `first-setup` | Contas + Cartões (multipágina) | não | `onboarding_status` | Refazer tutorial / boas-vindas |
| `dashboard` | Só Dashboard | sim | não | Ajuda nesta tela |
| `transactions` | Lista + formulário de transações | sim | não | Ajuda nesta tela |
| `bank-accounts` | Só Contas | não | não | Ajuda nesta tela |
| `payment-cards` | Só Cartões | não | não | Ajuda nesta tela |

---

## Tour `first-setup`

Fluxo: Boas-vindas → nav Contas → Contas → Cartões.

### Modal (Dashboard)

| Elemento | Conteúdo |
|----------|----------|
| Título | Bem-vindo ao Levita |
| Texto | Seja bem-vindo… contas e cartões são representações (sem integração bancária). Cadastrar é opcional. |
| CTA | Sim, mostrar / Agora não |

### Steps

| id | Âncora | Título |
|----|--------|--------|
| `fs-welcome` | (central) | Bem-vindo ao Levita |
| `dash-nav-contas` | `nav-bank-accounts` (abre menu no mobile) | Comece pelas contas |
| … | ver `firstSetup.js` | … |

Ao **concluir** o first-setup, o app grava `onboarding_status=completed` e inicia o tour `dashboard` (demo).

---

## Tour `dashboard` (demo)

Números e lista fictícios via `demoDashboardData`. Banner “Modo demonstração”.

| id | Âncora | Título |
|----|--------|--------|
| `dash-intro` | `dash-page` | Seu painel do mês |
| `dash-period` | `dash-period` | Período |
| `dash-balance` | `dash-balance` | Saldo (caixa) |
| `dash-stats` | `dash-stats` | Saldo do mês e gastos |
| `dash-recurring` | `dash-recurring` | Contas fixas |
| `dash-recent` | `dash-recent` | Últimas transações |
| `dash-new` | `dash-new` | Nova transação |

Conceitos cobertos no copy: saldo de caixa (âncora + entradas − saídas de dinheiro); saldo do mês = entradas − gastos − investimentos; gastos no crédito vs débito; contas fixas por **valor**.

---

## Tour `transactions` (demo)

Lista com exemplos; no form o tipo/pagamento são forçados pelo step (`onShow`) e o submit fica bloqueado.

| id | Rota | Âncora | Título |
|----|------|--------|--------|
| `tx-intro` | index | `tx-page` | Transações |
| `tx-filters` | index | `tx-filters` | Filtros |
| `tx-list` | index | `tx-list` | Lista |
| `tx-add` | index | `tx-add` | Adicionar |
| `tx-types` | create | `tx-types` | Tipos de lançamento |
| `tx-expense` | create | `tx-types` | Saída (gasto) |
| `tx-payment` | create | `tx-payment` | Pagamento e cartão |
| `tx-bank` | create | `tx-bank` | Conta bancária |
| `tx-income` | create | `tx-types` | Entrada |
| `tx-investment` | create | `tx-types` | Investimento |
| `tx-transfer` | create | `tx-types` | Pagamento de fatura |
| `tx-done` | create | `tx-submit` | Salvar de verdade |

---

## Tour `bank-accounts`

Mesmos pontos da etapa Contas do first-setup, sem navegar para Cartões. Ver `bankAccounts.js`.

---

## Tour `payment-cards`

Mesmos pontos da etapa Cartões do first-setup, só nesta tela (abre o formulário). Ver `paymentCards.js`.

---

## Padrão para novos tours

1. Criar `resources/js/tours/<nome>.js` com `{ id, label, persistOnboarding, useDemo, steps }`.
2. Registrar em `registry.js` (+ `pageTourByRoute` se houver Ajuda na tela). **Não** mapear `first-setup` em `pageTourByRoute`.
3. Documentar aqui.
4. Âncoras `data-tour` + `TourHelpButton` / `?tour=<id>` + `resumeIfActive`.
5. Se `useDemo: true`, consumir `useTourDemo` na página.

```js
{
  id: 'exemplo',
  route: 'dashboard',
  query: { tour: 'dashboard' },
  attachTo: { element: '[data-tour="dash-stats"]', on: 'bottom' },
  title: 'Título curto',
  text: 'Uma ou duas frases.',
  onShow: ({ setFormType, setPaymentSelection }) => { /* opcional */ },
}
```

---

## Gaps observados (ao montar os tours)

Itens para eventual melhoria de produto/UX — não bloqueiam o tutorial:

1. **Investimento vs gasto** — diferença sutil; usuários leigos podem lançar aporte como saída.
2. **Pagamento de fatura** — tipo `transfer` com label “Pagamento de fatura” ainda confunde com transferência entre contas (não existe esse tipo).
3. **Conta bancária opcional** — em vários fluxos some/aparece conforme PIX/débito; pode parecer bug.
4. **Cartão no select de pagamento** — cartões misturados com dinheiro/PIX/débito no mesmo `<select>`; o tour cobre, mas a hierarquia visual é densa no mobile.
5. **Dashboard vazio** — sem demo, o bloco de contas fixas some (`v-if`); o tour força a exibição com dados fictícios.
6. **Ajuda por tela** — Contas fixas, Relatórios e Dependentes ainda sem tour dedicado.

---

## Fora do escopo atual

Tours de Contas fixas, Relatórios, Dependentes; variantes por role; forçar criação antes de avançar.
