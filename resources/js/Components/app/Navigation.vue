<template>
    <nav class="min-w-[200px]">
            <Link :href="route('myFiles')" class="h-[80px] px-3 flex items-center gap-3">
                <!-- <ApplicationLogo
                    class="block h-9 w-auto fill-current text-gray-800"
                /> -->
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="block h-9 w-auto  text-indigo-700">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15a4.5 4.5 0 0 0 4.5 4.5H18a3.75 3.75 0 0 0 1.332-7.257 3 3 0 0 0-3.758-3.848 5.25 5.25 0 0 0-10.233 2.33A4.502 4.502 0 0 0 2.25 15Z" />
                </svg>


                <p class="font-bold">
                    <span class="text-indigo-600">Up</span>
                    <span class="text-indigo-700">In</span>
                    <span class="text-indigo-800">Cloud</span>
                </p>

            </Link>
        <div class="px-3">
            <CreateNewDropdown />

            <div class="py-3">
                <NavLink :href="route('myFiles')" :active="route().current('myFiles')">My Files</NavLink>
                <NavLink :href="route('file.sharedWithMe')" :active="route().current('file.sharedWithMe')">Shared with me</NavLink>
                <NavLink :href="route('file.sharedByMe')" :active="route().current('file.sharedByMe')">Shared by me</NavLink>
                <NavLink :href="route('trash')" :active="route().current('trash')">Trash</NavLink>
            </div>
        </div>
    </nav>
</template>
<script setup>
import { Link } from "@inertiajs/vue3";
import ApplicationLogo from "@/Components/ApplicationLogo.vue";
import CreateNewDropdown from "@/Components/app/CreateNewDropdown.vue";
import NavLink from "../NavLink.vue";
</script>
