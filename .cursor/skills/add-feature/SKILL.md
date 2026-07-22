---
name: add-feature
description: Adiciona uma feature completa no Levita (model, migration, policy, controller, página Inertia, testes). Use ao criar módulos ou CRUDs novos no app.
---

# Add Feature (Levita)

## Loop

1. **Model + migration** — incluir `account_id` se for dado da conta; índices.
2. **Policy** — owner vs dependent conforme `levita-domain`.
3. **Form Request** — validação pt-BR; garantir `category_id`/`user_id` da mesma conta.
4. **Controller** — queries com scope de conta; `Inertia::render` ou redirect.
5. **Rotas** — `auth` middleware; `can`/`authorize` onde couber.
6. **Página Vue** — em `resources/js/Pages/...`; usar `AppLayout`; pt-BR + R$.
7. **Feature test** — isolamento entre accounts + permissão do role.

## Checklist final

- [ ] Nenhum leak de `account_id`
- [ ] Dependent bloqueado se a feature for só owner
- [ ] UI esconde botões que o role não pode usar
