<script setup lang="ts">
// Use Inertia's page title and submission helper.
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import {
    Check,
    ClipboardList,
    HandHeart,
    Package,
    Plus,
    Search,
    Trash2,
    Users,
} from '@lucide/vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';

// Use the same page shell and breadcrumbs as the other stock screens.
defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Stock items', href: '/stock-items' },
            { title: 'Distribute food', href: '/distribution/create' },
        ],
    },
});

type Household = { id: number; first_name: string; last_name: string };
type StockItem = {
    id: number;
    product_name: string;
    product_path: string;
    brand: string;
    package_label: string;
    available_quantity: number;
};
type DraftItem = { stockItem: StockItem; quantity: number };

// Describe the values Laravel expects when saving a distribution.
type DistributionForm = {
    household_id: number | null;
    items: {
        stock_item_id: number;
        quantity: number;
    }[];
};

const props = defineProps<{
    households: Household[];
    stockItems: StockItem[];
}>();

// Keep the household and item selections as a local draft until submission.
const householdSearch = ref('');
const selectedHousehold = ref<Household | null>(null);
const stockSearch = ref('');
// Each search row owns its package input and any local validation message.
const itemQuantities = ref<Record<number, number | string>>({});
const itemErrors = ref<Record<number, string>>({});
const draftItems = ref<DraftItem[]>([]);

// Start with no household or items prepared for submission.
const form = useForm<DistributionForm>({
    household_id: null,
    items: [],
});

function householdName(household: Household): string {
    return `${household.first_name} ${household.last_name}`;
}

// Show household matches only after someone enters a search.
const filteredHouseholds = computed(() => {
    const text = householdSearch.value.trim().toLowerCase();
    if (!text) return [];
    return props.households.filter(
        (household) =>
            householdName(household).toLowerCase().includes(text) ||
            String(household.id).includes(text),
    );
});

// Stock selection opens only after a household has been chosen.
const filteredStockItems = computed(() => {
    const text = stockSearch.value.trim().toLowerCase();
    if (!text) return [];
    return props.stockItems.filter(
        (item) =>
            item.available_quantity > 0 &&
            (item.product_name.toLowerCase().includes(text) ||
                item.brand.toLowerCase().includes(text)),
    );
});

// Account for quantities already included in this draft without changing saved stock.
function remaining(item: StockItem): number {
    const row = draftItems.value.find((row) => row.stockItem.id === item.id);
    return Math.max(0, item.available_quantity - (row?.quantity ?? 0));
}
function selectHousehold(household: Household): void {
    selectedHousehold.value = household;
}
function changeHousehold(): void {
    selectedHousehold.value = null;
    itemErrors.value = {};
}
// Add directly from a search row, with no separate selection step.
function addItem(item: StockItem): void {
    if (!selectedHousehold.value) return;
    const count = Number(itemQuantities.value[item.id] ?? 1);
    delete itemErrors.value[item.id];
    if (!Number.isSafeInteger(count) || count < 1) {
        itemErrors.value[item.id] = 'Enter a whole number greater than zero.';
        return;
    }
    if (count > remaining(item)) {
        itemErrors.value[item.id] =
            `You can add up to ${remaining(item)} more packages.`;
        return;
    }
    // Repeated additions increase the existing draft row.
    const existing = draftItems.value.find(
        (row) => row.stockItem.id === item.id,
    );
    if (existing) existing.quantity += count;
    else draftItems.value.push({ stockItem: item, quantity: count });
    itemQuantities.value[item.id] = 1;
}
function removeItem(id: number): void {
    draftItems.value = draftItems.value.filter(
        (row) => row.stockItem.id !== id,
    );
    delete itemErrors.value[id];
}

