<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

// Receive the saved departments available as category parents.
defineProps<{
    departments: { id: number; name: string }[];
}>();

// Keep the selected department and typed name in the browser until submission.
const form = useForm({
    department_id: '',
    name: '',
});

function submit() {
    // Send both fields to Laravel and reset after a successful save.
    form.post('/categories', {
        onSuccess: () => form.reset(),
    });
}
</script>

<template>
    <Head title="Create Category" />

    <!-- Match Department Create's outer space and card width. -->
    <main class="p-4">
        <Card class="w-full max-w-3xl">
            <CardHeader>
                <CardTitle>Create Category</CardTitle>
            </CardHeader>

            <CardContent>
                <div class="mt-4 rounded-xl border border-border p-4">
                    <!-- Stack on phones; place fields and button together at sm. -->
                    <form
                        @submit.prevent="submit"
                        class="flex flex-col gap-4 sm:flex-row sm:items-start"
                    >
                        <!-- Share the available row width between both fields. -->
                        <div class="flex min-w-0 flex-1 flex-col gap-2">
                            <Label for="department_id">Department</Label>
                            <Select v-model="form.department_id">
                                <SelectTrigger
                                    id="department_id"
                                    class="w-full"
                                >
                                    <SelectValue
                                        placeholder="Select a department"
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="department in departments"
                                        :key="department.id"
                                        :value="String(department.id)"
                                    >
                                        {{ department.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="form.errors.department_id" />
                        </div>

                        <div class="flex min-w-0 flex-1 flex-col gap-2">
                            <Label for="name">Category name</Label>
                            <Input
                                id="name"
                                name="name"
                                type="text"
                                v-model="form.name"
                                maxlength="255"
                            />
                            <InputError :message="form.errors.name" />
                        </div>

                        <!-- Match Department Create's input-aligned button. -->
                        <div class="sm:pt-6">
                            <Button
                                type="submit"
                                class="w-full sm:w-auto"
                                :disabled="form.processing"
                            >
                                {{
                                    form.processing
                                        ? 'Saving...'
                                        : 'Create category'
                                }}
                            </Button>
                        </div>
                    </form>
                </div>
            </CardContent>
        </Card>
    </main>
</template>
