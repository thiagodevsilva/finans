# Levita

App web de finanças da família — conta compartilhada entre dono e dependentes.

## Stack

- Laravel 10 + Blade (landing)
- Inertia.js + Vue 3 + Tailwind
- MySQL + Eloquent
- Sanctum (sessão)
- Chart.js

## Requisitos

- PHP 8.1+
- Composer
- Node.js 18+
- MySQL 8

## Setup

```bash
cp .env.example .env
# ajuste DB_* no .env
composer install
php artisan key:generate
php artisan migrate
npm install
npm run build
php artisan serve
```

Em outro terminal (dev com hot reload):

```bash
npm run dev
```

Acesse `http://localhost:8000`.

### Docker MySQL (exemplo local)

```bash
docker run -d --name levita-mysql \
  -e MYSQL_DATABASE=levita \
  -e MYSQL_USER=levita \
  -e MYSQL_PASSWORD=secret \
  -e MYSQL_ROOT_PASSWORD=root \
  -p 3306:3306 mysql:8.0
```

## Papéis

| Papel | Pode |
|-------|------|
| Owner | Tudo (categorias, membros, qualquer transação) |
| Dependent | Ver tudo; lançar; editar/excluir só as próprias transações |

## Testes

```bash
php artisan test
```

## Agentes Cursor

Ver `AGENTS.md` e `.cursor/rules` / `.cursor/skills` para convenções e loops de feature/RBAC.
