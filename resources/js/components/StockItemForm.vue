<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { ChevronDown } from '@lucide/vue';
import {
    ComboboxAnchor,
    ComboboxContent,
    ComboboxEmpty,
    ComboboxInput,
    ComboboxItem,
    ComboboxPortal,
    ComboboxRoot,
    ComboboxTrigger,
    ComboboxViewport,
} from 'reka-ui';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

// Laravel will supply existing Products and complete package labels.
const props = withDefaults(
    defineProps<{
        products?: { id: number; label: string }[];
        packageSizes?: { id: number; label: string }[];
        stockItem?: {
            id: number;
            product_id: number;
            package_size_id: number;
            brand: string | null;
        };
        identityLocked?: boolean;
    }>(),
    {
        products: () => [],
        packageSizes: () => [],
        identityLocked: false,
    },
);

// Keep unsaved selections in the browser; brand_key is prepared by Laravel later.
const form = useForm({
    product_id: String(props.stockItem?.product_id ?? ''),
    brand: props.stockItem?.brand ?? '',
    package_size_id: String(props.stockItem?.package_size_id ?? ''),
});

function submit() {
    if (form.processing) return;
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            if (!props.stockItem) form.reset();
        },
    };
    if (props.stockItem)
        form.patch(`/stock-items/${props.stockItem.id}`, options);
    else form.post('/stock-items', options);
}
</script>

<template>
    <form @submit.prevent="submit" class="grid gap-6">
        <p v-if="identityLocked" class="text-sm text-muted-foreground">
            This item has stock history. Its Product and package size are fixed;
            brand spelling may be corrected.
        </p>
        <div class="grid min-w-0 gap-2">
            <Label for="product_id">Product</Label>
            <!-- Search labels, but keep only the selected Product ID in the form. -->
            <ComboboxRoot
                v-if="!stockItem"
                v-model="form.product_id"
                name="product_id"
                :disabled="!products.length || form.processing"
                open-on-focus
                open-on-click
                required
                class="min-w-0"
            >
                <ComboboxAnchor class="relative w-full">
                    <ComboboxInput
                        id="product_id"
                        class="h-9 w-full min-w-0 rounded-md border border-input bg-transparent py-1 pr-9 pl-3 text-base shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50 aria-invalid:border-destructive aria-invalid:ring-destructive/20 md:text-sm dark:bg-input/30"
                        :disabled="!products.length || form.processing"
                        :placeholder="
                            products.length
                                ? 'Search products…'
                                : 'No products available'
                        "
                        :aria-invalid="!!form.errors.product_id"
                        aria-describedby="product-error"
                        :display-value="
                            (value) =>
                                products.find(
                                    (product) => String(product.id) === value,
                                )?.label ?? ''
                        "
                    />
                    <ComboboxTrigger
                        type="button"
                        aria-label="Show products"
                        class="absolute inset-y-0 right-0 flex w-9 items-center justify-center rounded-r-md focus-visible:outline-ring disabled:opacity-50"
                    >
                        <ChevronDown class="size-4" />
                    </ComboboxTrigger>
                </ComboboxAnchor>
                <ComboboxPortal>
                    <ComboboxContent
                        position="popper"
                        :side-offset="4"
                        class="z-50 w-(--reka-combobox-trigger-width) max-w-[calc(100vw-2rem)] rounded-md border bg-popover text-popover-foreground shadow-md"
                    >
                        <ComboboxViewport class="max-h-60 overflow-y-auto p-1">
                            <ComboboxEmpty
                                class="p-3 text-sm text-muted-foreground"
                            >
                                No products found.
                            </ComboboxEmpty>
                            <ComboboxItem
                                v-for="product in products"
                                :key="product.id"
                                :value="String(product.id)"
                                :text-value="product.label"
                                class="cursor-default rounded-sm px-2 py-2 text-sm break-words data-[highlighted]:bg-accent data-[highlighted]:text-accent-foreground data-[state=checked]:font-medium"
                            >
                                {{ product.label }}
                            </ComboboxItem>
                        </ComboboxViewport>
                    </ComboboxContent>
                </ComboboxPortal>
            </ComboboxRoot>
            <Select
                v-else
                v-model="form.product_id"
                name="product_id"
                :disabled="
                    identityLocked || !products.length || form.processing
                "
                required
            >
                <SelectTrigger
                    id="product_id"
                    class="w-full"
                    :aria-invalid="!!form.errors.product_id"
                    aria-describedby="product-error"
                >
                    <SelectValue
                        :placeholder="
                            products.length
                                ? 'Select a product'
                                : 'No products available'
                        "
                    />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem
                        v-for="product in products"
                        :key="product.id"
                        :value="String(product.id)"
                        >{{ product.label }}</SelectItem
                    >
                </SelectContent>
            </Select>
            <InputError id="product-error" :message="form.errors.product_id" />
        </div>

        <!-- Align field groups at the top so Brand help text does not stretch Package size. -->
        <div class="grid items-start gap-4 sm:grid-cols-2">
            <div class="grid min-w-0 gap-2">
                <Label for="brand">Brand (optional)</Label>
                <Input
                    id="brand"
                    v-model="form.brand"
                    name="brand"
                    maxlength="255"
                    :disabled="form.processing"
                    placeholder="For example, Tilda"
                    :aria-invalid="!!form.errors.brand"
                    aria-describedby="brand-help brand-error"
                />
                <p id="brand-help" class="text-sm text-muted-foreground">
                    Leave empty for an unbranded item.
                </p>
                <InputError id="brand-error" :message="form.errors.brand" />
            </div>

            <div class="grid min-w-0 gap-2">
                <Label for="package_size_id">Package size</Label>
                <Select
                    v-model="form.package_size_id"
                    name="package_size_id"
                    :disabled="
                        identityLocked ||
                        !packageSizes.length ||
                        form.processing
                    "
                    required
                >
                    <SelectTrigger
                        id="package_size_id"
                        class="w-full"
                        :aria-invalid="!!form.errors.package_size_id"
                        aria-describedby="package-size-error"
                    >
                        <SelectValue
                            :placeholder="
                                packageSizes.length
                                    ? 'Select a package size'
                                    : 'No package sizes available'
                            "
                        />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="packageSize in packageSizes"
                            :key="packageSize.id"
                            :value="String(packageSize.id)"
                            >{{ packageSize.label }}</SelectItem
                        >
                    </SelectContent>
                </Select>
                <InputError
                    id="package-size-error"
                    :message="form.errors.package_size_id"
                />
            </div>
        </div>

        <div class="flex flex-col gap-2 sm:flex-row sm:justify-end">
            <Button as-child variant="outline"
                ><Link href="/stock-items">Cancel</Link></Button
            >
            <!-- Prevent repeated clicks while the server checks and saves the choices. -->
            <Button
                type="submit"
                :disabled="
                    form.processing || !products.length || !packageSizes.length
                "
                class="w-full sm:w-auto"
            >
                {{
                    form.processing
                        ? 'Saving…'
                        : stockItem
                          ? 'Save changes'
                          : 'Create stock item'
                }}
            </Button>
        </div>
    </form>
</template>
