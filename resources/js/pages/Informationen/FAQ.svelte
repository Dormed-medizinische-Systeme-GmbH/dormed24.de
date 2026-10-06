<script lang="ts">
    import { page } from '@inertiajs/svelte';
    import AppFooter from '@/components/AppFooter.svelte';
    import AppHead from '@/components/AppHead.svelte';
    import ShopHeader from '@/components/ShopHeader.svelte';
    import * as Accordion from '@/components/ui/accordion';

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

    const faqItems = [
        {
            id: 'lieferzeit',
            question: 'Wie lange dauert die Lieferung?',
            answer: 'Die Lieferzeit beträgt in der Regel 3–7 Werktage innerhalb Deutschlands. Bei Sonderanfertigungen oder Artikeln mit längerer Verfügbarkeitsprüfung informieren wir Sie individuell. Expresslieferung ist auf Anfrage möglich.',
        },
        {
            id: 'lieferkosten',
            question: 'Welche Versandkosten fallen an?',
            answer: 'Die Versandkosten richten sich nach Gewicht und Volumen der Bestellung. Für kleine Artikel gelten Standardversandkosten. Großgeräte und Möbel werden per Spedition geliefert – die genauen Kosten sehen Sie im Warenkorb vor dem Abschluss der Bestellung.',
        },
        {
            id: 'zahlung',
            question: 'Welche Zahlungsarten werden akzeptiert?',
            answer: 'Wir akzeptieren Vorkasse (Überweisung), Kreditkarte sowie PayPal. Für gewerbliche Kunden und Einrichtungen ist auf Anfrage auch Kauf auf Rechnung möglich. Bei Finanzierungs- und Leasinganfragen sprechen Sie uns bitte direkt an.',
        },
        {
            id: 'finanzierung',
            question: 'Bietet ihr Finanzierung oder Leasing an?',
            answer: 'Ja. Für Praxen, Kliniken und andere Einrichtungen bieten wir individuelle Finanzierungs- und Leasingmodelle an. Sprechen Sie uns direkt an – wir erstellen Ihnen gerne ein maßgeschneidertes Angebot.',
        },
        {
            id: 'rueckgabe',
            question: 'Wie läuft eine Rückgabe oder ein Umtausch ab?',
            answer: 'Sie haben als Verbraucher ein 14-tägiges Widerrufsrecht. Bitte nehmen Sie vor einer Rücksendung Kontakt zu uns auf, damit wir den Vorgang reibungslos koordinieren können. Hygieneartikel und individuell angefertigte Produkte sind vom Widerrufsrecht ausgenommen, sofern sie aus hygienischen Gründen versiegelt waren und die Versiegelung nach der Lieferung entfernt wurde.',
        },
        {
            id: 'garantie',
            question: 'Welche Garantie gilt für die Produkte?',
            answer: 'Es gilt die gesetzliche Gewährleistung von 2 Jahren ab Kaufdatum. Viele Hersteller gewähren darüber hinaus freiwillige Herstellergarantien von bis zu 5 Jahren. Die genauen Garantiebedingungen finden Sie auf den jeweiligen Produktseiten. Wir übernehmen auch die Abwicklung von Garantiefällen mit dem Hersteller für Sie.',
        },
        {
            id: 'installation',
            question: 'Bietet ihr Lieferung mit Aufbau und Installation an?',
            answer: 'Ja. Für größere Geräte, Praxismöbel und komplexe Systeme bieten wir einen Lieferungs- und Installationsservice an. Unser Fachpersonal richtet das Gerät bei Ihnen vor Ort ein und weist Sie in die Bedienung ein. Bitte sprechen Sie diesen Service beim Kauf an.',
        },
        {
            id: 'wartung',
            question: 'Können wir Wartung und Reparatur über euch abwickeln?',
            answer: 'Ja. Wir bieten Wartungsverträge und Einzelreparaturen für eine Vielzahl medizintechnischer Geräte an – unabhängig davon, ob das Gerät bei uns gekauft wurde. Kontaktieren Sie uns für ein Wartungsangebot oder bei einem akuten Reparaturfall.',
        },
        {
            id: 'beratung',
            question:
                'Ich bin unsicher, welches Produkt zu meiner Praxis passt. Helft ihr?',
            answer: 'Gerne. Unsere Fachberater kennen die Anforderungen von Praxen, Kliniken und Pflegeeinrichtungen und helfen Ihnen bei der Auswahl des richtigen Geräts. Rufen Sie uns an oder schreiben Sie uns – wir melden uns in der Regel innerhalb eines Werktages.',
        },
        {
            id: 'netzwerk',
            question:
                'Unterstützt ihr bei der Netzwerkanbindung von Medizingeräten?',
            answer: 'Ja. Viele moderne medizintechnische Geräte müssen in Praxisverwaltungssysteme oder Kliniknetzwerke integriert werden. Wir unterstützen bei der Netzwerkkonfiguration und Schnittstellenanbindung (z.B. GDT, DICOM) auf Anfrage.',
        },
        {
            id: 'schulung',
            question: 'Bietet ihr Schulungen zur Gerätebedienung an?',
            answer: 'Ja. Nach der Installation oder auch separat bieten wir Einweisungen und Schulungen für Ihr Team an – vor Ort oder per Remote-Session. Gerade bei komplexeren Geräten empfehlen wir eine strukturierte Einweisung, um den vollen Funktionsumfang sicher zu nutzen.',
        },
        {
            id: 'inzahlung',
            question: 'Nehmt ihr Altgeräte in Zahlung?',
            answer: 'In vielen Fällen ja. Wenn Sie ein älteres Gerät durch ein neues ersetzen möchten, können wir eine Inzahlungnahme prüfen. Den aktuellen Zeitwert Ihres Altgeräts bewerten wir nach Fotos und technischen Angaben. Sprechen Sie uns unverbindlich an.',
        },
        {
            id: 'gewerbe',
            question: 'Können auch Kliniken und Einrichtungen bestellen?',
            answer: 'Ja. Wir beliefern Arztpraxen, MVZ, Kliniken, Pflegeheime, Physiotherapiepraxen und andere medizinische Einrichtungen. Für gewerbliche Kunden gelten teilweise abweichende Konditionen. Kontaktieren Sie uns für ein individuelles Angebot oder eine Rahmenvereinbarung.',
        },
    ];

    // "<" escaped so the content can never close the surrounding script tag
    // early (script-breakout injection) — same convention as Products/Show.
    const jsonLd = JSON.stringify({
        '@context': 'https://schema.org',
        '@type': 'FAQPage',
        mainEntity: faqItems.map((item) => ({
            '@type': 'Question',
            name: item.question,
            acceptedAnswer: {
                '@type': 'Answer',
                text: item.answer,
            },
        })),
    }).replace(/</g, '\\u003c');

    const jsonLdHtml =
        `<script type="application/ld+json">${jsonLd}<` + `/script>`;
