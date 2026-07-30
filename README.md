# Levita

App web de finanças da família — conta compartilhada entre dono e dependentes.

## Stack

- Laravel 10 + Blade (landing)
- Inertia.js + Vue 3 + Tailwind
- MySQL + Eloquent
- Sanctum (sessão)
- Chart.js
- Docker (PHP 8.3 + Nginx + MySQL) — usado na **VPS**; no dia a dia local o fluxo padrão é sem Docker

## Desenvolvimento local (sem Docker)

Este é o fluxo normal no ambiente de desenvolvimento: PHP/Composer/Node na máquina + MySQL local (`127.0.0.1`).

```bash
cp .env.example .env   # se ainda não tiver .env
# DB_HOST=127.0.0.1  |  DB_USERNAME / DB_PASSWORD do seu MySQL local
composer install
php artisan key:generate
php artisan migrate
npm install && npm run build   # assets de produção; para HMR no front: npm run dev (em outro terminal)
php artisan serve
```

Acesse **http://127.0.0.1:8000** (porta padrão do `artisan serve`).

- Sem Docker, **não precisa** de `npm run dev` no dia a dia: um `npm run build` gera os arquivos em `public/build`.
- Use `npm run dev` só quando estiver mexendo em Vue/CSS e quiser hot reload.

`.env` típico no local:

```env
APP_URL=http://127.0.0.1:8000
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=levita
DB_USERNAME=root
DB_PASSWORD=sua_senha_local
```

Use a mesma origem no navegador e no `APP_URL` (`127.0.0.1` vs `localhost` são origens diferentes). Se o `serve` estiver em outra porta (`8001`), ajuste `APP_URL`.

Não use `DB_HOST=mysql` nem `APP_PORT` nesse modo — isso é só para Compose.

## Docker (VPS / deploy)

Requisitos: **Docker** + **Docker Compose**. Ideal para servidor; no local só se quiser espelhar o deploy.

```bash
cp .env.example .env
# APP_URL=http://SEU_IP_OU_DOMINIO
# DB_HOST=mysql  |  DB_USERNAME=levita (não use root)
# APP_PORT=80

docker compose up -d --build
```

Acesse `http://SEU_IP` (porta `APP_PORT`, padrão 80).

O container: instala vendor + build do frontend, espera o MySQL, gera `APP_KEY` se vazio, roda `migrate`, sobe Nginx + PHP-FPM.

### `.env` no Docker

```env
APP_URL=http://seu-dominio-ou-ip
DB_HOST=mysql
DB_DATABASE=levita
DB_USERNAME=levita
DB_PASSWORD=secret
DB_ROOT_PASSWORD=root
APP_PORT=80
APP_FORCE_ROOT_URL=true
```

`DB_HOST=mysql` é o nome do serviço no Compose. `DB_USERNAME` **não** pode ser `root`.

### Comandos úteis (Docker)

```bash
docker compose logs -f app
docker compose exec app php artisan migrate
docker compose exec app php artisan tinker
docker compose down
docker compose up -d --build
```

### Se `docker compose up` falhar

- **Conflict `/levita-mysql`**: `docker compose down` e, se precisar, `docker rm -f levita-mysql levita-app`
- **Porta em uso**: mude `APP_PORT` e `APP_URL` no `.env`
- **Tela em branco com porta ≠ 80**: `APP_URL` deve incluir a porta pública (ex. `http://localhost:8888`)

## Papéis

| Papel | Pode |
|-------|------|
| Owner | Tudo (categorias, membros, qualquer transação) |
| Dependent | Ver tudo; lançar; editar/excluir só as próprias |

## Testes

```bash
php artisan test
```

## Agentes Cursor

Ver `AGENTS.md` e `.cursor/rules` / `.cursor/skills`.
