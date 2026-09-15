<script setup>
import { ref } from 'vue'
import { session } from '../stores/session'
import { formatMoney } from '../money'
import { messageFrom } from '../api'

const props = defineProps({ product: { type: Object, required: true } })

const error = ref('')
const pending = ref(false)

async function addToCart() {
    error.value = ''
    pending.value = true

    try {
        await session.addToCart(props.product.id, 1)
    } catch (e) {
        error.value = messageFrom(e, 'Не удалось добавить товар')
    } finally {
        pending.value = false
    }
}
</script>

<template>
    <article class="group flex flex-col overflow-hidden rounded-xl border border-ink-200 bg-white transition hover:border-ink-300">
        <div class="relative aspect-4/5 bg-ink-900">
            <RouterLink
                :to="{ name: 'product', params: { slug: product.slug } }"
                class="absolute inset-0 block overflow-hidden"
            >
                <img
                    v-if="product.cover"
                    :src="product.cover.url"
                    :alt="product.name"
                    loading="lazy"
                    class="size-full object-cover transition duration-500 group-hover:scale-105"
                >
                <span v-else class="block size-full bg-linear-to-br from-ink-700 via-ink-900 to-ink-950"></span>

                <!-- The text now lies on the photo, so it carries its own
                     darkness and stays readable over a light one. -->
                <span class="absolute inset-0 bg-linear-to-t from-ink-950 via-ink-950/70 to-transparent"></span>
            </RouterLink>

            <RouterLink
                :to="{ name: 'product', params: { slug: product.slug } }"
                class="absolute right-3 top-3 flex size-9 items-center justify-center rounded-full bg-ink-950/45 text-white backdrop-blur-sm transition hover:bg-ink-950/70 hover:text-gold-300"
                aria-label="Открыть карточку товара"
            >
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="size-5">
                    <path
                        d="M12 20.4 4.3 12.8a4.6 4.6 0 0 1 0-6.6 4.6 4.6 0 0 1 6.5 0l1.2 1.2 1.2-1.2a4.6 4.6 0 0 1 6.5 0 4.6 4.6 0 0 1 0 6.6Z"
                        stroke-linejoin="round"
                    />
                </svg>
            </RouterLink>

            <div class="pointer-events-none absolute inset-x-0 bottom-0 flex flex-col gap-1.5 p-4">
                <RouterLink
                    :to="{ name: 'product', params: { slug: product.slug } }"
                    class="pointer-events-auto font-semibold leading-snug text-white transition hover:text-gold-300"
                >
                    {{ product.name }}
                </RouterLink>

                <!-- On a phone the description simply stays; where there is a
                     mouse the card opens it on hover, so the grid keeps its
                     photos and the text is one movement away. -->
                <div
                    class="grid transition-[grid-template-rows] duration-300 ease-out desktop:grid-rows-[0fr] desktop:group-hover:grid-rows-[1fr]"
                >
                    <div
                        class="flex flex-col gap-1.5 overflow-hidden transition duration-300 ease-out desktop:translate-y-1 desktop:opacity-0 desktop:group-hover:translate-y-0 desktop:group-hover:opacity-100"
                    >
                        <p class="line-clamp-3 text-sm leading-snug text-ink-200">{{ product.summary }}</p>

                        <p v-if="product.supplier" class="text-xs text-ink-300">Поставщик: {{ product.supplier }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex flex-1 flex-col gap-3 p-4">
            <div class="mt-auto flex items-center justify-between gap-3">
                <span class="text-lg font-bold">{{ formatMoney(product.price_minor, product.currency) }}</span>
                <button
                    type="button"
                    class="rounded-lg bg-gold-400 px-3 py-2 text-sm font-semibold text-ink-950 transition hover:bg-gold-300 disabled:cursor-not-allowed disabled:bg-ink-200 disabled:text-ink-400"
                    :disabled="!product.is_available || pending"
                    @click="addToCart"
                >
                    {{ product.is_available ? 'В корзину' : 'Нет в наличии' }}
                </button>
            </div>

            <p v-if="error" class="text-sm text-red-700">{{ error }}</p>
        </div>
    </article>
</template>
