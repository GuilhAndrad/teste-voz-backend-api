# Teste Voz — Backend API

API REST em Laravel 13 para gerenciamento de **Categorias** e **Produtos**, com paginação, busca, versionamento de rotas e testes automatizados via Pest.

> 📘 Quer entender o **porquê** das decisões de arquitetura (Observers, Cast customizado, Form Requests, API Resources, etc.)? Veja o [ARQUITETURA.md](./ARQUITETURA.md).

---

## Sumário

- [Stack utilizada](#stack-utilizada)
- [Pré-requisitos](#pré-requisitos)
- [Instalação](#instalação)
  - [Opção 1: Docker (recomendado)](#opção-1-docker-recomendado)
  - [Opção 2: Ambiente local (PHP + Composer)](#opção-2-ambiente-local-php--composer)
- [Rodando os testes](#rodando-os-testes)
- [Qualidade de código](#qualidade-de-código)
- [Endpoints da API](#endpoints-da-api)
  - [Health Check](#health-check)
  - [Categorias](#categorias)
  - [Produtos](#produtos)
- [Paginação](#paginação)
- [Busca (search)](#busca-search)
- [Includes (relacionamentos sob demanda)](#includes-relacionamentos-sob-demanda)
- [Rate limiting](#rate-limiting)
- [Tratamento de erros](#tratamento-de-erros)
- [Coleção Postman](#coleção-postman)

---

## Stack utilizada

| Tecnologia | Uso |
|---|---|
| PHP 8.3+ | Linguagem |
| Laravel 13 | Framework |
| Pest | Testes automatizados |
| Larastan (PHPStan nível 5) | Análise estática |
| Laravel Pint | Padronização de código (code style) |
| SQLite (local) / PostgreSQL 16 (Docker) | Banco de dados |
| Docker + Docker Compose | Containerização |

---

## Pré-requisitos

Escolha **uma** das opções de instalação abaixo. Você não precisa instalar tudo.

- **Docker:** Docker e Docker Compose instalados.
- **Local:** PHP 8.3+, Composer 2, extensão `pdo_sqlite` (ou `pdo_pgsql` se preferir usar Postgres localmente).

---

## Instalação

### Opção 1: Docker (recomendado)

Sobe a API já conectada a um banco PostgreSQL, sem precisar instalar PHP na sua máquina.

```bash
# 1. Clone o repositório
git clone <https://github.com/GuilhAndrad/teste-voz-backend-api.git>
cd teste-voz-backend-api

# 2. Copie o arquivo de ambiente
cp .env.example .env

# 3. No .env, ajuste a conexão para usar o Postgres do docker-compose
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=teste_voz
DB_USERNAME=postgres
DB_PASSWORD=postgres

# 4. Suba os containers
docker compose up -d --build

# 5. Gere a chave da aplicação
docker compose exec app php artisan key:generate

# 6. Rode as migrations
docker compose exec app php artisan migrate
```

A API estará disponível em `http://localhost:8000`.

> O `docker-compose.yaml` já sobe dois serviços: `app` (PHP 8.4 + `artisan serve`, porta 8000) e `postgres` (Postgres 16, porta 5432), com healthcheck garantindo que a aplicação só sobe depois do banco estar pronto.

### Opção 2: Ambiente local (PHP + Composer)

Usa SQLite por padrão — mais rápido para rodar e testar sem depender de um banco externo.

```bash
# 1. Clone o repositório
git clone <https://github.com/GuilhAndrad/teste-voz-backend-api.git>
cd teste-voz-backend-api

# 2. Instale as dependências
composer install

# 3. Rode o script de setup (copia o .env, gera a key, cria o sqlite e roda as migrations)
composer run setup

# 4. Suba o servidor
php artisan serve
```

A API estará disponível em `http://localhost:8000`.

> O comando `composer run setup` automatiza: cópia do `.env.example` para `.env`, `php artisan key:generate`, criação do arquivo `database/database.sqlite` e `php artisan migrate --force`. É o caminho mais rápido para começar a testar a API.

---

## Rodando os testes

O projeto usa [Pest](https://pestphp.com/) para testes de feature e unitários.

```bash
php artisan test
# ou, via Docker:
docker compose exec app php artisan test
```

Os testes cobrem: CRUD de Categorias e Produtos, validações, health check do banco, o cast de dinheiro (`MoneyCast`) e os logs disparados pelos Observers.

## Qualidade de código

```bash
# Verifica o code style (Laravel Pint)
vendor/bin/pint --test

# Roda a análise estática (Larastan / PHPStan nível 5)
vendor/bin/phpstan analyse

# Roda tudo de uma vez (style + análise estática + testes), igual ao CI
composer test
```

---

## Endpoints da API

Todas as rotas de recurso vivem sob o prefixo `/api/v1` e respondem em **JSON**. Todas as respostas dessas rotas incluem o header `X-Api-Version: v1`.

### Health Check

| Método | Rota | Descrição |
|---|---|---|
| `GET` | `/api/health-db` | Verifica se a aplicação consegue se conectar ao banco de dados. |

**Resposta `200 OK` (banco disponível):**
```json
{
  "status": "ok",
  "database": "up",
  "timestamp": "2026-08-30T12:00:00+00:00"
}
```

**Resposta `503 Service Unavailable` (banco indisponível):**
```json
{
  "status": "degraded",
  "database": "down",
  "timestamp": "2026-08-30T12:00:00+00:00"
}
```

---

### Categorias

Recurso REST completo em `/api/v1/categorias`.

| Método | Rota | Descrição |
|---|---|---|
| `GET` | `/api/v1/categorias` | Lista categorias (paginado, aceita `search` e `per_page`). |
| `POST` | `/api/v1/categorias` | Cria uma nova categoria. |
| `GET` | `/api/v1/categorias/{id}` | Exibe uma categoria específica. |
| `PUT`/`PATCH` | `/api/v1/categorias/{id}` | Atualiza uma categoria existente. |
| `DELETE` | `/api/v1/categorias/{id}` | Remove uma categoria (e seus produtos, em cascata). |

**Regras de validação (`nome`):** obrigatório, string, até 255 caracteres, único entre as categorias.

**Exemplo — criar categoria**
```bash
curl -X POST http://localhost:8000/api/v1/categorias \
  -H "Content-Type: application/json" \
  -d '{"nome": "Eletrônicos"}'
```

**Resposta `201 Created`:**
```json
{
  "data": {
    "id": 1,
    "nome": "Eletrônicos",
    "created_at": "2026-08-30T12:00:00.000000Z",
    "updated_at": "2026-08-30T12:00:00.000000Z"
  }
}
```

> O campo `produtos` só aparece na resposta se você pedir explicitamente via `?include=produtos` (veja [Includes](#includes-relacionamentos-sob-demanda)).

---

### Produtos

Recurso REST completo em `/api/v1/produtos`.

| Método | Rota | Descrição |
|---|---|---|
| `GET` | `/api/v1/produtos` | Lista produtos (paginado, aceita `search` e `per_page`), já vem com a categoria carregada. |
| `POST` | `/api/v1/produtos` | Cria um novo produto. |
| `GET` | `/api/v1/produtos/{id}` | Exibe um produto específico. |
| `PUT`/`PATCH` | `/api/v1/produtos/{id}` | Atualiza um produto existente. |
| `DELETE` | `/api/v1/produtos/{id}` | Remove um produto. |

**Regras de validação:**

| Campo | Regras |
|---|---|
| `nome` | obrigatório, string, até 255 caracteres |
| `descricao` | opcional, string |
| `preco` | obrigatório, numérico, maior ou igual a 0 |
| `categoria_id` | obrigatório, deve existir na tabela `categorias` |

**Exemplo — criar produto**
```bash
curl -X POST http://localhost:8000/api/v1/produtos \
  -H "Content-Type: application/json" \
  -d '{
    "nome": "Notebook",
    "descricao": "Notebook 15 polegadas",
    "preco": 3500.90,
    "categoria_id": 1
  }'
```

**Resposta `201 Created`:**
```json
{
  "data": {
    "id": 1,
    "nome": "Notebook",
    "descricao": "Notebook 15 polegadas",
    "preco": "3500.90",
    "categoria_id": 1,
    "created_at": "2026-08-30T12:00:00.000000Z",
    "updated_at": "2026-08-30T12:00:00.000000Z"
  }
}
```

> Note que `preco` sempre volta como string formatada com 2 casas decimais (`"3500.90"`), independente do formato enviado na requisição. Isso é feito por um cast customizado — veja detalhes no [ARQUITETURA.md](./ARQUITETURA.md#moneycast).

---

## Paginação

As rotas `index` (listagem) de Categorias e Produtos são paginadas por padrão.

| Query param | Padrão | Máximo | Descrição |
|---|---|---|---|
| `per_page` | 15 | 100 | Quantidade de itens por página. |
| `page` | 1 | — | Número da página (padrão do paginator do Laravel). |

```bash
curl "http://localhost:8000/api/v1/produtos?per_page=5&page=2"
```

A resposta segue o formato padrão de paginação de resource collections do Laravel, incluindo os blocos `links` e `meta` (com `current_page`, `last_page`, `total`, etc.), além do `data`.

## Busca (search)

Categorias e Produtos podem ser filtrados por nome com o parâmetro `search` (case-insensitive, busca parcial):

```bash
curl "http://localhost:8000/api/v1/produtos?search=notebook"
```

## Includes (relacionamentos sob demanda)

Para evitar respostas infladas, os relacionamentos só são incluídos quando pedidos explicitamente via `?include=`:

```bash
# Traz a categoria dentro de cada produto
curl "http://localhost:8000/api/v1/produtos?include=categoria"

# Traz os produtos dentro de cada categoria
curl "http://localhost:8000/api/v1/categorias?include=produtos"
```

> Esse parâmetro só tem efeito em requisições `GET`. Em `POST`, `PUT` e `DELETE` o relacionamento correspondente já vem sempre incluído na resposta (por exemplo, criar um produto já retorna a categoria junto).

## Rate limiting

Todas as rotas de recurso (`categorias` e `produtos`) estão sob o limite de **60 requisições por minuto** por IP (ou por usuário autenticado, se aplicável). Ao estourar o limite, a API responde `429 Too Many Requests`.

## Tratamento de erros

| Situação | Status | Corpo da resposta |
|---|---|---|
| Registro não encontrado (`{id}` inválido) | `404` | `{"message": "Registro não encontrado."}` |
| Falha de validação | `422` | `{"message": "...", "errors": {"campo": ["mensagem"]}}` |
| Limite de requisições excedido | `429` | — |
| Banco de dados indisponível (no health check) | `503` | `{"status": "degraded", "database": "down", ...}` |

## Coleção Postman

Uma coleção pronta com todos os endpoints está em [`postman/teste-voz-backend.postman_collection.json`](./postman/teste-voz-backend.postman_collection.json). Basta importar no Postman ou Insomnia.