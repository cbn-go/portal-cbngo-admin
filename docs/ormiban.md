# Módulo Ormiban: Rol de Ministros Ativos

Este módulo gerencia o rol oficial de ministros da **Ormiban GO** (Ordem dos Ministros Batistas Nacionais - Secional Goiás) no painel administrativo da CBN-GO.

## 1. Fonte de Dados & Segurança (LGPD)

O cadastro inicial do rol é carregado a partir do arquivo JSON extraído das fichas cadastrais ativas (69 fichas).

> [!CAUTION]
> **Proteção de Dados Sensíveis (LGPD):** O arquivo com os dados reais dos ministros (CPFs, identidades, telefones, endereços e familiares) contém dados pessoais e **NUNCA deve ser commitado no Git**. O arquivo está listado no `.gitignore`.

Para executar a importação localmente ou em produção, disponibilize o arquivo fora do versionamento (ex.: em `storage/app/ormiban/fichas.json` ou passando o caminho absoluto via parâmetro).

## 2. Comando Artisan de Importação

Para carregar ou atualizar o rol de ministros no banco de dados, execute:

```bash
# Executa a importação informando o caminho do arquivo JSON local:
php artisan ormiban:import-rol /caminho/para/FICHAS_CADASTROS_ATIVOS_GO.json

# Ou coloque o arquivo em storage/app/ormiban/fichas.json:
php artisan ormiban:import-rol
```

### Características do Importador:
- **Idempotente:** O comando pode ser executado repetidas vezes sem duplicar ministros, familiares ou histórico. A busca e sincronização são feitas com base na chave única de **CPF** e **matrícula**.
- **Normalização de Dados:**
  - **CPF:** Remove pontuações e formata com 11 dígitos numéricos (`12345678901`).
  - **Datas:** Converte datas dos formatos `d/m/Y` e `d/m/Y H:i:s` para o padrão ISO (`Y-m-d` e `Y-m-d H:i:s`).
  - **Histórico:** Mapeia e corrige a codificação UTF-8 dos eventos conhecidos (como `1` => `DIPLOMADO`, `2` => `ORDENAÇÃO`, `13` => `FILIAÇÃO`).
  - **Parentesco:** Normaliza descrições de familiares (`Cônjuge`, `Pai`, `Mãe`, `Filho`, `Filha`, etc.).
- **Vínculo com Igrejas (*Best-effort*):**
  - Tenta associar automaticamente o campo `igreja` do rol a uma igreja cadastrada na tabela `churches` por nome normalizado.
  - Caso a igreja não seja encontrada, o ministro é salvo com `church_id = null`, mantendo o nome original em `church_name` para visualização e conferência.
- **Relatório no Console:**
  - Total de fichas processadas.
  - Ministros criados e atualizados.
  - Quantidade e lista das igrejas que não possuíram vínculo automático.
  - Validação de 0 CPFs duplicados no arquivo.

## 3. Estrutura de Banco de Dados

- **`ministers`**: Dados cadastrais principais, identificadores (CPF, RG, Matrícula), dados demográficos e FKs para `churches` e `users`.
- **`minister_family_members`**: Cônjuges, filhos e pais dos ministros, com parentesco e data de nascimento.
- **`minister_history_events`**: Linha do tempo de eventos ministeriais (`occurred_on`, `code`, `description`), base para o cálculo dos indicadores de tempo de ministério (DIPLOMADO) e ordenação (ORDENAÇÃO).

## 4. Homologação Manual e Definition of Done (DoD)

Para consultar o roteiro de testes manuais passo a passo, critérios de aceitação e a matriz completa de cobertura dos testes automatizados que fecham o Épico #68, consulte:
👉 [Checklist de Homologação Manual e DoD — Épico #68](ormiban-dod.md)
