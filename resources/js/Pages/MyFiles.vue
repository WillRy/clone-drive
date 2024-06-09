<template>
    <AuthenticatedLayout>
        <nav class="flex items-center justify-between p-1 mb-3">
            <ol class="inline-flex items-center space-x-1 md:space-x-3">
                <li
                    v-for="ans of ancestors.data"
                    :key="ans.id"
                    class="inline-flex items-center"
                >
                    <Link
                        v-if="!ans.parent_id"
                        :href="route('myFiles')"
                        class="inline-flex items-center text-sm font-medium text-gray-700 hover:text-blue-600 dark:text-gray-400 dark:hover:text-white"
                    >
                        <HomeIcon class="w-4 h-4 mr-2" />
                        My Files
                    </Link>
                    <div v-else class="flex items-center">
                        <svg
                            aria-hidden="true"
                            class="w-6 h-6 text-gray-400"
                            fill="currentColor"
                            viewBox="0 0 20 20"
                            xmlns="http://www.w3.org/2000/svg"
                        >
                            <path
                                fill-rule="evenodd"
                                d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z"
                                clip-rule="evenodd"
                            ></path>
                        </svg>
                        <Link
                            :href="route('myFiles', { folder: ans.path })"
                            class="ml-1 text-sm font-medium text-gray-700 hover:text-blue-600 md:ml-2 dark:text-gray-400 dark:hover:text-white"
                        >
                            {{ ans.name }}
                        </Link>
                    </div>
                </li>
            </ol>
            <div class="flex">
                <label class="flex items-center mr-3">
                    Only favourites
                    <Checkbox
                        @change="showOnlyFavourites"
                        v-model:checked="onlyFavourites"
                        class="ml-2"
                    />
                </label>
                <ShareFilesButton
                    :all-selected="allSelected"
                    :selected-ids="selectedIds"
                    class="mr-2"
                />
                <DownloadFilesButton
                    :all="allSelected"
                    :ids="selectedIds"
                    class="mr-2"
                />
                <DeleteFilesButton
                    :delete-all="allSelected"
                    :delete-ids="selectedIds"
                    @delete="onDelete"
                />
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
                        ></th>
                        <th
                            class="text-sm font-medium text-gray-900 px-6 py-4 text-left"
                        >
                            Name
                        </th>
                        <th
                            v-if="search"
                            class="text-sm font-medium text-gray-900 px-6 py-4 text-left"
                        >
                            Path
                        </th>
                        <th
                            class="text-sm font-medium text-gray-900 px-6 py-4 text-left"
                        >
                            Owner
                        </th>
                        <th
                            class="text-sm font-medium text-gray-900 px-6 py-4 text-left"
                        >
                            Last modified
                        </th>
                        <th
                            class="text-sm font-medium text-gray-900 px-6 py-4 text-left"
                        >
                            Size
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
                        @dblclick="openFolder(file)"
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
                            class="px-6 py-4 max-w-[40px] text-sm font-medium text-gray-900 align-middle"
                        >
                            <FavouritesButton
                                @toggle="addRemoveFavourite(file)"
                                :favourite="file.is_favourite"
                            />
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
                            v-if="search"
                            class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 align-middle"
                        >
                            {{ file.path }}
                        </td>
                        <td
                            class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 align-middle"
                        >
                            {{ file.owner }}
                        </td>
                        <td
                            class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 align-middle"
                        >
                            {{ file.updated_at }}
                        </td>
                        <td
                            class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 align-middle"
                        >
                            {{ file.size }}
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
import ShareFilesButton from "@/Components/app/ShareFilesButton.vue";
import FavouritesButton from "@/Components/app/FavouritesButton.vue";
import DownloadFilesButton from "@/Components/app/DownloadFilesButton.vue";
import { onMounted } from "vue";
import { ref } from "vue";
import { onUpdated } from "vue";
import { watch } from "vue";
import { httpGet, httpPost } from "@/Helper/http-helper.js";
import Checkbox from "@/Components/Checkbox.vue";
import { computed } from "vue";
import { showSuccessNotification } from "@/Services/event-bus.js";

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

const onlyFavourites = ref(false);
const search = ref('');
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

const openFolder = (file) => {
    if (!file.is_folder) {
        return false;
    }

    router.visit(route("myFiles", { folder: file.path }));
};

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

const onDelete = () => {
    selected.value = {};
    allSelected.value = false;
};

const addRemoveFavourite = (file) => {
    httpPost(route("file.addToFavourites"), { id: file.id }).then(() => {
        file.is_favourite = !file.is_favourite;

        if (file.is_favourite) {
            showSuccessNotification("File has been added to favourites");
        } else {
            showSuccessNotification("File has been removed from favourites");
        }
    });
};

const showOnlyFavourites = () => {
    router.visit(route("myFiles", {
        folder: props.folder.path,
        ...(onlyFavourites.value ? {favourites: 1} : {})
    }));
}

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
    onlyFavourites.value = (new URLSearchParams(window.location.search)).get('favourites') === '1';
    search.value = (new URLSearchParams(window.location.search)).get('search');

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
