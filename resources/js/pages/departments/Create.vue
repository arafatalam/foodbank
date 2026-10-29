<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

// Keep the typed name and submission state in this form.
const form = useForm({
    name: '',
});

function submit() {
    // Send the name to Laravel and clear it after a successful save.
    form.post('/departments', {
        onSuccess: () => form.reset(),
    });
}
</script>

<template>
    <Head title="Create Department" />

    <!-- Leave space around the card on every screen size. -->
    <main class="p-4">
        <!-- Fill the available width, with the same limit as other forms. -->
        <Card class="w-full max-w-3xl">
            <CardHeader>
                <CardTitle>Create Department</CardTitle>
            </CardHeader>

            <CardContent>
                <!-- Match the bordered form area used by other screens. -->
                <div class="mt-4 rounded-xl border border-border p-4">
                    <form
                        @submit.prevent="submit"
                        class="flex flex-col gap-4 sm:flex-row sm:items-start"
                    >
                        <!-- Let the field use the space left beside the button. -->
                        <div class="flex min-w-0 flex-1 flex-col gap-2">
                            <Label for="name">Department name</Label>

                            <Input
                                id="name"
                                name="name"
                                type="text"
                                v-model="form.name"
                                maxlength="255"
                            />

                            <InputError :message="form.errors.name" />
                        </div>

                        <!-- Align the button with the input, below its label. -->
                        <div class="sm:pt-6">
                            <Button
                                type="submit"
                                class="w-full sm:w-auto"
                                :disabled="form.processing"
                            >
                                {{
                                    form.processing
                                        ? 'Saving...'
                                        : 'Create department'
                                }}
                            </Button>
                        </div>
                    </form>
                </div>
            </CardContent>
        </Card>
    </main>
</template>
