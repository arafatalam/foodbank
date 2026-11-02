<script setup lang="ts">
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Head, useForm } from '@inertiajs/vue3';

// --- Data shapes and incoming page data ---

// Describe the stock-item details Laravel sends to this page.
type StockItem = {
    id: number;
    product_name: string;
    product_path: string;
    brand: string;
    package_label: string;
};

// Each receipt row remembers the stock item's identity and quantity.
type ReceiptItem = {
    id: number;
    product_name: string;
    product_path: string;
    brand: string;
    package_label: string;
    quantity: number;
};

// Describe one item sent to Laravel when saving.
type SubmittedItem = {
    stock_item_id: number;
    quantity: number;
};

// Describe the fields our receipt form contains.
type ReceiptForm = {
    notes: string;
    items: SubmittedItem[];
};

// Declare the data this page receives from Laravel.
const props = defineProps<{
    stockItems: StockItem[];
}>();

// --- Remembered values ---

// Search

// Remember the search term.
const search = ref('');

// --- Selection and package quantity ---

// Remember the selected item's label.
const selectedLabel = ref('');

// Remember which stock item was selected; null means none yet.
const selectedId = ref<number | null>(null);

// Remember how many packages arrived.
const quantity = ref(1);

// Remember a message explaining an invalid quantity.
const quantityError = ref('');

// --- Receipt rows and note ---

// Start with an empty list of receipt rows.
const receiptItems = ref<ReceiptItem[]>([]);

// Create the form with an empty note and no items.
const form = useForm<ReceiptForm>({
    notes: '',
    items: [],
});

// --- Calculated search results ---

// Find stock items by product name or brand.
const filteredStockItems = computed(() => {
    // Ignore surrounding spaces and differences in capital letters.
    const searchString = search.value.trim().toLowerCase();

    // Keep the results empty until someone types.
    if (searchString === '') {
        return [];
    }

    return props.stockItems.filter((item) => {
        // Prepare both fields for comparison.
        const productName = item.product_name.toLowerCase();
        const brand = item.brand.toLowerCase();

        // Keep the item when either field matches.
        return (
            productName.includes(searchString) || brand.includes(searchString)
        );
    });
});

// --- User actions ---

// Receive a stock item with the fields described by our StockItem type.
function selectItem(item: StockItem): void {
    selectedId.value = item.id;
    // Include the category path so the selected product is clear.
    selectedLabel.value = `${item.product_name} (${item.product_path}) -- ${item.brand} -- ${item.package_label}`;

    // Start the new selection with one package.
    quantity.value = 1;

    // Clear any error from the previous selection.
    quantityError.value = '';
}

// Add the selected label and quantity as one receipt row.
function addItem(): void {
    // Stop if no stock item has been selected.
    if (selectedId.value === null) {
        return;
    }

    // Read the entered quantity as a number.
    const packages = Number(quantity.value);

    // Clear the previous message before checking this attempt.
    quantityError.value = '';

    // Stop unless the quantity is a positive whole number.
    if (!Number.isInteger(packages) || packages < 1) {
        quantityError.value =
            'Enter a whole number of packages greater than zero.';
        return;
    }

    // Look at each row already in the receipt.
    for (const item of receiptItems.value) {
        // If this row is the selected stock item, increase its quantity.
        if (item.id === selectedId.value) {
            // Increase the existing row by the checked number of packages.
            item.quantity = item.quantity + packages;

            // Stop so we don't add another row for the same item.
            return;
        }
    }

    // Find the stock item that matches the selected ID.
    const selectedItem = props.stockItems.find(
        (item) => item.id === selectedId.value,
    );

    // Stop if no matching stock item exists.
    if (!selectedItem) {
        return;
    }

    // Add the selected item's details and checked quantity to the receipt.
    receiptItems.value.push({
        id: selectedItem.id,
        product_name: selectedItem.product_name,
        product_path: selectedItem.product_path,
        brand: selectedItem.brand,
        package_label: selectedItem.package_label,
        quantity: packages,
    });

}

function submit(): void {

    // Ignore another attempt while the current request is running.
    if (form.processing) {
        return;
    }

    // Build the list Laravel expects from the visible receipt rows.
    form.items = receiptItems.value.map((item) => {
        return {
            stock_item_id: item.id,
            quantity: item.quantity,
        };
    });
    // Send the receipt items and note to Laravel.
    form.post('/receive', {
        onSuccess: () => {
            // Clear the submitted note and items.
            form.reset();

            // Clear the visible receipt and current selection.
            receiptItems.value = [];
            selectedId.value = null;
            selectedLabel.value = '';
            quantity.value = 1;
            quantityError.value = '';
            search.value = '';
        },
    });
}

</script>

