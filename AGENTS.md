# Levita — Guia para Agentes

## Produto

**Levita** é o app web de finanças da família. Um **owner** cria a conta familiar, convida **dependents** e todos compartilham transações e categorias no mesmo `account_id`.

## Stack

- Laravel 10 + Inertia.js + Vue 3 + Tailwind (landing pública e app autenticado)
- MySQL + Eloquent
- Auth: Laravel Sanctum (sessão)
- Gráficos: Chart.js / vue-chartjs
- Idioma: pt-BR · Moeda: R$

## Glossário

| Termo | Significado |
|-------|-------------|
| Account | Conta familiar (`accounts`) |
| Owner | `users.role = owner` — controle total |
| Dependent | `users.role = dependent` — lança/vê; sem categorias/membros |
| Category | Categoria da conta |
| PaymentCard | Cartão (`payment_cards`) — crédito tem `closing_day`/`due_day` |
| CreditCardInvoice | Fatura do cartão de crédito (`credit_card_invoices`) |
| BankAccount | Conta bancária opcional (`bank_accounts`) — entradas e pagamento de fatura |
| InstallmentPlan | Compra parcelada (`installment_plans`) — gera N expenses |
| RecurringBill | Conta fixa (`recurring_bills`) — gera lançamentos `planned` |
| Transaction | `income`, `expense` ou `transfer` (pagamento de fatura, sem categoria) |

## Estrutura

```
app/Models/          Account, User, Category, BankAccount, PaymentCard, CreditCardInvoice,
                     InstallmentPlan, RecurringBill, Transaction
app/Services/        CreditCardInvoiceService, CreditCardPaymentService,
                     InstallmentPlanService, RecurringBillService
app/Policies/        RBAC
app/Http/Controllers/
resources/js/Pages/  Landing, Dashboard, Transactions, Installments, RecurringBills,
                     BankAccounts, PaymentCards, Categories, Members, Reports
resources/js/Layouts/AppLayout.vue
database/migrations/
.cursor/rules/
.cursor/skills/
```

## Regras de ouro

1. **Todo dado de negócio é isolado por `account_id`.** Nunca retornar registros de outra conta.
2. Preferir trait/scope `BelongsToAccount` + Policies em vez de filtros ad hoc.
3. Owner gerencia categorias e membros; dependent edita/exclui só as próprias transações, cartões, contas, parcelas e contas fixas.
4. UI em português; valores formatados em BRL.
5. Identidade visual: amarelo `#ffc107` primário; azul `#2563eb` só em CTAs.
6. Entradas usam conta bancária opcional; saídas usam forma de pagamento (e cartão quando aplicável).
7. **Despesa = compra.** Pagamento de fatura é `type=transfer` e **não** entra em totais de gasto.
8. Relatórios/dashboard somam só `status=confirmed` e `type` income/expense (nunca transfer).
9. Parcelas: UI do mês mostra só a parcela do período; detalhe da compra no plano.
10. Contas fixas: `planned` até confirmar; só confirmadas contam como gasto.
11. **Testes nunca usam o MySQL do app.** A suite força `sqlite :memory:` (`phpunit.xml` + `CreatesApplication`). Requer extensão `pdo_sqlite` (`php8.1-sqlite3`).

## Skills do projeto

- `add-feature` — loop model → migration → policy → controller → página Inertia → testes
- `rbac-check` — checklist owner vs dependent
- `analyze-module` — auditoria de segurança/`account_id`/UX

## Fluxo de trabalho

1. Ler `AGENTS.md` e a rule de domínio relevante.
2. Implementar com Form Requests + Policies.
3. Cobrir com feature test o isolamento de conta e o RBAC.
4. Não commitar `.env` nem secrets.
