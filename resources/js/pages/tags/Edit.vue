<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

// Shared Inertia data should not be copied onto HTML elements.
defineOptions({ inheritAttrs: false });

// Receive the saved tag from the controller.
const props = defineProps<{
    tag: { id: number; name: string };
}>();

// Keep unsaved changes separate from the saved tag details.
const form = useForm({
    name: props.tag.name,
});

function submit() {
    // Update this existing tag through its protected PATCH route.
    form.patch(`/tags/${props.tag.id}`);
}
</script>

<template>
    <Head title="Edit Tag" />

    <!-- Use the same outer spacing and card width as Edit User. -->
    <main class="p-4">
        <Card class="w-full max-w-xl">
            <CardHeader>
                <CardTitle>Edit Tag</CardTitle>
            </CardHeader>

            <CardContent>
                <!-- Match Edit User's bordered form and stacked layout. -->
                <div
                    class="mt-4 overflow-hidden rounded-xl border border-border p-4"
                >
                    <form @submit.prevent="submit" class="flex flex-col gap-4">
                        <!-- Use one full-width column on every screen size. -->
                        <div class="grid grid-cols-1 gap-4">
                            <div class="flex flex-col gap-2">
                                <Label for="name">Tag name</Label>
                                <Input
                                    id="name"
                                    name="name"
                                    type="text"
                                    v-model="form.name"
                                    maxlength="255"
                                    :aria-invalid="!!form.errors.name"
                                    :aria-describedby="
                                        form.errors.name
                                            ? 'name-error'
                                            : undefined
                                    "
                                />
                                <InputError
                                    id="name-error"
                                    :message="form.errors.name"
                                />
                            </div>

                            <!-- Align the buttons with the input's right edge. -->
                            <div class="flex flex-wrap justify-end gap-2">
                                <Button variant="outline" as-child>
                                    <Link href="/tags">Cancel</Link>
                                </Button>
                                <!-- Block another save while this request is running. -->
                                <Button
                                    type="submit"
                                    :disabled="form.processing"
                                >
                                    {{
                                        form.processing
                                            ? 'Saving...'
                                            : 'Save changes'
                                    }}
                                </Button>
                            </div>
                        </div>
                    </form>
                </div>
            </CardContent>
        </Card>
    </main>
</template>
