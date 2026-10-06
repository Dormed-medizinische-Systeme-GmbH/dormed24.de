<script lang="ts">
    import { Link, page } from '@inertiajs/svelte';
    import AppFooter from '@/components/AppFooter.svelte';
    import AppHead from '@/components/AppHead.svelte';
    import ShopHeader from '@/components/ShopHeader.svelte';

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

<AppHead
    title="Datenschutzerklärung"
    description="Datenschutzerklärung des dormed24-Online-Shops der Dormed medizinische Systeme GmbH."
/>

<div class="flex min-h-screen flex-col bg-white">
    <ShopHeader />

    <main class="flex-1 mx-auto max-w-7xl px-4 py-10 lg:px-8">
        <h1 class="mb-8 text-2xl font-bold text-brand-secondary">
            Datenschutzerklärung
        </h1>

        <p class="mb-8 text-sm text-muted-foreground">
            Wir freuen uns über Ihren Besuch auf unserer Webseite und Ihr
            Interesse an unserem Unternehmen und unseren Angeboten.
        </p>

        <div class="space-y-10 text-sm text-muted-foreground">
            <!-- 1 -->
            <section>
                <h2 class="mb-4 text-base font-bold text-brand-secondary">
                    1. Erhebung und Speicherung personenbezogener Daten
                </h2>

                <div class="space-y-6">
                    <div>
                        <h3 class="mb-2 font-semibold text-foreground">
                            a) Beim Besuch unserer Webseite
                        </h3>
                        <p class="mb-3">
                            Bei jedem Zugriff auf unsere Webseite werden durch
                            den auf Ihrem Endgerät (Computer, Laptop, Tablet,
                            Smartphone, etc.) zum Einsatz kommenden
                            Internet-Browser automatisch Informationen an den
                            Server unserer Webseite gesendet. Diese
                            Informationen werden temporär in einem sogenannten
                            Logfile gespeichert. Folgende Informationen werden
                            dabei ohne Ihr Zutun erfasst und bis zur
                            automatisierten Löschung gespeichert:
                        </p>
                        <ul class="mb-3 ml-4 list-disc space-y-1">
                            <li>
                                IP-Adresse des anfragenden Rechners sowie
                                Geräte-ID und Gerätetyp
                            </li>
                            <li>
                                Name der abgerufenen Datei, übertragene
                                Datenmenge, Datum und Uhrzeit des Abrufs
                            </li>
                            <li>Meldung über erfolgreichen Abruf</li>
                            <li>Anfordernde Domain</li>
                            <li>
                                Beschreibung des Typs des verwendeten
                                Internet-Browsers und des verwendeten
                                Betriebssystems sowie der Name Ihres
                                Access-Providers
                            </li>
                            <li>
                                Browser-Verlauf und standardmäßige
                                Weblog-Informationen
                            </li>
                            <li>
                                Standortdaten, sofern Sie die Übertragung
                                erlaubt haben
                            </li>
                        </ul>
                        <p>
                            Die genannten Daten werden durch uns zu folgenden
                            Zwecken verarbeitet: Gewährleistung eines
                            reibungslosen Verbindungsaufbaus der Webseite und
                            komfortable Nutzung unserer Webseite sowie zur
                            Auswertung der Systemsicherheit und -stabilität. Die
                            Rechtsgrundlage für die Datenverarbeitung ist Art. 6
                            Abs. 1 S. 1 lit. f DSGVO.
                        </p>
                    </div>

                    <div>
                        <h3 class="mb-2 font-semibold text-foreground">
                            b) Bei Nutzung unseres Kontaktformulars
                        </h3>
                        <p class="mb-3">
                            Bei Fragen jeglicher Art bieten wir Ihnen die
                            Möglichkeit, mit uns über ein auf der Webseite
                            bereitgestelltes Formular Kontakt aufzunehmen. Dabei
                            ist die Angabe einer gültigen E-Mail-Adresse, Ihres
                            Namens, Ihrer Firma sowie Ihrer Telefonnummer
                            erforderlich, damit wir wissen, von wem die Anfrage
                            stammt und um diese beantworten zu können.
                        </p>
                        <p>
                            Die Datenverarbeitung zum Zwecke der Kontaktaufnahme
                            mit uns erfolgt gemäß Art. 6 Abs. 1 S. 1 lit. a
                            DSGVO auf Grundlage Ihrer freiwillig erteilten
                            Einwilligung. Die für die Benutzung des
                            Kontaktformulars von uns erhobenen personenbezogenen
                            Daten werden nach Erledigung der von Ihnen
                            gestellten Anfrage automatisch gelöscht.
                        </p>
                    </div>

                    <div>
                        <h3 class="mb-2 font-semibold text-foreground">
                            c) Bei Abschluss eines Vertragsverhältnisses
                        </h3>
                        <p class="mb-3">
                            Bei Abschluss eines Vertragsverhältnisses auf
                            unserer Webseite bitten wir Sie um die Angabe
                            folgender personenbezogener Daten:
                        </p>
                        <ul class="mb-3 ml-4 list-disc space-y-1">
                            <li>Name, E-Mail-Adresse</li>
                            <li>
                                Adresse, Liefer- und Rechnungsadresse,
                                Telefonnummer
                            </li>
                            <li>Akademischer Titel</li>
                            <li>
                                Unternehmensdaten (Firma, Adresse,
                                Kommunikationsdaten, USt-ID, Steuernummer)
                            </li>
                            <li>
                                Zahlungsmethode und zugehörige Informationen
                            </li>
                            <li>
                                Daten zur Authentifizierung und
                                Identitätsprüfung
                            </li>
                        </ul>
                        <p class="mb-3">
                            Die Verarbeitung der von Ihnen eingegebenen Daten
                            erfolgt auf Grundlage von Art. 6 Abs. 1 lit. b
                            DSGVO. Die Speicherung der Daten ist auf die Dauer
                            des Vertragsverhältnisses sowie auf die gesetzlichen
                            Aufbewahrungspflichten beschränkt.
                        </p>
                        <p>
                            Unsere Angebote auf dieser Webseite richten sich
                            sowohl an Privatpersonen als auch an Unternehmen.
                            Die Angabe von Unternehmensdaten (z.B. Firma,
                            USt-ID, Steuernummer) ist freiwillig und erfolgt
                            nur, wenn eine gewerbliche Rechnung benötigt wird.
                        </p>
                    </div>

                    <div>
                        <h3 class="mb-2 font-semibold text-foreground">
                            d) Zahlungsdienstleister
                        </h3>
                        <p>
                            Zur Bezahlung von mit uns kostenpflichtig
                            abgeschlossenen Verträgen arbeiten wir mit
                            Zahlungsdienstleistern zusammen. Die Weitergabe
                            Ihrer Zahlungsdaten an den jeweiligen
                            Zahlungsdienstleister erfolgt auf Grundlage von Art.
                            6 Abs. 1 lit. b DSGVO, soweit die Weitergabe für die
                            Zahlungsabwicklung erforderlich ist.
                        </p>
                    </div>

                    <div>
                        <h3 class="mb-2 font-semibold text-foreground">
                            e) Newsletter
                        </h3>
                        <p>
                            Sofern Sie nach Art. 6 Abs. 1 S. 1 lit. a DSGVO
                            ausdrücklich eingewilligt haben, verwenden wir Ihre
                            E-Mail-Adresse dafür, Ihnen regelmäßig unseren
                            Newsletter zu übersenden. Zur Abmeldung vom
                            Newsletter genügt eine E-Mail an uns mit dem Betreff
                            „Abmeldung Newsletter" oder Sie nutzen den
                            Abmeldelink im Newsletter.
                        </p>
                    </div>
                </div>
            </section>

            <!-- 2 -->
            <section>
                <h2 class="mb-3 text-base font-bold text-brand-secondary">
                    2. Weitergabe von Daten an Dritte
                </h2>
                <p class="mb-3">
                    Eine Übermittlung Ihrer Daten an Dritte zu anderen als den
                    im Folgenden aufgeführten Zwecken findet nicht statt. Wir
                    geben Ihre persönlichen Daten nur an Dritte weiter, wenn:
                </p>
                <ul class="mb-3 ml-4 list-disc space-y-1">
                    <li>
                        Sie Ihre nach Art. 6 Abs. 1 S. 1 lit. a DSGVO
                        ausdrückliche Einwilligung dazu erteilt haben,
                    </li>
                    <li>
                        die Weitergabe nach Art. 6 Abs. 1 S. 1 lit. b DSGVO für
                        die Abwicklung von Vertragsverhältnissen mit Ihnen
                        erforderlich ist,
                    </li>
                    <li>
                        nach Art. 6 Abs. 1 S. 1 lit. c DSGVO eine gesetzliche
                        Verpflichtung zur Weitergabe besteht, oder
                    </li>
                    <li>
                        die Weitergabe nach Art. 6 Abs. 1 S. 1 lit. f DSGVO zur
                        Geltendmachung, Ausübung oder Verteidigung von
                        Rechtsansprüchen erforderlich ist.
                    </li>
                </ul>
                <p>
                    Unsere Datenschutzbestimmungen stehen im Einklang mit den
                    geltenden datenschutzrechtlichen Bestimmungen und die Daten
                    werden nur in der Bundesrepublik Deutschland / Europäischen
                    Union verarbeitet. Eine Übermittlung in Drittländer findet
                    nicht statt und ist nicht beabsichtigt.
                </p>
            </section>

            <!-- 3 -->
            <section>
                <h2 class="mb-3 text-base font-bold text-brand-secondary">
                    3. Betroffenenrechte
                </h2>
                <p class="mb-3">Sie haben das Recht:</p>
                <ul class="mb-3 ml-4 list-disc space-y-2">
                    <li>
                        <span class="font-semibold"
                            >Auskunft (Art. 15 DSGVO):</span
                        > Auf Anfrage informieren wir Sie, ob und welche personenbezogenen
                        Daten zu Ihrer Person gespeichert sind, zu welchem Zweck,
                        aus welchen Quellen und gegenüber welchen Empfängern.
                    </li>
                    <li>
                        <span class="font-semibold"
                            >Berichtigung (Art. 16 DSGVO):</span
                        > Sie können die Berichtigung unrichtiger oder die Vervollständigung
                        unvollständiger Daten verlangen.
                    </li>
                    <li>
                        <span class="font-semibold"
                            >Einschränkung der Verarbeitung (Art. 18 DSGVO):</span
                        > Sie können unter den gesetzlichen Voraussetzungen die Einschränkung
                        der Verarbeitung verlangen.
                    </li>
                    <li>
                        <span class="font-semibold"
                            >Datenübertragbarkeit (Art. 20 DSGVO):</span
                        > Sie haben das Recht, die Sie betreffenden personenbezogenen
                        Daten in einem strukturierten, gängigen und maschinenlesbaren
                        Format zu erhalten oder die Übermittlung an einen anderen
                        Verantwortlichen zu verlangen.
                    </li>
                    <li>
                        <span class="font-semibold"
                            >Löschung (Art. 17 DSGVO):</span
                        > Sie können die Löschung Ihrer personenbezogenen Daten verlangen,
                        sofern hierfür die gesetzlichen Voraussetzungen vorliegen.
                        Eine automatische Löschung erfolgt, wenn der Zweck der Speicherung
                        entfällt oder die Verarbeitung unzulässig ist.
                    </li>
                    <li>
                        <span class="font-semibold"
                            >Widerruf der Einwilligung (Art. 7 Abs. 3 DSGVO):</span
                        > Sie haben das Recht, Ihre einmal erteilte Einwilligung jederzeit
                        gegenüber uns zu widerrufen. Dies hat zur Folge, dass wir
                        die Datenverarbeitung, die auf dieser Einwilligung beruhte,
                        für die Zukunft nicht mehr fortführen dürfen.
                    </li>
                    <li>
                        <span class="font-semibold"
                            >Widerspruch (Art. 21 DSGVO):</span
                        > Sie haben das Recht, jederzeit gegen die Verarbeitung Ihrer
                        personenbezogenen Daten Widerspruch zu erheben, sofern ein
                        Widerspruchsrecht gesetzlich vorgesehen ist.
                    </li>
                    <li>
                        <span class="font-semibold"
                            >Beschwerde (Art. 77 DSGVO):</span
                        > Wenn Sie der Ansicht sind, dass die Verarbeitung der Sie
                        betreffenden personenbezogenen Daten gegen die DSGVO verstößt,
                        haben Sie das Recht auf Beschwerde bei einer Aufsichtsbehörde.
                    </li>
                </ul>
                <p>
                    Zur Ausübung Ihrer Rechte wenden Sie sich bitte an:
                    <a
                        href="mailto:datenschutz@dormed24.de"
                        class="text-brand-accent hover:underline"
                        >datenschutz@dormed24.de</a
                    >
                </p>
            </section>

            <!-- 4 -->
            <section>
                <h2 class="mb-3 text-base font-bold text-brand-secondary">
                    4. Speicherdauer
                </h2>
                <p>
                    Die erhobenen Daten werden solange bei uns gespeichert, wie
                    dies für die Durchführung der mit uns eingegangenen Verträge
                    erforderlich ist oder Sie Ihr Recht auf Löschung oder Ihr
                    Recht auf Datenübertragung auf ein anderes Unternehmen nicht
                    ausgeübt haben.
                </p>
            </section>

            <!-- 5 -->
            <section>
                <h2 class="mb-3 text-base font-bold text-brand-secondary">
                    5. Cookies
                </h2>
                <p class="mb-3">
                    Wir setzen auf unserer Webseite Cookies ein. Hierbei handelt
                    es sich um kleine Text-Dateien, die Ihr Browser automatisch
                    erstellt und die auf Ihrem Endgerät gespeichert werden, wenn
                    Sie unsere Seite besuchen.
                </p>
                <div class="space-y-3">
                    <p>
                        <span class="font-semibold">Session-Cookies</span> erkennen,
                        dass Sie einzelne Unterseiten unserer Webseite bereits besucht
                        haben. Sie werden nach Verlassen unserer Seite automatisch
                        gelöscht.
                    </p>
                    <p>
                        <span class="font-semibold">Temporäre Cookies</span> werden
                        für einen bestimmten festgelegten Zeitraum auf Ihrem Endgerät
                        gespeichert und dienen der Optimierung der Benutzerfreundlichkeit.
                        Sie erkennen wiederkehrende Besucher und merken sich vorherige
                        Eingaben und Einstellungen.
                    </p>
                    <p>
                        <span class="font-semibold">Analyse-Cookies</span> dienen
                        der statistischen Erfassung und Auswertung der Nutzung unserer
                        Webseite sowie zur Optimierung unseres Angebots für Sie. Rechtsgrundlage
                        ist Art. 6 Abs. 1 lit. f DSGVO.
                    </p>
                    <p>
                        Die meisten Browser akzeptieren Cookies automatisch. Sie
                        können Ihren Browser jedoch so einstellen, dass keine
                        Cookies auf Ihrem Computer gespeichert werden oder stets
                        ein Hinweis erscheint, bevor ein neues Cookie angelegt
                        wird. Die vollständige Deaktivierung von Cookies kann
                        jedoch dazu führen, dass Sie nicht alle Funktionen
                        unserer Webseite nutzen können.
                    </p>
                </div>
            </section>

            <!-- 6 -->
            <section>
                <h2 class="mb-3 text-base font-bold text-brand-secondary">
                    6. Online-Marketing und Analyse-Tools
                </h2>
                <p class="mb-4">
                    Die von uns eingesetzten Tracking-Maßnahmen werden auf
                    Grundlage des Art. 6 Abs. 1 S. 1 lit. f DSGVO, § 15 Abs. 3
                    TMG durchgeführt. Zweck ist eine bedarfsgerechte Gestaltung
                    und fortlaufende Optimierung unserer Webseiten.
                </p>

                <div class="space-y-6">
                    <div>
                        <h3 class="mb-2 font-semibold text-foreground">
                            Google Analytics
                        </h3>
                        <p class="mb-2">
                            Zum Zwecke der bedarfsgerechten Gestaltung und
                            fortlaufenden Optimierung unserer Webseiten nutzen
                            wir Google Analytics, einen Webanalysedienst der
                            Google Inc. Durch den Cookie erzeugten Informationen
                            über Ihre Benutzung dieser Webseite
                            (Browser-Typ/-Version, Betriebssystem, Referrer-URL,
                            Hostname, Uhrzeit) werden an einen Server von Google
                            in den USA übertragen und dort gespeichert.
                        </p>
                        <p class="mb-2">
                            Die IP-Adressen werden anonymisiert, sodass eine
                            Zuordnung nicht möglich ist (IP-Masking). Die von
                            uns gesendeten und mit Cookies oder Nutzerkennungen
                            verknüpften Daten werden nach 14 Monaten automatisch
                            gelöscht.
                        </p>
                        <p>
                            Sie können die Speicherung der Cookies durch eine
                            entsprechende Einstellung Ihrer Browser-Software
                            verhindern oder die Erfassung der durch das Cookie
                            erzeugten und auf Ihre Nutzung der Webseite
                            bezogenen Daten an Google sowie die Verarbeitung
                            dieser Daten durch Google verhindern, indem Sie das
                            unter dem folgenden Link verfügbare Browser-Plugin
                            herunterladen und installieren:
                            <a
                                href="https://tools.google.com/dlpage/gaoptout?hl=de"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="text-brand-accent hover:underline"
                                >tools.google.com/dlpage/gaoptout</a
                            >
                        </p>
                    </div>

                    <div>
                        <h3 class="mb-2 font-semibold text-foreground">
                            Google AdWords / Conversion-Tracking
                        </h3>
                        <p>
                            Wir nutzen das Online-Werbeprogramm „Google AdWords"
                            und im Rahmen von Google AdWords das
                            Conversion-Tracking. Wenn Sie auf eine von Google
                            geschaltete Anzeige klicken, wird ein Cookie für das
                            Conversion-Tracking auf Ihrem Endgerät abgelegt.
                            Diese Cookies verlieren nach 30 Tagen ihre
                            Gültigkeit und enthalten keine personenbezogenen
                            Daten. Sie können die Cookies für das
                            Conversion-Tracking in Ihrem Browser deaktivieren,
                            indem Sie Cookies von der Domain
                            „googleadservices.com" blockieren.
                        </p>
                    </div>

                    <div>
                        <h3 class="mb-2 font-semibold text-foreground">
                            Google Remarketing
                        </h3>
                        <p>
                            Diese Webseite benutzt Google Remarketing, einen
                            Werbedienst der Google Inc. Drittanbieter,
                            einschließlich Google, schalten Anzeigen auf
                            Websites im Internet auf Grundlage vorheriger
                            Besuche eines Nutzers auf dieser Website. Sie können
                            die Verwendung von Cookies durch Google
                            deaktivieren, indem Sie die
                            <a
                                href="http://www.google.com/privacy_ads.html"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="text-brand-accent hover:underline"
                                >Seite zur Deaktivierung von Google-Anzeigen</a
                            > aufrufen.
                        </p>
                    </div>

                    <div>
                        <h3 class="mb-2 font-semibold text-foreground">
                            Social Plugins (Facebook, Instagram, XING, LinkedIn,
                            YouTube)
                        </h3>
                        <p>
                            Auf unserer Webseite sind Social Plugins
                            verschiedener Netzwerke integriert. Wenn Sie unsere
                            Webseite mit einem solchen Plugin öffnen, wird über
                            Ihren Browser eine direkte Verbindung mit den
                            Servern des jeweiligen Netzwerks hergestellt. Wenn
                            Sie in Ihrem Account eingeloggt sind, kann der
                            Besuch unserer Webseite direkt Ihrem Profil
                            zugeordnet werden. Wir weisen darauf hin, dass wir
                            als Anbieter unserer Webseite keine Kenntnis vom
                            Inhalt der übermittelten Daten sowie deren Nutzung
                            durch die Netzwerke erhalten.
                        </p>
                    </div>
                </div>
            </section>

            <!-- 7 -->
            <section>
                <h2 class="mb-3 text-base font-bold text-brand-secondary">
                    7. Zahlungsdienstleister
                </h2>

                <div class="space-y-6">
                    <div>
                        <h3 class="mb-2 font-semibold text-foreground">
                            Klarna
                        </h3>
                        <p>
                            Bei Auswahl einer Klarna-Zahlungsdienstleistung
                            erfolgt die Zahlungsabwicklung über die Klarna Bank
                            AB (publ), Sveavägen 46, 111 34 Stockholm, Schweden.
                            Zur Abwicklung der Zahlung werden Ihre persönlichen
                            Daten (Vor- und Nachname, Straße, Hausnummer,
                            Postleitzahl, Ort, Geschlecht, E-Mail-Adresse,
                            Telefonnummer und IP-Adresse) sowie Bestelldaten zum
                            Zweck der Identitäts- und Bonitätsprüfung an Klarna
                            weitergegeben. Rechtsgrundlage ist Art. 6 Abs. 1
                            lit. a DSGVO. Sie können Ihre Einwilligung jederzeit
                            widerrufen.
                        </p>
                    </div>

                    <div>
                        <h3 class="mb-2 font-semibold text-foreground">
                            PayPal
                        </h3>
                        <p>
                            Bei Zahlung via PayPal geben wir Ihre Zahlungsdaten
                            im Rahmen der Zahlungsabwicklung an die PayPal
                            (Europe) S.a.r.l. et Cie, S.C.A., 22-24 Boulevard
                            Royal, L-2449 Luxembourg weiter. Rechtsgrundlage ist
                            Art. 6 Abs. 1 lit. b DSGVO. PayPal behält sich für
                            bestimmte Zahlungsmethoden die Durchführung einer
                            Bonitätsauskunft vor. Weitere Informationen finden
                            Sie in der
                            <a
                                href="https://www.paypal.com/de/webapps/mpp/ua/privacy-full"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="text-brand-accent hover:underline"
                                >Datenschutzerklärung von PayPal</a
                            >.
                        </p>
                    </div>

                    <div>
                        <h3 class="mb-2 font-semibold text-foreground">
                            SOFORT
                        </h3>
                        <p>
                            Bei Auswahl der Zahlungsart „SOFORT" erfolgt die
                            Zahlungsabwicklung über die SOFORT GmbH,
                            Theresienhöhe 12, 80339 München, Deutschland (Teil
                            der Klarna Group). Die Weitergabe Ihrer Daten
                            erfolgt ausschließlich zum Zweck der
                            Zahlungsabwicklung und nur insoweit, als sie hierfür
                            erforderlich ist. Rechtsgrundlage ist Art. 6 Abs. 1
                            lit. b DSGVO.
                        </p>
                    </div>
                </div>
            </section>

            <!-- 8 -->
            <section>
                <h2 class="mb-3 text-base font-bold text-brand-secondary">
                    8. Datensicherheit
                </h2>
                <p class="mb-3">
                    Wir sind um alle notwendigen technischen und
                    organisatorischen Sicherheitsmaßnahmen bemüht, um Ihre
                    personenbezogenen Daten so zu speichern, dass sie weder
                    Dritten noch der Öffentlichkeit zugänglich sind.
                </p>
                <p>
                    Sollten Sie mit uns per E-Mail in Kontakt treten wollen, so
                    weisen wir Sie darauf hin, dass bei diesem Kommunikationsweg
                    die Vertraulichkeit der übermittelten Informationen nicht
                    vollständig gewährleistet werden kann. Wir empfehlen Ihnen
                    daher, uns vertrauliche Informationen ausschließlich über
                    den Postweg zu übersenden.
                </p>
            </section>

            <!-- 9 -->
            <section>
                <h2 class="mb-3 text-base font-bold text-brand-secondary">
                    9. Aktualität und Änderung dieser Datenschutzerklärung
                </h2>
                <p>
                    Diese Datenschutzerklärung ist aktuell gültig. Durch die
                    Weiterentwicklung unserer Webseite und Angebote darüber oder
                    aufgrund geänderter gesetzlicher beziehungsweise
                    behördlicher Vorgaben kann es notwendig werden, diese
                    Datenschutzerklärung zu ändern. Die jeweils aktuelle
                    Datenschutzerklärung kann jederzeit auf unserer Webseite
                    unter
                    <Link
                        href="/unternehmen/datenschutz"
                        class="text-brand-accent hover:underline"
                        >dormed-shop.test/unternehmen/datenschutz</Link
                    >
                    abgerufen und ausgedruckt werden.
                </p>
            </section>

            <!-- 10 -->
            <section>
                <h2 class="mb-3 text-base font-bold text-brand-secondary">
                    10. Verantwortlicher
                </h2>
                <address class="not-italic leading-relaxed">
                    Dormed medizinische Systeme GmbH<br />
                    Wilhelm-Röntgen-Straße 4<br />
                    59439 Holzwickede<br /><br />
                    Telefon:
                    <a
                        href={contact.phone_href}
                        class="text-brand-accent hover:underline"
                        >{contact.phone_display}</a
                    ><br />
                    E-Mail:
                    <a
                        href={`mailto:${contact.email}`}
                        class="text-brand-accent hover:underline"
                        >{contact.email}</a
                    ><br /><br />
                    Datenschutzanfragen:
                    <a
                        href="mailto:datenschutz@dormed24.de"
                        class="text-brand-accent hover:underline"
                        >datenschutz@dormed24.de</a
                    >
                </address>
            </section>
        </div>
    </main>

    <AppFooter />
</div>
