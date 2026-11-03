<script setup lang="ts">
import PageHeading from '@/components/PageHeading.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Head, Link, router } from '@inertiajs/vue3';
import { ChevronRight, Package } from '@lucide/vue';
import { onUnmounted, ref, watch } from 'vue';

type StockItemDetail = {
    id: number;
    brand: string;
    package: string;
    available_quantity: number;
    canViewHistory: boolean;
    total_quantity: string;
};

// Describe the fields Laravel prepares for one Product.
type ProductTotal = {
    id: number;
    product: string;
    path: string;
    total_quantity: string;
    stock_items: StockItemDetail[];
};

// Receive the controller's productTotals prop.
const props = defineProps<{
    search: string;
    productTotals: {
        data: ProductTotal[];
        // Laravel supplies the current and final page numbers.
        current_page: number;
        last_page: number;
        // A missing previous or next page has no URL.
        prev_page_url: string | null;
        next_page_url: string | null;
    };
}>();

// Remember open Products in the browser; this does not change saved stock.
const expandedProductIds = ref(new Set<number>());

function toggleStockItems(productId: number) {
    if (expandedProductIds.value.has(productId)) {
        expandedProductIds.value.delete(productId);
    } else {
        expandedProductIds.value.add(productId);
    }
}

// Keep the typed search locally until it is sent to Laravel.
const search = ref(props.search);
let searchTimer: number | undefined;

function searchProductTotals() {
    window.clearTimeout(searchTimer);
    // Omit page so a changed or cleared search starts at page one.
    router.get(
        '/product-totals',
        { search: search.value },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
}

watch(search, (value) => {
    window.clearTimeout(searchTimer);
    // Wait for a pause in typing; prop synchronization must not send a request.
    if (value !== props.search) {
        searchTimer = window.setTimeout(searchProductTotals, 300);
    }
});

watch(
    () => props.search,
    (value) => {
        // Restore the field when browser navigation supplies a different search.
        window.clearTimeout(searchTimer);
        search.value = value;
    },
);

onUnmounted(() => window.clearTimeout(searchTimer));

// Supply the page breadcrumb through the shared application layout.
defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Product totals', href: '/product-totals' }],
    },
});
</script>

