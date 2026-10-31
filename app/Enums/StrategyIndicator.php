<?php

namespace App\Enums;

enum StrategyIndicator: string
{
    // População Geral
    case POPULATION_CENSUS_2022 = 'population_census_2022';
    case POPULATION_ESTIMATE = 'population_estimate';
    case POPULATION_GROWTH = 'population_growth';

    // Faixas Etárias (Censo 2022)
    case AGE_0_14 = 'age_0_14';
    case AGE_15_29 = 'age_15_29';
    case AGE_30_59 = 'age_30_59';
    case AGE_60_PLUS = 'age_60_plus';

    // Cor ou Raça (Censo 2022)
    case RACE_WHITE = 'race_white';
    case RACE_BLACK = 'race_black';
    case RACE_YELLOW = 'race_yellow';
    case RACE_BROWN = 'race_brown';
    case RACE_INDIGENOUS = 'race_indigenous';

    // Socioeconômicos & Territoriais
    case PIB_PER_CAPITA = 'pib_per_capita';
    case TERRITORIAL_AREA = 'territorial_area';
    case DEMOGRAPHIC_DENSITY = 'demographic_density';

    // Populações Base para Percentuais
    case POPULATION_10_PLUS_2022 = 'population_10_plus_2022';
    case POPULATION_2010 = 'population_2010';

    // Perfil Religioso Censo 2022 (Pessoas de 10 anos ou mais - Absoluto)
    case RELIGION_CATHOLIC_2022 = 'religion_catholic_2022';
    case RELIGION_EVANGELICAL_2022 = 'religion_evangelical_2022';
    case RELIGION_NONE_2022 = 'religion_none_2022';
    case RELIGION_OTHER_2022 = 'religion_other_2022';

    // Perfil Religioso Censo 2022 (% Calculado)
    case RELIGION_CATHOLIC_PCT_2022 = 'religion_catholic_pct_2022';
    case RELIGION_EVANGELICAL_PCT_2022 = 'religion_evangelical_pct_2022';
    case RELIGION_NONE_PCT_2022 = 'religion_none_pct_2022';
    case RELIGION_OTHER_PCT_2022 = 'religion_other_pct_2022';

    // Perfil Religioso Censo 2010 (População Residente - Absoluto)
    case RELIGION_EVANGELICAL_2010 = 'religion_evangelical_2010';
    case RELIGION_BAPTIST_2010 = 'religion_baptist_2010';

    // Perfil Religioso Censo 2010 (% Calculado)
    case RELIGION_EVANGELICAL_PCT_2010 = 'religion_evangelical_pct_2010';
    case RELIGION_BAPTIST_PCT_2010 = 'religion_baptist_pct_2010';

    // Indicadores Estratégicos Derivados
    case RELIGION_EVANGELICAL_VARIATION = 'religion_evangelical_variation';
    case NON_EVANGELICAL_POPULATION_ESTIMATE = 'non_evangelical_population_estimate';

    public function label(): string
    {
        return match ($this) {
            self::POPULATION_CENSUS_2022 => 'População Residente (Censo 2022)',
            self::POPULATION_ESTIMATE => 'População Estimada (Mais recente)',
            self::POPULATION_GROWTH => 'Crescimento Populacional',
            self::AGE_0_14 => 'População de 0 a 14 anos',
            self::AGE_15_29 => 'População de 15 a 29 anos',
            self::AGE_30_59 => 'População de 30 a 59 anos',
            self::AGE_60_PLUS => 'População de 60 anos ou mais',
            self::RACE_WHITE => 'População Branca',
            self::RACE_BLACK => 'População Preta',
            self::RACE_YELLOW => 'População Amarela',
            self::RACE_BROWN => 'População Parda',
            self::RACE_INDIGENOUS => 'População Indígena',
            self::PIB_PER_CAPITA => 'PIB per capita',
            self::TERRITORIAL_AREA => 'Área Territorial',
            self::DEMOGRAPHIC_DENSITY => 'Densidade Demográfica',
            self::POPULATION_10_PLUS_2022 => 'População de 10 anos ou mais (Censo 2022)',
            self::POPULATION_2010 => 'População Residente (Censo 2010)',
            self::RELIGION_CATHOLIC_2022 => 'Católicos (Censo 2022)',
            self::RELIGION_EVANGELICAL_2022 => 'Evangélicos (Censo 2022)',
            self::RELIGION_NONE_2022 => 'Sem Religião (Censo 2022)',
            self::RELIGION_OTHER_2022 => 'Outras Religiões (Censo 2022)',
            self::RELIGION_CATHOLIC_PCT_2022 => '% Católicos (Censo 2022)',
            self::RELIGION_EVANGELICAL_PCT_2022 => '% Evangélicos (Censo 2022)',
            self::RELIGION_NONE_PCT_2022 => '% Sem Religião (Censo 2022)',
            self::RELIGION_OTHER_PCT_2022 => '% Outras Religiões (Censo 2022)',
            self::RELIGION_EVANGELICAL_2010 => 'Evangélicos (Censo 2010)',
            self::RELIGION_BAPTIST_2010 => 'Batistas (Censo 2010)',
            self::RELIGION_EVANGELICAL_PCT_2010 => '% Evangélicos (Censo 2010)',
            self::RELIGION_BAPTIST_PCT_2010 => '% Batistas (Censo 2010)',
            self::RELIGION_EVANGELICAL_VARIATION => 'Variação de Evangélicos (2010 → 2022)',
            self::NON_EVANGELICAL_POPULATION_ESTIMATE => 'População Não Evangélica Estimada',
        };
    }

