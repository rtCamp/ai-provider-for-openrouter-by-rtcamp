<?php
/**
 * OpenRouter Provider.
 *
 * @package rtcamp/rtcamps-ai-provider-for-openrouter
 *
 * @since 1.0.0
 */

declare( strict_types=1 );

namespace rtCamp\AIProviderForOpenRouter\Provider;

use rtCamp\AIProviderForOpenRouter\Metadata\OpenRouterModelMetadataDirectory;
use rtCamp\AIProviderForOpenRouter\Models\OpenRouterDecisionModel;
use rtCamp\AIProviderForOpenRouter\Models\OpenRouterImageGenerationModel;
use rtCamp\AIProviderForOpenRouter\Models\OpenRouterTextGenerationModel;
use rtCamp\AIProviderForOpenRouter\Models\OpenRouterTextToSpeechConversionModel;
use rtCamp\AIProviderForOpenRouter\Settings\OpenRouterSettings;
use WordPress\AiClient\AiClient;
use WordPress\AiClient\Common\Exception\InvalidArgumentException;
use WordPress\AiClient\Common\Exception\RuntimeException;
use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiProvider;
use WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface;
use WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface;
use WordPress\AiClient\Providers\DTO\ProviderMetadata;
use WordPress\AiClient\Providers\Enums\ProviderTypeEnum;
use WordPress\AiClient\Providers\Http\Enums\RequestAuthenticationMethod;
use WordPress\AiClient\Providers\Models\Contracts\ModelInterface;
use WordPress\AiClient\Providers\Models\DTO\ModelConfig;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Providers\Models\DTO\SupportedOption;
use WordPress\AiClient\Providers\Models\Enums\OptionEnum;

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
	// phpcs:ignore PluginCheck.CodeAnalysis.AIProvider.DirectIntegration -- AI Provider connects directly to OpenRouter API.
	private const OPENROUTER_BASE_URL = 'https://openrouter.ai/api/v1';

	/**
	 * Returns a decision model, ready to answer structured questions.
	 *
	 * The AI Client has no decision capability, so decision models cannot be
	 * resolved through the registry the way other models are.
	 *
	 * @since 1.3.0
	 *
	 * @param string|null                                             $model_id     Optional. The model ID, such as "typesafe/jev-1.13". Defaults to the configured Decision Model setting.
	 * @param \WordPress\AiClient\Providers\Models\DTO\ModelConfig|null $model_config Optional. Model configuration, for custom options (e.g. openrouter.decision_endpoint). Default null.
	 * @return \rtCamp\AIProviderForOpenRouter\Models\OpenRouterDecisionModel The decision model.
	 * @throws \WordPress\AiClient\Common\Exception\InvalidArgumentException If no model ID is provided or configured.
	 */
	public static function decisionModel( ?string $model_id = null, ?ModelConfig $model_config = null ): OpenRouterDecisionModel {
		$resolved_model_id = null !== $model_id ? trim( $model_id ) : '';
		if ( '' === $resolved_model_id ) {
			$resolved_model_id = OpenRouterSettings::get_selected_decision_model();
		}

		if ( '' === $resolved_model_id ) {
			throw new InvalidArgumentException(
				'A decision model ID must be provided or configured in OpenRouter Settings.'
			);
		}

		$model_metadata = new ModelMetadata(
			$resolved_model_id,
			$resolved_model_id,
			[],
			[ new SupportedOption( OptionEnum::customOptions() ) ]
		);

		$model = new OpenRouterDecisionModel( $model_metadata, static::metadata() );
		if ( null !== $model_config ) {
			$model->setConfig( $model_config );
		}

		$registry = AiClient::defaultRegistry();
		if ( $registry->hasProvider( static::class ) ) {
			$registry->bindModelDependencies( $model );
		}

		return $model;
	}

	/**
	 * Lists the OpenRouter model IDs that support decisions.
	 *
	 * @since 1.3.0
	 *
	 * @return list<string> The decision model IDs, sorted.
	 */
	public static function decisionModelIds(): array {
		return OpenRouterSettings::get_decision_model_ids();
	}

	/**
	 * Builds a URL under OpenRouter's /api/alpha namespace.
	 *
	 * @since 1.3.0
	 *
	 * @param string $path Optional path relative to /api/alpha (e.g. "/decisions").
	 * @return string The complete URL.
	 */
	public static function alphaUrl( string $path = '' ): string {
		$base = preg_replace( '#/v1$#', '', static::baseUrl() );
		$root = ( is_string( $base ) ? rtrim( $base, '/' ) : static::baseUrl() ) . '/alpha';

		if ( '' === $path ) {
			return $root;
		}

		return $root . '/' . ltrim( $path, '/' );
	}

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

		if ( in_array( 'text_to_speech_conversion', $capabilities_string_list, true ) ) {
			return new OpenRouterTextToSpeechConversionModel( $model_metadata, $provider_metadata );
		}

		$capabilities = $model_metadata->getSupportedCapabilities();
		foreach ( $capabilities as $capability ) {
			if ( $capability->isTextToSpeechConversion() ) {
				return new OpenRouterTextToSpeechConversionModel( $model_metadata, $provider_metadata );
			}
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
			'rtcamps-ai-provider-for-openrouter',
			'OpenRouter',
			ProviderTypeEnum::cloud(),
			// phpcs:ignore PluginCheck.CodeAnalysis.AIProvider.DirectIntegration -- Provider documentation reference URL.
			'https://openrouter.ai/docs/api/reference/overview',
			RequestAuthenticationMethod::apiKey(),
			__( 'Text, image, and speech generation using various models.', 'rtcamps-ai-provider-for-openrouter' ),
			RTCAMPS_AI_PROVIDER_FOR_OPENROUTER_PLUGIN_DIR . 'assets/images/openrouter-logo.svg'
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
