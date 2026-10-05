<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Einmalige Umzugshilfe: PayPal- und SMTP-Zugangsdaten lagen früher
 * (teils verschlüsselt) in der settings-Tabelle. Sie werden nicht mehr
 * gelesen — dieser Befehl gibt sie als Env-Zeilen aus, damit sie in die
 * Server-Env übernommen werden können. Danach können die Zeilen aus der
 * Datenbank entfernt werden.
 */
#[Signature('settings:legacy-credentials')]
#[Description('Gibt alte PayPal-/SMTP-Zugangsdaten aus der Datenbank als Env-Zeilen aus')]
class ShowLegacyCredentials extends Command
{
    /**
     * Alter Settings-Key => [Env-Variable, war verschlüsselt].
     *
     * Die frühere einzelne Webhook-ID wird beiden Modi zugeordnet; welche
     * PayPal-App sie tatsächlich gehört, muss beim Übernehmen geprüft werden.
     *
     * @var array<string, array{list<string>, bool}>
     */
    private const MAPPING = [
        'paypal.sandbox.client_id' => [['PAYPAL_SANDBOX_CLIENT_ID'], false],
        'paypal.sandbox.client_secret' => [['PAYPAL_SANDBOX_CLIENT_SECRET'], true],
        'paypal.sandbox.merchant_id' => [['PAYPAL_SANDBOX_MERCHANT_ID'], false],
        'paypal.live.client_id' => [['PAYPAL_LIVE_CLIENT_ID'], false],
        'paypal.live.client_secret' => [['PAYPAL_LIVE_CLIENT_SECRET'], true],
        'paypal.live.app_id' => [['PAYPAL_LIVE_APP_ID'], false],
        'paypal.live.merchant_id' => [['PAYPAL_LIVE_MERCHANT_ID'], false],
        'paypal.webhook_id' => [['PAYPAL_SANDBOX_WEBHOOK_ID', 'PAYPAL_LIVE_WEBHOOK_ID'], true],
        'mail.smtp_host' => [['MAIL_LIVE_HOST'], false],
        'mail.smtp_port' => [['MAIL_LIVE_PORT'], false],
        'mail.smtp_user' => [['MAIL_LIVE_USERNAME'], false],
        'mail.smtp_password' => [['MAIL_LIVE_PASSWORD'], true],
    ];

    public function handle(): int
    {
        $rows = DB::table('settings')
            ->whereIn('key', array_keys(self::MAPPING))
            ->pluck('value', 'key');

        if ($rows->filter()->isEmpty()) {
            $this->info('Keine alten PayPal-/SMTP-Zugangsdaten in der Datenbank gefunden.');

            return self::SUCCESS;
        }

        $this->warn('Enthält Geheimnisse — nur auf dem Server ausführen und die Ausgabe nicht weitergeben.');
        $this->newLine();

        foreach (self::MAPPING as $key => [$envNames, $encrypted]) {
            $value = $rows->get($key);

            if (blank($value)) {
                continue;
            }

            if ($encrypted) {
                // Früher per encrypt() (serialisiert) gespeichert, daher decrypt().
                try {
                    $value = (string) Crypt::decrypt((string) $value);
                } catch (\Throwable) {
                    $this->error("{$key}: konnte nicht entschlüsselt werden (anderer APP_KEY?).");

                    continue;
                }
            }

            foreach ($envNames as $envName) {
                $this->line($envName."='".str_replace("'", "\\'", (string) $value)."'");
            }
        }

        $this->newLine();
        $this->comment('SMTP-Werte sind als MAIL_LIVE_* ausgegeben; für die Sandbox eigene MAIL_SANDBOX_* setzen.');

        return self::SUCCESS;
    }
}
