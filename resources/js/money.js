const formatters = new Map()

export function formatMoney(minor, currency = 'RUB') {
    if (!formatters.has(currency)) {
        formatters.set(
            currency,
            new Intl.NumberFormat('ru-RU', { style: 'currency', currency, maximumFractionDigits: 0 }),
        )
    }

    return formatters.get(currency).format(minor / 100)
}

/**
 * Цена товара названа в его валюте, а платят в валюте магазина. Показывать
 * только одну из двух — значит либо скрыть ценник, либо скрыть сумму к оплате.
 */
export function settlementHint(minor, currency, site) {
    const settlement = site.currency ?? 'RUB'

    if (!currency || currency === settlement) {
        return ''
    }

    const rate = site.rates?.[currency]

    return rate ? `≈ ${formatMoney(Math.round(minor * rate), settlement)}` : ''
}
