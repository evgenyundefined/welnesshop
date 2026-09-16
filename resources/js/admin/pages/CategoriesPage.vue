<script setup>
import { onMounted, reactive, ref, watch } from 'vue'
import api, { fieldErrorsFrom, messageFrom } from '../api'
import Modal from '../components/Modal.vue'
import Pagination from '../components/Pagination.vue'
import { card, dangerButton, ghostButton, input, primaryButton, td, th } from '../ui'

const categories = ref([])
const meta = ref({ current_page: 1, last_page: 1, total: 0 })
const search = ref('')
const page = ref(1)
const error = ref('')

const editing = ref(null)
const form = reactive({ name: '', slug: '', description: '', position: 0, wholesale_only: false, min_order_quantity: 1 })
const formErrors = ref({})
const formMessage = ref('')
const saving = ref(false)

async function load() {
    const { data } = await api.get('/categories', {
        params: { search: search.value || undefined, page: page.value },
    })
    categories.value = data.data
    meta.value = data.meta
}

let searchTimer
watch(search, () => {
    clearTimeout(searchTimer)
    searchTimer = setTimeout(() => {
        page.value = 1
        load()
    }, 300)
})

watch(page, load)
onMounted(load)

function openCreate() {
    editing.value = 'new'
    Object.assign(form, { name: '', slug: '', description: '', position: 0, wholesale_only: false, min_order_quantity: 1 })
    formErrors.value = {}
    formMessage.value = ''
}

function openEdit(category) {
    editing.value = category
    Object.assign(form, {
        name: category.name,
        slug: category.slug,
        description: category.description ?? '',
        position: category.position,
        wholesale_only: category.wholesale_only,
        min_order_quantity: category.min_order_quantity,
    })
    formErrors.value = {}
    formMessage.value = ''
}

async function save() {
    formErrors.value = {}
    formMessage.value = ''
    saving.value = true

    const payload = {
        ...form,
        description: form.description || null,
        position: Number(form.position),
        min_order_quantity: Number(form.min_order_quantity),
    }

    try {
        if (editing.value === 'new') {
            await api.post('/categories', payload)
        } else {
            await api.patch(`/categories/${editing.value.id}`, payload)
        }

        editing.value = null
        await load()
    } catch (e) {
        formErrors.value = fieldErrorsFrom(e)
        formMessage.value = messageFrom(e, 'Не удалось сохранить категорию')
    } finally {
        saving.value = false
    }
}

async function move(category, direction) {
    error.value = ''

    try {
        await api.put(`/categories/${category.id}/position`, { direction })
        await load()
    } catch (e) {
        error.value = messageFrom(e, 'Не удалось изменить порядок')
    }
}

function isFirstOverall(index) {
    return meta.value.current_page === 1 && index === 0
}

function isLastOverall(index) {
    return meta.value.current_page === meta.value.last_page && index === categories.value.length - 1
}

async function remove(category) {
    if (!window.confirm(`Удалить категорию «${category.name}»?`)) {
        return
    }

    error.value = ''

    try {
        await api.delete(`/categories/${category.id}`)
        await load()
    } catch (e) {
        error.value = messageFrom(e, 'Не удалось удалить категорию')
    }
}
</script>

