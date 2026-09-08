<script setup>
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { session } from './stores/session'

const router = useRouter()
const customer = computed(() => session.state.customer)
const cartCount = computed(() => session.state.cart.total_quantity)

const linkClass = 'text-ink-300 transition hover:text-gold-300'
const activeClass = 'text-gold-300 font-semibold'

async function logout() {
    await session.logout()
    router.push({ name: 'catalog' })
}
</script>

<template>
    <div class="flex min-h-full flex-col">
        <header class="sticky top-0 z-10 bg-ink-950 text-white">
            <div class="mx-auto flex h-16 max-w-6xl items-center gap-6 px-5">
                <RouterLink :to="{ name: 'catalog' }" class="text-lg font-bold tracking-tight text-gold-300">
                    Wellness Store
                </RouterLink>

                <nav class="ml-auto flex items-center gap-5 text-sm">
                    <RouterLink :to="{ name: 'catalog' }" :class="linkClass" :active-class="activeClass">
                        Каталог
                    </RouterLink>

                    <RouterLink :to="{ name: 'cart' }" :class="linkClass" :active-class="activeClass">
                        Корзина
                        <span
                            v-if="cartCount"
                            class="ml-1 inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-gold-400 px-1.5 text-xs font-semibold text-ink-950"
                        >{{ cartCount }}</span>
                    </RouterLink>

                    <template v-if="customer">
                        <RouterLink :to="{ name: 'orders' }" :class="linkClass" :active-class="activeClass">
                            Заказы
                        </RouterLink>
                        <span class="hidden text-ink-400 sm:inline">{{ customer.name }}</span>
                        <button type="button" :class="linkClass" @click="logout">Выйти</button>
                    </template>
                    <template v-else>
                        <RouterLink :to="{ name: 'login' }" :class="linkClass" :active-class="activeClass">
                            Вход
                        </RouterLink>
                        <RouterLink
                            :to="{ name: 'register' }"
                            class="rounded-lg bg-gold-400 px-3 py-1.5 font-semibold text-ink-950 transition hover:bg-gold-300"
                        >
                            Регистрация
                        </RouterLink>
                    </template>
                </nav>
            </div>
        </header>

        <main class="mx-auto w-full max-w-6xl flex-1 px-5 py-8">
            <RouterView />
        </main>

        <footer class="mt-4 bg-ink-950 py-6 text-center text-xs text-ink-400">
            Wellness Store — каталог устройств, пептидов, витаминов и косметологических препаратов
        </footer>
    </div>
</template>
