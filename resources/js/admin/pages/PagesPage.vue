<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import api, { fieldErrorsFrom, messageFrom } from '../api'
import Modal from '../components/Modal.vue'
import Pagination from '../components/Pagination.vue'
import RichEditor from '../components/RichEditor.vue'
import { card, dangerButton, ghostButton, input, pageVisibilities, pageVisibilityBadges, primaryButton, td, th } from '../ui'

const pages = ref([])
const meta = ref({ current_page: 1, last_page: 1, total: 0 })
const search = ref('')
const page = ref(1)
const error = ref('')

const editing = ref(null)
const pageUrl = computed(() => `${window.location.origin}/pages/${form.slug}`)
const form = reactive({ title: '', slug: '', body: '', position: 0, visibility: 'published' })
const formErrors = ref({})
const formMessage = ref('')
const saving = ref(false)

async function load() {
    const { data } = await api.get('/pages', {
        params: { search: search.value || undefined, page: page.value },
    })
    pages.value = data.data
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
    Object.assign(form, { title: '', slug: '', body: '', position: 0, visibility: 'published' })
    formErrors.value = {}
    formMessage.value = ''
}

async function openEdit(row) {
    const { data } = await api.get(`/pages/${row.id}`)

    editing.value = data.data
    Object.assign(form, {
        title: data.data.title,
        slug: data.data.slug,
        body: data.data.body,
        position: data.data.position,
        visibility: data.data.visibility,
    })
    formErrors.value = {}
    formMessage.value = ''
}

async function save() {
    formErrors.value = {}
    formMessage.value = ''
    saving.value = true

    const payload = { ...form, position: Number(form.position) }

    try {
        if (editing.value === 'new') {
            await api.post('/pages', payload)
        } else {
            await api.patch(`/pages/${editing.value.id}`, payload)
        }

        editing.value = null
        await load()
    } catch (e) {
        formErrors.value = fieldErrorsFrom(e)
        formMessage.value = messageFrom(e, 'Не удалось сохранить страницу')
    } finally {
        saving.value = false
    }
}

async function remove(row) {
    if (!window.confirm(`Удалить страницу «${row.title}»?`)) {
        return
    }

    error.value = ''

    try {
        await api.delete(`/pages/${row.id}`)
        await load()
    } catch (e) {
        error.value = messageFrom(e, 'Не удалось удалить страницу')
    }
}
</script>

<template>
    <div class="space-y-4">
        <div class="flex flex-wrap items-center gap-3">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Страницы</h1>
                <p class="text-sm text-ink-400">Порядок задаёт позицию в верхнем меню и в футере.</p>
            </div>
            <button type="button" :class="primaryButton" class="ml-auto" @click="openCreate">Добавить</button>
        </div>

        <input v-model="search" type="search" placeholder="Заголовок или slug" :class="input" class="max-w-sm">

        <p v-if="error" class="text-sm text-red-700">{{ error }}</p>

        <div :class="card" class="overflow-x-auto p-5">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-ink-200">
                        <th :class="th">Заголовок</th>
                        <th :class="th">Slug</th>
                        <th :class="th">Позиция</th>
                        <th :class="th">Статус</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in pages" :key="row.id" class="border-b border-ink-100 last:border-0">
                        <td :class="td">{{ row.title }}</td>
                        <td :class="td" class="font-mono text-xs text-ink-500">{{ row.slug }}</td>
                        <td :class="td" class="tabular-nums">{{ row.position }}</td>
                        <td :class="td">
                            <span class="rounded-full px-2 py-1 text-xs font-medium" :class="pageVisibilityBadges[row.visibility]">
                                {{ pageVisibilities[row.visibility] }}
                            </span>
                        </td>
                        <td :class="td" class="text-right whitespace-nowrap">
                            <button type="button" :class="ghostButton" @click="openEdit(row)">Изменить</button>
                            <button type="button" :class="dangerButton" class="ml-2" @click="remove(row)">Удалить</button>
                        </td>
                    </tr>
                    <tr v-if="!pages.length">
                        <td :class="td" colspan="5" class="text-ink-400">Ничего не найдено.</td>
                    </tr>
                </tbody>
            </table>

            <Pagination v-model:page="page" :meta="meta" />
        </div>

        <Modal v-if="editing" :title="editing === 'new' ? 'Новая страница' : 'Страница'" @close="editing = null">
            <form class="space-y-4" @submit.prevent="save">
                <div>
                    <label for="title" class="mb-1.5 block text-sm text-ink-500">Заголовок</label>
                    <input id="title" v-model="form.title" type="text" required :class="input">
                    <p v-if="formErrors.title" class="mt-1 text-sm text-red-700">{{ formErrors.title }}</p>
                </div>

                <div>
                    <label for="slug" class="mb-1.5 block text-sm text-ink-500">Slug (необязательно)</label>
                    <input id="slug" v-model="form.slug" type="text" :class="input" placeholder="соберётся из заголовка">
                    <p v-if="formErrors.slug" class="mt-1 text-sm text-red-700">{{ formErrors.slug }}</p>
                </div>

                <div>
                    <span class="mb-1.5 block text-sm text-ink-500">Текст</span>
                    <RichEditor v-model="form.body" />
                    <p v-if="formErrors.body" class="mt-1 text-sm text-red-700">{{ formErrors.body }}</p>
                </div>

                <div class="flex flex-wrap items-end gap-4">
                    <div>
                        <label for="position" class="mb-1.5 block text-sm text-ink-500">Позиция</label>
                        <input id="position" v-model="form.position" type="number" min="0" :class="input" class="w-28">
                    </div>

                    <div class="min-w-56 flex-1">
                        <label for="visibility" class="mb-1.5 block text-sm text-ink-500">Где показывать</label>
                        <select id="visibility" v-model="form.visibility" :class="input">
                            <option v-for="(label, value) in pageVisibilities" :key="value" :value="value">{{ label }}</option>
                        </select>
                        <p v-if="formErrors.visibility" class="mt-1 text-sm text-red-700">{{ formErrors.visibility }}</p>
                    </div>
                </div>

                <p v-if="form.visibility === 'unlisted'" class="text-sm text-ink-500">
                    <template v-if="form.slug">
                        Ссылки на страницу нигде не будет, открыть её можно только по адресу
                        <a :href="pageUrl" target="_blank" class="text-gold-700 underline">{{ pageUrl }}</a>.
                    </template>
                    <template v-else>
                        Ссылки на страницу нигде не будет. Адрес соберётся из заголовка и появится здесь
                        после сохранения.
                    </template>
                </p>

                <p v-if="formMessage" class="text-sm text-red-700">{{ formMessage }}</p>

                <div class="flex justify-end gap-2">
                    <button type="button" :class="ghostButton" @click="editing = null">Отмена</button>
                    <button type="submit" :class="primaryButton" :disabled="saving">Сохранить</button>
                </div>
            </form>
        </Modal>
    </div>
</template>
