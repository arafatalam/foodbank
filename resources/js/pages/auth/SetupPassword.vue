<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { Card, CardContent } from '@/components/ui/card';
import { Sprout } from '@lucide/vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Label } from '@/components/ui/label';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import InputError from '@/components/InputError.vue';
import { store } from '@/routes/password/setup';

defineProps<{
    username: string;
}>();

defineOptions({
    // Show this page without the normal application layout.
    layout: [],
});
</script>

<template>
    <main class="flex min-h-svh items-center justify-center p-6">
        <Head title="Set your password" />

        <Card class="w-full max-w-md py-8">
            <CardContent class="flex flex-col gap-6 px-8">
                <!-- Keep the same FoodBank branding as the login page. -->
                <div class="flex items-center justify-center gap-3">
                    <Sprout class="size-10 text-primary" aria-hidden="true" />
                    <span class="text-3xl font-semibold">FoodBank</span>
                </div>

                <div class="flex flex-col gap-2 text-center">
                    <h1 class="text-2xl font-semibold">Welcome to FoodBank</h1>
                    <p>Choose a password for your account</p>
                </div>

                <!-- Reserve a read-only place for the signed-in user's username. -->
                <div class="flex flex-col gap-2">
                    <Label for="username">Username</Label>
                    <Input
                        id="username"
                        type="text"
                        :model-value="username"
                        class="font-semibold"
                        readonly
                    />
                </div>

                <Form
                    v-bind="store.form()"
                    v-slot="{ errors, processing }"
                    class="flex flex-col gap-6"
                >
                    <!-- Stack the label above the new-password field. -->
                    <div class="flex flex-col gap-2">
                        <Label for="password">New password</Label>
                        <PasswordInput
                            id="password"
                            name="password"
                            autocomplete="new-password"
                            placeholder="New password"
                        />
                    </div>

                    <!-- Ask for the password again to catch typing mistakes. -->
                    <div class="flex flex-col gap-2">
                        <Label for="password_confirmation"
                            >Confirm new password</Label
                        >
                        <PasswordInput
                            id="password_confirmation"
                            name="password_confirmation"
                            autocomplete="new-password"
                            placeholder="Confirm new password"
                        />
                    </div>

                    <!-- Password validation messages will appear here. -->
                    <InputError :message="errors.password" />

                    <!-- Disable the button while the request is being handled. -->
                    <Button type="submit" class="w-full" :disabled="processing">
                        {{ processing ? 'Saving…' : 'Set password' }}
                    </Button>
                </Form>
            </CardContent>
        </Card>
    </main>
</template>
