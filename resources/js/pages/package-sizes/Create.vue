<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PackageSizeForm from '@/components/PackageSizeForm.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

// Keep both pages under the Package sizes list in the breadcrumb.
defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Package sizes', href: '/package-sizes' },
            { title: 'Create Package Size', href: '#' },
        ],
    },
});
</script>

<template>
    <Head title="Create Package Size" />
    <main class="p-4">
        <Card class="w-full max-w-3xl">
            <CardHeader><CardTitle>Create Package Size</CardTitle></CardHeader>
            <CardContent>
                <div class="mt-4 rounded-xl border border-border p-4">
                    <!-- Share field layout, validation errors and submission state. -->
                    <PackageSizeForm />
                </div>
            </CardContent>
        </Card>
    </main>
</template>
