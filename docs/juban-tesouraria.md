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

---

### 6.6 Autenticação Forte e Passkeys WebAuthn (#109)
O painel da JUBAN conta com gerenciamento de credenciais FIDO2 / WebAuthn na página **"Minhas passkeys"** (`/juban/passkeys`, grupo `Segurança`), permitindo que tesoureiro e presidente cadastrem a biometria do próprio aparelho (Windows Hello, Touch ID, Face ID ou chave de segurança física):

- **Tabela `passkeys`:** Armazena `user_id`, `name`, `credential_id` (base64url único), `public_key` (chave pública COSE), `sign_count`, `transports` (json), `aaguid`, `backup_eligible` e `last_used_at`.
- **Cerimônia Criptográfica Estrita (`App\Services\WebAuthn\WebAuthnRegistrationService`):**
  - **RP ID:** Calculado pelo host do `APP_URL`.
  - **User Verification Obrigatório:** A cerimônia exige verificação de usuário (`userVerification: required`) e valida estritamente a flag UV no `authData`.
  - **Attestation:** `none` (suporte a None, Packed, Apple e FIDO U2F).
  - **Challenge Único:** Challenge randômico de 32 bytes gerado com TTL de 5 minutos e invalidado imediatamente no primeiro uso (anti-replay).
- **Interface e Segurança:**
  - Exigência de confirmação prévia da senha da conta antes de iniciar a captura biométrica no dispositivo.
  - Componente Alpine nativo (`passkey-register.js`) publicado com `filament:assets` sem dependências npm.
  - Usuários autorizados visualizam, renomeiam e removem exclusivamente as próprias credenciais.

---

### 6.7 Fechamento Trimestral, Snapshot, PDF e Trava Contábil (#110)
O fluxo de prestação de contas da JUBAN exige o congelamento formal dos números ao fim de cada trimestre calendário (Q1: Jan-Mar, Q2: Abr-Jun, Q3: Jul-Set, Q4: Out-Dez) através da tela **"Relatórios Contábeis"** (`/juban/relatorios`):

