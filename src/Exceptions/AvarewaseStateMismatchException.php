<?php

namespace Avarewase\SsoClient\Exceptions;

/**
 * Thrown when the `state` returned on the OAuth callback does not match
 * the one stored in the session — likely CSRF or an expired session.
 */
class AvarewaseStateMismatchException extends AvarewaseSsoException
{
}
