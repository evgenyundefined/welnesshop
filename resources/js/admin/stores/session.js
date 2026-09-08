import { reactive, readonly } from 'vue'
import api from '../api'

const state = reactive({ admin: null, ready: false })

export const session = {
    state: readonly(state),

    async bootstrap() {
        try {
            const { data } = await api.get('/me')
            state.admin = data.data
        } catch {
            state.admin = null
        }

        state.ready = true
    },

    async login(payload) {
        const { data } = await api.post('/login', payload)
        state.admin = data.data
    },

    async logout() {
        await api.post('/logout')
        state.admin = null
    },
}
