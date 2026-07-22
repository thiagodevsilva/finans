# Levita

App web de finanças da família — conta compartilhada entre dono e dependentes.

## Stack

- Laravel 10 + Blade (landing)
- Inertia.js + Vue 3 + Tailwind
- MySQL + Eloquent
- Sanctum (sessão)
- Chart.js
- Docker (PHP 8.3 + Nginx + MySQL)

## Subir com Docker (VPS / local)

Requisitos na máquina: só **Docker** + **Docker Compose**.

```bash
git clone <seu-repo> levita
cd levita
cp .env.example .env
# edite APP_URL, senhas DB_*, APP_KEY (ou deixe o container gerar)
nano .env

docker compose up -d --build
```

Acesse `http://SEU_IP` (porta `APP_PORT`, padrão 80).

O container:

- instala vendor + build do frontend na imagem
- espera o MySQL
- gera `APP_KEY` se estiver vazio
- roda `migrate`
- sobe Nginx + PHP-FPM

### Comandos úteis

```bash
docker compose logs -f app
docker compose exec app php artisan migrate
docker compose exec app php artisan tinker
docker compose down
docker compose up -d --build   # após mudanças no código
```

### `.env` importante no Docker

```env
APP_URL=http://seu-dominio-ou-ip
DB_HOST=mysql
DB_DATABASE=levita
DB_USERNAME=levita
DB_PASSWORD=secret
DB_ROOT_PASSWORD=root
APP_PORT=80
```

`DB_HOST=mysql` é o nome do serviço no Compose — não use `127.0.0.1` dentro dos containers.

## Setup sem Docker (dev)

```bash
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate
npm install && npm run build
php artisan serve
```

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
