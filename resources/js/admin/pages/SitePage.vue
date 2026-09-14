<script setup>
import { onMounted, reactive, ref } from 'vue'
import api, { fieldErrorsFrom, messageFrom } from '../api'
import { card, dangerButton, ghostButton, input, primaryButton } from '../ui'
import RichEditor from '../components/RichEditor.vue'

const form = reactive({
    banner_enabled: false,
    banner_title: '',
    banner_subtitle: '',
    banner_button_label: '',
    banner_button_url: '',
    promo_heading: '',
    promo_body: '',
    disclaimer: '',
    contacts_body: '',
    info_body: '',
})

const bannerImageUrl = ref(null)
const logoImageUrl = ref(null)
const errors = ref({})
const message = ref('')
const saved = ref(false)
const saving = ref(false)
const loaded = ref(false)
const fileInput = ref(null)
const logoInput = ref(null)

function apply(data) {
    Object.keys(form).forEach((field) => {
        form[field] = data[field] ?? (typeof form[field] === 'boolean' ? false : '')
    })
    bannerImageUrl.value = data.banner_image_url
    logoImageUrl.value = data.logo_image_url
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

async function upload(event, endpoint, failure) {
    const file = event.target.files[0]

    if (!file) {
        return
    }

    message.value = ''

    const payload = new FormData()
    payload.append('image', file)

    try {
        const { data } = await api.post(endpoint, payload)
        apply(data.data)
    } catch (e) {
        message.value = Object.values(fieldErrorsFrom(e))[0] ?? messageFrom(e, failure)
    } finally {
        event.target.value = ''
    }
}

async function remove(endpoint) {
    const { data } = await api.delete(endpoint)
    apply(data.data)
}

const uploadBanner = (event) => upload(event, '/site/banner', 'Не удалось загрузить баннер')
const removeBanner = () => remove('/site/banner')
const uploadLogo = (event) => upload(event, '/site/logo', 'Не удалось загрузить логотип')
const removeLogo = () => remove('/site/logo')
</script>

<template>
    <div v-if="loaded" class="max-w-3xl space-y-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight">Сайт</h1>
            <p class="text-sm text-ink-400">Логотип, баннер над каталогом, блок над футером и колонки футера.</p>
        </div>

        <section :class="card" class="space-y-4 p-6">
            <div class="flex flex-wrap items-center gap-3">
                <h2 class="font-semibold">Логотип</h2>
                <button type="button" :class="ghostButton" class="ml-auto" @click="logoInput.click()">
                    {{ logoImageUrl ? 'Заменить' : 'Загрузить' }}
                </button>
                <button v-if="logoImageUrl" type="button" :class="dangerButton" @click="removeLogo">Удалить</button>
                <input
                    ref="logoInput"
                    type="file"
                    accept="image/jpeg,image/png,image/webp,image/avif"
                    hidden
                    @change="uploadLogo"
                >
            </div>

            <p class="text-xs text-ink-400">
                Показывается в шапке и в футере вместо названия. Высота в шапке — 36 пикселей, так что
                удобнее всего картинка высотой 72–144 пикселя с прозрачным фоном, до 5 МБ.
            </p>

            <div v-if="logoImageUrl" class="inline-flex items-center rounded-lg bg-ink-950 px-5 py-4">
                <img :src="logoImageUrl" alt="" class="h-9 w-auto">
            </div>
            <p v-else class="text-sm text-ink-400">Логотип не загружен — в шапке и футере выводится название.</p>
        </section>

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
                <span class="mb-1.5 block text-sm text-ink-500">Текст</span>
                <RichEditor v-model="form.promo_body" />
            </div>

            <h2 class="border-t border-ink-100 pt-4 font-semibold">Футер</h2>

            <div>
                <span class="mb-1.5 block text-sm text-ink-500">Колонка «Информация»</span>
                <RichEditor v-model="form.info_body" />
                <p class="mt-1 text-xs text-ink-400">
                    Ссылки набираются вручную — колонка не следит за списком страниц.
                    Адрес страницы витрины выглядит как <code>/pages/slug</code>.
                </p>
                <p v-if="errors.info_body" class="mt-1 text-sm text-red-700">{{ errors.info_body }}</p>
            </div>

            <div>
                <span class="mb-1.5 block text-sm text-ink-500">Колонка «Контакты»</span>
                <RichEditor v-model="form.contacts_body" />
                <p class="mt-1 text-xs text-ink-400">
                    Футер тёмный, поэтому цвета в обеих колонках свои: жирный текст выводится белым,
                    ссылки подсвечиваются золотым.
                </p>
                <p v-if="errors.contacts_body" class="mt-1 text-sm text-red-700">{{ errors.contacts_body }}</p>
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
