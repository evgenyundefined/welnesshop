<script setup>
import { onBeforeUnmount, ref, watch } from 'vue'
import { EditorContent, useEditor } from '@tiptap/vue-3'
import StarterKit from '@tiptap/starter-kit'
import Image from '@tiptap/extension-image'
import api, { fieldErrorsFrom, messageFrom } from '../api'
import { ghostButton } from '../ui'

const props = defineProps({ modelValue: { type: String, default: '' } })
const emit = defineEmits(['update:modelValue'])

const showSource = ref(false)
const source = ref(props.modelValue)
const message = ref('')
const uploading = ref(false)
const fileInput = ref(null)

const editor = useEditor({
    content: props.modelValue,
    extensions: [StarterKit, Image],
    editorProps: { attributes: { class: 'prose-page min-h-64 px-4 py-3 outline-none' } },
    onUpdate: ({ editor }) => emit('update:modelValue', editor.getHTML()),
})

watch(
    () => props.modelValue,
    (value) => {
        if (showSource.value) {
            source.value = value ?? ''
        }

        if (editor.value && editor.value.getHTML() !== (value ?? '')) {
            editor.value.commands.setContent(value ?? '')
        }
    },
)

onBeforeUnmount(() => editor.value?.destroy())

function toggleSource() {
    if (showSource.value) {
        emit('update:modelValue', source.value)
        editor.value?.commands.setContent(source.value)
    } else {
        source.value = editor.value?.getHTML() ?? ''
    }

    showSource.value = !showSource.value
}

function applySource() {
    emit('update:modelValue', source.value)
}

async function uploadImage(event) {
    const file = event.target.files[0]

    if (!file) {
        return
    }

    message.value = ''
    uploading.value = true

    const payload = new FormData()
    payload.append('image', file)

    try {
        const { data } = await api.post('/content/images', payload)
        editor.value?.chain().focus().setImage({ src: data.data.url }).run()
    } catch (e) {
        message.value = Object.values(fieldErrorsFrom(e))[0] ?? messageFrom(e, 'Не удалось загрузить картинку')
    } finally {
        uploading.value = false
        event.target.value = ''
    }
}

const tool = 'rounded px-2 py-1 text-xs transition hover:bg-ink-100'
const active = 'bg-ink-900 text-white hover:bg-ink-900'

const buttons = [
    { label: 'Ж', title: 'Жирный', mark: 'bold', run: (c) => c.toggleBold() },
    { label: 'К', title: 'Курсив', mark: 'italic', run: (c) => c.toggleItalic() },
    { label: 'H2', title: 'Заголовок', mark: 'heading', attrs: { level: 2 }, run: (c) => c.toggleHeading({ level: 2 }) },
    { label: 'H3', title: 'Подзаголовок', mark: 'heading', attrs: { level: 3 }, run: (c) => c.toggleHeading({ level: 3 }) },
    { label: '•', title: 'Список', mark: 'bulletList', run: (c) => c.toggleBulletList() },
    { label: '1.', title: 'Нумерованный список', mark: 'orderedList', run: (c) => c.toggleOrderedList() },
    { label: '❝', title: 'Текст в рамке', mark: 'blockquote', run: (c) => c.toggleBlockquote() },
    { label: '—', title: 'Разделитель', run: (c) => c.setHorizontalRule() },
]

function apply(button) {
    button.run(editor.value.chain().focus()).run()
}

function isActive(button) {
    return button.mark ? editor.value?.isActive(button.mark, button.attrs) : false
}

function setLink() {
    const previous = editor.value?.getAttributes('link').href ?? ''
    const href = window.prompt('Ссылка', previous)

    if (href === null) {
        return
    }

    const chain = editor.value.chain().focus().extendMarkRange('link')

    href === '' ? chain.unsetLink().run() : chain.setLink({ href }).run()
}
</script>

<template>
    <div class="rounded-lg border border-ink-300 bg-white">
        <div class="flex flex-wrap items-center gap-1 border-b border-ink-200 px-2 py-1.5">
            <button
                v-for="button in buttons"
                :key="button.label"
                type="button"
                :title="button.title"
                :class="[tool, isActive(button) ? active : '']"
                @click="apply(button)"
            >
                {{ button.label }}
            </button>

            <button type="button" title="Ссылка" :class="[tool, editor?.isActive('link') ? active : '']" @click="setLink">
                Ссылка
            </button>

            <button type="button" title="Картинка" :class="tool" :disabled="uploading" @click="fileInput.click()">
                {{ uploading ? 'Загрузка…' : 'Картинка' }}
            </button>

            <button
                type="button"
                :class="[ghostButton, 'ml-auto px-2 py-1 text-xs']"
                @click="toggleSource"
            >
                {{ showSource ? 'Визуально' : 'HTML' }}
            </button>

            <input
                ref="fileInput"
                type="file"
                accept="image/jpeg,image/png,image/webp,image/avif,image/gif"
                hidden
                @change="uploadImage"
            >
        </div>

        <p v-if="message" class="border-b border-ink-200 px-4 py-2 text-sm text-red-700">{{ message }}</p>

        <textarea
            v-if="showSource"
            v-model="source"
            rows="16"
            class="w-full resize-y px-4 py-3 font-mono text-xs outline-none"
            @input="applySource"
        ></textarea>

        <EditorContent v-else :editor="editor" />
    </div>
</template>
