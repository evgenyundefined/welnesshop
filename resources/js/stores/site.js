import { reactive, readonly } from 'vue'
import api from '../api'

const state = reactive({
    banner: null,
    promo: null,
    disclaimer: null,
    online_payment: false,
    max_item_quantity: 99,
    delivery_methods: [],
    payment_methods: [],
    currency: 'RUB',
    rates: {},
    logo_url: null,
    contacts_html: null,
    info_html: null,
    pages: [],
    categories: [],
    products: [],
    ready: false,
})

export const site = {
    state: readonly(state),

    async load() {
        const { data } = await api.get('/site')

        Object.assign(state, data.data, { ready: true })
    },
}
