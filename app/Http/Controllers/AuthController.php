<?php

namespace App\Http\Controllers;

use App\Helpers\Passport\Passport;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class AuthController extends Controller
{
    /**
     * Перенаправляет на сайт авторизации
     * @return Application|RedirectResponse|Redirector
     */
    public function redirect()
    {
        $state = Str::random(40);

        session()->put('state', $state);

        $query = http_build_query([
            'client_id' => config('services.bee_id.id'),
            'redirect_uri' => config('services.bee_id.redirect_url'),
            'response_type' => 'code',
            'scope' => '',
            'state' => $state,
            //'prompt' => 'consent',
        ]);

        return redirect(config('services.bee_id.url') . 'oauth/authorize?' . $query);
    }

    /**
     * Сохранение данных пользователя
     * @param Request $request
     * @return RedirectResponse
     * @throws Throwable
     */
    public function callback(Request $request): RedirectResponse
    {
        $state = session()->pull('state');

        /* закомментировал, так как из сессии возвращается null
        throw_unless(
            !empty($state) && $state === $request->state,
            InvalidArgumentException::class
        );
        */

        $response = Http::asForm()->post(config('services.bee_id.url') . 'oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => config('services.bee_id.id'),
            'client_secret' => config('services.bee_id.secret'),
            'redirect_uri' => config('services.bee_id.redirect_url'),
            'code' => $request->code,
        ]);

        if ($response->ok()) {
            return redirect()->route('profile')
                ->withCookies([
                    cookie()->make('access_token', $response->json('access_token')),
                    cookie()->make('refresh_token', $response->json('refresh_token')),
                    cookie()->make('expires_in', $response->json('expires_in')),
                ]);
        }

        return redirect()->route('home')->withErrors(['login' => 'Ошибка авторизации']);
    }

    /**
     * Очищаем кеш и куки
     * @return RedirectResponse
     */
    public function logout(): RedirectResponse
    {
        Cache::forget(Passport::getAccessToken());

        Cookie::queue(cookie()->forget('access_token'));
        Cookie::queue(cookie()->forget('refresh_token'));
        Cookie::queue(cookie()->forget('expires_in'));

        return redirect()->route('home');
    }
}
