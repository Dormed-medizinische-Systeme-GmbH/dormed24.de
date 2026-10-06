<?php

namespace App\Support\Cart;

use App\Contracts\CartStore;
use Illuminate\Session\Store;

class SessionCartStore implements CartStore
{
    public function __construct(
        private readonly Store $session,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function get(): array
    {
        $payload = $this->session->get($this->sessionKey(), []);

        return is_array($payload) ? $payload : [];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function put(array $payload): void
    {
        $this->session->put($this->sessionKey(), $payload);
    }

    private function sessionKey(): string
    {
        return (string) config('shop.cart.session_key', 'cart');
    }
}
