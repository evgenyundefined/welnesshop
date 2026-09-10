<script setup>
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { session } from './stores/session'

const router = useRouter()
const admin = computed(() => session.state.admin)

const links = [
    { name: 'admin.statistics', label: 'Статистика' },
    { name: 'admin.orders', label: 'Заказы' },
    { name: 'admin.products', label: 'Товары' },
    { name: 'admin.categories', label: 'Категории' },
    { name: 'admin.customers', label: 'Покупатели' },
    { name: 'admin.pages', label: 'Страницы' },
    { name: 'admin.site', label: 'Сайт' },
]

async function logout() {
    await session.logout()
    router.push({ name: 'admin.login' })
}
</script>

<template>
    <div v-if="admin" class="flex min-h-full">
        <aside class="flex w-56 shrink-0 flex-col bg-ink-950 text-white">
            <div class="px-5 py-4">
                <p class="font-bold tracking-tight text-gold-300">agelesscode</p>
                <p class="text-xs text-ink-400">админка</p>
            </div>

            <nav class="flex-1 space-y-1 p-3 text-sm">
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

        <main class="min-w-0 flex-1 p-6">
            <RouterView />
        </main>
    </div>

    <RouterView v-else />
</template>