<template>
    <div class="space-y-4">
        <div class="flex flex-wrap items-center gap-3">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Категории</h1>
                <p class="text-sm text-ink-400">Порядок в списке — это порядок в каталоге, сверху вниз.</p>
            </div>
            <button type="button" :class="primaryButton" class="ml-auto" @click="openCreate">Добавить</button>
        </div>

        <input v-model="search" type="search" placeholder="Поиск по названию" :class="input" class="max-w-sm">

        <p v-if="error" class="text-sm text-red-700">{{ error }}</p>

        <div :class="card" class="overflow-x-auto p-5">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-ink-200">
                        <th :class="th">Название</th>
                        <th :class="th">Slug</th>
                        <th :class="th">Опт</th>
                        <th :class="th">Позиция</th>
                        <th :class="th">Товаров</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="(category, index) in categories"
                        :key="category.id"
                        class="border-b border-ink-100 last:border-0"
                    >
                        <td :class="td">{{ category.name }}</td>
                        <td :class="td" class="font-mono text-xs text-ink-500">{{ category.slug }}</td>
                        <td :class="td" class="text-xs text-ink-500">
                            <span v-if="category.wholesale_only" class="rounded-full bg-gold-100 px-2 py-1 text-gold-800">
                                опт
                            </span>
                            <span v-if="category.min_order_quantity > 1" class="ml-1 whitespace-nowrap">
                                от {{ category.min_order_quantity }} шт.
                            </span>
                            <span v-if="!category.wholesale_only && category.min_order_quantity <= 1">—</span>
                        </td>
                        <td :class="td">
                            <div class="flex items-center gap-1">
                                <span class="w-6 tabular-nums">{{ category.position }}</span>
                                <button
                                    type="button"
                                    :class="ghostButton"
                                    class="px-2 py-1 leading-none"
                                    :disabled="!!search || isFirstOverall(index)"
                                    :title="search ? 'Сначала очистите поиск' : 'Выше в каталоге'"
                                    @click="move(category, 'up')"
                                >
                                    ↑
                                </button>
                                <button
                                    type="button"
                                    :class="ghostButton"
                                    class="px-2 py-1 leading-none"
                                    :disabled="!!search || isLastOverall(index)"
                                    :title="search ? 'Сначала очистите поиск' : 'Ниже в каталоге'"
                                    @click="move(category, 'down')"
                                >
                                    ↓
                                </button>
                            </div>
                        </td>
                        <td :class="td">{{ category.products_count }}</td>
                        <td :class="td" class="text-right whitespace-nowrap">
                            <button type="button" :class="ghostButton" @click="openEdit(category)">Изменить</button>
                            <button type="button" :class="dangerButton" class="ml-2" @click="remove(category)">Удалить</button>
                        </td>
                    </tr>
                    <tr v-if="!categories.length">
                        <td :class="td" colspan="5" class="text-ink-400">Ничего не найдено.</td>
                    </tr>
                </tbody>
            </table>

            <Pagination v-model:page="page" :meta="meta" />
        </div>

        <Modal
            v-if="editing"
            :title="editing === 'new' ? 'Новая категория' : 'Категория'"
            @close="editing = null"
        >
            <form class="space-y-4" @submit.prevent="save">
                <div>
                    <label for="name" class="mb-1.5 block text-sm text-ink-500">Название</label>
                    <input id="name" v-model="form.name" type="text" required :class="input">
                    <p v-if="formErrors.name" class="mt-1 text-sm text-red-700">{{ formErrors.name }}</p>
                </div>

                <div>
                    <label for="slug" class="mb-1.5 block text-sm text-ink-500">Slug (необязательно)</label>
                    <input id="slug" v-model="form.slug" type="text" :class="input" placeholder="сгенерируется из названия">
                    <p v-if="formErrors.slug" class="mt-1 text-sm text-red-700">{{ formErrors.slug }}</p>
                </div>

                <div>
                    <label for="description" class="mb-1.5 block text-sm text-ink-500">Описание</label>
                    <textarea id="description" v-model="form.description" rows="3" :class="input"></textarea>
                    <p v-if="formErrors.description" class="mt-1 text-sm text-red-700">{{ formErrors.description }}</p>
                </div>

                <div>
                    <label for="position" class="mb-1.5 block text-sm text-ink-500">
                        Позиция в каталоге
                    </label>
                    <input id="position" v-model="form.position" type="number" min="0" :class="input">
                    <p class="mt-1 text-xs text-ink-400">Меньше — выше в списке категорий на витрине.</p>
                    <p v-if="formErrors.position" class="mt-1 text-sm text-red-700">{{ formErrors.position }}</p>
                </div>

                <div class="rounded-lg border border-ink-200 bg-ink-50 p-4">
                    <label class="flex items-center gap-2 text-sm">
                        <input v-model="form.wholesale_only" type="checkbox" class="size-4 accent-gold-500">
                        Только для оптовых закупок
                    </label>
                    <p class="mt-1 text-xs text-ink-400">
                        Подпись появится у цены — и в каталоге, и на странице товара.
                    </p>

                    <label for="min_order_quantity" class="mt-4 mb-1.5 block text-sm text-ink-500">
                        Минимум в заказе, шт.
                    </label>
                    <input
                        id="min_order_quantity"
                        v-model="form.min_order_quantity"
                        type="number"
                        min="1"
                        :class="input"
                    >
                    <p class="mt-1 text-xs text-ink-400">
                        Меньше этого количества товар нельзя положить в корзину и заказать. 1 — без ограничения.
                    </p>
                    <p v-if="formErrors.min_order_quantity" class="mt-1 text-sm text-red-700">
                        {{ formErrors.min_order_quantity }}
                    </p>
                </div>

                <p v-if="formMessage" class="text-sm text-red-700">{{ formMessage }}</p>

                <div class="flex justify-end gap-2">
                    <button type="button" :class="ghostButton" @click="editing = null">Отмена</button>
                    <button type="submit" :class="primaryButton" :disabled="saving">Сохранить</button>
                </div>
            </form>
        </Modal>
    </div>
</template>
