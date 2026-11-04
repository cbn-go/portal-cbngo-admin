# Módulo Ormiban: Rol de Ministros Ativos

Este módulo gerencia o rol oficial de ministros da **Ormiban GO** (Ordem dos Ministros Batistas Nacionais - Secional Goiás) no painel administrativo da CBN-GO.

## 1. Fonte de Dados

O cadastro inicial do rol é carregado a partir do arquivo JSON extraído das fichas cadastrais ativas:

- **Caminho padrão no repositório:** `database/data/FICHAS CADASTROS ATIVOS GO.json`
- **Total de fichas oficiais:** 69 registros ativos (67 pastores e 2 missionários).

## 2. Comando Artisan de Importação

Para carregar ou atualizar o rol de ministros no banco de dados, execute:

```bash
# Executa a importação usando o caminho padrão em database/data/
php artisan ormiban:import-rol

# Ou informe um caminho customizado para o arquivo JSON:
php artisan ormiban:import-rol "caminho/para/arquivo.json"
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