    public function unit(): string
    {
        return match ($this) {
            self::POPULATION_CENSUS_2022,
            self::POPULATION_ESTIMATE,
            self::AGE_0_14,
            self::AGE_15_29,
            self::AGE_30_59,
            self::AGE_60_PLUS,
            self::RACE_WHITE,
            self::RACE_BLACK,
            self::RACE_YELLOW,
            self::RACE_BROWN,
            self::RACE_INDIGENOUS,
            self::POPULATION_10_PLUS_2022,
            self::POPULATION_2010,
            self::RELIGION_CATHOLIC_2022,
            self::RELIGION_EVANGELICAL_2022,
            self::RELIGION_NONE_2022,
            self::RELIGION_OTHER_2022,
            self::RELIGION_EVANGELICAL_2010,
            self::RELIGION_BAPTIST_2010,
            self::NON_EVANGELICAL_POPULATION_ESTIMATE => 'habitantes',
            self::POPULATION_GROWTH,
            self::RELIGION_CATHOLIC_PCT_2022,
            self::RELIGION_EVANGELICAL_PCT_2022,
            self::RELIGION_NONE_PCT_2022,
            self::RELIGION_OTHER_PCT_2022,
            self::RELIGION_EVANGELICAL_PCT_2010,
            self::RELIGION_BAPTIST_PCT_2010 => '%',
            self::RELIGION_EVANGELICAL_VARIATION => 'p.p.',
            self::PIB_PER_CAPITA => 'R$',
            self::TERRITORIAL_AREA => 'km²',
            self::DEMOGRAPHIC_DENSITY => 'hab/km²',
        };
    }

