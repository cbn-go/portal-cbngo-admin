# Checklist de Homologação Manual e DoD — Épico #68 (Ormiban)

Este documento descreve o **Definition of Done (DoD)** e o **Roteiro de Homologação Manual** do **Épico #68: Rol de Ministros, Acesso por CPF e Indicadores Demográficos da Ormiban-GO**, consolidado pela Issue #74.

---

## 1. Resumo do Épico #68 e Sub-issues Entregues

| Issue | Escopo / Entrega | Status |
|---|---|:---:|
| **#69** | Schema do rol (`ministers`, `family_members`, `history_events`) + comando de importação idempotente | Concluído |
| **#70** | Papel `MINISTER`, autenticação por CPF e fluxo de 1º acesso com obrigatoriedade de senha forte | Concluído |
| **#71** | Painel exclusivo `/ormiban` (perfil do ministro, dados do rol, minha igreja e missões) | Concluído |
| **#72** | Gestão do rol no painel `/admin` (CRUD, busca, filtros de ordenação e auditoria) | Concluído |
| **#73** | Dashboard com 3 gráficos demográficos em `/admin/ormiban/indicadores` (idade, tempo de ministério e ordenações por ano) | Concluído |
| **#74** | Testes automatizados, isolamento, proteção de dados LGPD, fixtures sintéticas e fechamento do DoD | Concluído |

---

## 2. Garantias de Segurança e Privacidade (LGPD)

