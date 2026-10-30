<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { onUnmounted, ref, watch } from 'vue';

// Receive the tag records and page information from Laravel.
const props = defineProps<{
    search: string;
    canCreate: boolean;
    tags: {
        data: {
            id: number;
            name: string;
            canEdit: boolean;
        }[];
        prev_page_url: string | null;
        next_page_url: string | null;
        current_page: number;
        last_page: number;
    };
}>();

// Start the editable input with the search text supplied by Laravel.
const search = ref(props.search);

// Remember the waiting timer so we can cancel it.
let searchTimer: number | undefined;

function searchTags() {
    // Cancel any waiting search when submitting immediately.
    window.clearTimeout(searchTimer);

    // Request matching tags while keeping the input's local state.
    router.get('/tags', { search: search.value }, { preserveState: true });
}

// Notice when the input's value changes.
watch(search, () => {
    // Cancel the previous wait when another letter is typed.
    window.clearTimeout(searchTimer);

    // Search after typing has stopped for 300 milliseconds.
    searchTimer = window.setTimeout(() => {
        searchTags();
    }, 300);
});

// Cancel a waiting search when leaving this page.
onUnmounted(() => {
    window.clearTimeout(searchTimer);
});
</script>

<template>
    <Head title="Tags" />

    <!-- Match the spacing and card width of the user list. -->
    <main class="p-4">
        <Card class="w-full max-w-3xl">
            <CardHeader>
                <CardTitle>Tags</CardTitle>
                <Button v-if="canCreate" as-child class="w-fit">
                    <Link href="/tags/create">Create tag</Link>
                </Button>
            </CardHeader>

            <CardContent>
                <!-- Stack the label above the search input. -->
                <form
                    @submit.prevent="searchTags"
                    class="mt-4 flex flex-col gap-2"
                >
                    <Label for="tag-search">Search tags</Label>

                    <Input
                        id="tag-search"
                        v-model="search"
                        type="search"
                        maxlength="100"
                        placeholder="Search tags..."
                    />
                </form>
                <!-- Allow horizontal scrolling when the table needs more room. -->
                <div
                    class="mt-4 overflow-x-auto rounded-xl border border-border"
                >
                    <table class="w-full text-left">
                        <thead>
                            <tr>
                                <th scope="col" class="px-4 py-3">Tag name</th>
                                <th scope="col" class="px-4 py-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Use both columns for the empty-list message. -->
                            <tr v-if="tags.data.length === 0">
                                <td colspan="2" class="px-4 py-3">
                                    No tags found.
                                </td>
                            </tr>

                            <!-- Keep each row connected to its tag ID. -->
                            <tr v-for="tag in tags.data" :key="tag.id">
                                <td class="px-4 py-3">{{ tag.name }}</td>
                                <td class="px-4 py-3">
                                    <!-- Open this tag's edit address. -->
                                    <Button
                                        v-if="tag.canEdit"
                                        variant="outline"
                                        size="sm"
                                        as-child
                                    >
                                        <Link
                                            :href="`/tags/${tag.id}/edit`"
                                            :aria-label="`Edit ${tag.name}`"
                                        >
                                            Edit
                                        </Link>
                                    </Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Let the page controls wrap on narrow screens. -->
                <div
                    class="mt-4 flex flex-wrap items-center justify-between gap-3"
                >
                    <p class="pl-1">
                        Page: {{ tags.current_page }} of
                        {{ tags.last_page }}
                    </p>

                    <div class="flex gap-2">
                        <Button v-if="tags.prev_page_url" as-child>
                            <Link :href="tags.prev_page_url"> Previous </Link>
                        </Button>

                        <Button v-if="tags.next_page_url" as-child>
                            <Link :href="tags.next_page_url">Next</Link>
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>
    </main>
</template>
