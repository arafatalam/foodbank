<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { Check, Minus, Plus, Search, SlidersHorizontal } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';

// Link back to stock items from the Adjustment page breadcrumb.
defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Stock items', href: '/stock-items' },
            { title: 'Stock adjustment', href: '/adjustment/create' },
        ],
    },
});

type StockItem = {
    id: number;
    product_name: string;
    product_path: string;
    brand: string;
    package_label: string;
    available_quantity: number;
};

// Describe the fields sent to Laravel.
type AdjustmentForm = {
    stock_item_id: number | null;
    direction: 'add' | 'remove';
    quantity: number;
    reason: string;
};

const props = defineProps<{ stockItems: StockItem[] }>();

// Keep draft values in the browser until the user saves the adjustment.
const search = ref('');
const selectedItem = ref<StockItem | null>(null);
const direction = ref<'add' | 'remove'>('add');
const quantity = ref<number | string>('');
const reason = ref('');

// Refresh the selected item's saved count without discarding the user's draft.
watch(
    () => props.stockItems,
    (items) => {
        if (!selectedItem.value) return;
        const refreshedItem = items.find(
            (item) => item.id === selectedItem.value?.id,
        );
        if (refreshedItem) selectedItem.value = refreshedItem;
    },
);

// Create the submission form with its starting values.
const form = useForm<AdjustmentForm>({
    stock_item_id: null,
    direction: 'add',
    quantity: 1,
    reason: '',
});

// Search existing choices, including items with no stock available.
const filteredStockItems = computed(() => {
    const text = search.value.trim().toLowerCase();
    if (!text) return [];
    return props.stockItems.filter(
        (item) =>
            item.product_name.toLowerCase().includes(text) ||
            item.brand.toLowerCase().includes(text),
    );
});

// Only show a balance preview for positive, whole-package quantities.
const validQuantity = computed(() => {
    const count = Number(quantity.value);
    return Number.isSafeInteger(count) && count > 0;
});
const previewBalance = computed(() => {
    if (!selectedItem.value || !validQuantity.value) return null;
    const change =
        direction.value === 'add'
            ? Number(quantity.value)
            : -Number(quantity.value);
    const balance = selectedItem.value.available_quantity + change;
    return Number.isSafeInteger(balance) ? balance : null;
});

// Clear the previous draft when choosing a different stock item.
function selectItem(item: StockItem) {
    if (selectedItem.value?.id === item.id) return;
    // A new draft should not show errors from the previous item.
    form.clearErrors();
    selectedItem.value = item;
    direction.value = 'add';
    quantity.value = '';
    reason.value = '';
}

function clearSelection() {
    // Clear validation messages together with the discarded draft.
    form.clearErrors();
    selectedItem.value = null;
    direction.value = 'add';
    quantity.value = '';
    reason.value = '';
}

function submit(): void {
    // Stop if no item is selected or a request is already running.
    if (!selectedItem.value || form.processing) {
        return;
    }

    // Copy the selected item and visible inputs into the submission form.
    form.stock_item_id = selectedItem.value.id;
    form.direction = direction.value;
    form.quantity = Number(quantity.value);
    form.reason = reason.value;

    // Send the prepared values to Laravel to save the adjustment.
    form.post('/adjustment', {
        onSuccess: () => {
            // Reset the values held by Inertia's form.
            form.reset();

            // Reset the visible selection and inputs.
            clearSelection();

            // Clear the picker search.
            search.value = '';
        },
    });
}
</script>

