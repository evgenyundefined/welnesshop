<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import tinymce from 'tinymce/tinymce'
import 'tinymce/models/dom'
import 'tinymce/themes/silver'
import 'tinymce/icons/default'
import 'tinymce/plugins/lists'
import 'tinymce/plugins/link'
import 'tinymce/plugins/image'
import 'tinymce/plugins/media'
import 'tinymce/plugins/table'
import 'tinymce/plugins/code'
import 'tinymce/plugins/searchreplace'
import 'tinymce/skins/ui/oxide/skin.min.css'
import contentUiCss from 'tinymce/skins/ui/oxide/content.min.css?inline'
import contentCss from '../../../css/editor-content.css?inline'
import russian from '../tinymceRussian'
import api, { fieldErrorsFrom, messageFrom } from '../api'

tinymce.addI18n('ru', russian)

const props = defineProps({ modelValue: { type: String, default: '' } })
const emit = defineEmits(['update:modelValue'])

const element = ref(null)
let editor = null
let applyingExternal = true

function uploadBlob(blob, filename) {
    const payload = new FormData()
    payload.append('image', blob, filename)

    return api.post('/content/images', payload).then(({ data }) => data.data.url)
}

onMounted(async () => {
    const initial = props.modelValue ?? ''

    const [instance] = await tinymce.init({
        target: element.value,
        skin: false,
        content_css: false,
        content_style: [contentUiCss, contentCss].join('\n'),
        language: 'ru',
        language_load: false,
        height: 460,
        menubar: false,
        toolbar_mode: 'wrap',
        branding: false,
        promotion: false,
        convert_urls: false,
        plugins: 'lists link image media table code searchreplace',
        toolbar: 'undo redo | blocks | bold italic underline strikethrough | bullist numlist | '
            + 'blockquote hr | link image media table | removeformat | code',
        // Whatever is pasted or typed into the source dialog is kept verbatim:
        // that is the point of the editor for this project.
        valid_elements: '*[*]',
        extended_valid_elements: '*[*]',
        valid_children: '+body[style]',
        verify_html: false,
        automatic_uploads: true,
        file_picker_types: 'image',
        images_upload_handler: (blobInfo) => uploadBlob(blobInfo.blob(), blobInfo.filename()),
        file_picker_callback: (callback) => {
            const input = document.createElement('input')
            input.type = 'file'
            input.accept = 'image/jpeg,image/png,image/webp,image/avif,image/gif'
            input.addEventListener('change', async () => {
                const file = input.files[0]

                if (!file) {
                    return
                }

                try {
                    callback(await uploadBlob(file, file.name), { alt: '' })
                } catch (e) {
                    editor?.notificationManager.open({
                        type: 'error',
                        text: Object.values(fieldErrorsFrom(e))[0] ?? messageFrom(e, 'Не удалось загрузить картинку'),
                    })
                }
            })
            input.click()
        },
        setup: (created) => {
            created.on('Change KeyUp Undo Redo SetContent', () => {
                if (! applyingExternal) {
                    emit('update:modelValue', created.getContent())
                }
            })
        },
    })

    editor = instance
    editor.setContent(initial)
    applyingExternal = false
})

watch(
    () => props.modelValue,
    (value) => {
        if (! editor || editor.getContent() === (value ?? '')) {
            return
        }

        applyingExternal = true
        editor.setContent(value ?? '')
        applyingExternal = false
    },
)

onBeforeUnmount(() => {
    editor?.remove()
    editor = null
})
</script>

<template>
    <div class="tinymce-host">
        <textarea ref="element"></textarea>
    </div>
</template>
