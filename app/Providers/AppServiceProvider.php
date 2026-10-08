<?php

namespace App\Providers;

use App\Auth\EnvAdminUserProvider;
use App\Contracts\CartStore;
use App\Services\PayPalGateway;
use App\Support\Cart\SessionCartStore;
use App\Support\ModeMailConfiguration;
use App\Support\Orders\Gateway\PaymentGateway;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\Factory as ViewFactory;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(CartStore::class, SessionCartStore::class);
        $this->app->bind(PaymentGateway::class, PayPalGateway::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /** @var ViewFactory $view */
        $view = view();
        $view->addLocation(resource_path());

        $this->configureDefaults();
        ModeMailConfiguration::apply();

        Auth::provider('env-admin', fn (Application $app): EnvAdminUserProvider => new EnvAdminUserProvider(
            $app->make(Hasher::class),
            (array) config('auth.admin', []),
        ));
    }

    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        // Surface N+1 queries during development/testing; in production a
        // lazy load stays a silent performance issue instead of a crash.
        Model::preventLazyLoading(! app()->isProduction());

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
