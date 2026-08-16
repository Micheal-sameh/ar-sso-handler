<?php

namespace Avarewase\SsoClient\Exceptions;

/**
 * Thrown when the Avarewase SSO server could not be reached (or kept
 * failing) after the configured number of retries. No local-login
 * fallback is provided by the package — callers decide how to handle this.
 */
class AvarewaseConnectionException extends AvarewaseSsoException
{
}
