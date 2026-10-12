<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Trusted Proxies
    |--------------------------------------------------------------------------
    |
    | Proxies, deren X-Forwarded-*-Header (Schema, Host, Client-IP) Laravel
    | übernimmt: "*" für jeden Absender oder eine Komma-Liste von IPs/Netzen.
    |
    | Leer lassen, wenn die App direkt erreichbar ist, sonst kann jeder
    | Besucher diese Header fälschen. Im Docker-Stack setzt compose.yaml "*":
    | Der Container ist nur über 127.0.0.1 erreichbar, davor steht der Nginx
    | auf dem Host, der TLS beendet.
    |
    */

    'proxies' => env('TRUSTED_PROXIES'),

];