<template>
    <!-- Set the browser tab title. -->
    <Head title="Receive stock" />

    <!-- Stack the cards on phones; align them side by side on wider screens. -->
    <main class="grid items-start gap-6 p-4 md:grid-cols-2">
        <Card class="min-w-0">
            <CardHeader>
                <CardTitle>Receive stock</CardTitle>
            </CardHeader>

            <CardContent>
                <!-- Stack the label above its input, with a small gap. -->
                <div class="flex flex-col gap-2">
                    <label for="stock-search">Find a stock item</label>

                    <!-- Keep the search input connected to the search term. -->
                    <Input
                        id="stock-search"
                        type="search"
                        v-model="search"
                        placeholder="Search by product name or brand"
                    />
                    <!-- Show the table when the search has matching results. -->
                    <div
                        v-if="filteredStockItems.length > 0"
                        class="max-h-64 overflow-auto rounded-xl border border-border"
                    >
                        <table class="w-full text-left">
                            <!-- Keep the header sticky. -->
                            <thead class="sticky top-0 z-10">
                                <tr>
                                    <th scope="col" class="px-4 py-2">Product</th>
                                    <th scope="col" class="px-4 py-2">Brand</th>
                                    <th scope="col" class="px-4 py-2">Package size</th>
                                    <th scope="col" class="px-4 py-2">Action</th>
                                </tr>
                            </thead>

                            <!-- Matching stock-item rows will go here next. -->
                            <tbody>
                                <!-- Create one row for each matching stock item. -->
                                <tr v-for="item in filteredStockItems" :key="item.id">
                                    <td class="px-4 py-2">
                                        <p>{{ item.product_name }}</p>
                                        <!-- Show the department/category below the product name. -->
                                        <p class="text-sm text-muted-foreground">
                                            {{ item.product_path }}
                                        </p>
                                    </td>
                                    <td class="px-4 py-2">
                                        {{ item.brand }}
                                    </td>
                                    <td class="px-4 py-2">
                                        {{ item.package_label }}
                                    </td>
                                    <td class="px-4 py-2">
                                        <!-- Select this row's stock item for entering a quantity. -->
                                        <Button type="button" size="sm" @click="selectItem(item)">
                                            Add
                                        </Button>
                                    </td>

                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Show this message only after someone enters a search with no matches. -->
                    <p
                        v-if="
                            search.trim() !== '' &&
                            filteredStockItems.length === 0
                        "
                    >
                        No stock items match your search.
                    </p>
                    <!-- Show the selection only after someone chooses an item. -->
                    <p v-if="selectedLabel" class="font-semibold">
                        Selected: {{ selectedLabel }}
                    </p>
                    <!-- Stack the quantity controls and limit the group's width. -->
                    <div
                        v-if="selectedLabel"
                        class="flex max-w-xs flex-col gap-2"
                    >
                        <label for="quantity">Packages received</label>

                        <!-- Keep the input connected to our quantity value. -->
                        <Input
                            id="quantity"
                            type="number"
                            v-model="quantity"
                            min="1"
                            step="1"
                        />
                        <!-- Show the explanation only when there is an error message. -->
                        <p v-if="quantityError" role="alert">
                            {{ quantityError }}
                        </p>
                        
                        <Button type="button" class="self-end" @click="addItem">
                            Add item
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Keep the receipt in its own card as its contents grow. -->
        <Card class="min-w-0">
            <CardHeader>
                <CardTitle>Items in this receipt</CardTitle>
            </CardHeader>

            <CardContent>
                <!-- Show a message while the receipt list is empty. -->
                <p v-if="receiptItems.length === 0">No items added yet.</p>

                <!-- Give the header and item rows the same two-column layout. -->
                <div v-else>
                    <div class="overflow-x-auto rounded-xl border border-border">
                        <table class="w-full text-left">
                            <thead class="text-center">
                                <!-- Match the search table, with Quantity as the final column. -->
                                <tr>
                                    <th scope="col" class="px-4 py-2">Product</th>
                                    <th scope="col" class="px-4 py-2">Brand</th>
                                    <th scope="col" class="px-4 py-2">Package size</th>
                                    <th scope="col" class="px-4 py-2">Quantity</th>
                                </tr>
                            </thead>


                            <!-- Receipt rows will go here next. -->
                            <tbody class="text-center">
                                <!-- Create one row for each receipt item. -->
                                <tr v-for="item in receiptItems" :key="item.id">
                                    <td class="px-4 py-2">
                                        <p>{{ item.product_name }}</p>

                                        <!-- Keep the category path beneath the product name. -->
                                        <p class="text-sm text-muted-foreground">
                                            {{ item.product_path }}
                                        </p>
                                    </td>

                                    <!-- Keep these cells in the same order as the headings. -->
                                    <td class="px-4 py-2">{{ item.brand }}</td>
                                    <td class="px-4 py-2">{{ item.package_label }}</td>
                                    <td class="px-4 py-2">{{ item.quantity }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <!-- Keep the receipt note below the item list. -->
                <div class="mt-4 flex flex-col gap-2">
                    <label for="receipt-note">Receipt note (optional)</label>

                    <!-- Keep the typed note in temporary browser state. -->
                    <textarea
                        id="receipt-note"
                        v-model="form.notes"
                        rows="3"
                        class="w-full rounded-md border p-3"
                    ></textarea>
                </div>

                <!-- Show server errors when the receipt cannot be saved. -->
                <ul
                    v-if="Object.keys(form.errors).length > 0"
                    class="mt-4"
                    role="alert"
                >
                    <li v-for="(message, field) in form.errors" :key="field">
                        {{ message }}
                    </li>
                </ul>

                <!-- Add space above the row and align its button to the right. -->
                <div class="mt-4 flex justify-end">
                    <Button
                        type="button"
                        :disabled="form.processing || receiptItems.length === 0"
                        @click="submit"
                    >
                        {{ form.processing ? 'Saving...' : 'Save receipt' }}
                    </Button>
                </div>

            </CardContent>
        </Card>
    </main>
</template>
