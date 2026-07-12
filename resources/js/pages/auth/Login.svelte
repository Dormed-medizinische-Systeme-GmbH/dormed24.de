<script module lang="ts">
    export const layout = {
        title: 'Bei Ihrem Konto anmelden',
        description:
            'Geben Sie unten Ihre E-Mail-Adresse und Ihr Passwort ein, um sich anzumelden',
    };
</script>

<script lang="ts">
    import { Form } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import InputError from '@/components/InputError.svelte';
    // Passkey-Login bewusst deaktiviert, siehe Vermerk bei <PasskeyVerify /> weiter unten.
    // import PasskeyVerify from '@/components/PasskeyVerify.svelte';
    import PasswordInput from '@/components/PasswordInput.svelte';
    import TextLink from '@/components/TextLink.svelte';
    import { Button } from '@/components/ui/button';
    import { Checkbox } from '@/components/ui/checkbox';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { Spinner } from '@/components/ui/spinner';
    import { register } from '@/routes';
    import { store } from '@/routes/login';
    import { request } from '@/routes/password';

    let {
        status = '',
        canResetPassword,
    }: {
        status?: string;
        canResetPassword: boolean;
    } = $props();
</script>

<AppHead title="Anmelden" />

{#if status}
    <div class="mb-4 text-center text-sm font-medium text-green-600">
        {status}
    </div>
{/if}

<!--
    Passkey-Login aktuell bewusst deaktiviert (Feature-Entscheidung, nicht
    technisch bedingt). Zum Reaktivieren: Import oben und Zeile unten
    einkommentieren, keine weiteren Aenderungen noetig - Komponente ist
    in sich abgeschlossen (plug-and-play).
    <PasskeyVerify />
-->

<Form
    {...store.form()}
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
                    placeholder="email@beispiel.de"
                />
                <InputError message={errors.email} />
            </div>

            <div class="grid gap-2">
                <div class="flex items-center justify-between">
                    <Label for="password">Passwort</Label>
                    {#if canResetPassword}
                        <TextLink href={request()} class="text-sm">
                            Passwort vergessen?
                        </TextLink>
                    {/if}
                </div>
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    autocomplete="current-password"
                    placeholder="Passwort"
                />
                <InputError message={errors.password} />
            </div>

            <div class="flex items-center justify-between">
                <Label for="remember" class="flex items-center space-x-3">
                    <Checkbox id="remember" name="remember" />
                    <span>Angemeldet bleiben</span>
                </Label>
            </div>

            <Button
                type="submit"
                class="mt-4 w-full"
                disabled={processing}
                data-test="login-button"
            >
                {#if processing}<Spinner />{/if}
                Anmelden
            </Button>
        </div>

        <div class="text-center text-sm text-muted-foreground">
            Noch kein Konto?
            <TextLink href={register()}>Registrieren</TextLink>
        </div>
    {/snippet}
</Form>
