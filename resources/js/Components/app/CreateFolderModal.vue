<template>
    <Modal :show="modelValue" max-width="sm" @show="onShow">
        <div class="p-6">
            <h2 class="text-lg font-medium text-gray-900">Create New Folder</h2>
            <div class="mt-6">
                <InputLabel for="folderName" class="sr-only">Name</InputLabel>
                <TextInput
                    ref="folderNameInput"
                    type="text"
                    id="folderName"
                    v-model="form.name"
                    class="mt-1 block w-full"
                    :error="form.errors.name"
                    placeholder="Folder name"
                    @keyup.enter="createFolder"
                />
                <InputError :message="form.errors.name" class="mt-2" />
            </div>
            <div class="mt-6 flex justify-end">
                <SecondaryButton @click="closeModal">Cancel</SecondaryButton>
                <PrimaryButton @click="createFolder" class="ml-3" :class="{'opacity-25': form.processing}" :disabled="form.processing">Submit</PrimaryButton>
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

const $emit = defineEmits(["update:modelValue"]);

const props = defineProps({
    modelValue: Boolean,
});

const page = usePage();

const form = useForm({
    name: "",
    parent_id: null
});

const folderNameInput = ref(null);

const createFolder = () => {
    form.parent_id = page.props.folder.id;
    form.post(route("folder.create"), {
        preserveScroll: true,
        onSuccess: () => {
            closeModal();
        },
        onError: () => {
            folderNameInput.value.focus();
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
    folderNameInput.value.focus();
};
</script>
