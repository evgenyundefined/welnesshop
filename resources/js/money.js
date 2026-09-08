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
