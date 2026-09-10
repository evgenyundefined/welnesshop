<script setup>
import { onMounted, ref, watch } from 'vue'
import api from '../api'
import { useInternalLinkNavigation } from '../internalLinks'

const props = defineProps({ slug: { type: String, required: true } })

const followInternalLink = useInternalLinkNavigation()

const page = ref(null)
const missing = ref(false)

async function load() {
    page.value = null
    missing.value = false

    try {
        const { data } = await api.get(`/pages/${props.slug}`)
        page.value = data.data
    } catch {
        missing.value = true
    }
}

watch(() => props.slug, load)
onMounted(load)
</script>

<template>
    <article v-if="page" class="mx-auto max-w-3xl space-y-4">
        <h1 class="text-2xl font-bold tracking-tight">{{ page.title }}</h1>

        <div
            class="prose-page rounded-xl border border-ink-200 bg-white p-6"
            v-html="page.body_html"
            @click="followInternalLink"
        ></div>
    </article>

    <p v-else-if="missing" class="text-ink-400">Страница не найдена.</p>
    <p v-else class="text-ink-400">Загрузка…</p>
</template>

