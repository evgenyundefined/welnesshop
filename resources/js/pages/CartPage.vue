<script setup>
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import { site } from '../stores/site'
import { session } from '../stores/session'
import { formatMoney } from '../money'
import { messageFrom } from '../api'
import Thumbnail from '../components/Thumbnail.vue'

const router = useRouter()
const cart = computed(() => session.state.cart)
const error = ref('')

async function run(operation) {
    error.value = ''

    try {
        await operation()
    } catch (e) {
        error.value = messageFrom(e, 'Не удалось обновить корзину')
        await session.refreshCart()
    }
}

const changeQuantity = (item, quantity) => run(() => session.updateCartItem(item.id, Number(quantity)))
const remove = (item) => run(() => session.removeCartItem(item.id))
const clear = () => run(() => session.clearCart())

function goToCheckout() {
    router.push(session.state.customer ? { name: 'checkout' } : { name: 'login', query: { redirect: '/checkout' } })
}
</script>

<template>
    <h1 class="mb-6 text-2xl font-bold tracking-tight">Корзина</h1>

    <p v-if="!cart.items.length" class="text-ink-500">
        Корзина пуста.
        <RouterLink :to="{ name: 'catalog' }" class="text-gold-700 underline">Перейти в каталог</RouterLink>
    </p>

    <div v-else class="space-y-4 rounded-xl border border-ink-200 bg-white p-6">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-ink-200 text-xs uppercase tracking-wide text-ink-400">
                        <th class="py-2 text-left font-medium">Товар</th>
                        <th class="py-2 text-left font-medium">Цена</th>
                        <th class="py-2 text-left font-medium">Количество</th>
                        <th class="py-2 text-left font-medium">Сумма</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="item in cart.items" :key="item.id" class="border-b border-ink-100">
                        <td class="py-3 pr-4">
                            <RouterLink
                                :to="{ name: 'product', params: { slug: item.product.slug } }"
                                class="flex items-center gap-3 hover:text-gold-700"
                            >
                                <Thumbnail :url="item.product.cover?.url" :alt="item.product.name" />
                                <span>{{ item.product.name }}</span>
                            </RouterLink>
                        </td>
                        <td class="py-3 pr-4 whitespace-nowrap">{{ formatMoney(item.unit_price_minor, cart.currency) }}</td>
                        <td class="py-3 pr-4">
                            <input
                                type="number"
                                min="1"
                                :max="site.state.max_item_quantity"
                                :value="item.quantity"
                                class="w-20 rounded-lg border border-ink-300 px-2 py-1.5 outline-none focus:border-gold-500 focus:ring-1 focus:ring-gold-500"
                                @change="changeQuantity(item, $event.target.value)"
                            >
                        </td>
                        <td class="py-3 pr-4 font-semibold whitespace-nowrap">{{ formatMoney(item.total_minor, cart.currency) }}</td>
                        <td class="py-3 text-right">
                            <button type="button" class="text-ink-400 transition hover:text-red-700" @click="remove(item)">
                                Удалить
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <p v-if="error" class="text-sm text-red-700">{{ error }}</p>

        <div class="flex flex-wrap items-center justify-between gap-4 border-t border-ink-200 pt-4">
            <button
                type="button"
                class="rounded-lg border border-ink-300 px-3 py-2 text-sm transition hover:border-ink-400"
                @click="clear"
            >
                Очистить корзину
            </button>

            <div class="flex items-center gap-4">
                <span class="text-lg font-bold">Итого: {{ formatMoney(cart.total_minor, cart.currency) }}</span>
                <button
                    type="button"
                    class="rounded-lg bg-gold-400 px-4 py-2 text-sm font-semibold text-ink-950 transition hover:bg-gold-300"
                    @click="goToCheckout"
                >
                    Оформить заказ
                </button>
            </div>
        </div>
    </div>
</template>
