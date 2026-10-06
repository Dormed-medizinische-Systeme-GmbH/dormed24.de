<?php

namespace App\Contracts;

interface CartStore
{
    /**
     * @return array<string, mixed>
     */
    public function get(): array;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function put(array $payload): void;
}
