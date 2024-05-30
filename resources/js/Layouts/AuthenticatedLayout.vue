<template>
    <Head title="Dashboard" />

    <div class="h-screen bg-gray-50 flex w-full gap-4">
        <Navigation />
        <main
            @drop.prevent="handleDrop"
            @dragover.prevent="onDragOver"
            @dragleave.prevent="onDragLeave"
            class="flex flex-col flex-1 px-4 overflow-hidden"
            :class="dragOver ? 'dropzone' : ''"
        >
            <template
                v-if="dragOver"
                class="text-gray-500 text-center py-8 text-sm"
            >
                Drop files here to upload
            </template>
            <template v-else>
                <div class="flex items-center justify-between w-full">
                    <SearchForm />
                    <UserSettingsDropdown />
                </div>
                <div class="flex flex-1 flex-col overflow-hidden">
                    <slot />
                </div>
            </template>
        </main>
        <FormProgress :form="fileUploadForm"/>
        <ErrorDialog/>
        <Notification/>
    </div>
</template>
<script setup>
import { Head, useForm, usePage } from "@inertiajs/vue3";
import Navigation from "@/Components/app/Navigation.vue";
import SearchForm from "@/Components/app/SearchForm.vue";
import FormProgress from "@/Components/app/FormProgress.vue";
import Notification from "@/Components/app/Notification.vue";
import UserSettingsDropdown from "@/Components/app/UserSettingsDropdown.vue";
import { emitter, FILE_UPLOAD_STARTED, showErrorDialog, showSuccessNotification } from "@/Services/event-bus.js";
import { onMounted } from "vue";
import { onBeforeUnmount } from "vue";
import { ref } from "vue";
import ErrorDialog from "@/Components/app/ErrorDialog.vue";

const page = usePage();

const fileUploadForm = useForm({
    files: [],
    relative_paths: [],
    parent_id: null
});

const dragOver = ref(false);

function onDragOver() {
    dragOver.value = true;
}

function onDragLeave() {
    dragOver.value = false;
}

function handleDrop(event) {
    dragOver.value = false;
    const files = event.dataTransfer.files;

    if (!files.length) {
        return;
    }

    uploadFiles(files);
}

function uploadFiles(files) {
    fileUploadForm.parent_id = page.props.folder.id;
    fileUploadForm.files = files;
    fileUploadForm.relative_paths = Array.from(files).map((file) => file.webkitRelativePath);


    fileUploadForm.post(route("file.store"), {
        onSuccess: () => {
            showSuccessNotification(`${files.length} files have been uploaded`);
        },
        onError: (errors) => {
            let message = '';
            if(Object.keys(errors).length > 0) {
                message = errors[Object.keys(errors)[0]];
            } else {
                message = 'An error occurred while uploading files';
            }

            showErrorDialog(message);



        },
        onFinish: () => {
            fileUploadForm.clearErrors();
            fileUploadForm.reset();
        }
    });

}

onBeforeUnmount(() => {
    emitter.off(FILE_UPLOAD_STARTED);
});

onMounted(() => {
    emitter.on(FILE_UPLOAD_STARTED, uploadFiles);
});
</script>

<style scoped>
.dropzone {
    width: 100%;
    height: 100%;
    color: #8d8d8d;
    border: 2px dashed gray;
    display: flex;
    justify-content: center;
    align-items: center;
}
</style>
