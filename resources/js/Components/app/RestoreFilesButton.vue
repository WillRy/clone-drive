<template>
    <button
        :disabled="loading"
        @click="onClick"
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
                d="M8.25 9V5.25A2.25 2.25 0 0 1 10.5 3h6a2.25 2.25 0 0 1 2.25 2.25v13.5A2.25 2.25 0 0 1 16.5 21h-6a2.25 2.25 0 0 1-2.25-2.25V15m-3 0-3-3m0 0 3-3m-3 3H15"
            />
        </svg>

        Restore

        <ConfirmationDialog
            :show="showConfirmationDialog"
            message="Are you sure you want to restore selected files?"
            @cancel="onCancel"
            @confirm="onConfirm"
        />
    </button>
</template>
<script setup>
import ConfirmationDialog from "@/Components/ConfirmationDialog.vue";
import {
    showErrorDialog,
    showSuccessNotification,
} from "@/Services/event-bus.js";
import { useForm, usePage } from "@inertiajs/vue3";
import { ref } from "vue";

const props = defineProps({
    allSelected: {
        type: Boolean,
        default: false,
        required: false,
    },
    selectedIds: {
        type: Array,
        required: false,
        default: () => [],
    },
});

const emit = defineEmits(["restore"]);


const form = useForm({
    all: null,
    ids: [],
    parent_id: null,
});

const showConfirmationDialog = ref(false);
const loading = ref(false);

const onClick = () => {
    if (!props.allSelected && !props.selectedIds.length) {
        return showErrorDialog("Please select at least one file to restore");
    }

    showConfirmationDialog.value = true;
};

const onCancel = () => {
    showConfirmationDialog.value = false;
};

const onConfirm = () => {

    if (props.allSelected) {
        form.all = true;
    } else {
        form.ids = props.selectedIds;
    }

    loading.value = true;
    form.post(route("file.restore"), {
        preserveScroll: true,
        onSuccess: () => {
            showSuccessNotification("Selected files have been restored");
            emit("restore");
        },
        onError: (errors) => {
            let message = "";
            if (Object.keys(errors).length > 0) {
                message = errors[Object.keys(errors)[0]];
            } else {
                message = "An error occurred while restoring files";
            }

            showErrorDialog(message);
        },
        onFinish: () => {
            form.clearErrors();
            form.reset();
            showConfirmationDialog.value = false;

            loading.value = false;
        },
    });
};
</script>
