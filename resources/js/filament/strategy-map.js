// Mapa coroplético do Painel de Estratégia (#56).
// Componente Alpine carregado sob demanda pelo Filament (x-load). O Leaflet e a
// malha do IBGE são servidos pelo próprio painel: o navegador não chama
// nenhum servidor externo. Objetos do Leaflet ficam fora do estado reativo do
// Alpine, porque os proxies dele quebram o Leaflet.

const loaded = {}

function loadOnce(tag, attributes) {
    const key = attributes.src || attributes.href

    loaded[key] ??= new Promise((resolve, reject) => {
        const element = document.createElement(tag)
        Object.assign(element, attributes)
        element.onload = resolve
        element.onerror = () => reject(new Error(`Falha ao carregar ${key}`))
        document.head.appendChild(element)
    })

    return loaded[key]
}

const escapeHtml = (text) =>
    String(text ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c])

const numberFormat = (decimals) =>
    new Intl.NumberFormat('pt-BR', { minimumFractionDigits: decimals, maximumFractionDigits: decimals })

const formatters = {
    habitantes: (v) => numberFormat(0).format(v),
    '%': (v) => `${numberFormat(2).format(v)}%`,
    pontos: (v) => numberFormat(1).format(v),
}

export default function strategyMap({ geojsonUrl, leafletJs, leafletCss, municipalities, churches, layers, initialLayer }) {
    let map = null
    let geo = null

    return {
        layer: initialLayer,
        layers,
        loading: true,
        error: null,

        async init() {
            try {
                await Promise.all([
                    loadOnce('link', { rel: 'stylesheet', href: leafletCss }),
                    loadOnce('script', { src: leafletJs }),
                ])

                const response = await fetch(geojsonUrl, {
                    credentials: 'same-origin',
                    headers: { Accept: 'application/geo+json, application/json' },
                })

                if (!response.ok) {
                    throw new Error(`Malha indisponível (${response.status})`)
                }

                this.render(await response.json())
            } catch (exception) {
                console.error(exception)
                this.error = 'Não foi possível carregar o mapa. Recarregue a página.'
            } finally {
                this.loading = false
            }

            this.$watch('layer', () => geo?.setStyle((feature) => this.styleFor(feature)))
        },

        render(data) {
            const L = window.L
            const dark = document.documentElement.classList.contains('dark')

            map = L.map(this.$refs.map, {
                zoomSnap: 0.25,
                scrollWheelZoom: false,
                attributionControl: false,
            })

            L.control.attribution({ prefix: false }).addAttribution('Malha municipal: IBGE').addTo(map)

            geo = L.geoJSON(data, {
                style: (feature) => this.styleFor(feature),
                onEachFeature: (feature, path) => {
                    const municipality = municipalities[feature.properties.codarea]

                    path.bindTooltip(() => this.tooltipFor(municipality), { sticky: true, direction: 'top', opacity: 1 })

                    path.on({
                        mouseover: () => {
                            path.setStyle({ weight: 2.5, color: dark ? '#f0efec' : '#0b0b0b' })
                            path.bringToFront()
                        },
                        mouseout: () => geo.resetStyle(path),
                        click: () => {
                            if (municipality?.url) {
                                window.location.href = municipality.url
                            }
                        },
                    })
                },
            })

            // A vista precisa existir antes da camada: o Leaflet recorta os
            // polígonos pelos limites do mapa ao adicioná-los.
            const fit = () => map.fitBounds(geo.getBounds(), { padding: [8, 8] })
            fit()
            geo.addTo(map)

            churches.forEach((church) => {
                L.circleMarker([church.lat, church.lng], {
                    radius: 5,
                    weight: 2,
                    color: dark ? '#f0efec' : '#0b0b0b',
                    fillColor: dark ? '#1a1a19' : '#ffffff',
                    fillOpacity: 1,
                })
                    .bindPopup(
                        `<strong>${escapeHtml(church.name)}</strong><br>${escapeHtml(church.city)}<br>` +
                            `<a href="${escapeHtml(church.url)}">Abrir cadastro</a>`,
                    )
                    .addTo(map)
            })

            // O widget pode mudar de largura (menu lateral, rotação do celular).
            new ResizeObserver(() => {
                map.invalidateSize()
                fit()
            }).observe(this.$refs.map)
        },

        current() {
            return this.layers[this.layer]
        },

        special(key) {
            return this.current().special.find((item) => item.key === key)
        },

        // "Sem igreja" e "sem dados" têm cor própria: nunca viram zero.
        colorFor(municipality) {
            if (!municipality) {
                return this.special('no_data')?.color
            }

            if (this.layer === 'inhabitants_per_church' && municipality.churches === 0) {
                return this.special('no_church').color
            }

            const value = municipality[this.layer]

            if (value === null || value === undefined) {
                return this.special('no_data')?.color
            }

            const match = this.current().classes.find(
                (item) => (item.min === null || value >= item.min) && (item.max === null || value < item.max),
            )

            return match?.color ?? this.special('no_data')?.color
        },

        styleFor(feature) {
            const dark = document.documentElement.classList.contains('dark')

            return {
                fillColor: this.colorFor(municipalities[feature.properties.codarea]),
                fillOpacity: 1,
                // Contorno na cor do fundo: separa municípios vizinhos da mesma faixa.
                color: dark ? '#1a1a19' : '#ffffff',
                weight: 0.8,
            }
        },

        countFor(color) {
            return Object.values(municipalities).filter((municipality) => this.colorFor(municipality) === color).length
        },

        tooltipFor(municipality) {
            if (!municipality) {
                return 'Município sem dados'
            }

            const layer = this.current()
            const value = municipality[this.layer]
            let text

            if (this.layer === 'inhabitants_per_church' && municipality.churches === 0) {
                text = 'Sem igreja da CBN-GO'
            } else if (value === null || value === undefined) {
                text = 'Sem dados do IBGE'
            } else {
                text = `${formatters[layer.unit](value)} ${layer.unit === 'habitantes' ? 'hab.' : ''}`.trim()
            }

            const churchesLabel = municipality.churches === 1 ? '1 igreja ativa' : `${municipality.churches} igrejas ativas`

            return `<strong>${escapeHtml(municipality.name)}</strong><br>${escapeHtml(layer.label)}: ${escapeHtml(text)}<br>${churchesLabel}`
        },
    }
}
