<script setup>
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { session } from './stores/session'

const router = useRouter()
const route = useRoute()
const admin = computed(() => session.state.admin)
const menuOpen = ref(false)

const links = [
    { name: 'admin.statistics', label: 'Статистика' },
    { name: 'admin.orders', label: 'Заказы' },
    { name: 'admin.products', label: 'Товары' },
    { name: 'admin.categories', label: 'Категории' },
    { name: 'admin.customers', label: 'Покупатели' },
    { name: 'admin.pages', label: 'Страницы' },
    { name: 'admin.site', label: 'Сайт' },
]

watch(() => route.fullPath, () => {
    menuOpen.value = false
})

async function logout() {
    await session.logout()
    router.push({ name: 'admin.login' })
}
</script>

<template>
    <div v-if="admin" class="flex min-h-full">
        <div
            v-if="menuOpen"
            class="fixed inset-0 z-20 bg-ink-950/60 lg:hidden"
            @click="menuOpen = false"
        ></div>

        <aside
            class="fixed inset-y-0 left-0 z-30 flex w-64 shrink-0 flex-col bg-ink-950 text-white transition-transform lg:static lg:w-56 lg:translate-x-0"
            :class="menuOpen ? 'translate-x-0' : '-translate-x-full'"
        >
            <div class="px-5 py-4">
                <p class="font-bold tracking-tight text-gold-300">agelesscode</p>
                <p class="text-xs text-ink-400">админка</p>
            </div>

            <nav class="flex-1 space-y-1 overflow-y-auto p-3 text-sm">
                <RouterLink
                    v-for="link in links"
                    :key="link.name"
                    :to="{ name: link.name }"
                    class="block rounded-lg px-3 py-2 text-ink-300 transition hover:bg-ink-900 hover:text-white"
                    active-class="bg-gold-400 font-semibold text-ink-950 hover:bg-gold-300 hover:text-ink-950"
                >
                    {{ link.label }}
                </RouterLink>
            </nav>

            <div class="border-t border-ink-800 p-3 text-sm">
                <p class="px-3 pb-2 text-ink-400">{{ admin.name }}</p>
                <button
                    type="button"
                    class="w-full rounded-lg px-3 py-2 text-left text-ink-300 transition hover:bg-ink-900 hover:text-white"
                    @click="logout"
                >
                    Выйти
                </button>
            </div>
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
            <header class="flex items-center gap-3 bg-ink-950 px-4 py-3 text-white lg:hidden">
                <button
                    type="button"
                    class="rounded-lg border border-ink-700 px-3 py-1.5 text-sm transition hover:border-ink-500"
                    aria-label="Меню"
                    @click="menuOpen = true"
                >
                    ☰
                </button>
                <span class="font-bold tracking-tight text-gold-300">agelesscode</span>
            </header>

            <main class="min-w-0 flex-1 p-4 sm:p-6">
                <RouterView />
            </main>
        </div>
    </div>

    <RouterView v-else />
</template>
