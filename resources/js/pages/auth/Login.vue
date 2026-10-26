<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { Card, CardContent } from '@/components/ui/card';
import { Sprout } from '@lucide/vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { store } from '@/routes/login';
import InputError from '@/components/InputError.vue';

defineOptions({
    layout: [],
});
</script>

<template>
    <main class="flex min-h-svh items-center justify-center p-6">
        <Head title="Log in" />

        <Card class="w-full max-w-md py-8">
            <CardContent class="flex flex-col gap-6 px-8">
                <div class="flex items-center justify-center gap-3">
                    <Sprout class="size-10 text-primary" aria-hidden="true" />

                    <span class="text-3xl font-semibold">FoodBank</span>
                </div>

                <div class="flex flex-col gap-2 text-center">
                    <h1 class="text-2xl font-semibold">Welcome Back!</h1>
                    <p>Login to your foodbank account</p>
                </div>

                <Form
                    v-bind="store.form()"
                    v-slot="{ errors, processing }"
                    class="flex flex-col gap-6"
                >
                    <div class="flex flex-col gap-2">
                        <Label for="login">Username or email</Label>

                        <Input
                            id="login"
                            name="login"
                            type="text"
                            autocomplete="username"
                            placeholder="Your username or email"
                        />
                    </div>

                    <div class="flex flex-col gap-2">
                        <Label for="password">Password</Label>

                        <PasswordInput
                            id="password"
                            name="password"
                            autocomplete="current-password"
                            placeholder="Your password"
                            :autofocus="false"
                        />
                    </div>

                    <div class="flex items-center gap-2">
                        <Checkbox id="remember" name="remember" />
                        <Label for="remember">Remember me</Label>
                    </div>

                    <InputError :message="errors.login || errors.password" />

                    <Button type="submit" :disabled="processing">
                        {{ processing ? 'Logging in ...' : 'Log In' }}
                    </Button>
                </Form>
            </CardContent>
        </Card>
    </main>
</template>
