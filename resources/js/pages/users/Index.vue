<script setup lang="ts">
// Reuse the card components from our other screens.
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Head, Link, router } from '@inertiajs/vue3';
import { edit, index } from '@/routes/users';
import { onUnmounted, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';

// The paginated result contains a list of users inside data.
const props = defineProps<{
    search: string;
    users: {
        data: {
            id: number;
            first_name: string;
            last_name: string;
            email: string;
            phone: string;
            role: string;
            status: string;
            canEdit: boolean;
        }[];
        // URLs for moving between pages.
        prev_page_url: string | null;
        next_page_url: string | null;
        // Describe the page numbers Laravel supplies.
        current_page: number;
        last_page: number;
    };
}>();

// Start the editable input with the search text Laravel supplied.
const search = ref(props.search);

// Remember the pending search timer so we can cancel it.
let searchTimer: number | undefined;

// Send the search while keeping this page's local state.
function searchUsers() {
    // Cancel any delayed search before sending this request.
    window.clearTimeout(searchTimer);

    router.get(index.url(), { search: search.value }, { preserveState: true });
}

// Notice whenever the search text changes.
watch(search, () => {
    /// Cancel the previous search if it is still waiting.
    window.clearTimeout(searchTimer);

    // Remember a new timer that searches after 300 milliseconds.
    searchTimer = window.setTimeout(() => {
        searchUsers();
    }, 300);
});

// Cancel a waiting search when this page component is removed.
onUnmounted(() => {
    window.clearTimeout(searchTimer);
});

// Tell the shared layout where this page belongs.
defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Users', href: '/users' }],
    },
});
</script>

<template>
    <!-- Supply the page name for the browser tab. -->
    <Head title="Users" />
    <!-- Add space around the card. -->
    <main class="p-4">
        <!-- Fill available width without exceeding our usual card width. -->
        <Card class="w-full max-w-6xl">
            <CardHeader>
                <CardTitle>Users</CardTitle>
            </CardHeader>

            <CardContent>
                <div
                    class="mt-4 overflow-hidden rounded-xl border border-border"
                >
                    <!-- Send the search through Inertia when this form is submitted. -->
                    <form
                        @submit.prevent="searchUsers"
                        class="flex flex-col gap-2 p-4"
                    >
                        <Label for="user-search">Search users</Label>
                        <Input
                            id="user-search"
                            v-model="search"
                            type="search"
                            maxlength="100"
                            placeholder="Search users..."
                        />
                    </form>
                </div>
                <!-- Define the table's six column headings. -->
                <div
                    class="mt-4 overflow-hidden rounded-xl border border-border"
                >
                    <table class="w-full text-left">
                        <thead>
                            <tr>
                                <th scope="col" class="px-4 py-3">
                                    First name
                                </th>
                                <th scope="col" class="px-4 py-3">Last name</th>
                                <th scope="col" class="px-4 py-3">Email</th>
                                <th scope="col" class="px-4 py-3">Phone</th>
                                <th scope="col" class="px-4 py-3">Role</th>
                                <th scope="col" class="px-4 py-3">Status</th>
                                <th scope="col" class="px-4 py-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Show this row only when the returned user list is empty. -->
                            <tr v-if="users.data.length === 0">
                                <!-- Span all six columns so the message uses the table's width. -->
                                <td colspan="7" class="px-4 py-3">
                                    No users found.
                                </td>
                            </tr>

                            <!-- Create one row for each user, identified by their ID. -->
                            <tr v-for="user in users.data" :key="user.id">
                                <!-- Keep the cells in the same order as the headings. -->
                                <td class="px-4 py-3">{{ user.first_name }}</td>
                                <td class="px-4 py-3">{{ user.last_name }}</td>
                                <td class="px-4 py-3">{{ user.email }}</td>
                                <td class="px-4 py-3">{{ user.phone }}</td>
                                <td class="px-4 py-3">{{ user.role }}</td>
                                <td class="px-4 py-3">{{ user.status }}</td>
                                <td class="px-4 py-3">
                                    <!-- Disable editing when Laravel says permission is denied. -->
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        :disabled="!user.canEdit"
                                        @click="router.get(edit.url(user.id))"
                                    >
                                        Edit
                                    </Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <!-- Put page information on the left and the button group on the right. -->
                <div
                    class="mt-4 flex items-center justify-between overflow-hidden"
                >
                    <p class="pl-1">
                        Page: {{ users.current_page }} of {{ users.last_page }}
                    </p>

                    <!-- Keep both buttons together with space between them. -->
                    <div class="flex gap-2">
                        <Button v-if="users.prev_page_url" as-child>
                            <Link :href="users.prev_page_url"> Previous </Link>
                        </Button>

                        <Button v-if="users.next_page_url" as-child>
                            <Link :href="users.next_page_url"> Next </Link>
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>
    </main>
</template>
