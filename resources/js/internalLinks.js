import { useRouter } from 'vue-router'

/**
 * Blocks written in the admin editor hold plain <a href="/pages/…">. Without
 * this the browser would reload the whole application on every such link.
 */
export function useInternalLinkNavigation() {
    const router = useRouter()

    return (event) => {
        const link = event.target.closest('a')

        if (!link || event.defaultPrevented || event.button !== 0) {
            return
        }

        if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return
        }

        if (link.target === '_blank' || link.hasAttribute('download')) {
            return
        }

        const url = new URL(link.getAttribute('href') ?? '', window.location.origin)

        if (url.origin !== window.location.origin) {
            return
        }

        const target = router.resolve(url.pathname + url.search + url.hash)

        if (!target.matched.length) {
            return
        }

        event.preventDefault()
        router.push(target)
    }
}
