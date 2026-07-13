<script module lang="ts">
    export const layout = {
        title: 'Konto erstellen',
        description:
            'Geben Sie unten Ihre Daten ein, um Ihr Konto zu erstellen',
    };
</script>

<script lang="ts">
    import { Form } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import FormErrorsAlert from '@/components/FormErrorsAlert.svelte';
    import PasswordInput from '@/components/PasswordInput.svelte';
    import TextLink from '@/components/TextLink.svelte';
    import { Button } from '@/components/ui/button';
    import { Checkbox } from '@/components/ui/checkbox';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { Spinner } from '@/components/ui/spinner';
    import { login } from '@/routes';
    import { store } from '@/routes/register';

    let { passwordRules }: { passwordRules: string } = $props();
</script>

<AppHead title="Registrieren" />

<Form
    {...store.form()}
    resetOnSuccess={['password', 'password_confirmation']}
    class="flex flex-col gap-6"
>
    {#snippet children({ errors, processing })}
        <div class="grid gap-6">
            <div class="grid gap-2">
                <Label for="name">Name</Label>
                <Input
                    id="name"
                    type="text"
                    required
                    autocomplete="name"
                    name="name"
                    placeholder="Vor- und Nachname"
                />
            </div>

            <div class="grid gap-2">
                <Label for="email">E-Mail-Adresse</Label>
                <Input
                    id="email"
                    type="email"
                    required
                    autocomplete="email"
                    name="email"
                    placeholder="email@beispiel.de"
                />
            </div>

            <div class="grid gap-2">
                <Label for="password">Passwort</Label>
                <PasswordInput
                    id="password"
                    required
                    autocomplete="new-password"
                    name="password"
                    placeholder="Passwort"
                    passwordrules={passwordRules}
                />
            </div>

            <div class="grid gap-2">
                <Label for="password_confirmation">Passwort bestätigen</Label>
                <PasswordInput
                    id="password_confirmation"
                    required
                    autocomplete="new-password"
                    name="password_confirmation"
                    placeholder="Passwort bestätigen"
                    passwordrules={passwordRules}
                />
            </div>

            <div class="flex items-center justify-between">
                <Label for="terms" class="flex items-center space-x-3">
                    <Checkbox id="terms" name="terms" />
                    <span>
                        <a
                            href="/unternehmen/agb"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="hover:underline"
                            onclick={(e) => e.stopPropagation()}
                        >
                            AGBs
                        </a>
                        akzeptiert
                    </span>
                </Label>
            </div>

            <Button
                type="submit"
                class="mt-2 w-full"
                disabled={processing}
                data-test="register-user-button"
            >
                {#if processing}<Spinner />{/if}
                Konto erstellen
            </Button>
        </div>

        <div class="text-center text-sm text-muted-foreground">
            Bereits ein Konto?
            <TextLink href={login()} class="underline underline-offset-4">
                Anmelden
            </TextLink>
        </div>

        <FormErrorsAlert {errors} title="Registrierung fehlgeschlagen." />
    {/snippet}
</Form>
