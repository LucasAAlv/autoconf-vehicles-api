# autoconf-vehicles-api

Back-end Laravel 12 de um desafio técnico SaaS multiusuário para gestão de veículos.

## Requisitos

- PHP 8.3+
- Composer
- PostgreSQL 17
- Docker (opcional, recomendado)

## Configuração

### 1. Instalar dependências

```bash
composer install
```

### 2. Variáveis de ambiente

```bash
cp .env.example .env
php artisan key:generate
```

Edite o `.env` com as credenciais do banco de dados PostgreSQL.

### 3. Banco de dados

```bash
php artisan migrate
```

### 4. Link de storage público (passo obrigatório)

O upload de imagens usa o disco `public` do Laravel. Para que os arquivos enviados
fiquem acessíveis em `/storage/...`, é necessário criar o symlink de `public/storage`
apontando para `storage/app/public`:

```bash
php artisan storage:link
```

> Ao rodar via Docker, o `docker/entrypoint.sh` executa `storage:link --force`
> automaticamente antes de subir o servidor.

### 5. Iniciar o servidor de desenvolvimento

```bash
php artisan serve
```

A API ficará disponível em `http://localhost:8000`.

## Docker

```bash
docker compose --env-file .env -f docker/docker-compose.yml up --build
```

Isso sobe a aplicação (`app`) e o PostgreSQL (`db`). O `app` monta a raiz do
repositório em `/var/www/html` e lê o `APP_KEY` e demais variáveis do `.env`
da raiz — garanta que ele exista (`cp .env.example .env && php artisan key:generate`)
antes de subir o stack.

## Testes

```bash
composer test
# ou, direto:
vendor/bin/pest
```

Os testes rodam contra PostgreSQL real, em um segundo banco (`autoconf_vehicles_test`) —
SQLite não é suportado porque o índice único parcial da invariante de capa de imagem não
existe nesse driver, e testar essa invariante em SQLite daria falso positivo. Toda a
configuração do ambiente de teste vive em `phpunit.xml` (não há `.env.testing`); veja
`.claude/context/testing.md` para o porquê e para as convenções da suíte.

`autoconf_vehicles_test` precisa existir no mesmo servidor Postgres usado em
desenvolvimento, antes de rodar a suíte:

- **Volume novo** (primeira vez subindo o stack Docker): nada a fazer — o `db` já monta
  `docker/initdb` em `/docker-entrypoint-initdb.d`, e o Postgres roda o script
  `10-create-test-database.sh` de lá automaticamente na primeira inicialização de um data
  directory vazio.
- **Volume já existente** (banco já rodando, o caso comum no dia a dia):
  `/docker-entrypoint-initdb.d` não roda de novo contra um volume com dados. Crie o banco
  manualmente uma vez:

  ```bash
  docker exec autoconf-vehicles-api-db-1 psql -U postgres -c 'CREATE DATABASE autoconf_vehicles_test OWNER postgres'
  ```

Outros scripts úteis: `composer test:unit`, `composer test:feature`, `composer lint`
(Pint, corrige) e `composer lint:test` (Pint, só verifica).

## Stack

- PHP 8.3+, Laravel 12
- Autenticação: Sanctum (cookies HttpOnly)
- Banco: PostgreSQL 17
- Respostas de erro: RFC 7807 (`application/problem+json`)
