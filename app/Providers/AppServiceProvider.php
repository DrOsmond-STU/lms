<?php

declare(strict_types=1);

namespace App\Providers;

use App\Modules\Access\Permissions;
use App\Modules\Identity\Models\User;
use App\Support\Security\ProductionGuard;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Satu konteks tenant per request/job.
        $this->app->scoped(TenantContext::class);
    }

    public function boot(): void
    {
        // Staging diperlakukan seketat produksi (keamanan/13 SEC-INFRA-34).
        if ($this->app->environment('production', 'staging')) {
            ProductionGuard::enforce();
            URL::forceScheme('https');
        }

        // Proxy tepercaya dari konfigurasi (aman dengan config:cache).
        TrustProxies::at(config('security.trusted_proxies', []));

        // Semua URL absolut (termasuk di email) dibangun dari APP_URL, bukan header Host.
        URL::forceRootUrl((string) config('app.url'));

        // Non-produksi: tangkap mass assignment diam-diam, lazy loading, atribut hilang.
        Model::shouldBeStrict(! $this->app->isProduction());

        // Kebijakan kata sandi dasar (keamanan/02 SEC-AUTH-02). Minimum per peran
        // (12 untuk peran istimewa) ditegakkan di layanan terkait.
        Password::defaults(function () {
            $rule = Password::min((int) config('security.password.min_participant'))
                ->max((int) config('security.password.max'));

            return $this->app->environment('production', 'staging') ? $rule->uncompromised() : $rule;
        });

        // Izin berbasis kode `resource.action` (docs/07). Tidak ada bypass global untuk
        // Super Admin — izinnya didefinisikan eksplisit (keamanan/03 SEC-AUTHZ-19).
        Gate::before(function (User $user, string $ability): ?bool {
            if (! Permissions::exists($ability)) {
                return null; // biarkan Policy yang memutuskan
            }

            return $user->hasPermission($ability) ? true : null;
        });
    }
}
