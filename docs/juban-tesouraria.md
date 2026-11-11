# Módulo JUBAN: Tesouraria Digital

Este documento descreve a modelagem de dados, regras contábeis e dicionário de campos do livro-caixa digital da **JUBAN** (Juventude Batista Nacional — Secional Goiás) no painel administrativo da CBN-GO.

---

## 1. Visão Geral do Domínio

A tesouraria da JUBAN gerencia as receitas e despesas vinculadas a retiros, acampamentos, congressos e projetos de juventude da convenção. O livro-caixa digital substitui a gestão anterior em planilhas Excel desconectadas, garantindo:
- **Centralização:** Histórico financeiro confiável e auditável na base do portal.
- **Rastreabilidade (Não-Repúdio):** Registro de quem criou (`created_by`) e de quem alterou (`updated_by`) cada lançamento.
- **Auditoria Contábil:** Exclusões lógicas (`softDeletes`) que mantêm o registro no banco mesmo após exclusão.
- **Integridade Referencial:** Categorias com lançamentos não podem ser excluídas fisicamente (`restrictOnDelete`).

---

## 2. Estrutura de Tabelas

### 2.1 Tabela `juban_categories`
Armazena o plano de contas/categorias das movimentações da juventude.

| Campo | Tipo | Descrição |
|---|---|---|
| `id` | `BIGINT UNSIGNED` | Identificador único da categoria |
| `name` | `VARCHAR(100)` | Nome da categoria (ex: "Inscrições de Eventos") |
| `code` | `VARCHAR(50)` | Identificador textual único/slug (ex: `inscricoes-eventos`) |
| `kind` | `VARCHAR(20)` | Natureza contábil: `receita`, `despesa` ou `ambos` |
| `description` | `TEXT` (nullable) | Descrição orientativa de quando utilizar a categoria |
| `is_active` | `BOOLEAN` | Flag de ativação (default `true`). Categorias inativas não aparecem em novos lançamentos, mas preservam o histórico |
| `created_at` / `updated_at` | `TIMESTAMP` | Auditoria temporal de criação e alteração |

### 2.2 Tabela `juban_transactions`
Armazena as entradas (receitas) e saídas (despesas) de caixa.

| Campo | Tipo | Descrição |
|---|---|---|
| `id` | `BIGINT UNSIGNED` | Identificador único do lançamento |
| `type` | `VARCHAR(20)` | Tipo da transação: `receita` ou `despesa` |
| `amount` | `DECIMAL(12,2)` | Valor monetário em BRL, estritamente positivo (> 0). O sinal contábil é governado pelo campo `type` |
| `occurred_on` | `DATE` | Data de competência/ocorrência da transação financeira |
| `category_id` | `BIGINT UNSIGNED` | Chave estrangeira para `juban_categories.id` (`restrictOnDelete`) |
| `description` | `VARCHAR(255)` | Histórico descritivo do lançamento (ex: "Inscrição Retiro de Carnaval - 30 jovens PIB Goiânia") |
| `payment_method` | `VARCHAR(30)` (nullable) | Forma de liquidação: `pix`, `transferencia`, `dinheiro`, `cartao_debito`, `cartao_credito`, `boleto`, `cheque`, `outro` |
| `document_ref` | `VARCHAR(100)` (nullable) | Número de documento fiscal, recibo ou código de transação bancária |
| `notes` | `TEXT` (nullable) | Observações e detalhes internos para a tesouraria/secretaria |
| `created_by` | `BIGINT UNSIGNED` (nullable) | Chave estrangeira para `users.id` do operador que registrou o lançamento |
| `updated_by` | `BIGINT UNSIGNED` (nullable) | Chave estrangeira para `users.id` do último operador que alterou o lançamento |
| `deleted_at` | `TIMESTAMP` (nullable) | Timestamp de exclusão lógica (`softDeletes`) |
| `created_at` / `updated_at` | `TIMESTAMP` | Auditoria temporal |

---

## 3. Enums e Tipos

- **`App\Enums\JubanCategoryKind`**:
  - `RECEITA = 'receita'`
  - `DESPESA = 'despesa'`
  - `AMBOS = 'ambos'`
  - Método `allows(JubanTransactionType $type): bool` para validar compatibilidade.
- **`App\Enums\JubanTransactionType`**:
  - `RECEITA = 'receita'`
  - `DESPESA = 'despesa'`
  - Helpers `isReceita(): bool` e `isDespesa(): bool`.
- **`App\Enums\JubanPaymentMethod`**:
  - `PIX`, `TRANSFERENCIA`, `DINHEIRO`, `CARTAO_DEBITO`, `CARTAO_CREDITO`, `BOLETO`, `CHEQUE`, `OUTRO`.

---

## 4. Categorias Iniciais Padrão (`JubanCategorySeeder`)

O seeder registra 14 categorias fundamentais de forma idempotente (`updateOrCreate` por `code`):

### Receitas
1. `inscricoes-eventos` — Inscrições de Eventos
2. `ofertas-doacoes` — Ofertas e Doações
3. `venda-materiais` — Venda de Materiais e Camisetas
4. `repasse-convencao` — Repasse da Convenção
5. `outras-receitas` — Outras Receitas

### Despesas
6. `alimentacao` — Alimentação e Coffee Break
7. `hospedagem-transporte` — Hospedagem e Transporte
8. `preletores-musicos` — Preletores e Músicos
9. `som-multimidia` — Som, Iluminação e Multimídia
10. `grafica-brindes` — Gráfica, Camisetas e Brindes
11. `material-administrativo` — Material Administrativo
12. `taxas-bancarias` — Taxas e Tarifas Bancárias
13. `outras-despesas` — Outras Despesas

### Ambos
14. `ajuste-saldo` — Ajuste de Saldo (Abertura de caixa e conciliação)

---

## 5. Regras de Negócio e Cálculos

- **Saldo Contábil:** O método `signedAmount()` no modelo `JubanTransaction` retorna:
  - `+amount` quando `type === receita`
  - `-amount` quando `type === despesa`
- **Validação de Valor:** Lançamentos com valor menor ou igual a zero são rejeitados com `InvalidArgumentException`.
- **Auditoria Transacional:** Quando um usuário autenticado cria ou altera uma transação, os campos `created_by` e `updated_by` são preenchidos automaticamente pelo hook `saving` do Eloquent.

