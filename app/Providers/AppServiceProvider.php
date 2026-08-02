<?php

namespace App\Providers;

use App\Support\Otp\LogOtpChannel;
use App\Support\Otp\OtpChannel;
use App\Support\Otp\StaticOtpChannel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(OtpChannel::class, fn () => match (config('goldscore.otp.driver')) {
            'log' => new LogOtpChannel,
            default => new StaticOtpChannel((string) config('goldscore.otp.static_code')),
        });
    }

    public function boot(): void
    {
        // Also on in tests, so silently discarded mass-assignments fail loudly
        // during a test run rather than in front of a jeweller.
        Model::shouldBeStrict(! $this->app->isProduction());
        Paginator::useBootstrapFive();

        Blade::directive('active', function ($expression) {
            return "<?php echo \\Illuminate\\Support\\Facades\\Route::is($expression) ? 'active' : ''; ?>";
        });
    }
}
