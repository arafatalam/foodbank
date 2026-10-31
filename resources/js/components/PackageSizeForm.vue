<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { packageTypes, contentsUnits } from '@/lib/package-sizes';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

// Both pages use the same fields; Edit supplies the existing definition.
const props = defineProps<{
    packageSize?: {
        id: number;
        package_type: string;
        contents: string;
        contents_unit: string;
    };
}>();

// Keep unsaved input separate from the saved definition.
const form = useForm({
    package_type: props.packageSize?.package_type ?? '',
    contents: props.packageSize?.contents ?? '',
    contents_unit: props.packageSize?.contents_unit ?? '',
});

function submit() {
    // Ignore repeated submissions while the current request is being saved.
    if (form.processing) return;

    // Creation resets after success; editing returns to the list through Laravel.
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            if (!props.packageSize) form.reset();
        },
    };
    if (props.packageSize) {
        form.patch(`/package-sizes/${props.packageSize.id}`, options);
    } else {
        form.post('/package-sizes', options);
    }
}
</script>

<template>
    <form @submit.prevent="submit" class="flex flex-col gap-4">
        <!-- Stack fields on phones; share three columns on wider screens. -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div class="flex min-w-0 flex-col gap-2">
                <Label for="package_type">Package type</Label>
                <Select
                    v-model="form.package_type"
                    name="package_type"
                    required
                >
                    <SelectTrigger
                        id="package_type"
                        class="w-full"
                        :aria-invalid="!!form.errors.package_type"
                        aria-describedby="package-type-error"
                    >
                        <SelectValue placeholder="Select a type" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="type in packageTypes"
                            :key="type.value"
                            :value="type.value"
                        >
                            {{ type.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <InputError
                    id="package-type-error"
                    :message="form.errors.package_type"
                />
            </div>

            <div class="flex min-w-0 flex-col gap-2">
                <Label for="contents">Contents per package</Label>
                <Input
                    id="contents"
                    v-model="form.contents"
                    name="contents"
                    type="number"
                    :min="form.contents_unit === 'item' ? 1 : 0.001"
                    max="999999999.999"
                    :step="form.contents_unit === 'item' ? 1 : 0.001"
                    required
                    :aria-invalid="!!form.errors.contents"
                    aria-describedby="contents-error"
                />
                <InputError
                    id="contents-error"
                    :message="form.errors.contents"
                />
            </div>

            <div class="flex min-w-0 flex-col gap-2">
                <Label for="contents_unit">Contents unit</Label>
                <Select
                    v-model="form.contents_unit"
                    name="contents_unit"
                    required
                >
                    <SelectTrigger
                        id="contents_unit"
                        class="w-full"
                        :aria-invalid="!!form.errors.contents_unit"
                        aria-describedby="contents-unit-error"
                    >
                        <SelectValue placeholder="Select a unit" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="unit in contentsUnits"
                            :key="unit.value"
                            :value="unit.value"
                        >
                            {{ unit.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <InputError
                    id="contents-unit-error"
                    :message="form.errors.contents_unit"
                />
            </div>
        </div>

        <div class="flex flex-col gap-2 sm:flex-row sm:justify-end">
            <Button variant="outline" as-child
                ><Link href="/package-sizes">Cancel</Link></Button
            >
            <!-- Prevent duplicate clicks while Laravel handles the save. -->
            <Button
                type="submit"
                class="w-full sm:w-auto"
                :disabled="form.processing"
            >
                {{
                    form.processing
                        ? 'Saving...'
                        : packageSize
                          ? 'Save changes'
                          : 'Create package size'
                }}
            </Button>
        </div>
    </form>
</template>
