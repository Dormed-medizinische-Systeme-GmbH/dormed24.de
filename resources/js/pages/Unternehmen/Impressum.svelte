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
    title="Impressum"
    description="Impressum und Anbieterkennzeichnung der Dormed medizinische Systeme GmbH, Wilhelm-Röntgen-Straße 4, 59439 Holzwickede."
/>

<div class="flex min-h-screen flex-col bg-white">
    <ShopHeader />

    <main class="mx-auto max-w-7xl flex-1 px-4 py-10 lg:px-8">
        <h1 class="mb-8 text-2xl font-bold text-brand-secondary">Impressum</h1>

        <section class="mb-8">
            <h2 class="mb-3 text-base font-bold text-brand-secondary">
                Angaben zum Dienstanbieter
            </h2>
            <address
                class="not-italic text-sm leading-relaxed text-muted-foreground"
            >
                DORMED medizinische Systeme GmbH<br />
                Wilhelm-Röntgen-Straße 4<br />
                59439 Holzwickede<br /><br />
                Telefon:
                <a
                    href={contact.phone_href}
                    class="text-brand-accent hover:underline">{contact.phone}</a
                ><br />
                Telefax:
                <a
                    href={contact.fax_href}
                    class="text-brand-accent hover:underline">{contact.fax}</a
                ><br />
                E-Mail:
                <a
                    href={`mailto:${contact.email}`}
                    class="text-brand-accent hover:underline">{contact.email}</a
                >
            </address>
        </section>

        <section class="mb-8">
            <h2 class="mb-3 text-base font-bold text-brand-secondary">
                Vertreten durch den Geschäftsführer
            </h2>
            <p class="text-sm text-muted-foreground">
                Thomas Stücker-Everding, Andreas Draheim
            </p>
            <p class="mt-3 text-sm text-muted-foreground">
                Diese Seite wurde gestaltet und entwickelt von
                <a
                    href="https://everding.solutions"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="text-brand-accent hover:underline"
                    >Linus Stücker-Everding</a
                >.
            </p>
        </section>

        <section class="mb-8">
            <h2 class="mb-3 text-base font-bold text-brand-secondary">
                Registereintragung (§ 5 Abs. 1, Nr. 4 TMG)
            </h2>
            <p class="text-sm text-muted-foreground">
                Registergericht Hamm, HRB 4337
            </p>
        </section>

        <section class="mb-8">
            <h2 class="mb-3 text-base font-bold text-brand-secondary">
                USt-ID gemäß UStG § 27a
            </h2>
            <p class="text-sm text-muted-foreground">DE 125221841</p>
        </section>

        <hr class="my-8 border-border" />

        <section class="mb-8">
            <h2 class="mb-4 text-base font-bold text-brand-secondary">
                Haftungsausschluss
            </h2>

            <div class="space-y-6 text-sm text-muted-foreground">
                <div>
                    <h3 class="mb-2 font-semibold text-foreground">
                        1. Inhalt des Onlineangebotes
                    </h3>
                    <p>
                        Der Webseitenbetreiber übernimmt keinerlei Gewähr für
                        die Aktualität, Korrektheit, Vollständigkeit oder
                        Qualität der bereitgestellten Informationen.
                        Haftungsansprüche gegen den Webseitenbetreiber, welche
                        sich auf Schäden materieller oder ideeller Art beziehen,
                        die durch die Nutzung oder Nichtnutzung der dargebotenen
                        Informationen bzw. durch die Nutzung fehlerhafter und
                        unvollständiger Informationen verursacht wurden, sind
                        grundsätzlich ausgeschlossen, sofern seitens des
                        Webseitenbetreibers kein nachweislich vorsätzliches oder
                        grob fahrlässiges Verschulden vorliegt.
                    </p>
                </div>

                <div>
                    <h3 class="mb-2 font-semibold text-foreground">
                        2. Verweise und Links
                    </h3>
                    <p>
                        Bei direkten oder indirekten Verweisen auf fremde
                        Internetseiten („Links"), die außerhalb des
                        Verantwortungsbereichs des Webseitenbetreibers liegen,
                        würde eine Haftungsverpflichtung ausschließlich in dem
                        Fall in Kraft treten, in dem der Webseitenbetreiber von
                        den Inhalten Kenntnis hat und es ihm technisch möglich
                        und zumutbar wäre, die Nutzung im Falle rechtswidriger
                        Inhalte zu verhindern. Der Webseitenbetreiber erklärt
                        hiermit ausdrücklich, dass zum Zeitpunkt der Linksetzung
                        keine illegalen Inhalte auf den zu verlinkenden Seiten
                        erkennbar waren. Auf die aktuelle und zukünftige
                        Gestaltung, die Inhalte oder die Urheberschaft der
                        verlinkten/verknüpften Seiten hat der Webseitenbetreiber
                        keinerlei Einfluss. Deshalb distanziert er sich hiermit
                        ausdrücklich von allen Inhalten aller
                        verlinkten/verknüpften Seiten, die nach der Linksetzung
                        verändert wurden.
                    </p>
                </div>

                <div>
                    <h3 class="mb-2 font-semibold text-foreground">
                        3. Urheber- und Kennzeichenrecht
                    </h3>
                    <p>
                        Der Webseitenbetreiber ist bestrebt, in allen
                        Publikationen die Urheberrechte der verwendeten
                        Grafiken, Tondokumente, Videosequenzen und Texte zu
                        beachten, von ihm selbst erstellte Grafiken,
                        Tondokumente, Videosequenzen und Texte zu nutzen oder
                        auf lizenzfreie Grafiken, Tondokumente, Videosequenzen
                        und Texte zurückzugreifen. Alle innerhalb des
                        Internetangebotes genannten und ggf. durch Dritte
                        geschützten Marken- und Warenzeichen unterliegen
                        uneingeschränkt den Bestimmungen des jeweils gültigen
                        Kennzeichenrechts und den Besitzrechten der jeweiligen
                        eingetragenen Eigentümer. Allein aufgrund der bloßen
                        Nennung ist nicht der Schluss zu ziehen, dass
                        Markenzeichen nicht durch Rechte Dritter geschützt sind.
                    </p>
                </div>

                <div>
                    <h3 class="mb-2 font-semibold text-foreground">
                        4. Datenschutz
                    </h3>
                    <p>
                        Sofern innerhalb des Internetangebotes die Möglichkeit
                        zur Eingabe persönlicher oder geschäftlicher Daten
                        (Emailadressen, Namen, Anschriften) besteht, so erfolgt
                        die Preisgabe dieser Daten seitens des Nutzers auf
                        ausdrücklich freiwilliger Basis. Die Inanspruchnahme und
                        Bezahlung aller angebotenen Dienste ist – soweit
                        technisch möglich und zumutbar – auch ohne Angabe
                        solcher Daten bzw. unter Angabe anonymisierter Daten
                        oder eines Pseudonyms gestattet.
                    </p>
                </div>

                <div>
                    <h3 class="mb-2 font-semibold text-foreground">
                        5. Rechtswirksamkeit dieses Haftungsausschlusses
                    </h3>
                    <p>
                        Dieser Haftungsausschluss ist als Teil des
                        Internetangebotes zu betrachten, von dem aus auf diese
                        Seite verwiesen wurde. Sofern Teile oder einzelne
                        Formulierungen dieses Textes der geltenden Rechtslage
                        nicht, nicht mehr oder nicht vollständig entsprechen
                        sollten, bleiben die übrigen Teile des Dokumentes in
                        ihrem Inhalt und ihrer Gültigkeit davon unberührt.
                    </p>
                </div>
            </div>
        </section>
    </main>

    <AppFooter />
</div>