1. **Dados de Produção Blindados:**
   - O arquivo real de fichas cadastrais (`FICHAS CADASTROS ATIVOS GO.json` ou similares) está explicitamente ignorado no `.gitignore` (`*FICHAS*`, `*fichas*`, `/storage/app/ormiban/`).
   - O teste automatizado [`ProductionDataLeakTest`](file:///c:/Users/fboli/Projetos/cbn-go/portal-cbngo-admin/tests/Feature/Security/ProductionDataLeakTest.php) valida em tempo de execução que nenhum arquivo real está rastreado no Git.
2. **Fixture Sintética Anonimizada:**
   - O repositório utiliza exclusivamente a fixture [`tests/Fixtures/ormiban/fichas_amostra.json`](file:///c:/Users/fboli/Projetos/cbn-go/portal-cbngo-admin/tests/Fixtures/ormiban/fichas_amostra.json), contendo ministros sintéticos fictícios ("PASTOR TESTE ALFA", etc.) e CPFs matematicamente válidos para testes.
3. **Mascaramento de CPF:**
   - No painel administrativo e em listagens gerais, os CPFs são exibidos mascarados (`***.***.***-XX`), sendo visíveis por extenso apenas na tela de visualização detalhada para administradores autorizados.

---

## 3. Roteiro de Homologação Manual (Checklist Passo a Passo)

### Item 1: Importação do Rol de Ministros via CLI
- [ ] **Passo 1.1:** Disponibilize o arquivo de carga em `storage/app/ormiban/fichas.json` (ou caminho externo).
- [ ] **Passo 1.2:** Execute o comando de importação:
  ```bash
  php artisan ormiban:import-rol
  ```
- [ ] **Resultado Esperado:** O console exibe o resumo com a contagem total de fichas processadas, novos ministros criados, ministros atualizados e relatório de vínculos com igrejas.
- [ ] **Passo 1.3 (Idempotência):** Execute o comando uma segunda vez:
  ```bash
  php artisan ormiban:import-rol
  ```
- [ ] **Resultado Esperado:** O console deve indicar 0 criados, N atualizados, nenhum registro duplicado.

---

### Item 2: Autenticação por CPF (Login Central)
- [ ] **Passo 2.1:** Acesse a tela de login em `/admin/login`.
- [ ] **Passo 2.2:** No campo de identificação, informe o CPF de um ministro pré-cadastrado:
  - Teste 1: CPF desformatado (apenas 11 dígitos, ex.: `11144477735`).
  - Teste 2: CPF formatado com pontuação padrão (ex.: `111.444.777-35`).
- [ ] **Resultado Esperado:** O sistema normaliza o CPF removendo a pontuação e localiza o usuário correspondente.

---

### Item 3: Fluxo de Primeiro Acesso (Criação Obrigatória de Senha)
- [ ] **Passo 3.1:** Para um ministro com primeiro acesso pendente (`must_set_password = true`), ao submeter o CPF no primeiro acesso:
- [ ] **Resultado Esperado:** O usuário é direcionado para a tela `/ormiban/definir-senha`.
- [ ] **Passo 3.2:** Tente submeter uma senha fraca (ex.: `123456`).
- [ ] **Resultado Esperado:** O formulário rejeita com mensagem de erro informando que a senha deve atender aos critérios de segurança.
- [ ] **Passo 3.3:** Informe uma nova senha forte (com confirmação coincidente) e submeta.
- [ ] **Resultado Esperado:** A senha é gravada com hash seguro, o indicador `must_set_password` passa para `false` e o ministro é redirecionado para o painel `/ormiban`.

---

### Item 4: Isolamento Bidirecional de Painéis
- [ ] **Passo 4.1 (Ministro tentando acessar o Admin):** Autenticado como Ministro, tente navegar diretamente para:
  - `/admin`
  - `/admin/users`
  - `/admin/ministers`
  - `/admin/ormiban/indicadores`
- [ ] **Resultado Esperado:** O sistema bloqueia com status `403 Forbidden`.
- [ ] **Passo 4.2 (Usuários não-ministros tentando acessar o Ormiban):** Autenticado com papel `AUTHOR`, `CHURCH_REPRESENTATIVE` ou `CONVENTION_EDITOR`, tente navegar para:
  - `/ormiban`
  - `/ormiban/profile`
  - `/ormiban/minha-igreja`
- [ ] **Resultado Esperado:** O sistema bloqueia com status `403 Forbidden`.
- [ ] **Passo 4.3 (Convidado deslogado):** Acesse `/ormiban` sem sessão ativa.
- [ ] **Resultado Esperado:** Redirecionamento automático para a tela central de login.

---

### Item 5: Visualização dos 3 Gráficos Demográficos
- [ ] **Passo 5.1:** Autenticado como `SUPER_ADMIN` ou `CONVENTION_EDITOR`, acesse:
  - `/admin/ormiban/indicadores`
- [ ] **Resultado Esperado:** A página carrega exibindo os cards de resumo e os 3 gráficos Chart.js:
  1. **Gráfico 1 — Idade Biológica (`AgeChartWidget`):** Faixas etárias (`Menos de 30`, `30–39`, `40–49`, `50–59`, `60–69`, `70 ou mais`). Quem não possui data de nascimento informada não entra nas faixas.
  2. **Gráfico 2 — Idade de Ministério (`MinistryAgeChartWidget`):** Anos calculados a partir da ordenação mais antiga (`occurred_on` com código 2). Ministros sem evento de ordenação são agrupados na coluna `Sem ordenação`.
  3. **Gráfico 3 — Ordenações por Ano (`OrdinationsByYearChartWidget`):** Gráfico temporal anual preenchendo todos os anos consecutivos entre a primeira ordenação e o ano corrente.
- [ ] **Passo 5.2:** Altere o filtro de função no topo (ex.: de "Todos" para "Pastor" ou "Missionário").
- [ ] **Resultado Esperado:** Os 3 gráficos e os KPIs de resumo são recomputados em tempo real refletindo o subconjunto filtrado.

---

## 4. Matriz de Cobertura de Testes Automatizados

| Classe de Teste | Camada | Cobertura do DoD |
|---|---|---|
| [`ImportOrmibanRolCommandTest`](file:///c:/Users/fboli/Projetos/cbn-go/portal-cbngo-admin/tests/Feature/Console/ImportOrmibanRolCommandTest.php) | Feature / CLI | Carga de fichas, normalização de dados, vínculos de igreja e idempotência |
| [`AuthenticationAndRateLimitingTest`](file:///c:/Users/fboli/Projetos/cbn-go/portal-cbngo-admin/tests/Feature/Auth/AuthenticationAndRateLimitingTest.php) | Feature / Auth | Login por CPF (formatado/desformatado), rate limiting e primeiro acesso |
| [`OrmibanAuthTest`](file:///c:/Users/fboli/Projetos/cbn-go/portal-cbngo-admin/tests/Feature/Ormiban/OrmibanAuthTest.php) | Feature / Auth | Fluxo de redefinição e definição obrigatória de senha inicial no painel |
| [`OrmibanIsolationTest`](file:///c:/Users/fboli/Projetos/cbn-go/portal-cbngo-admin/tests/Feature/Ormiban/OrmibanIsolationTest.php) | Feature / Security | Isolamento estrito entre os painéis `/admin` e `/ormiban` para todos os papéis |
| [`MinisterResourceTest`](file:///c:/Users/fboli/Projetos/cbn-go/portal-cbngo-admin/tests/Feature/Filament/MinisterResourceTest.php) | Feature / Admin | Admin-only, visualização segura com mascaramento de CPF, busca e histórico |
| [`OrmibanIndicatorsTest`](file:///c:/Users/fboli/Projetos/cbn-go/portal-cbngo-admin/tests/Feature/Filament/OrmibanIndicatorsTest.php) | Feature / Filament | Acesso autorizado ao dashboard de indicadores e integração de filtros |
| [`OrmibanWidgetsSyntheticTest`](file:///c:/Users/fboli/Projetos/cbn-go/portal-cbngo-admin/tests/Feature/Ormiban/OrmibanWidgetsSyntheticTest.php) | Feature / Widgets | Cálculos determinísticos dos 3 gráficos com dataset sintético |
| [`ProductionDataLeakTest`](file:///c:/Users/fboli/Projetos/cbn-go/portal-cbngo-admin/tests/Feature/Security/ProductionDataLeakTest.php) | Feature / Security | Blindagem contra vazamento de arquivos de dados reais no repositório Git |
| [`MinisterDemographicsTest`](file:///c:/Users/fboli/Projetos/cbn-go/portal-cbngo-admin/tests/Unit/Services/Ormiban/MinisterDemographicsTest.php) | Unit / Service | Regras matemáticas de cálculo de idade, mediana, média e agregação temporal |
| [`CpfTest`](file:///c:/Users/fboli/Projetos/cbn-go/portal-cbngo-admin/tests/Unit/Support/CpfTest.php) | Unit / Support | Validação matemática de CPFs (dígitos verificadores, máscaras e sequências) |

