<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { onUnmounted, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

type StockRow = {
    id: number;
    product: string;
    path: string;
    brand: string | null;
    package: string;
    available_quantity: number;
    total_quantity: string;
    canUpdate: boolean;
    canDelete: boolean;
};
const props = defineProps<{
    canCreate: boolean;
    search: string;
    stockItems: {
        data: StockRow[];
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
}>();

const search = ref(props.search);
let searchTimer: number | undefined;
function searchStockItems() {
    window.clearTimeout(searchTimer);
    router.get(
        '/stock-items',
        { search: search.value },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
}
watch(search, () => {
    window.clearTimeout(searchTimer);
    searchTimer = window.setTimeout(searchStockItems, 300);
});
onUnmounted(() => window.clearTimeout(searchTimer));

const deleting = ref<StockRow | null>(null);
const processing = ref(false);
const deleteError = ref('');
function confirmDelete(item: StockRow) {
    deleteError.value = '';
    deleting.value = item;
}
function deleteItem() {
    if (!deleting.value || processing.value) return;
    processing.value = true;
    router.delete(`/stock-items/${deleting.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            deleting.value = null;
        },
        onError: (errors) => {
            deleteError.value = Object.values(errors).join(' ');
        },
        onFinish: () => {
            processing.value = false;
        },
    });
}
defineOptions({
    layout: { breadcrumbs: [{ title: 'Stock items', href: '/stock-items' }] },
});
</script>

<template>
    <Head title="Stock Items" />
    <main class="p-4">
        <Card class="w-full">
            <CardHeader>
                <CardTitle>Stock items</CardTitle>
                <Button v-if="canCreate" as-child class="w-fit"
                    ><Link href="/stock-items/create"
                        >Create stock item</Link
                    ></Button
                >
            </CardHeader>
            <CardContent>
                <form
                    @submit.prevent="searchStockItems"
                    class="mb-4 grid gap-2"
                >
                    <Label for="stock-search">Search Product or brand</Label>
                    <Input
                        id="stock-search"
                        v-model="search"
                        type="search"
                        maxlength="100"
                        placeholder="Search Product names or brands…"
                    />
                </form>
                <p
                    v-if="deleteError"
                    role="alert"
                    class="mb-4 text-destructive"
                >
                    {{ deleteError }}
                </p>
                <!-- Scroll this container instead of widening the entire phone page. -->
                <div class="overflow-x-auto rounded-xl border border-border">
                    <table class="w-full text-left">
                        <thead>
                            <tr>
                                <th scope="col" class="px-4 py-3">Product</th>
                                <th scope="col" class="px-4 py-3">Brand</th>
                                <th scope="col" class="px-4 py-3">
                                    Package size
                                </th>
                                <th scope="col" class="px-4 py-3">
                                    Available packages
                                </th>
                                <th scope="col" class="px-4 py-3">
                                    Total quantity
                                </th>
                                <th
                                    v-if="canCreate"
                                    scope="col"
                                    class="px-4 py-3"
                                >
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="!stockItems.data.length">
                                <td
                                    :colspan="canCreate ? 6 : 5"
                                    class="px-4 py-3"
                                >
                                    No stock items found.
                                </td>
                            </tr>
                            <tr v-for="item in stockItems.data" :key="item.id">
                                <td class="px-4 py-3">
                                    <p>{{ item.product }}</p>
                                    <p class="text-sm text-muted-foreground">
                                        {{ item.path }}
                                    </p>
                                </td>
                                <td class="px-4 py-3">
                                    {{ item.brand ?? 'Unbranded' }}
                                </td>
                                <td class="px-4 py-3">{{ item.package }}</td>
                                <td class="px-4 py-3">
                                    {{ item.available_quantity }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    {{ item.total_quantity }}
                                </td>
                                <td v-if="canCreate" class="px-4 py-3">
                                    <div class="flex gap-2">
                                        <Button
                                            v-if="item.canUpdate"
                                            as-child
                                            variant="outline"
                                            size="sm"
                                            ><Link
                                                :href="`/stock-items/${item.id}/edit`"
                                                :aria-label="`Edit ${item.product}, ${item.brand ?? 'Unbranded'}, ${item.package}`"
                                                >Edit</Link
                                            ></Button
                                        >
                                        <Button
                                            variant="destructive"
                                            size="sm"
                                            :disabled="
                                                !item.canDelete || processing
                                            "
                                            :aria-label="`Delete ${item.product}, ${item.brand ?? 'Unbranded'}, ${item.package}`"
                                            @click="confirmDelete(item)"
                                            >Delete</Button
                                        >
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div
                    class="mt-4 flex flex-wrap items-center justify-between gap-3"
                >
                    <p>
                        Page: {{ stockItems.current_page }} of
                        {{ stockItems.last_page }}
                    </p>
                    <div class="flex gap-2">
                        <Button v-if="stockItems.prev_page_url" as-child
                            ><Link :href="stockItems.prev_page_url"
                                >Previous</Link
                            ></Button
                        >
                        <Button v-if="stockItems.next_page_url" as-child
                            ><Link :href="stockItems.next_page_url"
                                >Next</Link
                            ></Button
                        >
                    </div>
                </div>
            </CardContent>
        </Card>
        <Dialog
            :open="!!deleting"
            @update:open="
                (open) => {
                    if (!open && !processing) deleting = null;
                }
            "
        >
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete unused stock item?</DialogTitle>
                    <DialogDescription v-if="deleting"
                        >{{ deleting.product }} ·
                        {{ deleting.brand ?? 'Unbranded' }} ·
                        {{ deleting.package }} will be deleted. The catalogue
                        Product and Package size will remain.</DialogDescription
                    >
                </DialogHeader>
                <p v-if="deleteError" role="alert" class="text-destructive">
                    {{ deleteError }}
                </p>
                <DialogFooter>
                    <Button
                        variant="outline"
                        :disabled="processing"
                        @click="deleting = null"
                        >Cancel</Button
                    >
                    <Button
                        variant="destructive"
                        :disabled="processing"
                        @click="deleteItem"
                        >{{
                            processing ? 'Deleting…' : 'Delete stock item'
                        }}</Button
                    >
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </main>
</template>