- **Tabela `juban_reports` e Model `JubanReport`:**
  - Armazena `year`, `quarter`, `period_from`, `period_until`, `status`, `snapshot` (JSON), `pdf_path`, `pdf_sha256`, `closed_by`, `closed_at`, `reopened_by`, `reopened_at` e `reopen_reason`.
  - Unicidade pelo par `[year, quarter]`.
  - Estados (`App\Enums\JubanReportStatus`):
    - `AGUARDANDO_TESOURARIA = 'aguardando_tesouraria'` (fechado, aguardando assinatura da tesouraria via passkey #111)
    - `AGUARDANDO_PRESIDENCIA = 'aguardando_presidencia'` (aguardando assinatura da presidência via passkey #112)
    - `ASSINADO = 'assinado'` (concluído com ambas as assinaturas digitais)
    - `REABERTO = 'reaberto'` (destravado para correções excepcionais)
- **Serviço de Fechamento (`App\Services\Juban\QuarterlyClosingService`):**
  - **Regra de Término do Trimestre:** O fechamento só pode ser executado após o encerramento cronológico do período (`Carbon::now()->isAfter($periodUntil)`).
  - **Geração de Snapshot Imutável:** Captura o fechamento consolidado pelo `CashSummary::forPeriod()`, incluindo totais de receita, despesa e saldo, discriminativo por categoria contábil, evolução mensal de despesas e a listagem integral de transações com data, tipo, valor e categoria.
  - **Geração de Documento PDF e Hash SHA-256:**
    - Renderização via biblioteca `barryvdh/laravel-dompdf` (`pdf/juban/quarterly-report.blade.php`).
    - Contém cabeçalho oficial CBN-GO / JUBAN, período contábil, resumo financeiro, discriminativo por categorias e tabela completa de movimentações.
    - O conteúdo binário do PDF é gravado em disco privado (`local/reports/juban/...`) e seu hash criptográfico `SHA-256` é indexado no registro para garantia de integridade contra adulterações.
- **Trava Contábil (Lock):**
  - Enquanto existir um relatório ativo (`status != 'reaberto'`) cobrindo uma data (`JubanReport::isDateLocked($date)`):
    - **Inclusão:** O formulário do Filament (`JubanTransactionResource`) valida o campo `occurred_on` e rejeita qualquer data pertencente a trimestre fechado.
    - **Edição e Exclusão:** `JubanTransactionPolicy::update()`, `delete()`, `restore()` e `forceDelete()` retornam `false`, bloqueando modificações retroativas na base contábil já homologada.
- **Reabertura Excepcional:**
  - Permitida **exclusivamente para o Super Administrador** (`SUPER_ADMIN`).
  - Permitida apenas enquanto o relatório **não estiver integralmente assinado** (`status !== ASSINADO`).
  - Ao reabrir, o PDF armazenado é excluído fisicamente, o status passa a `reaberto`, o hash é limpo e são registrados o usuário responsável, timestamp e justificativa textual, destravando o período para correções operacionais.
- **Interface Filament (`JubanReportResource`):**
  - Listagem com ano/trimestre, período, badge de status, saldo consolidado e auditoria de fechamento.
  - Ação "Fechar trimestre" no cabeçalho com seleção de ano e trimestre.
  - Ação "Baixar PDF" para download direto do relatório gerado.
  - Ação "Reabrir trimestre" (visível apenas para SuperAdmin em relatórios não assinados) com modal solicitando o motivo da reabertura.

---

### 6.8 Assinatura Digital do Relatório com Passkey (#111)
Para garantir autenticidade jurídica, integridade física e não-repúdio das contas homologadas, os relatórios trimestrais fechados são assinados digitalmente através de passkeys (FIDO2 / WebAuthn):

- **Tabela `juban_report_signatures` e Model `JubanReportSignature`:**
  - Armazena `report_id`, `user_id`, `role` (`tesouraria`, `presidencia`), `passkey_id`, `nonce`, `challenge`, `client_data_json`, `authenticator_data`, `signature`, `pdf_sha256`, `signed_at`, `ip` e `user_agent`.
  - Unicidade pelo par `[report_id, role]`.
- **Challenge Criptográfico Estrito:**
  - O challenge é gerado com validade de 5 minutos e consumo único no primeiro uso (anti-replay):
    $$\text{Challenge} = \text{SHA-256}("cbngo:juban-report:v1|\{report\_id\}|\{pdf\_sha256\}|\{user\_id\}|\{papel\}|\{nonce\}") \quad (\text{nonce de 32 bytes hex})$$
- **Ordem Rígida de Assinaturas e Segregação de Funções:**
  - **1ª Assinatura (Tesouraria):** papel `tesouraria`, executada por usuário com papel `JUBAN_TREASURER` (ou `SUPER_ADMIN` agindo como tesouraria). Relatório deve estar no status `aguardando_tesouraria`. Transiciona para `aguardando_presidencia`.
  - **2ª Assinatura (Presidência):** papel `presidencia`, executada por usuário com papel `JUBAN_PRESIDENT`. Relatório deve estar no status `aguardando_presidencia`.
  - **Regra de Não-Acumulação:** A mesma pessoa (`user_id`) é estritamente proibida de assinar nos dois papéis para o mesmo relatório, impedindo autofiscalização mesmo que um usuário acumule permissões administrativas.
  - **Conclusão:** Ao receber a assinatura da presidência (ambas as assinaturas registradas), o status do `JubanReport` é atualizado para `assinado` (`JubanReportStatus::ASSINADO`).
- **Verificação de Integridade Física do PDF:**
  - Antes de emitir o challenge ou aceitar a assinatura, o hash SHA-256 do arquivo PDF físico armazenado em disco privado é recalculado e comparado com `pdf_sha256`. Qualquer divergência bloqueia a assinatura.
- **Validação WebAuthn (`App\Services\Juban\JubanReportSigningService`):**
  - Exige passkey cadastrada pertencente ao próprio usuário autenticado.
  - Exigência estrita de verificação de usuário (**User Verification - UV obrigatório** no `authenticatorData`).
  - Validação de RP ID e Origin.
  - Atualização do contador de assinaturas (`sign_count`) e timestamp de último uso.
- **Reverificação Criptográfica Independente (`App\Services\Juban\IndependentSignatureVerifier`):**
  - Permite auditar e validar a autenticidade de qualquer assinatura armazenada **sem utilizar a biblioteca `webauthn-lib`**:
    1. Reconstitui o challenge a partir da fórmula canônica `cbngo:juban-report:v1|...` e compara com o challenge gravado e o campo dentro de `clientDataJSON`.
    2. Valida o bit UV no `authenticatorData`.
    3. Converte a chave pública COSE armazenada na `Passkey` em padrão PEM `SubjectPublicKeyInfo` (EC P-256).
    4. Executa verificação criptográfica com `openssl_verify($authenticatorDataRaw . hash('sha256', $clientDataJSONRaw, true), $signatureRaw, $publicKeyPem, OPENSSL_ALGO_SHA256)`.

---

### 6.9 Telas de Relatórios, Linha do Tempo e Comprovante de Assinaturas (#112)
O gerenciamento dos relatórios trimestrais conta com interface completa de detalhamento, conferência e assinatura biométrica:

- **Página de Detalhe do Relatório (`ViewJubanReport` em `/juban/relatorios/{record}`):**
  - **Resumo Financeiro:** Exibe cards destacados com Receitas, Despesas, Saldo Líquido, Contagem de Lançamentos e o Hash SHA-256 do relatório.
  - **Linha do Tempo de Homologação (Timeline):** Apresenta o ciclo auditável em 3 etapas sequenciais: Fechamento Contábil $\rightarrow$ Assinatura da Tesouraria $\rightarrow$ Assinatura da Presidência, com data/hora, assinante e dispositivo/passkey utilizado (ou indicação de pendência / reabertura).
  - **Ação Inteligente "Assinar com passkey":**
    - Visível exclusivamente quando é a vez do usuário autenticado (`AGUARDANDO_TESOURARIA` para tesoureiro, `AGUARDANDO_PRESIDENCIA` para presidente).
    - Exibe aviso destacado caso o usuário não possua passkeys cadastradas, com link de redirecionamento para `/juban/passkeys`.
    - Componente Alpine nativo `passkey-sign.js` integrado à API WebAuthn do navegador para assinatura direta no computador ou celular (iOS Safari, Android Chrome).
    - Tratamento de exceções e cancelamentos biométricos com mensagens claras em pt-BR.
- **Comprovante Oficial de Assinaturas (Página e PDF Dedicado):**
  - Seção com trilha técnica de auditoria: papel, titular, data e hora (`d/m/Y H:i:s`), passkey/AAGUID, IP de auditoria e challenge SHA-256.
  - Serviço `App\Services\Juban\SignatureReceiptPdfService` que gera documento PDF oficial do comprovante de assinaturas (`pdf/juban/signature-receipt.blade.php`) via DomPDF para download.
- **Ferramenta de Conferência de Autenticidade do PDF:**
  - Ação modal "Conferir PDF": permite o envio de um arquivo PDF para recálculo do hash SHA-256 e conferência imediata com o relatório assinado, emitindo alerta de confirmação de autenticidade ou aviso de adulteração/divergência.





