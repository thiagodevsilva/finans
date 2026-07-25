# Onboarding Levita — manual de tours

Fonte da verdade para copy, seletores `data-tour` e fluxo sugerido.
Espelhado em `resources/js/tours/*.js` + registry em `resources/js/tours/registry.js`.

## Regras gerais

- Tour **sugerido**, nunca bloqueante.
- Cadastro durante o guia é **opcional** — o tom do copy deixa isso explícito.
- Tours de tela podem usar **dados fictícios** só no cliente (`useTourDemo`) — nunca persistem.
- Texto leve: 1–2 frases por step.
- Âncoras sempre via `data-tour="..."`, nunca classes CSS frágeis.
- Progresso ativo: `sessionStorage` (`levita.tour.active`, `levita.tour.step`).
- `first-setup` grava `users.onboarding_status` (`completed` / `skipped` / `null`).
- Tours de tela (`dashboard`, `transactions`) **não** alteram `onboarding_status`.
- Entrada por tela: botão **Ajuda** no header mobile do `AppLayout` (à direita do nome Levita); resolve o tour pela rota atual.
- Reabrir guia Contas/Cartões: Sidebar / Perfil → **Refazer guia**.

## Tours disponíveis

| id | Escopo | Demo | Persistência |
|----|--------|------|--------------|
| `first-setup` | Contas + Cartões (multipágina) | não | `onboarding_status` |
| `dashboard` | Só Dashboard | sim | não |
| `transactions` | Lista + formulário de transações | sim | não |

---

## Tour `first-setup`

Fluxo: Boas-vindas → nav Contas → Contas → Cartões.

### Modal (Dashboard)

| Elemento | Conteúdo |
|----------|----------|
| Título | Bem-vindo ao Levita |
| Texto | Um passeio rápido pelas telas de contas e cartões. Você não precisa cadastrar nada agora — pode só conhecer o fluxo e criar depois, no seu tempo. |
| CTA | Sim, mostrar / Agora não |

### Steps

Ver `resources/js/tours/firstSetup.js` (espelho).

---

## Tour `dashboard` (demo)

Números e lista fictícios via `demoDashboardData`. Banner “Modo demonstração”.

| id | Âncora | Título |
|----|--------|--------|
| `dash-intro` | `dash-page` | Seu painel do mês |
| `dash-period` | `dash-period` | Período |
| `dash-stats` | `dash-stats` | Saldo, entradas e saídas |
| `dash-cash` | `dash-cash` | Fluxo de caixa |
| `dash-invest` | `dash-invest` | Investimentos |
| `dash-invoices` | `dash-invoices` | Faturas |
| `dash-recurring` | `dash-recurring` | Contas fixas |
| `dash-recent` | `dash-recent` | Últimas transações |
| `dash-new` | `dash-new` | Nova transação |

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
| `tx-expense` | create | `tx-types` | Saída |
| `tx-payment` | create | `tx-payment` | Pagamento e cartão |
| `tx-bank` | create | `tx-bank` | Conta bancária |
| `tx-income` | create | `tx-types` | Entrada |
| `tx-investment` | create | `tx-types` | Investimento |
| `tx-transfer` | create | `tx-types` | Pagamento de fatura |
| `tx-done` | create | `tx-submit` | Salvar de verdade |

---

## Padrão para novos tours

1. Criar `resources/js/tours/<nome>.js` com `{ id, label, persistOnboarding, useDemo, steps }`.
2. Registrar em `registry.js` (+ `pageTourByRoute` se houver Ajuda na tela).
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

Itens para eventual melhoria de produto/UX — não bloqueiam o guia:

1. **Saldo vs fluxo de caixa** — conceitos fáceis de misturar; o tour explica, mas a UI ainda não tem tooltip permanente.
2. **Investimento vs Saída** — diferença sutil; usuários leigos podem lançar aporte como saída.
3. **Pagamento de fatura** — tipo `transfer` com label “Pagamento de fatura” ainda confunde com transferência entre contas (não existe esse tipo).
4. **Conta bancária opcional** — em vários fluxos some/aparece conforme PIX; pode parecer bug.
5. **Cartão no select de pagamento** — cartões misturados com dinheiro/PIX no mesmo `<select>`; o tour cobre, mas a hierarquia visual é densa no mobile.
6. **Dashboard vazio** — sem demo, faturas/contas fixas/investimentos somem (`v-if`); o tour força a exibição com dados fictícios.
7. **Ajuda por tela** — Contas fixas, Relatórios e Dependentes ainda sem tour dedicado.

---

## Fora do escopo atual

Tours de Contas fixas, Relatórios, Dependentes; variantes por role; forçar criação antes de avançar.
