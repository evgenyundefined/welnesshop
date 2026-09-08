import { reactive, readonly } from 'vue'
import api from '../api'

const emptyCart = { items: [], total_minor: 0, total_quantity: 0, currency: 'RUB' }

const state = reactive({
    customer: null,
    cart: { ...emptyCart },
    ready: false,
})

async function loadCart() {
    const { data } = await api.get('/cart')
    state.cart = data.data
}

async function loadCustomer() {
    try {
        const { data } = await api.get('/me')
        state.customer = data.data
    } catch {
        state.customer = null
    }
}

export const session = {
    state: readonly(state),

    async bootstrap() {
        await Promise.all([loadCustomer(), loadCart()])
        state.ready = true
    },

    async register(payload) {
        const { data } = await api.post('/register', payload)
        state.customer = data.data
        await loadCart()
    },

    async login(payload) {
        const { data } = await api.post('/login', payload)
        state.customer = data.data
        await loadCart()
    },

    async logout() {
        await api.post('/logout')
        state.customer = null
        await loadCart()
    },

    async addToCart(productId, quantity = 1) {
        const { data } = await api.post('/cart/items', { product_id: productId, quantity })
        state.cart = data.data
    },

    async updateCartItem(itemId, quantity) {
        const { data } = await api.patch(`/cart/items/${itemId}`, { quantity })
        state.cart = data.data
    },

    async removeCartItem(itemId) {
        const { data } = await api.delete(`/cart/items/${itemId}`)
        state.cart = data.data
    },

    async clearCart() {
        const { data } = await api.delete('/cart')
        state.cart = data.data
    },

    async checkout(payload) {
        const { data } = await api.post('/checkout', payload)
        await loadCart()

        return data.data
    },

    refreshCart: loadCart,
}
