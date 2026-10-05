<?php

namespace App\Providers;

use App\Auth\EnvAdminUserProvider;
use App\Contracts\CartStore;
use App\Models\Setting;
use App\Support\Cart\SessionCartStore;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Foundation\Application;
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
        $this->configureFromDatabase();

        Auth::provider('env-admin', fn (Application $app): EnvAdminUserProvider => new EnvAdminUserProvider(
            $app['hash'],
            (array) $app['config']->get('auth.admin', []),
        ));
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureFromDatabase(): void
    {
        try {
            $smtpHost = Setting::get('mail.smtp_host');
            if ($smtpHost) {
                config([
                    'mail.default' => 'smtp',
                    'mail.mailers.smtp.host' => $smtpHost,
                    'mail.mailers.smtp.port' => Setting::get('mail.smtp_port', '587'),
                    'mail.mailers.smtp.username' => Setting::get('mail.smtp_user'),
                    'mail.mailers.smtp.password' => Setting::get('mail.smtp_password'),
                ]);
            }

            $smtpUser = Setting::get('mail.smtp_user');
            if ($smtpUser) {
                config(['mail.from.address' => $smtpUser]);
            } elseif ($shopEmail = Setting::get('shop.email')) {
                config(['mail.from.address' => $shopEmail]);
            }

            $shopName = Setting::get('shop.name');
            if ($shopName) {
                config(['mail.from.name' => $shopName]);
            }
        } catch (\Throwable) {
            // DB not yet available (e.g. during migrations)
        }
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