</script>

<!-- eslint-disable-next-line svelte/no-at-html-tags -- JSON-LD; "<" is escaped in jsonLd -->
<svelte:head>{@html jsonLdHtml}</svelte:head>

<AppHead
    title="Häufige Fragen (FAQ) — dormed24.de"
    description="Antworten auf häufige Fragen zu Lieferung, Zahlung, Garantie, Rückgabe, Wartung und Beratung rund um medizintechnische Produkte bei dormed24.de."
/>

<div class="flex min-h-screen flex-col bg-white">
    <ShopHeader />

    <main class="flex-1">
        <div class="mx-auto max-w-7xl px-4 py-10 lg:px-8">
            <h1 class="mb-2 text-2xl font-bold text-brand-secondary">
                Häufige Fragen
            </h1>
            <p class="mb-10 text-sm text-muted-foreground">
                Sie finden hier Antworten auf die häufigsten Fragen zu
                Bestellung, Lieferung, Zahlung und unseren Serviceleistungen.
                Bei weiteren Fragen erreichen Sie uns unter
                <a
                    href={contact.phone_href}
                    class="text-brand-accent hover:underline"
                    >{contact.phone_display}</a
                >
                oder
                <a
                    href={`mailto:${contact.email}`}
                    class="text-brand-accent hover:underline">{contact.email}</a
                >.
            </p>

            <Accordion.Root
                type="single"
                class="divide-y divide-gray-100 border-y border-border"
            >
                {#each faqItems as item (item.id)}
                    <Accordion.Item value={item.id} class="group">
                        <Accordion.Trigger
                            class="flex w-full items-center justify-between gap-4 py-5 text-left text-sm font-medium text-brand-secondary transition-colors hover:text-brand-accent [&[data-state=open]>svg]:rotate-180"
                        >
                            {item.question}
                        </Accordion.Trigger>
                        <Accordion.Content
                            class="pb-5 text-sm leading-relaxed text-muted-foreground"
                        >
                            {item.answer}
                        </Accordion.Content>
                    </Accordion.Item>
                {/each}
            </Accordion.Root>

            <div
                class="mt-12 rounded-xl border border-brand-accent/20 bg-[#f0f7ff] px-6 py-6"
            >
                <p class="text-sm font-semibold text-brand-secondary">
                    Ihre Frage ist nicht dabei?
                </p>
                <p class="mt-1 text-sm text-muted-foreground">
                    Unser Team hilft Ihnen gerne persönlich weiter.
                </p>
                <div class="mt-4 flex flex-wrap gap-3">
                    <a
                        href={contact.phone_href}
                        class="inline-flex items-center rounded-md bg-brand-secondary px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-brand-accent"
                    >
                        {contact.phone_display}
                    </a>
                    <a
                        href={`mailto:${contact.email}`}
                        class="inline-flex items-center rounded-md border border-brand-secondary px-4 py-2 text-sm font-medium text-brand-secondary transition-colors hover:bg-brand-secondary hover:text-white"
                    >
                        {contact.email}
                    </a>
                </div>
            </div>
        </div>
    </main>

    <AppFooter />
</div>
