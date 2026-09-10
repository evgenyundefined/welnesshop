<script setup>
import { computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { session } from './stores/session'
import { site } from './stores/site'

const router = useRouter()
const customer = computed(() => session.state.customer)
const cartCount = computed(() => session.state.cart.total_quantity)

const linkClass = 'text-ink-300 transition hover:text-gold-300'
const activeClass = 'text-gold-300 font-semibold'
const footerLink = 'text-ink-400 transition hover:text-gold-300'

onMounted(site.load)

async function logout() {
    await session.logout()
    router.push({ name: 'catalog' })
}
</script>

<template>
    <div class="flex min-h-full flex-col">
        <header class="sticky top-0 z-10 border-b border-ink-700 bg-ink-950 text-white">
            <div class="mx-auto flex h-16 max-w-6xl items-center gap-6 px-5">
                <RouterLink :to="{ name: 'catalog' }" class="text-lg font-bold tracking-tight text-gold-300">
                    agelesscode
                </RouterLink>

                <nav class="hidden items-center gap-5 text-sm lg:flex">
                    <RouterLink :to="{ name: 'catalog' }" :class="linkClass" :active-class="activeClass">
                        Каталог
                    </RouterLink>
                    <RouterLink
                        v-for="page in site.state.pages"
                        :key="page.slug"
                        :to="{ name: 'page', params: { slug: page.slug } }"
                        :class="linkClass"
                        :active-class="activeClass"
                    >
                        {{ page.title }}
                    </RouterLink>
                </nav>

                <nav class="ml-auto flex items-center gap-5 text-sm">
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
                        <span class="hidden text-ink-400 xl:inline">{{ customer.name }}</span>
                        <button type="button" :class="linkClass" @click="logout">Выйти</button>
                    </template>
                    <template v-else>
                        <RouterLink :to="{ name: 'login' }" :class="linkClass" :active-class="activeClass">
                            Вход
                        </RouterLink>
                        <RouterLink
                            :to="{ name: 'register' }"
                            class="hidden rounded-lg bg-gold-400 px-3 py-1.5 font-semibold text-ink-950 transition hover:bg-gold-300 sm:block"
                        >
                            Регистрация
                        </RouterLink>
                    </template>
                </nav>
            </div>

            <nav class="mx-auto flex max-w-6xl flex-wrap gap-4 px-5 pb-3 text-sm lg:hidden">
                <RouterLink :to="{ name: 'catalog' }" :class="linkClass" :active-class="activeClass">Каталог</RouterLink>
                <RouterLink
                    v-for="page in site.state.pages"
                    :key="page.slug"
                    :to="{ name: 'page', params: { slug: page.slug } }"
                    :class="linkClass"
                    :active-class="activeClass"
                >
                    {{ page.title }}
                </RouterLink>
            </nav>
        </header>

        <main class="mx-auto w-full max-w-6xl flex-1 px-5 py-8">
            <RouterView />
        </main>

        <footer class="mt-10 border-t border-ink-700 bg-ink-950 text-sm text-ink-400">
            <div class="mx-auto max-w-6xl px-5 py-10">
                <p class="mb-8 text-lg font-bold tracking-tight text-gold-300">agelesscode</p>

                <div class="grid gap-8 sm:grid-cols-2 lg:grid-cols-4">
                    <section v-if="site.state.products.length">
                        <h2 class="mb-3 font-semibold text-white">Пептиды</h2>
                        <ul class="flex flex-wrap gap-x-4 gap-y-2">
                            <li v-for="product in site.state.products" :key="product.slug">
                                <RouterLink
                                    :to="{ name: 'product', params: { slug: product.slug } }"
                                    :class="footerLink"
                                >
                                    {{ product.name }}
                                </RouterLink>
                            </li>
                        </ul>
                    </section>

                    <section v-if="site.state.categories.length">
                        <h2 class="mb-3 font-semibold text-white">Каталог</h2>
                        <ul class="space-y-2">
                            <li v-for="category in site.state.categories" :key="category.slug">
                                <RouterLink
                                    :to="{ name: 'catalog', query: { category: category.slug } }"
                                    :class="footerLink"
                                >
                                    {{ category.name }}
                                </RouterLink>
                            </li>
                        </ul>
                    </section>

                    <section v-if="site.state.pages.length">
                        <h2 class="mb-3 font-semibold text-white">Информация</h2>
                        <ul class="space-y-2">
                            <li v-for="page in site.state.pages" :key="page.slug">
                                <RouterLink :to="{ name: 'page', params: { slug: page.slug } }" :class="footerLink">
                                    {{ page.title }}
                                </RouterLink>
                            </li>
                        </ul>
                    </section>

                    <section v-if="site.state.contacts_html">
                        <h2 class="mb-3 font-semibold text-white">Контакты</h2>
                        <div class="prose-footer" v-html="site.state.contacts_html"></div>
                    </section>
                </div>

                <p v-if="site.state.disclaimer" class="mt-10 border-t border-ink-800 pt-6 text-xs leading-relaxed">
                    {{ site.state.disclaimer }}
                </p>
            </div>
        </footer>
    </div>
</template>