function saveDistribution(): void {
    // Prevent another submission while the current save is running.
    if (form.processing) return;

    // Check the current selections when Save is clicked.
    form.clearErrors();
    const household = selectedHousehold.value;

    if (household === null) {
        form.setError('household_id', 'Choose a household before saving.');
    }
    if (draftItems.value.length === 0) {
        form.setError('items', 'Add at least one food item before saving.');
    }

    // Stop here and show the missing selections before sending a request.
    if (household === null || draftItems.value.length === 0) return;

    // Copy the selected household ID into the submission.
    form.household_id = household.id;

    // Send only each stock-item ID and its requested quantity.
    form.items = draftItems.value.map((row) => ({
        stock_item_id: row.stockItem.id,
        quantity: row.quantity,
    }));

    // Submit the distribution and clear the screen only after success.
    form.post('/distribution', {
        preserveScroll: true,
        onSuccess: () => {
            // Clear the values prepared for submission.
            form.reset();

            // Start a fresh household selection and food list.
            selectedHousehold.value = null;
            draftItems.value = [];
            householdSearch.value = '';
            stockSearch.value = '';

            // Clear the quantity inputs and local item messages.
            itemQuantities.value = {};
            itemErrors.value = {};
        },
    });
}
</script>

<template>
    <Head title="Distribute food" />
    <main class="min-w-0 space-y-6 p-4">
        <header class="flex flex-wrap items-start justify-between gap-4">
            <div class="flex items-start gap-3">
                <div class="rounded-xl bg-accent p-3 text-accent-foreground">
                    <HandHeart class="size-5" aria-hidden="true" />
                </div>
                <div>
                    <h1 class="text-xl font-semibold">Distribute food</h1>
                    <p class="mt-1 max-w-xl text-sm text-muted-foreground">
                        Choose a household, prepare their food items, and review
                        the package quantities.
                    </p>
                </div>
            </div>
        </header>

        <!-- Stack on smaller screens; give the picker more room alongside the review on wide screens. -->
        <!-- Keep all distribution controls still while the save is running. -->
        <fieldset
            :disabled="form.processing"
            :aria-busy="form.processing"
            aria-label="Prepare and save a distribution"
            class="m-0 grid min-w-0 items-start gap-6 border-0 p-0 xl:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]"
        >
            <div class="min-w-0 space-y-6">
                <Card class="min-w-0">
                    <CardHeader>
                        <div class="flex items-center gap-2">
                            <Users
                                class="size-4 text-muted-foreground"
                                aria-hidden="true"
                            />
                            <CardTitle>1. Choose a household</CardTitle>
                            <Check
                                v-if="selectedHousehold"
                                class="ml-auto size-4 text-primary"
                                aria-hidden="true"
                            />
                        </div>
                        <CardDescription
                            >Confirm the household's appointment outside the app
                            before preparing their
                            distribution.</CardDescription
                        >
                    </CardHeader>
                    <CardContent>
                        <section
                            v-if="selectedHousehold"
                            aria-label="Selected household"
                            class="flex items-start justify-between gap-3 rounded-xl border border-primary/25 bg-accent/30 p-4"
                        >
                            <div class="flex min-w-0 items-start gap-3">
                                <div
                                    class="rounded-full bg-accent p-2 text-accent-foreground"
                                >
                                    <Users class="size-5" aria-hidden="true" />
                                </div>
                                <div class="min-w-0 wrap-anywhere">
                                    <p class="font-semibold">
                                        {{ householdName(selectedHousehold) }}
                                    </p>
                                    <p
                                        class="mt-1 text-sm text-muted-foreground"
                                    >
                                        Household #{{ selectedHousehold.id }}
                                    </p>
                                    <p
                                        v-if="draftItems.length"
                                        class="mt-2 text-xs text-muted-foreground"
                                    >
                                        Your draft items stay here if you change
                                        the household.
                                    </p>
                                </div>
                            </div>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                @click="changeHousehold"
                                >Change</Button
                            >
                        </section>
                        <div v-else class="space-y-4">
                            <div class="space-y-2">
                                <label
                                    for="household-search"
                                    class="text-sm font-medium"
                                    >Find a household</label
                                >
                                <div class="relative">
                                    <Search
                                        class="pointer-events-none absolute top-3 left-3 size-4 text-muted-foreground"
                                        aria-hidden="true"
                                    />
                                    <Input
                                        id="household-search"
                                        v-model="householdSearch"
                                        type="search"
                                        class="pl-9"
                                        placeholder="Search by name or household number"
                                    />
                                </div>
                            </div>
                            <p
                                aria-live="polite"
                                class="text-xs text-muted-foreground"
                            >
                                {{
                                    householdSearch.trim()
                                        ? `${filteredHouseholds.length} matching households`
                                        : 'Start typing a name or household number to see matches.'
                                }}
                            </p>
                            <!-- Keep a long household list inside its card rather than stretching the page. -->
                            <div
                                v-if="filteredHouseholds.length"
                                class="max-h-64 overflow-y-auto rounded-xl border"
                            >
                                <ul class="divide-y divide-border">
                                    <li
                                        v-for="household in filteredHouseholds"
                                        :key="household.id"
                                        class="flex items-center justify-between gap-3 px-4 py-3"
                                    >
                                        <div class="min-w-0 wrap-anywhere">
                                            <p class="text-sm font-medium">
                                                {{ householdName(household) }}
                                            </p>
                                            <p
                                                class="mt-1 text-xs text-muted-foreground"
                                            >
                                                Household #{{ household.id }}
                                            </p>
                                        </div>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            :aria-label="`Select ${householdName(household)}, household ${household.id}`"
                                            @click="selectHousehold(household)"
                                            >Select</Button
                                        >
                                    </li>
                                </ul>
                            </div>
                            <div
                                v-else-if="householdSearch.trim()"
                                class="rounded-xl border border-dashed p-6 text-center"
                            >
                                <p class="text-sm font-medium">
                                    {{
                                        householdSearch.trim()
                                            ? 'No households match your search'
                                            : 'No households available'
                                    }}
                                </p>
                                <p class="mt-1 text-sm text-muted-foreground">
                                    {{
                                        householdSearch.trim()
                                            ? 'Try another name or household number.'
                                            : 'Household choices will appear here when they are available.'
                                    }}
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card class="min-w-0">
                    <CardHeader>
                        <div class="flex items-center gap-2">
                            <Package
                                class="size-4 text-muted-foreground"
                                aria-hidden="true"
                            /><CardTitle>2. Add food items</CardTitle>
                        </div>
                        <CardDescription
                            >Enter a whole-package quantity beside an available
                            item, then add it to the
                            distribution.</CardDescription
                        >
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div
                            v-if="!selectedHousehold"
                            class="rounded-xl border border-dashed bg-muted/20 px-6 py-8 text-center"
                        >
                            <Package
                                class="mx-auto mb-3 size-7 text-muted-foreground"
                                aria-hidden="true"
                            />
                            <p class="text-sm font-medium">
                                Choose a household first
                            </p>
                            <p class="mt-1 text-sm text-muted-foreground">
                                Available food choices will appear here.
                            </p>
                        </div>
                        <template v-else>
                            <div class="space-y-2">
                                <label
                                    for="stock-search"
                                    class="text-sm font-medium"
                                    >Find a stock item</label
                                >
                                <div class="relative">
                                    <Search
                                        class="pointer-events-none absolute top-3 left-3 size-4 text-muted-foreground"
                                        aria-hidden="true"
                                    />
                                    <Input
                                        id="stock-search"
                                        v-model="stockSearch"
                                        type="search"
                                        class="pl-9"
                                        placeholder="Search by product name or brand"
                                    />
                                </div>
                            </div>
                            <p
                                aria-live="polite"
                                class="text-xs text-muted-foreground"
                            >
                                {{
                                    stockSearch.trim()
                                        ? `${filteredStockItems.length} choices · Only items with stock available`
                                        : 'Start typing a product name or brand to see available stock.'
                                }}
                            </p>
                            <!-- Horizontal scrolling belongs to the table wrapper, never the whole page. -->
                            <div
                                v-if="filteredStockItems.length"
                                class="max-h-80 overflow-auto rounded-xl border"
                            >
                                <table
                                    class="w-full min-w-[640px] text-left text-sm"
                                >
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
                                                Packages to give
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="item in filteredStockItems"
                                            :key="item.id"
                                        >
                                            <td
                                                class="max-w-56 px-4 py-3 wrap-anywhere"
                                            >
                                                <p class="font-medium">
                                                    {{ item.product_name }}
                                                </p>
                                                <p
                                                    class="mt-0.5 text-muted-foreground"
                                                >
                                                    {{ item.brand }}
                                                </p>
                                                <p
                                                    class="mt-1 text-xs text-muted-foreground"
                                                >
                                                    {{ item.product_path }}
                                                </p>
                                            </td>
                                            <td
                                                class="max-w-40 px-4 py-3 wrap-anywhere"
                                            >
                                                {{ item.package_label }}
                                            </td>
                                            <td
                                                class="px-4 py-3 text-right tabular-nums"
                                            >
                                                {{ item.available_quantity }}
                                                <p
                                                    v-if="
                                                        remaining(item) !==
                                                        item.available_quantity
                                                    "
                                                    class="mt-1 text-xs text-muted-foreground"
                                                >
                                                    {{ remaining(item) }} left
                                                    to add
                                                </p>
                                            </td>
                                            <td class="px-4 py-3">
                                                <!-- Enter the package count and add this item from the same row. -->
                                                <form
                                                    class="flex items-center gap-2"
                                                    novalidate
                                                    @submit.prevent="
                                                        addItem(item)
                                                    "
                                                >
                                                    <label
                                                        :for="`stock-quantity-${item.id}`"
                                                        class="sr-only"
                                                        >Packages for
                                                        {{ item.product_name }},
                                                        {{ item.brand }},
                                                        {{
                                                            item.package_label
                                                        }}</label
                                                    >
                                                    <Input
                                                        :id="`stock-quantity-${item.id}`"
                                                        :model-value="
                                                            itemQuantities[
                                                                item.id
                                                            ] ?? 1
                                                        "
                                                        type="number"
                                                        min="1"
                                                        :max="remaining(item)"
                                                        step="1"
                                                        class="w-20 shrink-0"
                                                        :disabled="
                                                            remaining(item) ===
                                                            0
                                                        "
                                                        :aria-invalid="
                                                            Boolean(
                                                                itemErrors[
                                                                    item.id
                                                                ],
                                                            )
                                                        "
                                                        :aria-describedby="
                                                            itemErrors[item.id]
                                                                ? `stock-error-${item.id}`
                                                                : undefined
                                                        "
                                                        @update:model-value="
                                                            itemQuantities[
                                                                item.id
                                                            ] = $event;
                                                            delete itemErrors[
                                                                item.id
                                                            ];
                                                        "
                                                    />
                                                    <Button
                                                        type="submit"
                                                        size="sm"
                                                        variant="outline"
                                                        :disabled="
                                                            remaining(item) ===
                                                            0
                                                        "
                                                        :aria-label="`Add ${item.product_name}, ${item.brand}, ${item.product_path}, ${item.package_label}`"
                                                        ><Plus
                                                            v-if="
                                                                remaining(
                                                                    item,
                                                                ) > 0
                                                            "
                                                            class="size-4"
                                                            aria-hidden="true"
                                                        />{{
                                                            remaining(item) ===
                                                            0
                                                                ? 'In draft'
                                                                : 'Add'
                                                        }}</Button
                                                    >
                                                </form>
                                                <p
                                                    v-if="itemErrors[item.id]"
                                                    :id="`stock-error-${item.id}`"
                                                    role="alert"
                                                    class="mt-2 max-w-48 text-xs text-destructive"
                                                >
                                                    {{ itemErrors[item.id] }}
                                                </p>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div
                                v-else-if="stockSearch.trim()"
                                class="rounded-xl border border-dashed p-6 text-center"
                            >
                                <p class="text-sm font-medium">
                                    {{
                                        stockSearch.trim()
                                            ? 'No stock items match your search'
                                            : 'No stock available'
                                    }}
                                </p>
                                <p class="mt-1 text-sm text-muted-foreground">
                                    {{
                                        stockSearch.trim()
                                            ? 'Try another product name or brand.'
                                            : 'Items will appear here after stock has been received.'
                                    }}
                                </p>
                            </div>
                        </template>
                    </CardContent>
                </Card>
            </div>

            <!-- Keep the review beside the choices on wide screens; flow normally on phones. -->
            <Card class="min-w-0 xl:sticky xl:top-6">
                <CardHeader>
                    <div class="flex items-center gap-2">
                        <ClipboardList
                            class="size-4 text-muted-foreground"
                            aria-hidden="true"
                        /><CardTitle>3. Review distribution</CardTitle>
                    </div>
                    <CardDescription
                        >Check the household and package quantities before
                        recording the distribution.</CardDescription
                    >
                </CardHeader>
                <CardContent class="space-y-5">
                    <div class="rounded-xl border bg-muted/20 p-4">
                        <p class="text-xs font-medium text-muted-foreground">
                            HOUSEHOLD
                        </p>
                        <template v-if="selectedHousehold"
                            ><p class="mt-2 font-semibold wrap-anywhere">
                                {{ householdName(selectedHousehold) }}
                            </p>
                            <p class="mt-1 text-xs text-muted-foreground">
                                Household #{{ selectedHousehold.id }}
                            </p></template
                        >
                        <p v-else class="mt-2 text-sm text-muted-foreground">
                            No household selected
                        </p>
                    </div>
                    <div
                        v-if="!draftItems.length"
                        class="rounded-xl border border-dashed px-5 py-10 text-center"
                    >
                        <ClipboardList
                            class="mx-auto mb-3 size-8 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <p class="text-sm font-medium">
                            Your distribution is empty
                        </p>
                        <p
                            class="mx-auto mt-1 max-w-60 text-sm text-muted-foreground"
                        >
                            Add food items to see the household's distribution
                            here.
                        </p>
                    </div>
                    <!-- Compact rows keep identity, quantity and removal alongside each other. -->
                    <div v-else class="min-w-0">
                        <div
                            aria-hidden="true"
                            class="grid grid-cols-[minmax(0,1fr)_4.5rem_2rem] items-center gap-2 border-b pb-2 text-xs text-muted-foreground"
                        >
                            <span>Item</span>
                            <span class="text-center">Packages</span>
                            <span></span>
                        </div>
                        <ul class="divide-y divide-border">
                            <li
                                v-for="row in draftItems"
                                :key="row.stockItem.id"
                                class="grid grid-cols-[minmax(0,1fr)_4.5rem_2rem] items-start gap-x-2 gap-y-1 py-3"
                            >
                                <div class="min-w-0 wrap-anywhere">
                                    <!-- Share a line when space allows; wrap details on narrow screens. -->
                                    <div
                                        class="flex flex-wrap items-baseline gap-x-3 gap-y-0.5"
                                    >
                                        <p class="min-w-0 text-sm font-medium">
                                            {{ row.stockItem.product_name }}
                                        </p>
                                        <p
                                            class="min-w-0 text-xs text-muted-foreground"
                                        >
                                            {{ row.stockItem.brand }}
                                        </p>
                                        <p
                                            class="min-w-0 text-xs text-muted-foreground"
                                        >
                                            {{ row.stockItem.package_label }}
                                        </p>
                                    </div>
                                    <p
                                        class="mt-0.5 text-xs text-muted-foreground"
                                    >
                                        {{ row.stockItem.product_path }}
                                    </p>
                                </div>
                                <div class="min-w-0 text-center">
                                    <p
                                        class="py-1 text-sm font-medium tabular-nums"
                                    >
                                        {{ row.quantity
                                        }}<span class="sr-only"> packages</span>
                                    </p>
                                </div>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    class="size-8 text-muted-foreground hover:text-destructive"
                                    :aria-label="`Remove ${row.stockItem.product_name}, ${row.stockItem.brand}, ${row.stockItem.package_label}`"
                                    @click="removeItem(row.stockItem.id)"
                                >
                                    <Trash2 class="size-4" aria-hidden="true" />
                                </Button>
                            </li>
                        </ul>
                    </div>
                    <div class="space-y-3 border-t pt-4">
                        <!-- Show missing selections and any errors returned by Laravel. -->
                        <ul
                            v-if="Object.keys(form.errors).length > 0"
                            role="alert"
                            class="space-y-1 text-sm text-destructive"
                        >
                            <li
                                v-for="(message, field) in form.errors"
                                :key="field"
                            >
                                {{ message }}
                            </li>
                        </ul>
                        <!-- Submit when clicked; prevent another click while saving. -->
                        <Button
                            type="button"
                            class="w-full"
                            :disabled="form.processing"
                            @click="saveDistribution"
                        >
                            <HandHeart class="size-4" aria-hidden="true" />
                            {{
                                form.processing
                                    ? 'Saving...'
                                    : 'Save distribution'
                            }}
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </fieldset>
    </main>
</template>
