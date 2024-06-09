<template>
    <form class="w-[600px] h-[80px] flex items-center" @submit.prevent="onSearch">
        <TextInput
            type="text"
            class="block w-full mr-2"
            v-model="search"
            autocomplete
            placeholder="Search for files and folders"
        />
    </form>
</template>
<script setup>
import { router, useForm } from "@inertiajs/vue3";
import TextInput from "../TextInput.vue";
import { ref } from "vue";
import { onMounted } from "vue";

const search = ref('');

const onSearch = async () => {
    const params = new URLSearchParams(window.location.search);
    params.set('search', search.value);

    router.visit(route(route().current()), {
        data: params,
    });
};

onMounted(() => {
    search.value = new URLSearchParams(window.location.search).get('search') || '';
})
</script>
