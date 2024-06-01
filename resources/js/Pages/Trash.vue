<template>
    <AuthenticatedLayout>
        <nav class="flex items-center justify-end p-1 mb-3">
            <div class="flex">
                <DeleteForeverButton :all-seleceted="allSelected" :selected-ids="selectedIds" class="mr-2" @delete="resetForm"/>
                <RestoreFilesButton :all-seleceted="allSelected" :selected-ids="selectedIds" @restore="resetForm"/>
            </div>
        </nav>
        <div class="flex-1 overflow-auto">
            <table class="min-w-full">
                <thead class="bg-gray-100 border-b">
                    <tr>
                        <th
                            class="text-sm font-medium text-gray-900 px-6 py-4 text-left w-[30px] max-w-[30px] pr-0"
                        >
                            <Checkbox
                                @change="onSelectAllChange"
                                v-model:checked="allSelected"
                            />
                        </th>
                        <th
                            class="text-sm font-medium text-gray-900 px-6 py-4 text-left"
                        >
                            Name
                        </th>
                        <th
                            class="text-sm font-medium text-gray-900 px-6 py-4 text-left"
                        >
                            Path
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="file in allFiles.data"
                        :key="file.id"
                        class="border-b transition duration-300 ease-in-out hover:bg-blue-100 cursor-pointer"
                        :class="
                            (
                                selected[file.id] !== undefined
                                    ? selected[file.id]
                                    : allSelected
                            )
                                ? 'bg-blue-50'
                                : 'bg-white'
                        "
                        @click.ctrl="toggleFileSelect(file)"
                    >
                        <td
                            class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 align-middle w-[30px] max-w-[30px] pr-0"
                        >
                            <div class="flex items-center">
                                <Checkbox
                                    v-model="selected[file.id]"
                                    :checked="
                                        selected[file.id] !== undefined
                                            ? selected[file.id]
                                            : allSelected
                                    "
                                    @change="onSelectCheckboxChange(file)"
                                />
                            </div>
                        </td>
                        <td
                            class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 align-middle"
                        >
                            <div class="flex items-center">
                                <FileIcon :file="file" />
                                {{ file.name }}
                            </div>
                        </td>
                        <td
                            class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 align-middle"
                        >
                            {{ file.path }}
                        </td>
                    </tr>
                </tbody>
            </table>

            <div
                v-if="!allFiles.data.length"
                class="py-8 text-center text-sm text-gray-400"
            >
                There is no data in this folder
            </div>

            <div
                v-if="loading"
                class="py-8 text-center flex justify-center text-xl text-gray-400"
            >
                <LoadingIcon />
            </div>

            <div ref="loadMoreIntersect"></div>
        </div>
    </AuthenticatedLayout>
</template>
<script setup>
import { HomeIcon } from "@heroicons/vue/20/solid";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Link, router } from "@inertiajs/vue3";
import FileIcon from "@/Components/app/FileIcon.vue";
import LoadingIcon from "@/Components/app/LoadingIcon.vue";
import DeleteFilesButton from "@/Components/app/DeleteFilesButton.vue";
import DownloadFilesButton from "@/Components/app/DownloadFilesButton.vue";
import { onMounted } from "vue";
import { ref } from "vue";
import { onUpdated } from "vue";
import { watch } from "vue";
import { httpGet } from "@/Helper/http-helper.js";
import Checkbox from "@/Components/Checkbox.vue";
import { computed } from "vue";
import RestoreFilesButton from "../Components/app/RestoreFilesButton.vue";
import DeleteForeverButton from "../Components/app/DeleteForeverButton.vue";

const props = defineProps({
    files: {
        type: Object,
        default: () => ({ data: [] }),
    },
    folder: {
        type: Object,
    },
    ancestors: {
        type: Object,
    },
});

const allSelected = ref(false);
const selected = ref({});
const loadMoreIntersect = ref(null);
const loading = ref(false);
const allFiles = ref({
    data: props.files.data,
    next: props.files.links.next,
});

const selectedIds = computed(() => {
    return Object.keys(selected.value).filter((id) => selected.value[id]);
});


const loadMore = () => {
    const url = allFiles.value.next;

    if (!url) {
        return;
    }

    loading.value = true;

    httpGet(url)
        .then((response) => {
            allFiles.value.data = [
                ...allFiles.value.data,
                ...response.data.data,
            ];
            allFiles.value.next = response.data.links.next;
        })
        .finally(() => {
            loading.value = false;
        });
};

const onSelectAllChange = () => {
    allFiles.value.data.forEach((file) => {
        selected.value[file.id] = allSelected.value;
    });
};

const toggleFileSelect = (file) => {
    selected.value[file.id] = !selected.value[file.id];
    onSelectCheckboxChange(file);
};

const onSelectCheckboxChange = (file) => {
    if (!selected.value[file.id]) {
        allSelected.value = false;
        return;
    }

    allSelected.value = allFiles.value.data.every(
        (file) => selected.value[file.id]
    );
};

const resetForm = () => {
    selected.value = {};
    allSelected.value = false;
};

watch(
    () => props.files,
    () => {
        Object.assign(allFiles.value, {
            data: props.files.data,
            next: props.files.links.next,
        });
    }
);

//infinity scroll
onMounted(() => {
    const observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) {
                    return;
                }

                loadMore();
            });
        },
        {
            rootMargin: "-250px 0px 0px 0px",
        }
    );

    observer.observe(loadMoreIntersect.value);
});
</script>
