import axios from 'axios'

/**
 * CSRF rides the XSRF-TOKEN cookie rather than a token rendered into the page:
 * signing in and out regenerates the session, and the cookie follows that.
 */
const api = axios.create({
    baseURL: '/api',
    headers: { Accept: 'application/json' },
    withCredentials: true,
    withXSRFToken: true,
})

export function messageFrom(error, fallback = 'Что-то пошло не так') {
    // A 422 summary repeats the first field error and appends Laravel's own
    // untranslated "(and N more errors)"; the fields say it better themselves.
    if (error?.response?.data?.errors) {
        return ''
    }

    return error?.response?.data?.message ?? fallback
}

export function fieldErrorsFrom(error) {
    const errors = error?.response?.data?.errors ?? {}

    return Object.fromEntries(Object.entries(errors).map(([field, messages]) => [field, messages[0]]))
}

export default api
