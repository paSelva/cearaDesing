<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Http\Responses\NeutralPasswordResetLinkResponse;
use App\Http\Responses\PanelLoginResponse;
use App\Http\Responses\RoleHomeLoginResponse;
use App\Models\User;
use Filament\Auth\Http\Responses\Contracts\LoginResponse as FilamentLoginResponse;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(FailedPasswordResetLinkRequestResponse::class, NeutralPasswordResetLinkResponse::class);
        $this->app->singleton(LoginResponse::class, RoleHomeLoginResponse::class);
        $this->app->bind(FilamentLoginResponse::class, PanelLoginResponse::class);
    }

    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        Fortify::loginView(fn () => view('auth.login'));
        Fortify::registerView(fn () => view('auth.register'));
        Fortify::requestPasswordResetLinkView(fn () => view('auth.forgot-password'));
        Fortify::resetPasswordView(fn (Request $request) => view('auth.reset-password', ['request' => $request]));
        // Confirmação de senha não é usada nesta etapa (sem 2FA/áreas sensíveis): 404 em vez de erro.
        Fortify::confirmPasswordView(fn () => abort(404));

        // Mesma resposta genérica para senha errada e para papel sem acesso ao site,
        // para não revelar quais e-mails pertencem a admin ou jurado.
        Fortify::authenticateUsing(function (Request $request): ?User {
            $user = User::query()->where('email', Str::lower((string) $request->input(Fortify::username())))->first();

            if ($user === null
                || ! $user->role->usesSiteAuth()
                || ! Hash::check((string) $request->input('password'), $user->password)) {
                return null;
            }

            return $user;
        });

        RateLimiter::for('login', function (Request $request) {
            // Roda antes da validação: o e-mail pode vir como lista.
            $email = $request->input(Fortify::username());
            $throttleKey = Str::transliterate(Str::lower(is_string($email) ? $email : '').'|'.$request->ip());

            // Por IP, contra a mesma senha testada em muitos e-mails. Folgado: uma universidade
            // ou empresa inteira pode sair por um IP só.
            return [
                Limit::perMinute(5)->by($throttleKey),
                Limit::perMinute(30)->by('ip|'.$request->ip()),
            ];
        });

        // Demais formulários de conta, por IP (config/fortify.php → middleware).
        RateLimiter::for('conta', fn (Request $request) => match ($request->route()?->getName()) {
            'password.email' => Limit::perHour(10)->by('reset-pedido|'.$request->ip()),
            'password.update' => Limit::perHour(10)->by('reset-envio|'.$request->ip()),
            // Só cadastro concluído conta: erro de preenchimento não gasta a cota de uma rede compartilhada.
            'register.store' => Limit::perHour(20)->by('cadastro|'.$request->ip())->after(fn () => Auth::check()),
            default => Limit::none(),
        });

        // O link do e-mail usa sempre o domínio configurado, nunca o Host informado por quem pediu.
        ResetPassword::createUrlUsing(fn (User $user, string $token) => rtrim((string) config('app.url'), '/')
            .route('password.reset', ['token' => $token, 'email' => $user->getEmailForPasswordReset()], false));
    }
}
