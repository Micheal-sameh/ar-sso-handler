<?php

namespace Avarewase\SsoClient\Exceptions;

/**
 * Thrown when the SSO server responds (successfully, at the transport
 * level) but rejects the authorization code / refresh token exchange,
 * e.g. invalid_grant, invalid_client.
 */
class AvarewaseTokenException extends AvarewaseSsoException
{
}
