/**
 * The server renders the title for the first page; every navigation after that
 * happens in the browser, where nothing updates it on its own. A stale title is
 * what ends up in a bookmark and in the history.
 */
const fallback = (name, value) => document.querySelector(`meta[name="${name}"]`)?.content || value

export function setDocumentTitle(title) {
    document.title = title
        ? `${title} — ${fallback('site-brand', 'agelesscode')}`
        : fallback('site-title', 'agelesscode')
}

export function setMetaDescription(description) {
    const tag = document.querySelector('meta[name="description"]')

    if (tag) {
        tag.setAttribute('content', description ?? '')
    }
}
