<script lang="ts">
    import { Link, page } from '@inertiajs/svelte';
    import Mail from '@lucide/svelte/icons/mail';
    import Phone from '@lucide/svelte/icons/phone';
    import Printer from '@lucide/svelte/icons/printer';

    type ContactInfo = {
        email: string;
        phone: string;
        fax: string;
        phone_href: string;
        fax_href: string;
        phone_display: string;
        fax_display: string;
    };

    const contact = $derived(page.props.contact as ContactInfo);
</script>

<!--
    TEST-DESIGN: Navy-Bookend-Footer (Option A aus dem Artifact-Vergleich),
    schliesst den Kreis zum Navy-Hero. Logo braucht auf dunklem Grund einen
    weissen Chip, da der Schriftzug im SVG dunkel (#1d1d1b) ist.
-->
<footer class="mt-auto bg-primary text-white/70">
    <div class="mx-auto max-w-7xl px-4 pb-7 pt-14 lg:px-8">
        <div
            class="flex flex-wrap justify-between gap-10 border-b border-white/10 pb-10"
        >
            <div class="max-w-xs">
                <div class="inline-block rounded-lg bg-white px-4 py-3">
                    <img src="/logo.svg" alt="dormed 24" class="h-10 w-auto" />
                </div>
                <p class="mt-4 text-sm text-white/55">
                    ein Angebot der Dormed medizinische Systeme GmbH
                </p>
                <div class="mt-4 flex flex-col gap-2 text-sm font-semibold">
                    <a
                        href={contact.phone_href}
                        class="flex items-center gap-2 text-white transition hover:text-[#8fc4f0]"
                    >
                        <Phone class="size-4 shrink-0 text-white/45" />
                        {contact.phone_display}
                    </a>
                    <a
                        href={contact.fax_href}
                        class="flex items-center gap-2 text-white transition hover:text-[#8fc4f0]"
                    >
                        <Printer class="size-4 shrink-0 text-white/45" />
                        {contact.fax_display}
                    </a>
                    <a
                        href={`mailto:${contact.email}`}
                        class="flex items-center gap-2 text-white transition hover:text-[#8fc4f0]"
                    >
                        <Mail class="size-4 shrink-0 text-white/45" />
                        {contact.email}
                    </a>
                </div>
            </div>

            <div class="flex flex-wrap gap-10 sm:gap-14">
                <div>
                    <p
                        class="text-xs font-bold uppercase tracking-[0.1em] text-[#8fc4f0]"
                    >
                        Leistungen
                    </p>
                    <ul class="mt-3 flex flex-col gap-2">
                        <li>
                            <a
                                href="https://dormed.de/leistungen/beratung"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="text-sm text-white/75 hover:text-white hover:underline"
                            >
                                Beratung
                            </a>
                        </li>
                        <li>
                            <a
                                href="https://dormed.de/leistungen/finanzierung"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="text-sm text-white/75 hover:text-white hover:underline"
                            >
                                Finanzierung &amp; Leasing
                            </a>
                        </li>
                        <li>
                            <a
                                href="https://dormed.de/leistungen/lieferung"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="text-sm text-white/75 hover:text-white hover:underline"
                            >
                                Lieferung &amp; Installation
                            </a>
                        </li>
                        <li>
                            <a
                                href="https://dormed.de/leistungen/wartung-reparatur"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="text-sm text-white/75 hover:text-white hover:underline"
                            >
                                Wartung &amp; Reparatur
                            </a>
                        </li>
                        <li>
                            <a
                                href="https://dormed.de/leistungen/netzwerkanbindung"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="text-sm text-white/75 hover:text-white hover:underline"
                            >
                                Netzwerkanbindung
                            </a>
                        </li>
                        <li>
                            <a
                                href="https://dormed.de/leistungen/schulung-einweisung"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="text-sm text-white/75 hover:text-white hover:underline"
                            >
                                Schulung &amp; Einweisung
                            </a>
                        </li>
                        <li>
                            <a
                                href="https://dormed.de/leistungen/garantie-versicherung"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="text-sm text-white/75 hover:text-white hover:underline"
                            >
                                Garantie &amp; Versicherung
                            </a>
                        </li>
                        <li>
                            <a
                                href="https://dormed.de/leistungen/inzahlungnahme"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="text-sm text-white/75 hover:text-white hover:underline"
                            >
                                Inzahlungnahme
                            </a>
                        </li>
                    </ul>
                </div>

                <div>
                    <p
                        class="text-xs font-bold uppercase tracking-[0.1em] text-[#8fc4f0]"
                    >
                        Informationen
                    </p>
                    <ul class="mt-3 flex flex-col gap-2">
                        <li>
                            <Link
                                href="/informationen/versand"
                                class="text-sm text-white/75 hover:text-white hover:underline"
                                >Versand</Link
                            >
                        </li>
                        <li>
                            <Link
                                href="/informationen/zahlung"
                                class="text-sm text-white/75 hover:text-white hover:underline"
                                >Zahlung</Link
                            >
                        </li>
                        <li>
                            <Link
                                href="/informationen/faq"
                                class="text-sm text-white/75 hover:text-white hover:underline"
                                >FAQ</Link
                            >
                        </li>
                        <li>
                            <Link
                                href="/informationen/widerrufsbelehrung"
                                class="text-sm text-white/75 hover:text-white hover:underline"
                                >Widerrufsbelehrung</Link
                            >
                        </li>
                    </ul>
                </div>

                <div>
                    <p
                        class="text-xs font-bold uppercase tracking-[0.1em] text-[#8fc4f0]"
                    >
                        Unternehmen
                    </p>
                    <ul class="mt-3 flex flex-col gap-2">
                        <li>
                            <Link
                                href="/unternehmen/impressum"
                                class="text-sm text-white/75 hover:text-white hover:underline"
                                >Impressum</Link
                            >
                        </li>
                        <li>
                            <Link
                                href="/unternehmen/agb"
                                class="text-sm text-white/75 hover:text-white hover:underline"
                                >AGB</Link
                            >
                        </li>
                        <li>
                            <Link
                                href="/unternehmen/datenschutz"
                                class="text-sm text-white/75 hover:text-white hover:underline"
                                >Datenschutz</Link
                            >
                        </li>
                        <li>
                            <a
                                href="https://dormed.de/kontakt"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="text-sm text-white/75 hover:text-white hover:underline"
                                >Kontakt</a
                            >
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <div
            class="mt-6 flex flex-wrap items-center justify-between gap-2 text-xs text-white/40"
        >
            <span>© dormed24.de</span>
            <span>ein Angebot der Dormed medizinische Systeme GmbH</span>
        </div>
    </div>
</footer>