    public function source(): string
    {
        return match ($this) {
            self::POPULATION_CENSUS_2022 => 'IBGE/SIDRA 4714 (Censo Demográfico 2022)',
            self::POPULATION_ESTIMATE => 'IBGE/SIDRA 6579 (Estimativas da População)',
            self::POPULATION_GROWTH => 'Calculado (Censo 2022 → Estimativa recente)',
            self::AGE_0_14,
            self::AGE_15_29,
            self::AGE_30_59,
            self::AGE_60_PLUS => 'IBGE/SIDRA 9514 (Censo Demográfico 2022)',
            self::RACE_WHITE,
            self::RACE_BLACK,
            self::RACE_YELLOW,
            self::RACE_BROWN,
            self::RACE_INDIGENOUS => 'IBGE/SIDRA 9606 (Censo Demográfico 2022)',
            self::PIB_PER_CAPITA => 'IBGE Cidades (Pesquisa 38 - PIB dos Municípios)',
            self::TERRITORIAL_AREA => 'IBGE/SIDRA 4714 (Área Territorial Oficial)',
            self::DEMOGRAPHIC_DENSITY => 'Calculado (População / Área)',
            self::POPULATION_10_PLUS_2022,
            self::RELIGION_CATHOLIC_2022,
            self::RELIGION_EVANGELICAL_2022,
            self::RELIGION_NONE_2022,
            self::RELIGION_OTHER_2022 => 'IBGE/SIDRA 9537 (Censo 2022 - Pessoas de 10 anos ou mais)',
            self::POPULATION_2010,
            self::RELIGION_EVANGELICAL_2010,
            self::RELIGION_BAPTIST_2010 => 'IBGE/SIDRA 137 (Censo Demográfico 2010)',
            self::RELIGION_CATHOLIC_PCT_2022,
            self::RELIGION_EVANGELICAL_PCT_2022,
            self::RELIGION_NONE_PCT_2022,
            self::RELIGION_OTHER_PCT_2022 => 'Calculado (% sobre População de 10 anos ou mais do Censo 2022)',
            self::RELIGION_EVANGELICAL_PCT_2010,
            self::RELIGION_BAPTIST_PCT_2010 => 'Calculado (% sobre População Residente Total do Censo 2010)',
            self::RELIGION_EVANGELICAL_VARIATION => 'Calculado (Diferença % Evangélicos 2022 - 2010 em p.p.)',
            self::NON_EVANGELICAL_POPULATION_ESTIMATE => 'Calculado (População Estimada × (1 - % Evangélicos 2022))',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::POPULATION_CENSUS_2022 => 'Total oficial de pessoas residentes recenseadas no Censo Demográfico 2022.',
            self::POPULATION_ESTIMATE => 'Estimativa anual oficial de habitantes residentes divulgada pelo IBGE.',
            self::POPULATION_GROWTH => 'Variação percentual entre a população do Censo 2022 e a estimativa mais recente.',
            self::AGE_0_14 => 'Contingente populacional infanto-juvenil de 0 a 14 anos de idade.',
            self::AGE_15_29 => 'Contingente de jovens de 15 a 29 anos de idade.',
            self::AGE_30_59 => 'Contingente populacional adulto de 30 a 59 anos de idade.',
            self::AGE_60_PLUS => 'Contingente de idosos com 60 anos ou mais de idade.',
            self::RACE_WHITE => 'Pessoas residentes autodeclaradas brancas no Censo 2022.',
            self::RACE_BLACK => 'Pessoas residentes autodeclaradas pretas no Censo 2022.',
            self::RACE_YELLOW => 'Pessoas residentes autodeclaradas amarelas no Censo 2022.',
            self::RACE_BROWN => 'Pessoas residentes autodeclaradas pardas no Censo 2022.',
            self::RACE_INDIGENOUS => 'Pessoas residentes autodeclaradas indígenas no Censo 2022.',
            self::PIB_PER_CAPITA => 'Produto Interno Bruto municipal a preços correntes dividido pela população residente.',
            self::TERRITORIAL_AREA => 'Área da unidade territorial oficial em quilômetros quadrados.',
            self::DEMOGRAPHIC_DENSITY => 'Relação entre o número de habitantes e a área territorial do município.',
            self::POPULATION_10_PLUS_2022 => 'Pessoas residentes com 10 anos ou mais de idade recenseadas no Censo 2022.',
            self::POPULATION_2010 => 'Total oficial de pessoas residentes recenseadas no Censo Demográfico 2010.',
            self::RELIGION_CATHOLIC_2022 => 'Pessoas de 10 anos ou mais que se declaram católicas apostólicas romanas no Censo 2022.',
            self::RELIGION_EVANGELICAL_2022 => 'Pessoas de 10 anos ou mais que se declaram evangélicas no Censo 2022.',
            self::RELIGION_NONE_2022 => 'Pessoas de 10 anos ou mais sem declaração de religião no Censo 2022.',
            self::RELIGION_OTHER_2022 => 'Pessoas de 10 anos ou mais declaradas em outras religiosidades, tradições ou espiritualidades no Censo 2022.',
            self::RELIGION_CATHOLIC_PCT_2022 => 'Percentual de pessoas de 10 anos ou mais católicas apostólicas romanas no Censo 2022.',
            self::RELIGION_EVANGELICAL_PCT_2022 => 'Percentual de pessoas de 10 anos ou mais evangélicas no Censo 2022.',
            self::RELIGION_NONE_PCT_2022 => 'Percentual de pessoas de 10 anos ou mais sem religião no Censo 2022.',
            self::RELIGION_OTHER_PCT_2022 => 'Percentual de pessoas de 10 anos ou mais declaradas em outras religiões no Censo 2022.',
            self::RELIGION_EVANGELICAL_2010 => 'Total de pessoas residentes declaradas evangélicas no Censo 2010.',
            self::RELIGION_BAPTIST_2010 => 'Número de pessoas que se declararam da Igreja Evangélica Batista no Censo 2010.',
            self::RELIGION_EVANGELICAL_PCT_2010 => 'Percentual de pessoas residentes declaradas evangélicas no Censo 2010.',
            self::RELIGION_BAPTIST_PCT_2010 => 'Percentual de pessoas residentes declaradas da Igreja Evangélica Batista no Censo 2010.',
            self::RELIGION_EVANGELICAL_VARIATION => 'Variação percentual de evangélicos entre o Censo 2010 e o Censo 2022 em pontos percentuais.',
            self::NON_EVANGELICAL_POPULATION_ESTIMATE => 'Estimativa de contingente populacional não evangélico usada como base para planejamento evangelístico.',
        };
    }

