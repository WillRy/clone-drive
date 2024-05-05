<template>
    <AuthenticatedLayout>
        <table class="min-w-full">
            <thead class="bg-gray-100 border-b">
                <tr>
                    <th class="text-sm font-medium text-gray-900 px-6 py-4 text-left">
                        Name
                    </th>
                    <th class="text-sm font-medium text-gray-900 px-6 py-4 text-left">
                        Owner
                    </th>
                    <th class="text-sm font-medium text-gray-900 px-6 py-4 text-left">
                        Last modified
                    </th>
                    <th class="text-sm font-medium text-gray-900 px-6 py-4 text-left">
                        Size
                    </th>
                </tr>

            </thead>
            <tbody>
                <tr
                    v-for="file in files.data"
                    :key="file.id"
                    class="bg-white border-b transition duration-300 ease-in-out hover:bg-gray-100 cursor-pointer"
                    @dblclick="openFolder(file)"
                >
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 align-middle">
                        {{ file.name }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 align-middle">
                        {{ file.owner }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 align-middle">
                        {{ file.updated_at }}
                    </td>
                     <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 align-middle">
                        {{ file.size }}
                    </td>
                </tr>
            </tbody>
        </table>
        <div v-if="!files.data.length" class="py-8 text-center text-sm text-gray-400">
            There is no data in this folder
        </div>
    </AuthenticatedLayout>
</template>
<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    files: {
        type: Object,
        default: () => ({ data: [] }),
    },
});

const openFolder = (file) => {
    if(!file.is_folder) {
        return false;
    }

    router.visit(route('myFiles', {folder: file.path}))
}
</script>
