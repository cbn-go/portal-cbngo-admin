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

---

## 6. Controle de Acesso Baseado em Papéis (RBAC)

O controle de acesso ao módulo financeiro da JUBAN é regido por duas políticas principais: [`JubanTransactionPolicy`](file:///c:/Users/fboli/Projetos/cbn-go/portal-cbngo-admin/app/Policies/JubanTransactionPolicy.php) e [`JubanCategoryPolicy`](file:///c:/Users/fboli/Projetos/cbn-go/portal-cbngo-admin/app/Policies/JubanCategoryPolicy.php), registradas no [`AppServiceProvider`](file:///c:/Users/fboli/Projetos/cbn-go/portal-cbngo-admin/app/Providers/AppServiceProvider.php).

### 6.1 Matriz de Personas e Permissões

| Persona / Papel | Habilidades Autorizadas | Restrições Mandatórias |
|---|---|---|
| **Super Administrador** (`SUPER_ADMIN`) | `viewAny`, `view`, `create`, `update`, `delete` (soft), `restore`, `forceDelete`, `export` | Nenhuma. Acesso pleno de administração do sistema. |
| **Tesouraria JUBAN** (`JUBAN_TREASURER`) | `viewAny`, `view`, `create`, `update`, `delete` (soft), `export` | Não possui permissão para `restore` nem `forceDelete` (trilha de auditoria preservada). |
| **Presidência JUBAN** (`JUBAN_PRESIDENT`) | `viewAny`, `view`, `export` | **Acesso estritamente somente-leitura** (`create: false`, `update: false`, `delete: false`, `restore: false`, `forceDelete: false`). Visualiza o resumo de caixa, listagem de lançamentos/categorias e exporta CSV para auditoria e prestação de contas. |
| **Secretaria da Convenção** (`CONVENTION_EDITOR`) | `viewAny`, `view`, `export`, `create` (cobertura), `update` (cobertura) | **Estritamente proibida de deletar** (`delete: false`, `restore: false`, `forceDelete: false`). |
| **Demais Papéis** (`MINISTER`, `AUTHOR`, `CHURCH_REPRESENTATIVE`) | Nenhuma | Acesso 100% negado a todas as rotas e ações da JUBAN. |
| **Usuários Inativos** (`is_active = false`) | Nenhuma | Bloqueio imediato no método `before()` de todas as políticas. |

### 6.2 Premissas de Implementação e Transição
1. **Papel Formal `JUBAN_TREASURER`:** Foi provisionado o caso `JUBAN_TREASURER = 'juban_treasurer'` no backed enum [`UserRole`](file:///c:/Users/fboli/Projetos/cbn-go/portal-cbngo-admin/app/Enums/UserRole.php), acompanhado dos helpers `isJubanTreasurer()`, `canManageJubanTreasury()` e `canViewJubanTreasury()`.
2. **Cobertura Operacional pela Secretaria:** Enquanto os tesoureiros da JUBAN estiverem sendo provisionados no sistema, o `CONVENTION_EDITOR` (Secretaria Executiva) pode cobrir operacionalmente o lançamento e edição de transações, mantendo-se porém a restrição contábil fundamental: a Secretaria **nunca** pode excluir lançamentos do caixa.
3. **Integridade de Categorias com Transações:** A política `JubanCategoryPolicy::delete` impede a exclusão física de categorias que já possuam lançamentos vinculados (`$category->transactions()->exists()`), reforçando a restrição de chave estrangeira (`restrictOnDelete`) do banco de dados.
4. **Visibilidade de Menus Filament:** Os recursos e telas do Filament utilizam a verificação nativa `canViewAny()`, que delega para o `Gate::allows('viewAny', Model::class)`. Assim, os menus da JUBAN tornam-se visíveis exclusivamente para as personas autorizadas (`SUPER_ADMIN`, `JUBAN_TREASURER` e `CONVENTION_EDITOR`).

### 6.3 Painel `/juban` (Tesouraria JUBAN)
O CRUD da tesouraria (#96) fica num painel Filament próprio, `App\Providers\Filament\JubanPanelProvider` (`id`/`path` = `juban`), separado do `admin`, para que a tesouraria não enxergue os demais módulos da convenção.

- **Quem entra:** `UserRole::hasJubanPanelAccess()` = `canViewJubanTreasury()` → `SUPER_ADMIN`, `JUBAN_TREASURER`, `JUBAN_PRESIDENT` e `CONVENTION_EDITOR`. Os outros papéis e os usuários inativos recebem 403.
- **Painel padrão:** `JUBAN_TREASURER` e `JUBAN_PRESIDENT` caem em `/juban` após o login e no link de redefinição de senha (`getDefaultPanelUrl()` / `panelForPasswordReset()`). SuperAdmin e Secretaria continuam com `/admin` como padrão e acessam `/juban` pelo endereço.
- **Recursos** (`app/Filament/Juban/Resources`):
  - `JubanTransactionResource` (`/juban/lancamentos`): tipo, valor (> 0), data (obrigatória), categoria (só as ativas compatíveis com o tipo; validação no servidor contra categoria incompatível), descrição, forma de pagamento, referência/documento e observações. Busca por descrição, categoria e referência; filtros por período, tipo e categoria. `created_by` é preenchido no create. Exclusão lógica para SuperAdmin e tesouraria; restaurar e lixeira só para o SuperAdmin.
  - `JubanCategoryResource` (`/juban/categorias`): nome, código único, natureza (receita/despesa/ambos), descrição e ativa/inativa. Categorias com lançamentos não podem ser excluídas; desative-as.

### 6.4 Resumo de caixa (página inicial do `/juban`)
A página inicial do painel (`App\Filament\Juban\Pages\Dashboard`, "Resumo de caixa", #97) mostra o caixa do período escolhido, no layout da tela da tesouraria do design (saudação e data, cards brancos, gastos por mês e por categoria). Os números vêm de um único serviço, `App\Services\Juban\CashSummary`.

- **Cabeçalho:** saudação e data (`PanelGreeting`), botões "Ver lançamentos" e "Novo lançamento" (este só para quem pode criar).
- **Período:** filtros "De" e "Até", inclusivos sobre `occurred_on`. O padrão é sempre o ano corrente: o período escolhido fica só na URL, não na sessão. Os títulos dizem "em 2026", "em outubro de 2026" ou "de dd/mm/aaaa a dd/mm/aaaa".
- **Cards** (`CashSummaryStatsWidget`): Gastos, Receita e **Saldo** = receita − gastos, em R$ pt-BR. O número de lançamentos no card de saldo abre a lista com o mesmo filtro de período, para conferência.
- **Gastos por mês e por categoria** (`CashByCategoryWidget`): uma barra por mês do período (até 24, em milhares de reais quando o maior mês passa de R$ 1.000) e as categorias de despesa com valor, % e barra de proporção. Ao lado, a receita por categoria. Uma categoria "ambos" aparece nos dois.
- **Fora do escopo por enquanto:** os cards "Último relatório" e "Ofícios aguardando resposta" do design, e a cor de barra por status de relatório ao Conselho Fiscal, dependem de módulos que ainda não existem.
- **Consistência:** os mesmos critérios da listagem (`whereDate` e exclusão lógica respeitada); lançamentos excluídos não entram no resumo.
- **Acesso:** o mesmo do painel (SuperAdmin, tesouraria JUBAN, presidência JUBAN e Secretaria; a Secretaria lança e edita, a presidência tem acesso somente-leitura, e ninguém exceto SuperAdmin e tesoureiro exclui).

### 6.5 Exportação de Lançamentos em CSV (#98)
A listagem de lançamentos (`/juban/lancamentos`) conta com uma ação de cabeçalho da tabela para exportação direta em formato CSV (`App\Services\Juban\JubanTransactionExportService`):

- **Filtros Respeitados:** A exportação utiliza `HasTable::getFilteredTableQuery()`, garantindo que todos os filtros ativos na tela (intervalo de datas, tipo, categoria contábil, termo de busca e lixeira para administradores) sejam refletidos com exatidão no arquivo gerado.
- **Padrão pt-BR:** Geração com BOM UTF-8 (`\xEF\xBB\xBF`) e delimitador ponto e vírgula (`;`), permitindo abertura direta e sem quebras no Microsoft Excel e LibreOffice.
- **Colunas:** Data (`d/m/Y`), Tipo (`Receita`/`Despesa`), Categoria, Descrição, Valor formatado em BRL (`Valor (R$)`), Valor decimal numérico para fórmulas contábeis (`Valor Contábil`), Forma de Pagamento, Documento/Referência e Usuário responsável.
- **Autorização:** Ação vinculada à habilidade `export` da `JubanTransactionPolicy` (permitida para `SUPER_ADMIN`, `JUBAN_TREASURER`, `JUBAN_PRESIDENT` e `CONVENTION_EDITOR`).

