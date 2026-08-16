<?php

namespace Avarewase\SsoClient\Http\Controllers;

use Avarewase\SsoClient\Client\AvarewaseClient;
use Avarewase\SsoClient\Contracts\ProvisionsAvarewaseUsers;
use Avarewase\SsoClient\Events\AvarewaseUserAuthenticated;
use Avarewase\SsoClient\Exceptions\AvarewaseStateMismatchException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class AvarewaseAuthController extends Controller
{
    protected const SESSION_STATE_KEY = 'avarewase_sso.state';

    protected const SESSION_VERIFIER_KEY = 'avarewase_sso.code_verifier';

    public function __construct(protected AvarewaseClient $client)
    {
    }

    public function redirect(Request $request): RedirectResponse
    {
        $state = $this->client->pkce()->state();
        $verifier = $this->client->pkce()->verifier();
        $challenge = $this->client->pkce()->challengeFor($verifier);

        $request->session()->put(self::SESSION_STATE_KEY, $state);
        $request->session()->put(self::SESSION_VERIFIER_KEY, $verifier);

        return redirect()->away($this->client->authorizationUrl($state, $challenge));
    }

    public function callback(Request $request, ProvisionsAvarewaseUsers $provisioner): RedirectResponse
    {
        $request->validate(['code' => 'required|string']);

        $expectedState = $request->session()->pull(self::SESSION_STATE_KEY);
        $verifier = $request->session()->pull(self::SESSION_VERIFIER_KEY);

        if (! $expectedState || ! hash_equals($expectedState, (string) $request->query('state'))) {
            throw new AvarewaseStateMismatchException('The Avarewase login state did not match.');
        }

        $tokens = $this->client->exchangeCodeForTokens($request->string('code'), $verifier);
        $userInfo = $this->client->userInfo($tokens->accessToken);

        $user = $provisioner->resolve($userInfo);

        Auth::guard(config('avarewase-sso.guard'))->login($user, remember: true);

        $request->session()->regenerate();

        AvarewaseUserAuthenticated::dispatch($user, $userInfo, $tokens);

        return redirect()->intended(config('avarewase-sso.routes.redirect_after_login'));
    }
}