    public function methodologicalNote(): ?string
    {
        return match ($this) {
            self::RELIGION_EVANGELICAL_VARIATION => 'A base de cálculo difere entre os censos: no Censo 2010 o recorte religioso cobriu a população residente total, enquanto no Censo 2022 o IBGE investigou religião apenas para pessoas de 10 anos ou mais de idade.',
            self::NON_EVANGELICAL_POPULATION_ESTIMATE => 'Calculada aplicando a proporção de não evangélicos do Censo 2022 (pessoas de 10 anos ou mais) sobre a estimativa anual de população mais recente divulgada pelo IBGE.',
            default => null,
        };
    }

    /**
     * Retorna a configuração para consulta do indicador na API do SIDRA.
     * Retorna null se for um indicador derivado/calculado ou consultado por cliente dedicado.
     *
     * @return array{table: int, period: string, variable: int, classification: string|null, aggregate_sum?: bool}|null
     */
    public function sidraConfig(): ?array
    {
        return match ($this) {
            self::POPULATION_CENSUS_2022 => [
                'table' => 4714,
                'period' => '2022',
                'variable' => 93,
                'classification' => null,
            ],
            self::POPULATION_ESTIMATE => [
                'table' => 6579,
                'period' => '-1',
                'variable' => 9324,
                'classification' => null,
            ],
            self::AGE_0_14 => [
                'table' => 9514,
                'period' => '2022',
                'variable' => 93,
                'classification' => '287[93070,93084,93085]',
                'aggregate_sum' => true,
            ],
            self::AGE_15_29 => [
                'table' => 9514,
                'period' => '2022',
                'variable' => 93,
                'classification' => '287[93086,93087,93088]',
                'aggregate_sum' => true,
            ],
            self::AGE_30_59 => [
                'table' => 9514,
                'period' => '2022',
                'variable' => 93,
                'classification' => '287[93089,93090,93091,93092,93093,93094]',
                'aggregate_sum' => true,
            ],
            self::AGE_60_PLUS => [
                'table' => 9514,
                'period' => '2022',
                'variable' => 93,
                'classification' => '287[93095,93096,93097,93098,49108,49109,60040,60041,6653]',
                'aggregate_sum' => true,
            ],
            self::RACE_WHITE => [
                'table' => 9606,
                'period' => '2022',
                'variable' => 93,
                'classification' => '86[2776]',
            ],
            self::RACE_BLACK => [
                'table' => 9606,
                'period' => '2022',
                'variable' => 93,
                'classification' => '86[2777]',
            ],
            self::RACE_YELLOW => [
                'table' => 9606,
                'period' => '2022',
                'variable' => 93,
                'classification' => '86[2778]',
            ],
            self::RACE_BROWN => [
                'table' => 9606,
                'period' => '2022',
                'variable' => 93,
                'classification' => '86[2779]',
            ],
            self::RACE_INDIGENOUS => [
                'table' => 9606,
                'period' => '2022',
                'variable' => 93,
                'classification' => '86[2780]',
            ],
            self::TERRITORIAL_AREA => [
                'table' => 4714,
                'period' => '2022',
                'variable' => 6318,
                'classification' => null,
            ],
            self::POPULATION_10_PLUS_2022 => [
                'table' => 9537,
                'period' => '2022',
                'variable' => 140,
                'classification' => '133[95278]|2[6794]|58[95253]',
            ],
            self::RELIGION_CATHOLIC_2022 => [
                'table' => 9537,
                'period' => '2022',
                'variable' => 140,
                'classification' => '133[95263]|2[6794]|58[95253]',
            ],
            self::RELIGION_EVANGELICAL_2022 => [
                'table' => 9537,
                'period' => '2022',
                'variable' => 140,
                'classification' => '133[95277]|2[6794]|58[95253]',
            ],
            self::RELIGION_NONE_2022 => [
                'table' => 9537,
                'period' => '2022',
                'variable' => 140,
                'classification' => '133[2836]|2[6794]|58[95253]',
            ],
            self::RELIGION_OTHER_2022 => [
                'table' => 9537,
                'period' => '2022',
                'variable' => 140,
                'classification' => '133[2826,2827,95274,95275,12890,2837]|2[6794]|58[95253]',
                'aggregate_sum' => true,
            ],
            self::POPULATION_2010 => [
                'table' => 137,
                'period' => '2010',
                'variable' => 93,
                'classification' => '133[0]',
            ],
            self::RELIGION_EVANGELICAL_2010 => [
                'table' => 137,
                'period' => '2010',
                'variable' => 93,
                'classification' => '133[95277]',
            ],
            self::RELIGION_BAPTIST_2010 => [
                'table' => 137,
                'period' => '2010',
                'variable' => 93,
                'classification' => '133[99741]',
            ],
            default => null,
        };
    }