<template>
    <Head title="Stock adjustment" />

    <main class="space-y-6 p-4">
        <header class="flex items-start gap-3">
            <div class="rounded-xl bg-accent p-3 text-accent-foreground">
                <SlidersHorizontal class="size-5" aria-hidden="true" />
            </div>
            <div>
                <h1 class="text-xl font-semibold">Stock adjustment</h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Correct a package count or record damaged or spoiled stock.
                </p>
            </div>
        </header>

        <!-- Keep both panels within the screen; stack them until there is room for two columns. -->
        <div class="grid items-start gap-6 xl:grid-cols-2">
            <Card class="min-w-0">
                <CardHeader>
                    <CardTitle>1. Choose a stock item</CardTitle>
                    <CardDescription
                        >Search by product name or brand. Choose one item to
                        adjust.</CardDescription
                    >
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="space-y-2">
                        <label for="stock-search" class="text-sm font-medium"
                            >Find a stock item</label
                        >
                        <div class="relative">
                            <Search
                                class="pointer-events-none absolute top-3 left-3 size-4 text-muted-foreground"
                                aria-hidden="true"
                            />
                            <Input
                                id="stock-search"
                                v-model="search"
                                type="search"
                                class="pl-9"
                                placeholder="Search by product name or brand"
                            />
                        </div>
                    </div>

                    <p aria-live="polite" class="text-sm text-muted-foreground">
                        {{
                            search.trim()
                                ? `${filteredStockItems.length} matching choices`
                                : 'Start typing to find a stock item, including items with zero stock.'
                        }}
                    </p>

                    <!-- Contain table scrolling here so narrow screens do not scroll sideways. -->
                    <div
                        v-if="filteredStockItems.length"
                        class="max-h-96 overflow-auto rounded-xl border border-border"
                    >
                        <table class="w-full min-w-[560px] text-left text-sm">
                            <thead class="sticky top-0 z-10">
                                <tr>
                                    <th scope="col" class="px-4 py-3">
                                        Product / brand
                                    </th>
                                    <th scope="col" class="px-4 py-3">
                                        Package
                                    </th>
                                    <th
                                        scope="col"
                                        class="px-4 py-3 text-right"
                                    >
                                        Available
                                    </th>
                                    <th scope="col" class="px-4 py-3">
                                        <span class="sr-only">Select item</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="item in filteredStockItems"
                                    :key="item.id"
                                    :class="
                                        selectedItem?.id === item.id
                                            ? 'bg-accent/40'
                                            : ''
                                    "
                                >
                                    <td
                                        class="max-w-64 px-4 py-3 wrap-break-word"
                                    >
                                        <p class="font-medium">
                                            {{ item.product_name }}
                                        </p>
                                        <p>{{ item.brand }}</p>
                                        <p
                                            class="mt-1 text-xs text-muted-foreground"
                                        >
                                            {{ item.product_path }}
                                        </p>
                                    </td>
                                    <td
                                        class="max-w-40 px-4 py-3 wrap-break-word"
                                    >
                                        {{ item.package_label }}
                                    </td>
                                    <td
                                        class="px-4 py-3 text-right tabular-nums"
                                    >
                                        {{ item.available_quantity }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <Button
                                            type="button"
                                            size="sm"
                                            :variant="
                                                selectedItem?.id === item.id
                                                    ? 'secondary'
                                                    : 'outline'
                                            "
                                            :aria-pressed="
                                                selectedItem?.id === item.id
                                            "
                                            :aria-label="`Select ${item.product_name}, ${item.brand}, ${item.product_path}, ${item.package_label}`"
                                            @click="selectItem(item)"
                                        >
                                            <Check
                                                v-if="
                                                    selectedItem?.id === item.id
                                                "
                                                class="size-4"
                                                aria-hidden="true"
                                            />
                                            {{
                                                selectedItem?.id === item.id
                                                    ? 'Selected'
                                                    : 'Select'
                                            }}
                                        </Button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div
                        v-else-if="search.trim()"
                        class="rounded-xl border border-dashed p-6 text-center"
                    >
                        <p class="font-medium">No matching stock items</p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Try another product name or brand.
                        </p>
                    </div>
                </CardContent>
            </Card>

            <Card class="min-w-0">
                <CardHeader>
                    <CardTitle>2. Adjustment details</CardTitle>
                    <CardDescription
                        >Adjust whole packages and explain why the count needs
                        to change.</CardDescription
                    >
                </CardHeader>
                <CardContent class="space-y-6">
                    <div
                        v-if="!selectedItem"
                        class="rounded-xl border border-dashed p-8 text-center"
                    >
                        <SlidersHorizontal
                            class="mx-auto mb-3 size-8 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <p class="font-medium">Choose a stock item to begin</p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Its package details and current count will appear
                            here.
                        </p>
                    </div>
                    <template v-else>
                        <section
                            aria-label="Selected stock item"
                            class="space-y-3 rounded-xl border bg-muted/30 p-4"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0 wrap-break-word">
                                    <p class="font-semibold">
                                        {{ selectedItem.product_name }}
                                    </p>
                                    <p class="text-sm">
                                        {{ selectedItem.brand }}
                                    </p>
                                    <p
                                        class="mt-1 text-xs text-muted-foreground"
                                    >
                                        {{ selectedItem.product_path }}
                                    </p>
                                </div>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    @click="clearSelection"
                                    >Clear</Button
                                >
                            </div>
                            <dl class="grid gap-3 border-t pt-3 sm:grid-cols-2">
                                <div class="min-w-0">
                                    <dt class="text-xs text-muted-foreground">
                                        Package size
                                    </dt>
                                    <dd class="mt-1 text-sm wrap-break-word">
                                        {{ selectedItem.package_label }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-xs text-muted-foreground">
                                        Currently available
                                    </dt>
                                    <dd class="mt-1 font-semibold tabular-nums">
                                        {{ selectedItem.available_quantity }}
                                        packages
                                    </dd>
                                </div>
                            </dl>
                        </section>
                        <!-- Explain why Laravel rejected the selected stock item. -->
                        <InputError :message="form.errors.stock_item_id" />

                        <!-- Native radio inputs provide keyboard control for the two directions. -->
                        <fieldset class="space-y-2">
                            <legend class="text-sm font-medium">
                                What needs to change?
                            </legend>
                            <div class="grid grid-cols-2 gap-3">
                                <label
                                    class="flex cursor-pointer items-center gap-2 rounded-lg border p-3 text-sm has-[:checked]:border-primary has-[:checked]:bg-accent/40"
                                >
                                    <input
                                        v-model="direction"
                                        type="radio"
                                        name="direction"
                                        value="add"
                                        class="size-4 accent-primary"
                                    />
                                    <Plus
                                        class="size-4 shrink-0"
                                        aria-hidden="true"
                                    />
                                    Add
                                </label>
                                <label
                                    class="flex cursor-pointer items-center gap-2 rounded-lg border p-3 text-sm has-[:checked]:border-primary has-[:checked]:bg-accent/40"
                                >
                                    <input
                                        v-model="direction"
                                        type="radio"
                                        name="direction"
                                        value="remove"
                                        class="size-4 accent-primary"
                                    />
                                    <Minus
                                        class="size-4 shrink-0"
                                        aria-hidden="true"
                                    />
                                    Remove
                                </label>
                            </div>
                        </fieldset>
                        <!-- Show Laravel's validation message for the adjustment direction. -->
                        <InputError :message="form.errors.direction" />

                        <!-- Stack on phones; place the quantity and preview beside each other on wider screens. -->
                        <div class="grid items-start gap-4 sm:grid-cols-2">
                            <div class="min-w-0 space-y-2">
                                <label
                                    for="adjustment-quantity"
                                    class="text-sm font-medium"
                                    >Packages to {{ direction }}</label
                                >
                                <Input
                                    id="adjustment-quantity"
                                    v-model="quantity"
                                    type="number"
                                    min="1"
                                    step="1"
                                    placeholder="Enter package count"
                                    aria-describedby="quantity-help quantity-error"
                                    :aria-invalid="
                                        quantity !== '' && !validQuantity
                                    "
                                />
                                <p
                                    id="quantity-help"
                                    class="text-xs text-muted-foreground"
                                >
                                    Enter whole packages, not their weight or
                                    contents.
                                </p>
                                <p
                                    v-if="quantity !== '' && !validQuantity"
                                    id="quantity-error"
                                    role="alert"
                                    class="text-sm text-destructive"
                                >
                                    Enter a positive whole-package quantity.
                                </p>
                                <!-- Show Laravel's validation message for the package count. -->
                                <InputError :message="form.errors.quantity" />
                            </div>

                            <!-- This is a local calculation only; it never updates saved stock. -->
                            <section
                                aria-label="Balance preview"
                                aria-live="polite"
                                class="min-w-0 rounded-xl border p-4 wrap-break-word"
                            >
                                <p class="text-sm font-medium">
                                    Balance preview
                                </p>
                                <template v-if="previewBalance !== null">
                                    <p
                                        class="mt-2 text-2xl font-semibold tabular-nums"
                                    >
                                        {{ previewBalance }}
                                        <span
                                            class="text-sm font-normal text-muted-foreground"
                                            >packages</span
                                        >
                                    </p>
                                    <p
                                        class="mt-1 text-sm text-muted-foreground"
                                    >
                                        {{ selectedItem.available_quantity }}
                                        {{ direction === 'add' ? '+' : '−' }}
                                        {{ quantity }} packages
                                    </p>
                                    <p
                                        v-if="previewBalance < 0"
                                        role="alert"
                                        class="mt-2 text-sm text-destructive"
                                    >
                                        This removes more packages than are
                                        currently available. Reduce the
                                        quantity.
                                    </p>
                                </template>
                                <p
                                    v-else
                                    class="mt-2 text-sm text-muted-foreground"
                                >
                                    Enter a valid quantity to preview the
                                    resulting balance.
                                </p>
                            </section>
                        </div>

                        <div class="space-y-2">
                            <label
                                for="adjustment-reason"
                                class="text-sm font-medium"
                                >Reason
                                <span class="text-muted-foreground"
                                    >(required)</span
                                ></label
                            >
                            <textarea
                                id="adjustment-reason"
                                v-model="reason"
                                rows="3"
                                required
                                placeholder="For example: two damaged packages found during a stock count."
                                class="flex min-h-24 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            />
                            <!-- Show Laravel's validation message for the reason field. -->
                            <InputError :message="form.errors.reason" />
                        </div>
                    </template>

                    <div class="space-y-3 border-t pt-4">
                        <p id="save-help" class="text-sm text-muted-foreground">
                            Saving records this adjustment and updates the
                            available stock.
                        </p>
                        <!-- Send the draft once; prevent another click while the request is running. -->
                        <Button
                            type="button"
                            :disabled="!selectedItem || form.processing"
                            aria-describedby="save-help"
                            class="w-full"
                            @click="submit"
                        >
                            {{
                                form.processing
                                    ? 'Saving...'
                                    : 'Save adjustment'
                            }}
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    </main>
</template>
