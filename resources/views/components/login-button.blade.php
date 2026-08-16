@props(['label' => 'Login with Avarewase'])

<a
    href="{{ route(config('avarewase-sso.routes.login_name')) }}"
    {{ $attributes->class([
        'inline-flex items-center justify-center gap-2 rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-slate-500 focus:ring-offset-2',
    ]) }}
>
    {{ $slot->isEmpty() ? $label : $slot }}
</a>
