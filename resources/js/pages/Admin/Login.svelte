<script module lang="ts">
    export const layout = {
        title: 'Admin Login',
    };
</script>

<script lang="ts">
    import { Form } from '@inertiajs/svelte';
    import * as AdminLoginController from '@/actions/App/Http/Controllers/Admin/LoginController';
    import AppHead from '@/components/AppHead.svelte';
    import FormErrorsAlert from '@/components/FormErrorsAlert.svelte';
    import PasswordInput from '@/components/PasswordInput.svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { Spinner } from '@/components/ui/spinner';
</script>

<AppHead title="Admin Login" />

<Form
    action={AdminLoginController.login.url()}
    method="post"
    resetOnSuccess={['password']}
    class="flex flex-col gap-6"
>
    {#snippet children({ errors, processing })}
        <div class="grid gap-6">
            <div class="grid gap-2">
                <Label for="email">E-Mail-Adresse</Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    required
                    autocomplete="email"
                    placeholder="admin@example.com"
                />
            </div>

            <div class="grid gap-2">
                <Label for="password">Passwort</Label>
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    autocomplete="current-password"
                    placeholder="Passwort"
                />
            </div>

            <Button type="submit" class="mt-4 w-full" disabled={processing}>
                {#if processing}<Spinner />{/if}
                Anmelden
            </Button>
        </div>

        <FormErrorsAlert
            {errors}
            title="Anmeldung fehlgeschlagen."
            showDetails={false}
        />
    {/snippet}
</Form>
