<template>
    <button
        :disabled="loading"
        @click="onDeleteClick"
        class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-900 bg-white border border-gray-200 rounded-lg hover:bg-gray-100 hover:text-blue-700 focus:z-10 focus:ring-2 focus:ring-blue-700 focus:text-blue-700 dark:bg-gray-700 dark:border-gray-600 dark:text-white dark:hover:text-white dark:hover:bg-gray-600 dark:focus:ring-blue-500 dark:focus:text-white"
    >
        <svg
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            viewBox="0 0 24 24"
            stroke-width="1.5"
            stroke="currentColor"
            class="w-4 h-4 mr-2"
        >
            <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"
            />
        </svg>

        Delete

        <ConfirmationDialog
            :show="showDeleteDialog"
            message="Are you sure you want to delete the selected files?"
            @cancel="onDeleteCancel"
            @confirm="onDeleteConfirm"
        />
    </button>
</template>
<script setup>
import ConfirmationDialog from '@/Components/ConfirmationDialog.vue';
import { showErrorDialog, showSuccessNotification } from '@/Services/event-bus.js';
import { useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    deleteAll: {
        type: Boolean,
        default: false,
        required: false,
    },
    deleteIds: {
        type: Array,
        required: false,
        default: () => []
    }
})

const emit = defineEmits(['delete'])


const page = usePage();

const deleteFilesForm = useForm({
    all: null,
    ids: [],
    parent_id: null,
})

const showDeleteDialog = ref(false);
const loading = ref(false);

const onDeleteClick = () => {
    if(!props.deleteAll && !props.deleteIds.length) {
        return showErrorDialog('Please select at least one file to delete');
    }

    showDeleteDialog.value = true;
};

const onDeleteCancel = () => {
    showDeleteDialog.value = false;
};

const onDeleteConfirm = () => {
    deleteFilesForm.parent_id = page.props.folder.id;

    if(props.deleteAll) {
        deleteFilesForm.all = true;
    } else {
        deleteFilesForm.ids = props.deleteIds;
    }

    loading.value = true;
    deleteFilesForm.delete(route('file.delete'), {
        preserveScroll: true,
        onSuccess: () => {
            showSuccessNotification('Selected files have been deleted');
            emit('delete');
        },
        onError: (errors) => {
            let message = '';
            if(Object.keys(errors).length > 0) {
                message = errors[Object.keys(errors)[0]];
            } else {
                message = 'An error occurred while deleting files';
            }

            showErrorDialog(message);
        },
        onFinish: () => {
            deleteFilesForm.clearErrors();
            deleteFilesForm.reset();
            showDeleteDialog.value = false;

            loading.value = false;
        }
    })


};
</script>
