<template>
    <MenuItem v-slot="{ close }">
        <button class="text-gray-700 block w-full px-4 py-2 text-sm relative" @click="open">
            Upload Folder

            <input type="file" class="hidden" ref="inputFile" @change="onChange" directory webkitdirectory>
        </button>
    </MenuItem>
</template>
<script setup>
import { MenuItem } from "@headlessui/vue";
import { ref } from "vue";
import {emitter, FILE_UPLOAD_STARTED} from '@/Services/event-bus.js';

const inputFile = ref(null);

const open = () => {
    inputFile.value.click();
}

const onChange = (event) => {
    emitter.emit(FILE_UPLOAD_STARTED, event.target.files);
}
</script>
