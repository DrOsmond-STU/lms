<?php

declare(strict_types=1);

namespace App\Providers;

use App\Modules\Access\Permissions;
use App\Modules\Identity\Models\User;
use App\Modules\Settings\Services\SystemSettings;
use App\Support\Media\ClamdScanner;
use App\Support\Media\MalwareScanner;
use App\Support\Media\UnscannedScanner;
use App\Support\Security\ProductionGuard;
use App\Support\Tenancy\TenantContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Satu konteks tenant per request/job.
        $this->app->scoped(TenantContext::class);

        $this->app->singleton(MalwareScanner::class, fn () => config('media.scanner') === 'clamd'
            ? new ClamdScanner((string) config('media.clamd_address'))
            : new UnscannedScanner);
    }

    public function boot(): void
    {
        // Parameter rute berformat UUID divalidasi sebelum menyentuh basis data (ID tidak
        // valid → 404, bukan galat SQL). Token tautan: 43 karakter base64url.
        $uuid = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}';
        // Pengaturan UI (dalam batas aman) menimpa konfigurasi baseline.
        SystemSettings::applyToConfig();

        Route::patterns(array_fill_keys([
            'organization', 'user', 'domain', 'assignment', 'program', 'class', 'module', 'chapter', 'lesson', 'enrollment',
            'assessment', 'attempt', 'bank', 'question', 'certificate', 'template', 'approval', 'member', 'media', 'notification', 'session',
        ], $uuid) + ['token' => '[A-Za-z0-9_-]{16,128}']);

        // Verifikasi publik: 10/menit & 100/hari per IP (keamanan/07 SEC-CERT-12).
        RateLimiter::for('verification', fn (Request $request) => [
            Limit::perMinute(10)->by('verify-m:'.$request->ip()),
            Limit::perDay(100)->by('verify-d:'.$request->ip()),
        ]);

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