    public function isCalculated(): bool
    {
        return $this === self::POPULATION_GROWTH
            || $this === self::DEMOGRAPHIC_DENSITY
            || $this === self::RELIGION_CATHOLIC_PCT_2022
            || $this === self::RELIGION_EVANGELICAL_PCT_2022
            || $this === self::RELIGION_NONE_PCT_2022
            || $this === self::RELIGION_OTHER_PCT_2022
            || $this === self::RELIGION_EVANGELICAL_PCT_2010
            || $this === self::RELIGION_BAPTIST_PCT_2010
            || $this === self::RELIGION_EVANGELICAL_VARIATION
            || $this === self::NON_EVANGELICAL_POPULATION_ESTIMATE;
    }

    /**
     * Seção do indicador nas telas do Painel de Estratégia.
     */
    public function group(): string
    {
        return match ($this) {
            self::POPULATION_CENSUS_2022,
            self::POPULATION_ESTIMATE,
            self::POPULATION_GROWTH,
            self::POPULATION_10_PLUS_2022,
            self::NON_EVANGELICAL_POPULATION_ESTIMATE => 'População',
            self::AGE_0_14,
            self::AGE_15_29,
            self::AGE_30_59,
            self::AGE_60_PLUS => 'Faixa etária',
            self::RACE_WHITE,
            self::RACE_BLACK,
            self::RACE_YELLOW,
            self::RACE_BROWN,
            self::RACE_INDIGENOUS => 'Cor ou raça',
            self::RELIGION_CATHOLIC_2022,
            self::RELIGION_EVANGELICAL_2022,
            self::RELIGION_NONE_2022,
            self::RELIGION_OTHER_2022,
            self::RELIGION_CATHOLIC_PCT_2022,
            self::RELIGION_EVANGELICAL_PCT_2022,
            self::RELIGION_NONE_PCT_2022,
            self::RELIGION_OTHER_PCT_2022,
            self::RELIGION_EVANGELICAL_VARIATION => 'Religião (Censo 2022)',
            self::POPULATION_2010,
            self::RELIGION_EVANGELICAL_2010,
            self::RELIGION_BAPTIST_2010,
            self::RELIGION_EVANGELICAL_PCT_2010,
            self::RELIGION_BAPTIST_PCT_2010 => 'Religião (Censo 2010)',
            self::PIB_PER_CAPITA,
            self::TERRITORIAL_AREA,
            self::DEMOGRAPHIC_DENSITY => 'Território e economia',
        };
    }

    /**
     * Valor formatado em pt-BR com a unidade do indicador.
     */
    public function format(float|string|null $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        $value = (float) $value;

        return match ($this->unit()) {
            'habitantes' => number_format($value, 0, ',', '.'),
            '%' => number_format($value, 2, ',', '.').'%',
            'p.p.' => ($value > 0 ? '+' : '').number_format($value, 2, ',', '.').' p.p.',
            'R$' => 'R$ '.number_format($value, 2, ',', '.'),
            default => number_format($value, 2, ',', '.').' '.$this->unit(),
        };
    }
}
