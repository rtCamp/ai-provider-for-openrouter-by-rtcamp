<?php
/**
 * OpenRouter Text Generation Model.
 *
 * @package rtcamp/rtcamps-ai-provider-for-openrouter
 *
 * @since 1.0.0
 */

declare( strict_types=1 );

namespace rtCamp\AIProviderForOpenRouter\Models;

use rtCamp\AIProviderForOpenRouter\Provider\OpenRouterProvider;
use rtCamp\AIProviderForOpenRouter\Settings\OpenRouterSettings;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\DTO\Response;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\Http\Exception\ResponseException;
use WordPress\AiClient\Providers\Http\Util\ResponseUtil;
use WordPress\AiClient\Providers\OpenAiCompatibleImplementation\AbstractOpenAiCompatibleTextGenerationModel;

/**
 * Class for an OpenRouter text generation model.
 *
 * Extends the OpenAI-compatible base class since OpenRouter exposes the
 * standard /v1/chat/completions endpoint. This class overrides request
 * creation to target the OpenRouter API base URL and injects the optional
 * model override from plugin settings.
 *
 * @since 1.0.0
 */
class OpenRouterTextGenerationModel extends AbstractOpenAiCompatibleTextGenerationModel {

	/**
	 * {@inheritDoc}
	 *
	 * Overrides the model ID with the settings selection when the user has
	 * configured a default model in the plugin settings page.
	 *
	 * @since 1.0.0
	 *
	 * @param array $prompt Prompt messages.
	 * @phpstan-param list<\WordPress\AiClient\Messages\DTO\Message> $prompt
	 * @return array<string, mixed>
	 */
	protected function prepareGenerateTextParams( array $prompt ): array {
		$params = parent::prepareGenerateTextParams( $prompt );

		$selected_model = OpenRouterSettings::get_selected_model();
		if ( '' !== $selected_model ) {
			$params['model'] = $selected_model;
		}

		/**
		 * Filters the OpenRouter text generation request parameters.
		 *
		 * @since 1.0.0
		 * @since 1.1.1 Renamed hook to include the full plugin prefix.
		 *
		 * @param array<string, mixed> $params Request parameters for OpenRouter chat completions.
		 */
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook is prefixed with the full plugin slug.
		return apply_filters( 'rtcamps_ai_provider_for_openrouter_text_generation_params', $params );
	}

	/**
	 * {@inheritDoc}
	 *
	 * Wraps the output schema in the name/schema/strict envelope required by
	 * OpenAI-compatible structured-output APIs (which OpenRouter mirrors).
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed>|null $outputSchema The output schema.
	 * @return array<string, mixed>
	 */
	protected function prepareResponseFormatParam( ?array $outputSchema ): array {
		if ( is_array( $outputSchema ) ) {
			return [
				'type'        => 'json_schema',
				'json_schema' => [
					'name'   => 'result',
					'schema' => $outputSchema,
					'strict' => true,
				],
			];
		}
		return [ 'type' => 'json_object' ];
	}

	/**
	 * {@inheritDoc}
	 *
	 * Additionally checks for OpenRouter's error envelope, which can arrive
	 * with a 200 HTTP status when a model-level error occurs.
	 *
	 * @since 1.0.0
	 *
	 * @param \WordPress\AiClient\Providers\Http\DTO\Response $response The HTTP response to check.
	 * @throws \WordPress\AiClient\Providers\Http\Exception\ResponseException On error.
	 */
	protected function throwIfNotSuccessful( Response $response ): void {
		ResponseUtil::throwIfNotSuccessful( $response );

		// OpenRouter may return HTTP 200 with {"error":{...}} on model-level failures.
		$data = $response->getData();
		if ( isset( $data['error'] ) ) {
			$message = ( is_array( $data['error'] ) && isset( $data['error']['message'] ) )
				? (string) $data['error']['message']
				: 'Unknown OpenRouter error.';
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message, not output.
			throw new ResponseException( $message );
		}
	}

	/**
	 * Creates an HTTP request targeted at the OpenRouter API.
	 *
	 * Adds the HTTP-Referer and X-Title headers that OpenRouter uses for
	 * attribution and rate-limit bucketing.
	 *
	 * @since 1.0.0
	 *
	 * @param \WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum $method  The HTTP method.
	 * @param string                                                  $path    The API endpoint path (e.g. "chat/completions").
	 * @param array<string, string|list<string>>                      $headers Additional headers.
	 * @param mixed                                                   $data    The request body.
	 */
	protected function createRequest(
		HttpMethodEnum $method,
		string $path,
		array $headers = [],
		$data = null
	): Request {
		$headers['HTTP-Referer'] = home_url();
		$headers['X-Title']      = get_bloginfo( 'name' );

		$url = OpenRouterProvider::url( '/' . ltrim( $path, '/' ) );

		return new Request(
			$method,
			$url,
			$headers,
			$data,
			$this->getRequestOptions()
		);
	}
}
