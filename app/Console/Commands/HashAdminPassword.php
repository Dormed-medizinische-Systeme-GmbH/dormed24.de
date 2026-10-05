<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

#[Signature('admin:hash-password')]
#[Description('Erzeugt den Passwort-Hash für ADMIN_PASSWORD (Admin-Zugang aus der Env)')]
class HashAdminPassword extends Command
{
    public function handle(): int
    {
        while (true) {
            $password = (string) $this->secret('Neues Admin-Passwort (mind. 12 Zeichen)');

            if (mb_strlen($password) < 12) {
                $this->error('Das Passwort muss mindestens 12 Zeichen lang sein.');

                continue;
            }

            if ($password !== (string) $this->secret('Passwort wiederholen')) {
                $this->error('Die Passwörter stimmen nicht überein.');

                continue;
            }

            break;
        }

        $this->newLine();
        $this->line('In die Env übernehmen (in einer .env-Datei in einfachen Anführungszeichen):');
        $this->newLine();
        $this->line("ADMIN_PASSWORD='".Hash::make($password)."'");
        $this->newLine();
        $this->comment('Danach `php artisan config:clear` bzw. `php artisan optimize`, falls die Config gecacht ist.');

        return self::SUCCESS;
    }
}
