---
name: analyze-module
description: Audita um módulo do Levita (segurança account_id, RBAC, UX pt-BR). Use ao revisar Controllers, Policies ou Pages de um domínio.
---

# Analyze Module

## Passos

1. Listar rotas e controllers do módulo.
2. Confirmar filtro/`BelongsToAccount` em toda query.
3. Rodar mentalmente a matriz RBAC (skill `rbac-check`).
4. Checar props Inertia: dados mínimos, nomes em pt-BR na UI.
5. Listar gaps (falta teste, leak, UX, validação).

## Saída

Relatório curto:

- **Seguro?** sim/não + evidência
- **RBAC?** gaps
- **UX?** gaps
- **Próximos passos** (máx. 5 bullets)
