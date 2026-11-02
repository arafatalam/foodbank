<script setup lang="ts">
import type { Component } from 'vue';

defineProps<{
    title: string;
    description: string;
    icon: Component;
}>();
</script>

<template>
    <!-- Keep the icon beside a title that can wrap on narrow screens. -->
    <header class="flex items-start gap-3">
        <div class="shrink-0 rounded-xl bg-accent p-3 text-accent-foreground">
            <component :is="icon" class="size-5" aria-hidden="true" />
        </div>
        <div class="min-w-0">
            <h1 class="text-xl font-semibold wrap-break-word">{{ title }}</h1>
            <p class="mt-1 text-sm text-muted-foreground">{{ description }}</p>
        </div>
    </header>
</template>
