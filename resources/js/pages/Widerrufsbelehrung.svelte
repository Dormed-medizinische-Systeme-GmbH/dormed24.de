<script lang="ts">
    import { page } from '@inertiajs/svelte';
    import AppFooter from '@/components/AppFooter.svelte';
    import AppHead from '@/components/AppHead.svelte';
    import ShopHeader from '@/components/ShopHeader.svelte';

    type ContactInfo = {
        email: string;
        phone: string;
        fax: string;
        phone_href: string;
        fax_href: string;
    };

    const contact = $derived(page.props.contact as ContactInfo);
</script>

<AppHead
    title="Widerrufsbelehrung"
    description="Widerrufsrecht und Rückgabebedingungen für Käufe im dormed24-Shop der Dormed medizinische Systeme GmbH."
/>

<div class="flex min-h-screen flex-col bg-white">
    <ShopHeader />

    <main class="flex-1 mx-auto max-w-7xl px-4 py-10 lg:px-8">
        <h1 class="mb-8 text-2xl font-bold text-brand-secondary">
            Widerrufsbelehrung
        </h1>

        <div class="space-y-6 text-sm leading-relaxed text-gray-700">
            <section>
                <p>
                    Verbraucher haben das Recht, binnen 14 Tagen ohne Angabe von
                    Gründen diesen Vertrag zu widerrufen. Die Widerrufsfrist
                    beträgt 14 Tage ab dem Tag, an dem Sie oder ein von Ihnen
                    benannter Dritter, der nicht der Beförderer ist, die Waren
                    in Besitz genommen haben.
                </p>
            </section>

            <section>
                <p>
                    Um Ihr Widerrufsrecht auszuüben, müssen Sie uns (Dormed
                    medizinische Systeme GmbH, Wilhelm-Röntgen-Straße 4, 59439
                    Holzwickede, E-Mail: mail@dormed.de) mittels einer
                    eindeutigen Erklärung (z.B. ein mit der Post versandter
                    Brief oder E-Mail) über Ihren Entschluss, diesen Vertrag zu
                    widerrufen, informieren.
                </p>
            </section>

            <section>
                <h2 class="mb-2 font-semibold text-gray-900">
                    Folgen des Widerrufs
                </h2>
                <p>
                    Wenn Sie diesen Vertrag widerrufen, haben wir Ihnen alle
                    Zahlungen, die wir von Ihnen erhalten haben, einschließlich
                    der Lieferkosten (mit Ausnahme der zusätzlichen Kosten, die
                    sich daraus ergeben, dass Sie eine andere Art der Lieferung
                    als die von uns angebotene günstigste Standardlieferung
                    „Standardversand (DPD)" gewählt haben) unverzüglich und
                    spätestens binnen vierzehn Tagen ab dem Tag zurückzuzahlen,
                    an dem die Mitteilung über Ihren Widerruf dieses Vertrags
                    bei uns eingegangen ist.
                </p>
                <p class="mt-3">
                    Für diese Rückzahlung verwenden wir dasselbe Zahlungsmittel,
                    das Sie bei der ursprünglichen Transaktion eingesetzt haben,
                    es sei denn, mit Ihnen wurde ausdrücklich etwas anderes
                    vereinbart.
                </p>
            </section>

            <section class="border-t pt-6">
                <h2 class="mb-2 font-semibold text-gray-900">
                    Freiwillige Rückgaberichtlinie
                </h2>
                <p>
                    Unabhängig davon gewähren wir Ihnen im Rahmen unserer
                    freiwilligen Rückgaberichtlinie folgende Möglichkeit:
                    Defekte oder beschädigte Produkte können innerhalb von 14
                    Tagen nach Erhalt zurückgegeben oder umgetauscht werden.
                    Bitte setzen Sie sich hierzu mit unserem Kundenservice in
                    Verbindung. Die Rücksendung ist kostenlos.
                </p>
                <p class="mt-3">
                    Das Rücksendeetikett ist bereits in der Lieferung enthalten.
                    Bitte verwenden Sie dieses Etikett für die Rücksendung.
                </p>
                <p class="mt-3">
                    Die Rücksendung erfolgt ausschließlich per Post. Die
                    Rückerstattung erfolgt innerhalb von 5 Werktagen nach
                    Eingang der Ware.
                </p>
            </section>

            <section class="border-t pt-6">
                <h2 class="mb-2 font-semibold text-gray-900">Kontakt</h2>
                <p>
                    Weitere Informationen erhalten Sie telefonisch oder über
                    unser Kontaktformular.
                </p>
                <ul class="mt-3 space-y-1">
                    <li>
                        Telefon: <a
                            href={contact.phone_href}
                            class="text-brand-accent hover:underline"
                            >{contact.phone}</a
                        >
                    </li>
                    <li>
                        Telefax: <a
                            href={contact.fax_href}
                            class="text-brand-accent hover:underline"
                            >{contact.fax}</a
                        >
                    </li>
                    <li>
                        E-Mail: <a
                            href={`mailto:${contact.email}`}
                            class="text-brand-accent hover:underline"
                            >{contact.email}</a
                        >
                    </li>
                </ul>
            </section>
        </div>
    </main>

    <AppFooter />
</div>
