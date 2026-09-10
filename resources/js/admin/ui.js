export const input =
    'w-full rounded-lg border border-ink-300 bg-white px-3 py-2 text-sm outline-none focus:border-gold-500 focus:ring-1 focus:ring-gold-500'

export const primaryButton =
    'rounded-lg bg-gold-400 px-3 py-2 text-sm font-semibold text-ink-950 transition hover:bg-gold-300 disabled:cursor-not-allowed disabled:bg-ink-200 disabled:text-ink-400'

export const ghostButton =
    'rounded-lg border border-ink-300 px-3 py-2 text-sm transition hover:border-ink-400 '
    + 'disabled:cursor-not-allowed disabled:border-ink-200 disabled:text-ink-300 disabled:hover:border-ink-200'

export const dangerButton =
    'rounded-lg border border-red-300 px-3 py-2 text-sm text-red-700 transition hover:bg-red-50'

export const card = 'rounded-xl border border-ink-200 bg-white'

export const th = 'py-2 pr-4 text-left text-xs font-medium uppercase tracking-wide text-ink-400'

export const td = 'py-3 pr-4 align-middle'

export const orderStatuses = {
    awaiting_payment: 'Ожидает оплаты',
    paid: 'Оплачен',
    cancelled: 'Отменён',
}

export const paymentStatuses = {
    pending: 'Не проведена',
    awaiting_gateway: 'Ждёт платёжную систему',
    succeeded: 'Оплачен',
    failed: 'Отклонён',
}

export const paymentMethods = {
    card: 'Банковская карта',
    sbp: 'СБП',
    invoice: 'Счёт для юр. лиц',
}

export const deliveryMethods = {
    courier: 'Курьером',
    transport_company: 'Транспортной компанией',
}

export const productStatuses = {
    draft: 'Черновик',
    published: 'Опубликован',
    archived: 'В архиве',
}

export const pageVisibilities = {
    published: 'В меню и по ссылке',
    unlisted: 'Только по ссылке',
    draft: 'Черновик',
}

export const pageVisibilityBadges = {
    published: 'bg-ink-100 text-ink-600',
    unlisted: 'bg-gold-100 text-gold-800',
    draft: 'bg-red-50 text-red-700',
}
