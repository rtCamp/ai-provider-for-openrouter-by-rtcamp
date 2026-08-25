<?php
/**
 * OpenRouter Provider.
 *
 * @package rtcamp/ai-provider-for-openrouter-by-rtcamp
 *
 * @since 1.0.0
 */

declare( strict_types=1 );

namespace rtCamp\AIProviderForOpenRouterByrtCamp\Provider;

use rtCamp\AIProviderForOpenRouterByrtCamp\Metadata\OpenRouterModelMetadataDirectory;
use rtCamp\AIProviderForOpenRouterByrtCamp\Models\OpenRouterImageGenerationModel;
use rtCamp\AIProviderForOpenRouterByrtCamp\Models\OpenRouterTextGenerationModel;
use WordPress\AiClient\Common\Exception\RuntimeException;
use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiProvider;
use WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface;
use WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface;
use WordPress\AiClient\Providers\DTO\ProviderMetadata;
use WordPress\AiClient\Providers\Enums\ProviderTypeEnum;
use WordPress\AiClient\Providers\Http\Enums\RequestAuthenticationMethod;
use WordPress\AiClient\Providers\Models\Contracts\ModelInterface;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;

/**
 * Class for the OpenRouter provider.
 *
 * @since 1.0.0
 */
class OpenRouterProvider extends AbstractApiProvider {

	/**
	 * Base URL for the OpenRouter API.
	 *
	 * @since 1.0.0
	 */
	private const OPENROUTER_BASE_URL = 'https://openrouter.ai/api/v1';

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	protected static function baseUrl(): string {
		$env_url = getenv( 'OPENROUTER_BASE_URL' );
		if ( false !== $env_url && '' !== $env_url ) {
			return rtrim( $env_url, '/' );
		}

		return self::OPENROUTER_BASE_URL;
	}

	/**
	 * Creates a model instance based on the provided metadata.
	 *
	 * @param \WordPress\AiClient\Providers\Models\DTO\ModelMetadata $model_metadata    The model metadata.
	 * @param \WordPress\AiClient\Providers\DTO\ProviderMetadata     $provider_metadata The provider metadata.
	 *
	 * @return \WordPress\AiClient\Providers\Models\Contracts\ModelInterface The created model instance.
	 *
	 * @throws \WordPress\AiClient\Common\Exception\RuntimeException If the model capabilities are unsupported.
	 *
	 * @since 1.0.0
	 */
	protected static function createModel(
		ModelMetadata $model_metadata,
		ProviderMetadata $provider_metadata
	): ModelInterface {

		$capabilities_string_list = $model_metadata->toArray()[ ModelMetadata::KEY_SUPPORTED_CAPABILITIES ];

		if ( in_array( 'image_generation', $capabilities_string_list, true ) ) {
			return new OpenRouterImageGenerationModel( $model_metadata, $provider_metadata );
		}

		$capabilities = $model_metadata->getSupportedCapabilities();
		foreach ( $capabilities as $capability ) {
			if ( $capability->isTextGeneration() ) {
				return new OpenRouterTextGenerationModel( $model_metadata, $provider_metadata );
			}
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message, not output.
		throw new RuntimeException( 'Unsupported model capabilities for OpenRouter model: ' . $model_metadata->getId() );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	protected static function createProviderMetadata(): ProviderMetadata {
		return new ProviderMetadata(
			'ai-provider-for-openrouter-by-rtcamp',
			'OpenRouter',
			ProviderTypeEnum::cloud(),
			'https://openrouter.ai/docs/api/reference/overview',
			RequestAuthenticationMethod::apiKey(),
			__( 'Text and image generation using various models.', 'ai-provider-for-openrouter-by-rtcamp' ),
			AI_PROVIDER_FOR_OPENROUTER_BY_RTCAMP_PLUGIN_DIR . 'assets/images/openrouter-logo.svg'
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	protected static function createProviderAvailability(): ProviderAvailabilityInterface {
		return new OpenRouterProviderAvailability();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	protected static function createModelMetadataDirectory(): ModelMetadataDirectoryInterface {
		return new OpenRouterModelMetadataDirectory();
	}
}
