---
name: rbac-check
description: Checklist de permissões owner vs dependent no Levita. Use ao revisar auth, policies ou telas com ações restritas.
---

# RBAC Check

## Verificar

1. Policy registrada e usada no controller (`authorize` ou middleware).
2. Dependent **não** acessa rotas de escrita de Category/Member.
3. Dependent **só** edita/exclui Transaction com `user_id === auth()->id()`.
4. Owner edita/exclui qualquer Transaction da mesma conta.
5. Frontend não mostra botões de ação proibidos (`role === 'owner'`).
6. Teste feature cobre o caso negativo (403).

## Matriz rápida

- Categorias write → owner
- Membros write → owner
- Transação write própria → ambos
- Transação write alheia → owner
