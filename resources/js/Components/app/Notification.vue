<template>
    <transition
        enter-active-class="ease-out duration-300"
        enter-from-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
        enter-to-class="opacity-100 translate-y-0 sm:scale-100"
        leave-active-class="ease-in duration-200"
        leave-from-class="opacity-100 translate-y-0 sm:scale-100"
        leave-to-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
    >
        <div v-if="show" class="fixed bottom-4 left-4 text-white py-2 px-4 rounded-lg shadow-md w-[200px]"
             :class="{
                'bg-emerald-500': type === 'success',
                'bg-red-500': type === 'error'
            }">
            {{ message }}
        </div>
    </transition>
</template>
<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import {emitter, SHOW_NOTIFICATION} from "@/Services/event-bus.js"
const show = ref(false)
const message = ref('')
const type = ref('success')

const close = () => {
    show.value = false;
    type.value = 'success';
    message.value = '';
}

onBeforeUnmount(() => {
    emitter.off(SHOW_NOTIFICATION)
})

onMounted(() => {
    let timeout = null;
    emitter.on(SHOW_NOTIFICATION, ({type: t, message: msg}) => {
        type.value = t
        message.value = msg
        show.value = true

        if(timeout) {
            clearTimeout(timeout)
        }

        timeout = setTimeout(() => {
            close();
        }, 5000)
    })
})
</script>
