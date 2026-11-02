<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import PageHeading from '@/components/PageHeading.vue';
import { Palette } from '@lucide/vue';
import { edit } from '@/routes/appearance';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Appearance settings',
                href: edit(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Appearance settings" />

    <div class="space-y-6">
        <PageHeading
            title="Appearance settings"
            description="Choose how FoodBank looks on your device."
            :icon="Palette"
        />
        <AppearanceTabs />
    </div>
</template>
