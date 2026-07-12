<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import AppFooter from '@/components/AppFooter.svelte';
    import AppHead from '@/components/AppHead.svelte';
    import ShopHeader from '@/components/ShopHeader.svelte';
</script>

<AppHead
    title="Versand und Zahlung"
    description="Informationen zu Versandkosten, Lieferzeiten und Abholung bei dormed24 – Medizintechnik schnell und zuverlässig geliefert."
/>

<div class="flex min-h-screen flex-col bg-white">
    <ShopHeader />

    <main class="flex-1 mx-auto max-w-7xl px-4 py-10 lg:px-8">
        <h1 class="mb-6 text-2xl font-bold text-brand-secondary">Versand</h1>

        <p class="mb-8 text-sm text-muted-foreground">
            <strong>Hinweis:</strong> Unsere Angebote richten sich an
            Privatpersonen und Unternehmen. Privatkunden steht ein
            Widerrufsrecht gemäß §355 BGB zu. Weitere Informationen finden Sie
            in unserer <Link
                href="/informationen/widerrufsbelehrung"
                class="text-brand-accent hover:underline"
                >Widerrufsbelehrung</Link
            >.
        </p>

        <!-- Standardversand (DPD) -->
        <h2 class="mb-3 text-lg font-bold text-brand-secondary">
            Standardversand (DPD)
        </h2>
        <div class="mb-8 overflow-x-auto">
            <table class="w-full border-collapse text-sm">
                <thead>
                    <tr class="bg-muted text-left text-muted-foreground">
                        <th class="border border-border px-3 py-2 font-semibold"
                            >Preisspanne (netto)</th
                        >
                        <th class="border border-border px-3 py-2 font-semibold"
                            >Preisspanne (brutto)</th
                        >
                        <th class="border border-border px-3 py-2 font-semibold"
                            >Lieferzeit</th
                        >
                        <th class="border border-border px-3 py-2 font-semibold"
                            >Versandkosten (netto)</th
                        >
                        <th class="border border-border px-3 py-2 font-semibold"
                            >Versandkosten (brutto)</th
                        >
                    </tr>
                </thead>
                <tbody class="text-muted-foreground">
                    <tr>
                        <td class="border border-border px-3 py-2"
                            >0,01&nbsp;€ – 149,99&nbsp;€</td
                        >
                        <td class="border border-border px-3 py-2"
                            >0,01&nbsp;€ – 178,49&nbsp;€</td
                        >
                        <td class="border border-border px-3 py-2"
                            >7 – 14 Tage</td
                        >
                        <td class="border border-border px-3 py-2"
                            >9,52&nbsp;€</td
                        >
                        <td class="border border-border px-3 py-2"
                            >11,33&nbsp;€</td
                        >
                    </tr>
                    <tr class="bg-muted">
                        <td class="border border-border px-3 py-2"
                            >150,00&nbsp;€ – 499,99&nbsp;€</td
                        >
                        <td class="border border-border px-3 py-2"
                            >178,50&nbsp;€ – 594,99&nbsp;€</td
                        >
                        <td class="border border-border px-3 py-2"
                            >7 – 14 Tage</td
                        >
                        <td class="border border-border px-3 py-2"
                            >16,07&nbsp;€</td
                        >
                        <td class="border border-border px-3 py-2"
                            >19,12&nbsp;€</td
                        >
                    </tr>
                    <tr>
                        <td class="border border-border px-3 py-2"
                            >500,00&nbsp;€ – 999,99&nbsp;€</td
                        >
                        <td class="border border-border px-3 py-2"
                            >595,00&nbsp;€ – 1.189,99&nbsp;€</td
                        >
                        <td class="border border-border px-3 py-2"
                            >7 – 14 Tage</td
                        >
                        <td class="border border-border px-3 py-2"
                            >17,85&nbsp;€</td
                        >
                        <td class="border border-border px-3 py-2"
                            >21,24&nbsp;€</td
                        >
                    </tr>
                    <tr class="bg-muted">
                        <td class="border border-border px-3 py-2"
                            >ab 1.000,00&nbsp;€</td
                        >
                        <td class="border border-border px-3 py-2"
                            >ab 1.190,00&nbsp;€</td
                        >
                        <td class="border border-border px-3 py-2"
                            >7 – 14 Tage</td
                        >
                        <td class="border border-border px-3 py-2"
                            >0,00&nbsp;€ (versandfrei)</td
                        >
                        <td class="border border-border px-3 py-2"
                            >0,00&nbsp;€ (versandfrei)</td
                        >
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Standardversand + Video-Einweisung -->
        <h2 class="mb-3 text-lg font-bold text-brand-secondary">
            Standardversand (DPD) + Video-Einweisung
        </h2>
        <div class="mb-8 overflow-x-auto">
            <table class="w-full border-collapse text-sm">
                <thead>
                    <tr class="bg-muted text-left text-muted-foreground">
                        <th class="border border-border px-3 py-2 font-semibold"
                            >Preisspanne (netto)</th
                        >
                        <th class="border border-border px-3 py-2 font-semibold"
                            >Preisspanne (brutto)</th
                        >
                        <th class="border border-border px-3 py-2 font-semibold"
                            >Lieferzeit</th
                        >
                        <th class="border border-border px-3 py-2 font-semibold"
                            >Versandkosten (netto)</th
                        >
                        <th class="border border-border px-3 py-2 font-semibold"
                            >Versandkosten (brutto)</th
                        >
                    </tr>
                </thead>
                <tbody class="text-muted-foreground">
                    <tr>
                        <td class="border border-border px-3 py-2"
                            >0,01&nbsp;€ – 149,99&nbsp;€</td
                        >
                        <td class="border border-border px-3 py-2"
                            >0,01&nbsp;€ – 178,49&nbsp;€</td
                        >
                        <td class="border border-border px-3 py-2"
                            >7 – 14 Tage</td
                        >
                        <td class="border border-border px-3 py-2"
                            >303,45&nbsp;€</td
                        >
                        <td class="border border-border px-3 py-2"
                            >361,11&nbsp;€</td
                        >
                    </tr>
                    <tr class="bg-muted">
                        <td class="border border-border px-3 py-2"
                            >ab 150,00&nbsp;€</td
                        >
                        <td class="border border-border px-3 py-2"
                            >ab 178,50&nbsp;€</td
                        >
                        <td class="border border-border px-3 py-2"
                            >7 – 14 Tage</td
                        >
                        <td class="border border-border px-3 py-2"
                            >297,50&nbsp;€</td
                        >
                        <td class="border border-border px-3 py-2"
                            >361,11&nbsp;€</td
                        >
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Selbstabholung -->
        <h2 class="mb-3 text-lg font-bold text-brand-secondary">
            Selbstabholung
        </h2>
        <div class="mb-8 overflow-x-auto">
            <table class="w-full border-collapse text-sm">
                <thead>
                    <tr class="bg-muted text-left text-muted-foreground">
                        <th class="border border-border px-3 py-2 font-semibold"
                            >Lieferzeit</th
                        >
                        <th class="border border-border px-3 py-2 font-semibold"
                            >Versandkosten (netto)</th
                        >
                        <th class="border border-border px-3 py-2 font-semibold"
                            >Versandkosten (brutto)</th
                        >
                    </tr>
                </thead>
                <tbody class="text-muted-foreground">
                    <tr>
                        <td class="border border-border px-3 py-2"
                            >1 – 3 Tage</td
                        >
                        <td class="border border-border px-3 py-2"
                            >0,00&nbsp;€ (kostenlos)</td
                        >
                        <td class="border border-border px-3 py-2"
                            >0,00&nbsp;€ (kostenlos)</td
                        >
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Persönliche Lieferung -->
        <h2 class="mb-3 text-lg font-bold text-brand-secondary">
            Persönliche Lieferung inkl. Einweisung &amp; Installation
        </h2>
        <div class="mb-8 overflow-x-auto">
            <table class="w-full border-collapse text-sm">
                <thead>
                    <tr class="bg-muted text-left text-muted-foreground">
                        <th class="border border-border px-3 py-2 font-semibold"
                            >Lieferzeit</th
                        >
                        <th class="border border-border px-3 py-2 font-semibold"
                            >Kosten (netto)</th
                        >
                        <th class="border border-border px-3 py-2 font-semibold"
                            >Kosten (brutto)</th
                        >
                    </tr>
                </thead>
                <tbody class="text-muted-foreground">
                    <tr>
                        <td class="border border-border px-3 py-2"
                            >7 – 14 Tage</td
                        >
                        <td class="border border-border px-3 py-2"
                            >575,00&nbsp;€</td
                        >
                        <td class="border border-border px-3 py-2"
                            >684,25&nbsp;€</td
                        >
                    </tr>
                </tbody>
            </table>
        </div>

        <p class="mb-8 text-sm text-muted-foreground">
            Alle angegebenen Preise verstehen sich inkl. der jeweils gültigen
            gesetzlichen Mehrwertsteuer (bei Privatkunden) oder zzgl. MwSt. (bei
            Unternehmern).
        </p>

        <!-- Eigentumsvorbehalt -->
        <h2 class="mb-2 text-lg font-bold text-brand-secondary">
            Eigentumsvorbehalt
        </h2>
        <div class="mb-8 space-y-3 text-sm text-muted-foreground">
            <p>
                Bis zur vollständigen Bezahlung behalten wir uns das Eigentum an
                der gelieferten Ware vor. Bei vertragswidrigem Verhalten des
                Kunden, insbesondere bei Zahlungsverzug, sind wir berechtigt,
                die Ware zurückzufordern, und der Kunde ist zur Herausgabe
                verpflichtet! In der Zurücknahme liegt nur dann ein Rücktritt
                vom Vertrag vor, wenn wir dies ausdrücklich schriftlich
                erklären.
            </p>
            <p>
                Die Ware darf bis zur vollständigen Bezahlung ohne unsere
                schriftliche Zustimmung weder verpfändet noch
                sicherungsübereignet werden!
            </p>
            <p>
                Wird die Ware mit anderen, uns nicht gehörenden Gegenständen
                untrennbar verbunden oder vermischt, so erwerben wir das
                Miteigentum an der neuen Sache im Verhältnis des Wertes der
                gelieferten Ware zu den anderen verbundenen oder vermischten
                Gegenständen zum Zeitpunkt der Verbindung oder Vermischung.
            </p>
            <p>
                Der Kunde ist berechtigt, die Ware im ordentlichen Geschäftsgang
                weiterzuverkaufen. Alle Ansprüche gegen die Abnehmer aus dem
                Verkauf oder der Weitergabe unserer Waren, insbesondere der
                Anspruch auf Zahlung des Kaufpreises, werden hiermit zum
                Ausgleich unserer Forderungen an uns abgetreten!
            </p>
            <p>
                Wir verpflichten uns, die Forderung nicht einzuziehen, solange
                der Kunde seinen uns gegenüber obliegenden
                Zahlungsverpflichtungen ordnungsgemäß nachkommt und nicht in
                Zahlungsverzug gerät. Ist dies jedoch der Fall, wird uns der
                Kunde seinen Schuldner bekannt geben, sowie alle zum Einzug
                erforderlichen Angaben auch gegenüber seinem Schuldner machen!
            </p>
            <p>
                Greifen Dritte auf die Ware zu, an der wir uns das Eigentum ganz
                oder zum Teil vorbehalten haben, hat der Kunde uns davon
                unverzüglich per Brief, Fax, E-Mail oder telefonisch zu
                informieren. Bei Vollstreckungsmaßnahmen ist der
                Gerichtsvollzieher vom Kunden darauf hinzuweisen, dass
                Dritteigentum besteht!
            </p>
        </div>

        <!-- Mängelanzeige -->
        <h2 class="mb-2 text-lg font-bold text-brand-secondary">
            Mängelanzeige
        </h2>
        <p class="mb-8 text-sm text-muted-foreground">
            Sollte die gelieferte Ware offensichtliche Mängel aufweisen, so ist
            dies innerhalb von drei Werktagen nach Erhalt der Ware anzuzeigen.
            Beschädigungen an der Verpackung sind vom Transportunternehmen
            schriftlich zu bestätigen. Verdeckte Mängel sind sofort nach der
            Entdeckung anzuzeigen. Für die Rechtzeitigkeit der Mängelanzeige
            genügt die Absendung innerhalb der Frist.
        </p>

        <!-- Verjährung -->
        <h2 class="mb-2 text-lg font-bold text-brand-secondary">
            Verjährung von Mängelansprüchen
        </h2>
        <p class="mb-8 text-sm text-muted-foreground">
            Mängelansprüche gegen uns verjähren nach 12 Monaten. Dies gilt nicht
            für den Fall schuldhafter Pflichtverletzungen, die zur Verletzung
            des Lebens, Körpers oder der Gesundheit führen oder für grob
            fahrlässige Pflichtverletzungen.
        </p>

        <!-- Nacherfüllung -->
        <h2 class="mb-2 text-lg font-bold text-brand-secondary">
            Nacherfüllung
        </h2>
        <p class="mb-8 text-sm text-muted-foreground">
            Ist eine von uns gelieferte Sache mangelhaft und wir sind zur
            Nacherfüllung verpflichtet, können wir nach unserer Wahl den Mangel
            an der gelieferten Sache beseitigen oder gegen Rückgabe der
            fehlerhaften Sache eine neue mangelfreie Sache liefern.
        </p>

        <!-- Werbeaussagen -->
        <h2 class="mb-2 text-lg font-bold text-brand-secondary">
            Werbeaussagen
        </h2>
        <p class="mb-8 text-sm text-muted-foreground">
            Soweit die Beschaffenheit des Kaufgegenstandes nicht vereinbart
            wurde, gehören Eigenschaften, die aufgrund von Äußerungen von
            Dritten, insbesondere Werbeaussagen von Herstellern, Importeuren und
            Werbeagenturen der gekauften Sache zugewiesen werden, nur dann zu
            ihrer Sollbeschaffenheit, wenn der Kunde nachweist, dass wir diese
            Äußerung kannten oder hätten kennen müssen und die Äußerung seine
            Kaufentscheidung beeinflusst hat und wir nicht nachweisen, dass die
            Äußerung bis Vertragsschluss in gleichwertiger Weise berichtigt
            wurde.
        </p>

        <!-- Datenschutz -->
        <h2 class="mb-2 text-lg font-bold text-brand-secondary">Datenschutz</h2>
        <p class="mb-8 text-sm text-muted-foreground">
            Wir speichern und verwenden die Bestelldaten des Kunden für die
            Abwicklung der Geschäftsbeziehung. Eine Weitergabe der Daten an
            ausgewählte Partner zur Erfüllung bzw. Zahlungsabwicklung erfolgt
            unter strenger Beachtung der geltenden datenschutzrechtlichen
            Bestimmungen.
        </p>

        <!-- Erfüllungsort -->
        <h2 class="mb-2 text-lg font-bold text-brand-secondary">
            Erfüllungsort, Gerichtsstand
        </h2>
        <p class="mb-8 text-sm text-muted-foreground">
            Erfüllungsort und Gerichtsstand ist Holzwickede.
        </p>
    </main>

    <AppFooter />
</div>