<template>
    <Head title="Product totals" />

    <!-- Match the spacing used by the other index pages. -->
    <main class="space-y-6 p-4">
        <PageHeading
            title="Product totals"
            description="Combined stock across brands and package sizes."
            :icon="Package"
        />

        <Card class="w-full">
            <CardContent>
                <!-- Match the search layout used by the other stock index pages. -->
                <form
                    class="mb-4 grid gap-2"
                    @submit.prevent="searchProductTotals"
                >
                    <Label for="product-total-search">Search Products</Label>
                    <Input
                        id="product-total-search"
                        v-model="search"
                        type="search"
                        maxlength="100"
                        placeholder="Search Product names…"
                    />
                </form>
                <!-- Match the bordered table container and keep overflow inside it. -->
                <div class="overflow-x-auto rounded-xl border border-border">
                    <!-- Fill the available width and align the text to the left. -->
                    <table class="w-full text-left">
                        <thead>
                            <!-- Label the three columns. -->
                            <tr>
                                <th scope="col" class="px-4 py-3">Product</th>
                                <th scope="col" class="px-4 py-3">
                                    Department / Category
                                </th>
                                <th scope="col" class="px-4 py-3">
                                    Total quantity
                                </th>
                            </tr>
                        </thead>

                        <tbody>
                            <!-- Explain an empty result instead of showing a blank table. -->
                            <tr v-if="props.productTotals.data.length === 0">
                                <td colspan="3" class="px-4 py-3">
                                    No Product totals found.
                                </td>
                            </tr>
                            <!-- Keep each Product and its expandable details together. -->
                            <template
                                v-for="productTotal in props.productTotals.data"
                                :key="productTotal.id"
                            >
                                <tr>
                                    <td class="px-4 py-3 break-words">
                                        <button
                                            type="button"
                                            class="flex items-center gap-2 rounded text-left focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-ring"
                                            :aria-expanded="
                                                expandedProductIds.has(
                                                    productTotal.id,
                                                )
                                            "
                                            :aria-controls="`product-stock-items-${productTotal.id}`"
                                            @click="
                                                toggleStockItems(
                                                    productTotal.id,
                                                )
                                            "
                                        >
                                            <ChevronRight
                                                class="size-4 shrink-0 transition-transform"
                                                :class="{
                                                    'rotate-90':
                                                        expandedProductIds.has(
                                                            productTotal.id,
                                                        ),
                                                }"
                                                aria-hidden="true"
                                            />
                                            {{ productTotal.product }}
                                            <span class="sr-only"
                                                >stock items</span
                                            >
                                        </button>
                                    </td>
                                    <td class="px-4 py-3 break-words">
                                        {{ productTotal.path }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        {{ productTotal.total_quantity }}
                                    </td>
                                </tr>
                                <!-- Span all three parent columns so details have the full width. -->
                                <tr
                                    v-show="
                                        expandedProductIds.has(productTotal.id)
                                    "
                                    :id="`product-stock-items-${productTotal.id}`"
                                >
                                    <td colspan="3" class="px-4 py-3">
                                        <!-- Inset the breakdown and visually connect it to its Product. -->
                                        <section
                                            class="space-y-3 rounded-lg border-l-2 border-border bg-muted/50 p-4 sm:ml-6"
                                            :aria-labelledby="`stock-breakdown-heading-${productTotal.id}`"
                                        >
                                            <h2
                                                :id="`stock-breakdown-heading-${productTotal.id}`"
                                                class="text-sm font-medium"
                                            >
                                                Stock breakdown
                                                <span class="sr-only"
                                                    >for
                                                    {{
                                                        productTotal.product
                                                    }}</span
                                                >
                                            </h2>
                                            <!-- One column on phones, two on medium screens, three on wide screens. -->
                                            <ul
                                                class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3"
                                            >
                                                <li
                                                    v-for="stockItem in productTotal.stock_items"
                                                    :key="stockItem.id"
                                                    class="min-w-0 space-y-3 rounded-lg border border-border bg-card p-4"
                                                >
                                                    <!-- Keep details left and the history button at the top right. -->
                                                    <div
                                                        class="flex items-start justify-between gap-3"
                                                    >
                                                        <div
                                                            class="min-w-0 space-y-1"
                                                        >
                                                            <p
                                                                class="font-medium break-words"
                                                            >
                                                                {{
                                                                    stockItem.brand
                                                                }}
                                                            </p>
                                                            <p
                                                                class="text-sm break-words text-muted-foreground"
                                                            >
                                                                {{
                                                                    stockItem.package
                                                                }}
                                                            </p>
                                                        </div>
                                                        <!-- Link to this specific stock item's protected History page. -->
                                                        <Button
                                                            v-if="
                                                                stockItem.canViewHistory
                                                            "
                                                            as-child
                                                            variant="outline"
                                                            size="sm"
                                                            class="shrink-0"
                                                        >
                                                            <Link
                                                                :href="`/stock-items/${stockItem.id}/history?from=product-totals`"
                                                                :aria-label="`Show history for ${productTotal.product}, ${stockItem.brand}, ${stockItem.package}`"
                                                                >Show
                                                                history</Link
                                                            >
                                                        </Button>
                                                    </div>
                                                    <!-- Wrap the count and total when a card is narrow. -->
                                                    <div
                                                        class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 text-sm"
                                                    >
                                                        <p
                                                            class="text-muted-foreground"
                                                        >
                                                            {{
                                                                stockItem.available_quantity
                                                            }}
                                                            {{
                                                                stockItem.available_quantity ===
                                                                1
                                                                    ? 'package'
                                                                    : 'packages'
                                                            }}
                                                            available
                                                        </p>
                                                        <p
                                                            class="font-semibold"
                                                        >
                                                            {{
                                                                stockItem.total_quantity
                                                            }}
                                                            total
                                                        </p>
                                                    </div>
                                                </li>
                                            </ul>
                                        </section>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
                <!-- Keep the page count left and controls right, wrapping when needed. -->
                <nav
                    aria-label="Product totals pages"
                    class="mt-4 flex flex-wrap items-center justify-between gap-3"
                >
                    <p>
                        Page: {{ props.productTotals.current_page }} of
                        {{ props.productTotals.last_page }}
                    </p>
                    <div class="flex gap-2">
                        <!-- Show Previous only when Laravel supplies its destination. -->
                        <Button
                            v-if="props.productTotals.prev_page_url"
                            as-child
                        >
                            <Link :href="props.productTotals.prev_page_url"
                                >Previous</Link
                            >
                        </Button>

                        <!-- Show Next only when another page exists. -->
                        <Button
                            v-if="props.productTotals.next_page_url"
                            as-child
                        >
                            <Link :href="props.productTotals.next_page_url"
                                >Next</Link
                            >
                        </Button>
                    </div>
                </nav>
            </CardContent>
        </Card>
    </main>
</template>
