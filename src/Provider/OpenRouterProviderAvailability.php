<?php
/**
 * OpenRouter Provider Availability.
 *
 * @package rtcamp/ai-provider-for-openrouter-by-rtcamp
 *
 * @since 1.0.0
 */

declare( strict_types=1 );

namespace rtCamp\AIProviderForOpenRouterByrtCamp\Provider;

use WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface;
use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;

/**
 * Availability check for the OpenRouter provider.
 *
 * @since 1.0.0
 */
class OpenRouterProviderAvailability implements ProviderAvailabilityInterface {

	/**
	 * {@inheritDoc}
	 *
	 * OpenRouter is considered configured when a non-empty API key has been
	 * registered via Settings > Connectors or the OPENROUTER_API_KEY env var.
	 * A fallback empty-key auth object registered at startup does not count.
	 *
	 * @since 1.0.0
	 */
	public function isConfigured(): bool {
		if ( ! class_exists( \WordPress\AiClient\AiClient::class ) ) {
			return false;
		}

		$registry = \WordPress\AiClient\AiClient::defaultRegistry();

		if ( ! $registry->hasProvider( 'openrouter' ) ) {
			return false;
		}

		$auth = $registry->getProviderRequestAuthentication( 'openrouter' );
		if ( null === $auth ) {
			return false;
		}

		if ( $auth instanceof ApiKeyRequestAuthentication ) {
			return '' !== $auth->getApiKey();
		}

		return true;
	}
}
