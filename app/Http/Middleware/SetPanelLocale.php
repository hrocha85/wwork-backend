<?php

namespace App\Http\Middleware;

use App\Enums\Locale;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetPanelLocale
{
    public const COOKIE = 'wwork_panel_locale';

    public function handle(Request $request, Closure $next): Response
    {
        app()->setLocale($this->locale($request));

        return $next($request);
    }

    private function locale(Request $request): string
    {
        $chosen = $request->cookie(self::COOKIE);
        if (is_string($chosen) && $this->supported($chosen)) {
            return $chosen;
        }

        $session = session('panel_locale');
        if (is_string($session) && $this->supported($session)) {
            return $session;
        }

        foreach ($request->getLanguages() as $tag) {
            $primary = strtolower(substr($tag, 0, 2));
            if ($this->supported($primary)) {
                return $primary;
            }
        }

        $user = $request->user('staff');
        if ($user instanceof User && $user->locale instanceof Locale) {
            return $user->locale->value;
        }

        return (string) config('app.locale');
    }

    private function supported(mixed $locale): bool
    {
        return is_string($locale) && Locale::tryFrom($locale) instanceof Locale;
    }
}
