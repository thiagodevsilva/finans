# Levita — Guia para Agentes

## Produto

**Levita** é o app web de finanças da família. Um **owner** cria a conta familiar, convida **dependents** e todos compartilham transações e categorias no mesmo `account_id`.

## Stack

- Laravel 10 + Blade (landing pública)
- Inertia.js + Vue 3 + Tailwind (app autenticado)
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
| Transaction | Entrada (`income`) ou saída (`expense`) |

## Estrutura

```
app/Models/          Account, User, Category, Transaction
app/Policies/        RBAC
app/Http/Controllers/
resources/views/     Blade (landing)
resources/js/Pages/  Inertia Vue (Dashboard, Transactions, Categories, Members, Reports)
resources/js/Layouts/AppLayout.vue
database/migrations/
.cursor/rules/       Convenções persistentes
.cursor/skills/      Workflows reutilizáveis
```

## Regras de ouro

1. **Todo dado de negócio é isolado por `account_id`.** Nunca retornar registros de outra conta.
2. Preferir trait/scope `BelongsToAccount` + Policies em vez de filtros ad hoc.
3. Owner gerencia categorias e membros; dependent edita/exclui só as próprias transações.
4. UI em português; valores formatados em BRL.
5. Identidade visual: amarelo `#ffc107` primário; azul `#2563eb` só em CTAs.

## Skills do projeto

- `add-feature` — loop model → migration → policy → controller → página Inertia → testes
- `rbac-check` — checklist owner vs dependent
- `analyze-module` — auditoria de segurança/`account_id`/UX

## Fluxo de trabalho

1. Ler `AGENTS.md` e a rule de domínio relevante.
2. Implementar com Form Requests + Policies.
3. Cobrir com feature test o isolamento de conta e o RBAC.
4. Não commitar `.env` nem secrets.
