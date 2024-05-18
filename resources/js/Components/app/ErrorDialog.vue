<template>
    <Modal :show="show" max-width="md">
        <div class="p-6">
            <h2 class="text-2xl mb-2 text-red-600 font-semibold">Error</h2>
            <p>{{ message }}</p>
            <div class="mt-6 flex justify-end">
                <PrimaryButton @click="close">Ok</PrimaryButton>
            </div>
        </div>
    </Modal>
</template>
<script setup>
import Modal from "@/Components/Modal.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import { onBeforeUnmount, onMounted, ref } from "vue";
import { emitter, SHOW_ERROR_DIALOG } from "@/Services/event-bus.js";

const show = ref(false);
const message = ref("");

const emit = defineEmits(["close"]);

const close = () => {
    show.value = false;
    message.value = "";
    emit("close");
};

onBeforeUnmount(() => {
    emitter.off(SHOW_ERROR_DIALOG);
});

onMounted(() => {
    emitter.on(SHOW_ERROR_DIALOG, ({message: msg}) => {
        message.value = msg;
        show.value = true;
    });
});
</script>
