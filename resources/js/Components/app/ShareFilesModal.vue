<template>
    <Modal :show="modelValue" max-width="sm" @show="onShow">
        <div class="p-6">
            <h2 class="text-lg font-medium text-gray-900">Share Files</h2>
            <div class="mt-6">
                <InputLabel for="shareEmail" class="sr-only">Enter E-mail Address</InputLabel>
                <TextInput
                    ref="emailInput"
                    type="text"
                    id="shareEmail"
                    v-model="form.email"
                    class="mt-1 block w-full"
                    :error="form.errors.email"
                    placeholder="Enter E-mail Address"
                    @keyup.enter="share"
                />
                <InputError :message="form.errors.email" class="mt-2" />
            </div>
            <div class="mt-6 flex justify-end">
                <SecondaryButton @click="closeModal">Cancel</SecondaryButton>
                <PrimaryButton @click="share" class="ml-3" :class="{'opacity-25': form.processing}" :disabled="form.processing">Submit</PrimaryButton>
            </div>
        </div>
    </Modal>
</template>
<script setup>
import { useForm, usePage } from "@inertiajs/vue3";
import InputLabel from "../InputLabel.vue";
import InputError from "../InputError.vue";
import Modal from "../Modal.vue";
import TextInput from "../TextInput.vue";
import SecondaryButton from "../SecondaryButton.vue";
import PrimaryButton from "../PrimaryButton.vue";
import { ref } from "vue";
import { nextTick } from "vue";
import { showSuccessNotification } from "@/Services/event-bus.js";

const $emit = defineEmits(["update:modelValue"]);

const props = defineProps({
    modelValue: Boolean,
    allSelected: Boolean,
    selectedIds: Array
});

const page = usePage();

const form = useForm({
    email: "",
    parent_id: null,
    all: false,
    ids: [],
});

const emailInput = ref(null);

const share = () => {
    form.parent_id = page.props.folder.id;

    if(props.allSelected) {
        form.all = true;
    } else {
        form.ids = props.selectedIds;
    }

    form.post(route("file.share"), {
        preserveScroll: true,
        onSuccess: () => {
            showSuccessNotification(`Selected files will be shared to "${form.email}" if email exists in the system`)
            closeModal();
        },
        onError: () => {
            emailInput.value.focus();
        },
        onFinish: () => {
            form.reset();
        }
    });
};

const closeModal = () => {
    $emit("update:modelValue", false);
    form.clearErrors();
    form.reset();
};

const onShow = async () => {
    await nextTick();
    emailInput.value.focus();
};
</script>
