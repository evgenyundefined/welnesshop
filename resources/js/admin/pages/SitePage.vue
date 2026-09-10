<script setup>
import { onMounted, reactive, ref } from 'vue'
import api, { fieldErrorsFrom, messageFrom } from '../api'
import { card, dangerButton, ghostButton, input, primaryButton } from '../ui'

const form = reactive({
    banner_enabled: false,
    banner_title: '',
    banner_subtitle: '',
    banner_button_label: '',
    banner_button_url: '',
    promo_heading: '',
    promo_body: '',
    disclaimer: '',
    contact_email: '',
    contact_phone: '',
})

const bannerImageUrl = ref(null)
const errors = ref({})
const message = ref('')
const saved = ref(false)
const saving = ref(false)
const loaded = ref(false)
const fileInput = ref(null)

function apply(data) {
    Object.keys(form).forEach((field) => {
        form[field] = data[field] ?? (typeof form[field] === 'boolean' ? false : '')
    })
    bannerImageUrl.value = data.banner_image_url
}

onMounted(async () => {
    const { data } = await api.get('/site')
    apply(data.data)
    loaded.value = true
})

async function submit() {
    errors.value = {}
    message.value = ''
    saved.value = false
    saving.value = true

    try {
        const { data } = await api.put('/site', form)
        apply(data.data)
        saved.value = true
    } catch (e) {
        errors.value = fieldErrorsFrom(e)
        message.value = messageFrom(e, 'Не удалось сохранить настройки')
    } finally {
        saving.value = false
    }
}

async function uploadBanner(event) {
    const file = event.target.files[0]

    if (!file) {
        return
    }

    message.value = ''

    const payload = new FormData()
    payload.append('image', file)

    try {
        const { data } = await api.post('/site/banner', payload)
        apply(data.data)
    } catch (e) {
        message.value = Object.values(fieldErrorsFrom(e))[0] ?? messageFrom(e, 'Не удалось загрузить баннер')
    } finally {
        event.target.value = ''
    }
}

async function removeBanner() {
    const { data } = await api.delete('/site/banner')
    apply(data.data)
}
</script>

<template>
    <div v-if="loaded" class="max-w-3xl space-y-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight">Сайт</h1>
            <p class="text-sm text-ink-400">Баннер над каталогом, блок над футером и контакты в футере.</p>
        </div>

        <section :class="card" class="space-y-4 p-6">
            <div class="flex flex-wrap items-center gap-3">
                <h2 class="font-semibold">Баннер над каталогом</h2>
                <button type="button" :class="ghostButton" class="ml-auto" @click="fileInput.click()">
                    {{ bannerImageUrl ? 'Заменить' : 'Загрузить' }}
                </button>
                <button v-if="bannerImageUrl" type="button" :class="dangerButton" @click="removeBanner">Удалить</button>
                <input
                    ref="fileInput"
                    type="file"
                    accept="image/jpeg,image/png,image/webp,image/avif"
                    hidden
                    @change="uploadBanner"
                >
            </div>

            <p class="text-xs text-ink-400">Рекомендуемый размер — 2400 × 800, до 5 МБ.</p>

            <div v-if="bannerImageUrl" class="overflow-hidden rounded-lg bg-ink-100">
                <img :src="bannerImageUrl" alt="" class="w-full">
            </div>
            <p v-else class="text-sm text-ink-400">Картинка не загружена — баннер на витрине не показывается.</p>

            <label class="flex items-center gap-2 text-sm">
                <input v-model="form.banner_enabled" type="checkbox" class="size-4 accent-gold-500" :disabled="!bannerImageUrl">
                Показывать баннер
            </label>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="banner_title" class="mb-1.5 block text-sm text-ink-500">Заголовок</label>
                    <input id="banner_title" v-model="form.banner_title" type="text" :class="input">
                </div>
                <div>
                    <label for="banner_button_url" class="mb-1.5 block text-sm text-ink-500">Ссылка кнопки</label>
                    <input id="banner_button_url" v-model="form.banner_button_url" type="text" :class="input" placeholder="/">
                </div>
            </div>
        </section>

        <form :class="card" class="space-y-4 p-6" novalidate @submit.prevent="submit">
            <h2 class="font-semibold">Блок над футером</h2>

            <div>
                <label for="promo_heading" class="mb-1.5 block text-sm text-ink-500">Заголовок</label>
                <input id="promo_heading" v-model="form.promo_heading" type="text" :class="input">
            </div>

            <div>
                <label for="promo_body" class="mb-1.5 block text-sm text-ink-500">Текст</label>
                <textarea id="promo_body" v-model="form.promo_body" rows="10" :class="input" class="font-mono text-xs"></textarea>
                <p class="mt-1 text-xs text-ink-400">Markdown, как на страницах.</p>
            </div>

            <h2 class="border-t border-ink-100 pt-4 font-semibold">Футер</h2>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="contact_phone" class="mb-1.5 block text-sm text-ink-500">Телефон</label>
                    <input id="contact_phone" v-model="form.contact_phone" type="text" :class="input">
                </div>
                <div>
                    <label for="contact_email" class="mb-1.5 block text-sm text-ink-500">E-mail</label>
                    <input id="contact_email" v-model="form.contact_email" type="email" :class="input">
                    <p v-if="errors.contact_email" class="mt-1 text-sm text-red-700">{{ errors.contact_email }}</p>
                </div>
            </div>

            <div>
                <label for="disclaimer" class="mb-1.5 block text-sm text-ink-500">Дисклеймер</label>
                <textarea id="disclaimer" v-model="form.disclaimer" rows="5" :class="input"></textarea>
                <p class="mt-1 text-xs text-ink-400">Юридический текст о назначении продукции — составьте под свой бизнес.</p>
            </div>

            <p v-if="message" class="text-sm text-red-700">{{ message }}</p>
            <p v-if="saved" class="text-sm text-gold-700">Сохранено.</p>

            <div class="flex justify-end">
                <button type="submit" :class="primaryButton" :disabled="saving">Сохранить</button>
            </div>
        </form>
    </div>
</template>
