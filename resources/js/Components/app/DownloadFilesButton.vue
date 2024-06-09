<template>
    <PrimaryButton :disabled="loading" @click="download">
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
                d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"
            />
        </svg>

        Download
    </PrimaryButton>
</template>
<script setup>
import { showErrorDialog } from "@/Services/event-bus.js";
import { useForm, usePage } from "@inertiajs/vue3";
import { ref } from "vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import { httpGet } from "@/Helper/http-helper.js";

const props = defineProps({
    all: {
        type: Boolean,
        default: false,
        required: false,
    },
    ids: {
        type: Array,
        required: false,
        default: () => [],
    },
    sharedWithMe: {
        type: Boolean,
        default: false,
        required: false,
    },
    sharedByMe: {
        type: Boolean,
        default: false,
        required: false,
    },
});


const page = usePage();

const loading = ref(false);

const download = () => {
    if (!props.all && !props.ids.length) {
        return showErrorDialog("Please select at least one file to download");
    }

    const p = new URLSearchParams();

    if(page.props?.folder?.id) {
        p.append('parent_id', page.props.folder.id);
    }

    if(props.all) {
        p.append('all', props.all);
    } else {
        props.ids.forEach(id => {
            p.append('ids[]', id);
        });
    }


    let url = route('file.download');
    if(props.sharedWithMe) {
        url = route('file.downloadSharedWithMe');
    } else if(props.sharedByMe) {
        url = route('file.downloadSharedByMe');
    }

    const search = (new URLSearchParams(window.location.search)).get('search');
    if(search) {
        url = route('file.downloadSearch');
        p.append('search', search);
    }

    httpGet(url+'?'+p.toString()).then((r) => {
        if(r.data.message) {
            return showErrorDialog(r.data.message);
        }

        if(!r.data.url) {
            return;
        }

        const a = document.createElement('a');
        a.href = r.data.url;
        a.download = r.data.fileName;
        a.click();

    }).catch((e) => {
        if(e.response) {
            return showErrorDialog(e.response.data.message);
        }
    });

    console.log('Download files');
};
</script>
