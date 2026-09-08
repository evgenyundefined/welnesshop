<script setup>
import { onMounted, ref } from 'vue'
import api, { fieldErrorsFrom, messageFrom } from '../api'
import { card, dangerButton, ghostButton } from '../ui'

const props = defineProps({ productId: { type: [String, Number], required: true } })

const images = ref([])
const message = ref('')
const busy = ref(false)
const fileInput = ref(null)

const endpoint = `/products/${props.productId}/images`

onMounted(async () => {
    const { data } = await api.get(endpoint)
    images.value = data.data
})

async function run(request) {
    message.value = ''
    busy.value = true

    try {
        const { data } = await request()
        images.value = data.data
    } catch (e) {
        const fields = fieldErrorsFrom(e)
        message.value = Object.values(fields)[0] ?? messageFrom(e, 'Не удалось изменить галерею')
    } finally {
        busy.value = false
    }
}

async function upload(event) {
    const files = [...event.target.files]

    if (!files.length) {
        return
    }

    const payload = new FormData()
    files.forEach(file => payload.append('images[]', file))

    await run(() => api.post(endpoint, payload))

    // Clearing the input lets the same file be picked again after a failure.
    event.target.value = ''
}

const makeCover = image => run(() => api.put(`${endpoint}/${image.id}/primary`))
const remove = image => run(() => api.delete(`${endpoint}/${image.id}`))
</script>

<template>
    <section :class="card" class="space-y-4 p-6">
        <div class="flex flex-wrap items-center gap-3">
            <div>
                <h2 class="font-semibold">Галерея</h2>
                <p class="text-sm text-ink-400">Обложка — фото, которое видно в каталоге.</p>
            </div>

            <button
                type="button"
                :class="ghostButton"
                class="ml-auto"
                :disabled="busy"
                @click="fileInput.click()"
            >
                Добавить фото
            </button>

            <input
                ref="fileInput"
                type="file"
                accept="image/jpeg,image/png,image/webp,image/avif"
                multiple
                hidden
                @change="upload"
            >
        </div>

        <p v-if="message" class="text-sm text-red-700">{{ message }}</p>

        <p v-if="!images.length" class="text-sm text-ink-400">Фотографий пока нет.</p>

        <ul v-else class="grid gap-4 sm:grid-cols-3 lg:grid-cols-4">
            <li v-for="image in images" :key="image.id" class="space-y-2">
                <div
                    class="relative aspect-square overflow-hidden rounded-lg border"
                    :class="image.is_primary ? 'border-gold-400 ring-2 ring-gold-300' : 'border-ink-200'"
                >
                    <img :src="image.url" alt="" class="size-full object-cover">
                    <span
                        v-if="image.is_primary"
                        class="absolute left-2 top-2 rounded-full bg-gold-400 px-2 py-0.5 text-xs font-semibold text-ink-950"
                    >
                        Обложка
                    </span>
                </div>

                <div class="flex gap-2">
                    <button
                        v-if="!image.is_primary"
                        type="button"
                        :class="ghostButton"
                        class="grow text-xs"
                        :disabled="busy"
                        @click="makeCover(image)"
                    >
                        Сделать обложкой
                    </button>
                    <button type="button" :class="dangerButton" class="text-xs" :disabled="busy" @click="remove(image)">
                        Удалить
                    </button>
                </div>
            </li>
        </ul>
    </section>
</template>
