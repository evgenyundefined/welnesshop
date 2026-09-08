import axios from 'axios'

const api = axios.create({
    baseURL: '/admin/api',
    headers: { Accept: 'application/json' },
    withCredentials: true,
    withXSRFToken: true,
})

export function messageFrom(error, fallback = 'Что-то пошло не так') {
    return error?.response?.data?.message ?? fallback
}

export function fieldErrorsFrom(error) {
    const errors = error?.response?.data?.errors ?? {}

    return Object.fromEntries(Object.entries(errors).map(([field, messages]) => [field, messages[0]]))
}

export default api
