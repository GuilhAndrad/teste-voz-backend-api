# Arquitetura e Conceitos

Este documento explica **como** e **por que** a API foi construída da forma que está — pensado tanto para quem vai avaliar o código quanto para quem está retomando contato com desenvolvimento backend e quer entender a lógica por trás de cada decisão.

A ideia não é só listar "o que tem no projeto", mas explicar o raciocínio: que problema cada peça resolve, e por que ela mora onde mora.

---

## Sumário

1. [Visão geral da arquitetura](#1-visão-geral-da-arquitetura)
2. [Estrutura de pastas](#2-estrutura-de-pastas)
3. [O fluxo de uma requisição](#3-o-fluxo-de-uma-requisição)
4. [Form Requests: validação fora do Controller](#4-form-requests-validação-fora-do-controller)
5. [API Resources: controlando o formato da resposta](#5-api-resources-controlando-o-formato-da-resposta)
6. [Eloquent Models e Attributes do PHP 8](#6-eloquent-models-e-attributes-do-php-8)
7. [MoneyCast: um Custom Cast do Eloquent](#7-moneycast-um-custom-cast-do-eloquent)
8. [Observers: reagindo a eventos do Model](#8-observers-reagindo-a-eventos-do-model)
9. [Traits reutilizáveis nos Controllers](#9-traits-reutilizáveis-nos-controllers)
10. [Versionamento de API](#10-versionamento-de-api)
11. [Tratamento centralizado de exceções](#11-tratamento-centralizado-de-exceções)
12. [Rate Limiting](#12-rate-limiting)
13. [Qualidade de código: Pint, PHPStan/Larastan e Pest](#13-qualidade-de-código-pint-phpstanlarastan-e-pest)
14. [Docker: por que dois serviços](#14-docker-por-que-dois-serviços)
15. [Boas práticas aplicadas — resumo](#15-boas-práticas-aplicadas--resumo)

---

## 1. Visão geral da arquitetura

Esta é uma **API RESTful "pura"** (sem views, sem sessão de navegador) construída em Laravel, seguindo o padrão arquitetural que o próprio framework incentiva: uma variação de **MVC** adaptada para APIs, onde a "View" tradicional é substituída por classes que formatam JSON (os **API Resources**).

O fluxo de responsabilidades é dividido assim:

```
Requisição HTTP
      │
      ▼
  Middleware (versionamento, rate limit)
      │
      ▼
  Form Request (valida e autoriza a entrada)
      │
      ▼
  Controller (orquestra: chama o Model, decide o que responder)
      │
      ▼
  Model / Eloquent (regras de negócio simples, casts, relacionamentos)
      │
      ├──► Observer (efeitos colaterais: log, cache)
      │
      ▼
  API Resource (formata a saída em JSON)
      │
      ▼
  Resposta HTTP
```

Cada uma dessas camadas tem **uma responsabilidade só**. Essa separação é o conceito mais importante do projeto: cada classe deve ter um motivo só para mudar (é a ideia central do **S** de SOLID — *Single Responsibility Principle*).

---

## 2. Estrutura de pastas

```
app/
├── Casts/              # Conversões customizadas de tipo (ex.: dinheiro)
├── Http/
│   ├── Controllers/
│   │   ├── Api/
│   │   │   ├── HealthController.php
│   │   │   └── V1/                 # Controllers da versão 1 da API
│   │   └── Concerns/                # Traits compartilhadas entre controllers
│   ├── Middleware/
│   ├── Requests/Api/V1/             # Form Requests (validação) por versão
│   └── Resources/Api/V1/            # API Resources (formatação de saída) por versão
├── Models/              # Entidades Eloquent (Categoria, Produto, User)
├── Observers/           # Reações a eventos do ciclo de vida do Model
└── Providers/           # Configuração de serviços (ex.: rate limiter)
```

Repare no padrão `Api/V1/` se repetindo em Controllers, Requests e Resources. Isso não é acidental — é o que permite, no futuro, criar uma pasta `V2/` inteira sem tocar em uma linha da V1 (mais detalhes na seção [Versionamento](#10-versionamento-de-api)).

---

## 3. O fluxo de uma requisição

Peguemos como exemplo `POST /api/v1/produtos`, o caminho mais completo do sistema:

1. **`routes/api.php`** registra a rota dentro do grupo `v1`, que aplica o middleware `AddApiVersionHeader` e o `throttle:60,1`.
2. O Laravel resolve `Route::apiResource('produtos', ProdutoController::class)`, que mapeia automaticamente os 7 métodos REST padrão (`index`, `store`, `show`, `update`, `destroy`, etc.) sem precisar declarar rota por rota.
3. Antes de chegar no método `store()` do Controller, o Laravel faz a **injeção de dependência** de `StoreProdutoRequest` — e como essa classe estende `FormRequest`, a validação roda **automaticamente**, antes de qualquer linha do Controller ser executada.
4. Se a validação falhar, o fluxo já para ali e o Laravel devolve `422 Unprocessable Content` — o Controller nunca chega a ser executado.
5. Se passar, o Controller chama `Produto::create($request->validated())` — repare que `validated()` retorna **só** os campos validados, nunca o payload bruto (isso evita que campos não previstos ("mass assignment" indesejado) sejam salvos).
6. O `Model` roda o `MoneyCast` sobre o campo `preco` ao salvar, e dispara o evento `created`, que o `ProdutoObserver` está ouvindo (gera um log).
7. O Controller devolve `ProdutoResource::make(...)`, que formata a saída final em JSON.

Esse é o "caminho feliz". Para entender cada peça em detalhe, siga as seções abaixo.

---

## 4. Form Requests: validação fora do Controller

Veja `app/Http/Requests/Api/V1/Produto/StoreProdutoRequest.php`:

```php
public function rules(): array
{
    return [
        'nome' => ['required', 'string', 'max:255'],
        'descricao' => ['nullable', 'string'],
        'preco' => ['required', 'numeric', 'min:0'],
        'categoria_id' => ['required', 'integer', Rule::exists('categorias', 'id')],
    ];
}
```

**Por que não validar direto no Controller** (`$request->validate([...])`)? Duas razões práticas:

- **Reuso e organização**: cada Form Request é uma classe própria, testável isoladamente, e o Controller fica limpo — só orquestra, não se preocupa com "o que é um produto válido".
- **Autorização acoplada à validação**: todo Form Request tem um método `authorize()`. Aqui ele retorna `true` (a API não tem autenticação de usuário implementada), mas em um cenário com login, seria o lugar natural para checar "este usuário pode criar produtos?" antes mesmo de validar os dados.

Repare também na diferença entre `StoreProdutoRequest` e `UpdateProdutoRequest`: no update, a regra de unicidade de `UpdateCategoriaRequest` usa `->ignore($this->route('categoria'))` — isso evita que, ao atualizar uma categoria, ela "colida" com o próprio nome dela mesma na validação de unicidade.

---

## 5. API Resources: controlando o formato da resposta

Veja `app/Http/Resources/Api/V1/ProdutoResource.php`. A pergunta que a Resource resolve é: **"o que exatamente eu quero expor para quem consome a API?"**

Sem uma Resource, `return $produto;` exporia **todas** as colunas do banco, incluindo eventuais campos internos futuros (senha de hash, tokens, flags internas). Com a Resource, o `toArray()` é uma lista explícita e controlada:

```php
public function toArray(Request $request): array
{
    return [
        'id' => $this->id,
        'nome' => $this->nome,
        'descricao' => $this->descricao,
        'preco' => $this->preco,
        'categoria_id' => $this->categoria_id,
        'categoria' => $this->when(
            $this->wantsInclude($request, 'categoria'),
            fn () => CategoriaResource::make($this->whenLoaded('categoria')),
        ),
        ...
    ];
}
```

Dois detalhes valiosos aqui:

- **`$this->when(condição, valor)`**: um helper do Laravel que só inclui a chave no JSON final se a condição for verdadeira. Evita se ter que montar arrays condicionalmente "na mão".
- **`whenLoaded('categoria')`**: só tenta acessar o relacionamento se ele já tiver sido carregado via `with()`/`load()`. Isso previne o problema clássico de performance conhecido como **N+1 queries** — se você esquecer de dar `load()`, a Resource simplesmente omite o campo em vez de disparar uma query extra por produto.

A combinação de `when` + `whenLoaded` + o helper `wantsInclude` (explicado na seção 9) é o que implementa o padrão de **"includes sob demanda"**: o cliente da API decide, via `?include=categoria`, se quer pagar o custo de carregar aquele relacionamento ou não.

---

## 6. Eloquent Models e Attributes do PHP 8

Os Models usam **Attributes nativos do PHP** (a sintaxe `#[...]`), um recurso relativamente recente que o Laravel passou a suportar como alternativa às propriedades tradicionais:

```php
#[Fillable(['nome'])]
#[ObservedBy(CategoriaObserver::class)]
class Categoria extends Model
```

Isso é equivalente a escrever `protected $fillable = ['nome'];` e registrar o Observer manualmente em um `ServiceProvider` — mas de forma **declarativa e localizada**: quem abre o Model já vê, ali mesmo, quais campos podem ser preenchidos em massa e qual classe reage aos eventos dele, sem precisar ir caçar essa informação em outro arquivo.

O `$fillable` (ou aqui, o `#[Fillable]`) existe para **proteger contra mass assignment**: sem ele, um `Produto::create($request->all())` malicioso poderia tentar sobrescrever qualquer coluna da tabela, mesmo uma que não devesse ser exposta.

### Scopes de busca

Tanto `Categoria` quanto `Produto` têm um `scopeSearch`:

```php
public function scopeSearch(Builder $query, ?string $termo): Builder
{
    return $query->when(
        filled($termo),
        fn (Builder $query): Builder => $query->whereRaw(
            'LOWER(nome) LIKE ?',
            ['%'.mb_strtolower($termo).'%'],
        ),
    );
}
```

Um **Local Scope** é a forma "Eloquent" de encapsular uma cláusula `WHERE` reutilizável. Ao prefixar o método com `scope`, o Laravel permite chamá-lo como `Produto::search('notebook')` — bem mais legível do que espalhar `whereRaw` pelo Controller. O uso de `LOWER()` + `mb_strtolower()` garante uma busca *case-insensitive* mesmo com acentuação (multibyte-safe).

---

## 7. MoneyCast: um Custom Cast do Eloquent

Este é um dos pontos mais didáticos do projeto. Veja `app/Casts/MoneyCast.php`:

```php
final class MoneyCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value === null ? null : number_format((float) $value, 2, '.', '');
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if (! is_numeric($value)) {
            throw new InvalidArgumentException("O valor de [{$key}] precisa ser numérico.");
        }

        return number_format((float) $value, 2, '.', '');
    }
}
```

**O problema que isso resolve:** valores monetários não devem ser tratados como `float` comum em nenhuma etapa — cálculos com ponto flutuante podem gerar erros de arredondamento sutis (ex.: `0.1 + 0.2` não é exatamente `0.3` em ponto flutuante). Formatar sempre como string com 2 casas decimais fixas, tanto na entrada (`set`) quanto na saída (`get`), garante que o valor de `preco` seja **consistente** em qualquer lugar do sistema — no banco, na Resource, e ao reatribuir o valor no próprio código.

Isso é usado no Model assim:

```php
protected function casts(): array
{
    return ['preco' => MoneyCast::class];
}
```

Um **Cast** no Eloquent é o mecanismo que transforma automaticamente um valor toda vez que ele entra (`set`) ou sai (`get`) do Model — o Laravel já vem com casts prontos (`integer`, `boolean`, `datetime`...), mas quando a regra de conversão é específica do seu domínio (como "dinheiro sempre com 2 casas decimais"), criar um Cast próprio, implementando `CastsAttributes`, é a forma correta e testável de fazer isso — em vez de espalhar `number_format()` pelo Controller ou pela Resource.

---

## 8. Observers: reagindo a eventos do Model

Veja `app/Observers/ProdutoObserver.php`. Um **Observer** centraliza o código que deve rodar quando um Model passa por um evento do seu ciclo de vida (`created`, `updated`, `deleted`, `saving`, etc.), tirando essa responsabilidade do Controller:

```php
public function updated(Produto $produto): void
{
    Log::info('Produto atualizado', [
        'id' => $produto->id,
        'alteracoes' => $produto->getChanges(),
    ]);

    Cache::forget("produtos:{$produto->id}");
}
```

Duas ideias importantes aqui:

- **Auditoria via log**: toda alteração relevante (criação, atualização, remoção) gera um registro de log estruturado. Isso é valioso para debugar "o que aconteceu com esse registro" sem precisar de uma tabela de auditoria completa.
- **Invalidação de cache**: `Cache::forget()` remove uma entrada de cache associada àquele registro sempre que ele muda. Isso é a aplicação prática do princípio "cache é sempre um trade-off": ele acelera leituras, mas alguém precisa garantir que ele não fique desatualizado (**stale**) depois de uma escrita. Repare que **esse projeto não usa efetivamente esse cache em nenhuma leitura hoje** — o padrão está ali pronto para o dia em que uma leitura cacheada for adicionada (por exemplo, em `show()`), sem que ninguém esqueça de invalidá-la.

**Por que não fazer isso direto no Controller?** Porque criar/atualizar/deletar um Produto pode acontecer de vários lugares (um Job, um Seeder, um Comando artisan, um teste) — não só pela rota HTTP. Colocar a lógica no Observer garante que ela rode **sempre**, independente de quem disparou a alteração.

---

## 9. Traits reutilizáveis nos Controllers

Em `app/Http/Controllers/Concerns/`, duas traits pequenas evitam duplicação entre `CategoriaController` e `ProdutoController`:

**`HandlesPagination`** — centraliza a regra "quantos itens por página o cliente pode pedir":

```php
protected function getPerPage(Request $request): int
{
    return min($request->integer('per_page', self::DEFAULT_PER_PAGE), self::MAX_PER_PAGE);
}
```

Sem isso, alguém poderia pedir `?per_page=999999` e forçar o banco a carregar a tabela inteira de uma vez. O `min()` com um teto (`MAX_PER_PAGE = 100`) é uma proteção simples contra esse tipo de abuso, sem precisar de nenhuma configuração externa.

**`ResolvesIncludes`** — decide se um relacionamento deve ou não ser incluído na resposta:

```php
private function wantsInclude(Request $request, string $relation): bool
{
    if (! $request->isMethod('get')) {
        return true;
    }

    return in_array($relation, $this->requestedIncludes($request), true);
}
```

Interessante notar a regra: em métodos que não são `GET` (ou seja, em `POST`/`PUT`), o relacionamento **sempre** é incluído — só em listagens (`GET`) que o cliente precisa pedir explicitamente via `?include=`. Isso é uma decisão de UX de API: depois de criar ou atualizar um produto, é razoável assumir que o cliente quer ver o resultado completo (incluindo a categoria), sem precisar fazer uma segunda requisição.

O conceito geral por trás dessas duas classes é o **`trait`** do PHP: uma forma de compartilhar comportamento entre classes que não têm uma relação de herança direta (`CategoriaController` e `ProdutoController` não herdam uma da outra, mas ambas "ganham" os mesmos métodos ao usar `use HandlesPagination;`).

---

## 10. Versionamento de API

Toda rota vive sob `/api/v1/...`, e a estrutura de pastas espelha isso (`Controllers/Api/V1`, `Requests/Api/V1`, `Resources/Api/V1`). Isso é proposital: o dia em que uma mudança **quebrar contrato** para quem já consome a API (por exemplo, renomear um campo, mudar um formato de data), a solução não é editar a V1 — é criar uma pasta `V2/` nova, com suas próprias Requests/Resources/Controllers, e registrar as rotas dela sob `/api/v2`. Os dois conjuntos convivem, e cada cliente migra na hora que quiser.

O middleware `AddApiVersionHeader` reforça isso na prática, devolvendo o header `X-Api-Version: v1` em toda resposta — útil para quem consome a API debugar contra qual versão está falando, e essencial no dia em que houver uma V2 rodando em paralelo.

---

## 11. Tratamento centralizado de exceções

Em `bootstrap/app.php`:

```php
->withExceptions(function (Exceptions $exceptions): void {
    $exceptions->shouldRenderJsonWhen(
        fn (Request $request, Throwable $e) => $request->is('api/*') || $request->expectsJson(),
    );

    $exceptions->render(function (NotFoundHttpException $e, Request $request) {
        if ($request->is('api/*') && $e->getPrevious() instanceof ModelNotFoundException) {
            return response()->json(['message' => 'Registro não encontrado.'], 404);
        }
    });
})
```

Duas ideias-chave:

- **`shouldRenderJsonWhen`**: garante que, para qualquer rota sob `/api/*`, erros sempre virem JSON (nunca a página HTML de erro padrão do Laravel) — mesmo para exceções que ninguém tratou explicitamente.
- **Padronização de 404**: por padrão, quando o Laravel não acha um Model pela rota (`Route Model Binding`), ele lança uma `ModelNotFoundException` que vira uma `NotFoundHttpException` — mas a mensagem de erro padrão é genérica ("Not Found"). Aqui, isso é interceptado para devolver uma mensagem consistente e em português (`"Registro não encontrado."`) em qualquer recurso, sem precisar repetir esse tratamento em cada Controller.

Esse é o conceito de **tratamento de erros centralizado**: em vez de cada Controller ter um `try/catch` próprio, a aplicação toda decide, em um único lugar, "como um erro vira uma resposta HTTP".

---

## 12. Rate Limiting

Em `app/Providers/AppServiceProvider.php`:

```php
RateLimiter::for('api', function (Request $request) {
    return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
});
```

E aplicado nas rotas via `Route::middleware('throttle:60,1')`. A regra `by($request->user()?->id ?: $request->ip())` é o detalhe interessante: o limite é aplicado **por usuário autenticado**, se houver um; senão, cai para **por IP**. Isso evita que um único cliente (autenticado ou não) consiga sobrecarregar a API sozinho, e é uma prática básica de proteção contra abuso em qualquer API pública.

---

## 13. Qualidade de código: Pint, PHPStan/Larastan e Pest

Três ferramentas, três responsabilidades diferentes — vale entender a diferença:

| Ferramenta | O que verifica | Analogia |
|---|---|---|
| **Laravel Pint** | *Estilo* do código (indentação, espaços, ordem de imports) | Um revisor de formatação de texto |
| **PHPStan / Larastan** | *Análise estática* — erros que o PHP só acusaria em tempo de execução (tipo errado, método inexistente) | Um revisor que lê o código sem rodar e aponta inconsistências lógicas |
| **Pest** | *Comportamento* — o código realmente faz o que deveria? | Um QA testando a aplicação de verdade |

O `phpstan.neon` configura o Larastan (uma extensão do PHPStan com conhecimento específico do Laravel — entende Eloquent, Facades, etc.) no **nível 5** de rigor (a escala vai de 0 a 9+; nível 5 já pega bastante inconsistência de tipos sem exigir tipagem excessivamente estrita).

Os testes em `tests/Feature/` usam o Pest com uma sintaxe funcional (`it('faz algo', function () {...})`) por cima do PHPUnit — são chamados de **testes de feature** porque testam o comportamento observável de fora (faz uma requisição HTTP de verdade, contra um banco de teste, e verifica a resposta), diferente de um **teste unitário**, que testaria uma função ou classe isolada.

O comando `composer test` roda as três ferramentas em sequência — é essencialmente o mesmo processo que rodaria em um pipeline de CI antes de aceitar uma alteração no código.

---

## 14. Docker: por que dois serviços

O `docker-compose.yaml` sobe **dois containers**, não um só:

```yaml
services:
  app:       # PHP + artisan serve
  postgres:  # banco de dados
```

Essa separação segue o princípio de **um processo por container**: o container da aplicação não sabe rodar um banco de dados, e vice-versa — cada um faz uma coisa só, e eles se comunicam pela rede interna do Docker Compose (por isso, dentro do `.env`, o `DB_HOST` é `postgres`, o nome do serviço, e não `localhost`).

O `healthcheck` no serviço `postgres` (`pg_isready`) combinado com `depends_on: condition: service_healthy` no `app` evita um problema clássico de orquestração: sem isso, o container da aplicação poderia subir e tentar se conectar ao banco **antes dele estar pronto para aceitar conexões**, causando um erro de conexão logo na inicialização.

---

## 15. Boas práticas aplicadas — resumo

Para consulta rápida, aqui está o "porquê" de cada prática em uma frase:

- **`declare(strict_types=1)`** em (quase) todo arquivo PHP: desliga a conversão implícita de tipos do PHP, fazendo com que erros de tipo apareçam cedo, em vez de causar comportamento inesperado silenciosamente.
- **Classes `final`**: sinaliza intenção — "esta classe não foi desenhada para ser estendida"; evita heranças acidentais que dificultariam mudanças futuras.
- **Type hints e retorno tipado em todo método**: o próprio código serve de documentação, e o PHPStan consegue verificar consistência de tipos.
- **PHPDoc genérico em relacionamentos** (`@return HasMany<Produto, $this>`): dá ao PHPStan/IDE informação de tipo que o PHP puro não consegue expressar sozinho.
- **Separação por camada** (Request valida → Controller orquestra → Model guarda regra de dado → Resource formata): cada peça é testável e substituível isoladamente.
- **Nenhuma lógica de negócio "escondida" no Controller**: os Controllers deste projeto são deliberadamente finos — só chamam outras camadas.
- **Testes cobrindo o caminho feliz e os de erro** (validação, 404, rate limit implícito): dá confiança para refatorar sem medo de quebrar comportamento existente.

---

Se você está retomando a área e quer aprofundar algum desses tópicos, os melhores pontos de partida são a documentação oficial do Laravel para [Eloquent: Mutators & Casting](https://laravel.com/docs/eloquent-mutators), [Eloquent: Resources](https://laravel.com/docs/eloquent-resources) e [Validation](https://laravel.com/docs/validation) — a maior parte do que está aqui é a aplicação direta dessas três páginas.