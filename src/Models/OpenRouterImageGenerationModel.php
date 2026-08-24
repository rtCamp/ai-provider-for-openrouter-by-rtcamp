<?php
/**
 * OpenRouter Image Generation Model.
 *
 * @package rtcamp/ai-provider-for-openrouter-by-rtcamp
 *
 * @since 1.0.0
 */

declare( strict_types=1 );

namespace rtCamp\AIProviderForOpenRouterByrtCamp\Models;

use rtCamp\AIProviderForOpenRouterByrtCamp\Provider\OpenRouterProvider;
use rtCamp\AIProviderForOpenRouterByrtCamp\Settings\OpenRouterSettings;
use WordPress\AiClient\Common\Exception\InvalidArgumentException;
use WordPress\AiClient\Files\DTO\File;
use WordPress\AiClient\Files\Enums\MediaOrientationEnum;
use WordPress\AiClient\Messages\DTO\Message;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Messages\Enums\MessageRoleEnum;
use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiBasedModel;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\DTO\RequestOptions;
use WordPress\AiClient\Providers\Http\DTO\Response;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\Http\Exception\ResponseException;
use WordPress\AiClient\Providers\Http\Util\ResponseUtil;
use WordPress\AiClient\Providers\Models\ImageGeneration\Contracts\ImageGenerationModelInterface;
use WordPress\AiClient\Results\DTO\Candidate;
use WordPress\AiClient\Results\DTO\GenerativeAiResult;
use WordPress\AiClient\Results\DTO\TokenUsage;
use WordPress\AiClient\Results\Enums\FinishReasonEnum;

/**
 * Class for an OpenRouter image generation model.
 *
 * Uses OpenRouter's chat completions endpoint for image generation by sending
 * messages plus modalities=["image"], while retaining legacy parsing fallback
 * for providers that still return images/generations style payloads.
 *
 * @since 1.0.0
 */
/**
 * @phpstan-type ImageGenerationParams array{
 *     model: string,
 *     messages: list<array{role: string, content: string}>,
 *     modalities: list<string>,
 *     n?: int,
 *     size?: string,
 *     ...<string, mixed>
 * }
 * @phpstan-type ImageMessageData array{
 *     images?: list<array{
 *         type?: string,
 *         image_url?: array{url?: string}
 *     }>
 * }
 * @phpstan-type CompletionChoiceData array{
 *     finish_reason?: string,
 *     message?: ImageMessageData
 * }
 * @phpstan-type ChoiceData array{
 *     url?: string,
 *     b64_json?: string
 * }
 * @phpstan-type UsageData array{
 *     prompt_tokens?: int,
 *     completion_tokens?: int,
 *     input_tokens?: int,
 *     output_tokens?: int,
 *     total_tokens?: int
 * }
 * @phpstan-type ResponseData array{
 *     id?: string,
 *     choices?: list<CompletionChoiceData>,
 *     data?: list<ChoiceData>,
 *     usage?: UsageData
 * }
 */
class OpenRouterImageGenerationModel extends AbstractApiBasedModel implements ImageGenerationModelInterface {

	/**
	 * Generates images from a prompt using OpenRouter's OpenAI-compatible endpoint.
	 *
	 * @since 1.0.0
	 *
	 * @param list<Message> $prompt Prompt messages.
	 */
	public function generateImageResult( array $prompt ): GenerativeAiResult {
		$params          = $this->prepareGenerateImageParams( $prompt );
		$request_options = $this->prepareRequestOptionsForImageGeneration();

		$request = new Request(
			HttpMethodEnum::POST(),
			OpenRouterProvider::url( '/chat/completions' ),
			[
				'Content-Type'  => 'application/json',
				'HTTP-Referer'  => home_url(),
				'X-Title'       => get_bloginfo( 'name' ),
			],
			$params,
			$request_options
		);

		$request  = $this->getRequestAuthentication()->authenticateRequest( $request );
		$response = $this->getHttpTransporter()->send( $request );
		ResponseUtil::throwIfNotSuccessful( $response );

		return $this->parseResponseToGenerativeAiResult( $response, 'image/png' );
	}

	/**
	 * Prepares image-generation params for OpenRouter.
	 *
	 * @since 1.0.0
	 *
	 * @param list<Message> $prompt Prompt messages.
	 * @return ImageGenerationParams
	 */
	protected function prepareGenerateImageParams( array $prompt ): array {
		$config = $this->getConfig();
		$params = [
			'model'      => $this->metadata()->getId(),
			'messages'   => [
				[
					'role'    => 'user',
					'content' => $this->extractPromptText( $prompt ),
				],
			],
			'modalities' => [ 'image' ],
		];

		$candidate_count = $config->getCandidateCount();
		if ( null !== $candidate_count ) {
			$params['n'] = $candidate_count;
		}

		$output_media_orientation = $config->getOutputMediaOrientation();
		$output_media_aspect      = $config->getOutputMediaAspectRatio();
		if ( null !== $output_media_orientation || null !== $output_media_aspect ) {
			$params['size'] = $this->prepareSizeParam( $output_media_orientation, $output_media_aspect );
		}

		$custom_options = $config->getCustomOptions();
		foreach ( $custom_options as $key => $value ) {
			if ( isset( $params[ $key ] ) ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message only.
				throw new InvalidArgumentException( sprintf( 'The custom option "%s" conflicts with an existing parameter.', $key ) );
			}
			$params[ $key ] = $value;
		}

		$selected_model = OpenRouterSettings::get_selected_image_model();
		if ( '' !== $selected_model ) {
			$params['model'] = $selected_model;
		}

		/** @var ImageGenerationParams $params */
		return apply_filters( 'openrouter_image_generation_params', $params );
	}

	/**
	 * Prepares request options for image generation with a longer default timeout.
	 *
	 * Supported custom options:
	 * - openrouter.request_timeout (seconds)
	 * - openrouter.connect_timeout (seconds)
	 *
	 * @since 1.0.0
	 *
	 */
	private function prepareRequestOptionsForImageGeneration(): RequestOptions {
		$existing_options = $this->getRequestOptions();
		if ( null !== $existing_options ) {
			$request_options = RequestOptions::fromArray( $existing_options->toArray() );
		} else {
			$request_options = new RequestOptions();
		}

		$custom_options = $this->getConfig()->getCustomOptions();

		$request_timeout = 300.0;
		if ( isset( $custom_options['openrouter.request_timeout'] ) && is_numeric( $custom_options['openrouter.request_timeout'] ) ) {
			$request_timeout = (float) $custom_options['openrouter.request_timeout'];
		}

		$connect_timeout = 10.0;
		if ( isset( $custom_options['openrouter.connect_timeout'] ) && is_numeric( $custom_options['openrouter.connect_timeout'] ) ) {
			$connect_timeout = (float) $custom_options['openrouter.connect_timeout'];
		}

		$request_options->setTimeout( $request_timeout );

		if ( null === $request_options->getConnectTimeout() ) {
			$request_options->setConnectTimeout( $connect_timeout );
		}

		return $request_options;
	}

	/**
	 * Parses the OpenRouter response to a generative AI result.
	 *
	 * @since 1.0.0
	 *
	 * @param Response $response           OpenRouter response.
	 * @param string   $expected_mime_type Expected MIME type.
	 */
	private function parseResponseToGenerativeAiResult( Response $response, string $expected_mime_type ): GenerativeAiResult {
		/** @var ResponseData $response_data */
		$response_data = $response->getData();
		$provider_name = $this->providerMetadata()->getName();

		$candidates = [];
		if ( isset( $response_data['choices'] ) && is_array( $response_data['choices'] ) ) {
			$candidates = $this->parseChatCompletionChoicesToCandidates( $response_data['choices'], $expected_mime_type, $provider_name );
		}

		if ( [] === $candidates && isset( $response_data['data'] ) && is_array( $response_data['data'] ) ) {
			$candidates = $this->parseLegacyDataToCandidates( $response_data['data'], $expected_mime_type, $provider_name );
		}

		if ( [] === $candidates ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message only.
			throw ResponseException::fromMissingData( $provider_name, 'choices.message.images|data' );
		}

		$id = isset( $response_data['id'] ) && is_string( $response_data['id'] ) ? $response_data['id'] : '';

		if ( isset( $response_data['usage'] ) && is_array( $response_data['usage'] ) ) {
			$usage         = $response_data['usage'];
			$input_tokens  = isset( $usage['input_tokens'] ) ? (int) $usage['input_tokens'] : ( isset( $usage['prompt_tokens'] ) ? (int) $usage['prompt_tokens'] : 0 );
			$output_tokens = isset( $usage['output_tokens'] ) ? (int) $usage['output_tokens'] : ( isset( $usage['completion_tokens'] ) ? (int) $usage['completion_tokens'] : 0 );
			$total_tokens  = isset( $usage['total_tokens'] ) ? (int) $usage['total_tokens'] : ( $input_tokens + $output_tokens );
			$token_usage   = new TokenUsage( $input_tokens, $output_tokens, $total_tokens );
		} else {
			$token_usage = new TokenUsage( 0, 0, 0 );
		}

		$provider_metadata = $response_data;
		unset( $provider_metadata['id'], $provider_metadata['data'], $provider_metadata['usage'] );

		return new GenerativeAiResult(
			$id,
			$candidates,
			$token_usage,
			$this->providerMetadata(),
			$this->metadata(),
			$provider_metadata
		);
	}

	/**
	 * Parses chat-completions image output candidates.
	 *
	 * @since 1.0.0
	 *
	 * @param list<CompletionChoiceData> $choices            Choice data.
	 * @param string                     $expected_mime_type Expected mime type.
	 * @param string                     $provider_name      Provider name.
	 * @return list<Candidate>
	 */
	private function parseChatCompletionChoicesToCandidates( array $choices, string $expected_mime_type, string $provider_name ): array {
		$candidates = [];

		foreach ( $choices as $choice_index => $choice_data ) {
			if ( ! is_array( $choice_data ) || array_is_list( $choice_data ) ) {
				continue;
			}

			$images = $choice_data['message']['images'] ?? null;
			if ( ! is_array( $images ) || [] === $images ) {
				continue;
			}

			foreach ( $images as $image_index => $image_data ) {
				if ( ! is_array( $image_data ) || array_is_list( $image_data ) ) {
					continue;
				}

				$url = $image_data['image_url']['url'] ?? null;
				if ( ! is_string( $url ) || '' === trim( $url ) ) {
					// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message only.
					throw ResponseException::fromInvalidData(
						$provider_name, // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message only.
						sprintf( 'choices[%d].message.images[%d].image_url.url', (int) $choice_index, (int) $image_index ),
						'The value must be a non-empty string.'
					);
				}

				$candidates[] = new Candidate(
					new Message( MessageRoleEnum::model(), [ new MessagePart( new File( $url, $expected_mime_type ) ) ] ),
					FinishReasonEnum::stop()
				);
			}
		}

		return $candidates;
	}

	/**
	 * Parses legacy images/generations style output candidates.
	 *
	 * @since 1.0.0
	 *
	 * @param list<ChoiceData> $data               Data entries.
	 * @param string           $expected_mime_type Expected mime type.
	 * @param string           $provider_name      Provider name.
	 * @return list<Candidate>
	 */
	private function parseLegacyDataToCandidates( array $data, string $expected_mime_type, string $provider_name ): array {
		$candidates = [];

		foreach ( $data as $index => $choice_data ) {
			if ( ! is_array( $choice_data ) || array_is_list( $choice_data ) ) {
				continue;
			}

			if ( isset( $choice_data['url'] ) && is_string( $choice_data['url'] ) ) {
				$image_file = new File( $choice_data['url'], $expected_mime_type );
			} elseif ( isset( $choice_data['b64_json'] ) && is_string( $choice_data['b64_json'] ) ) {
				$image_file = new File( $choice_data['b64_json'], $expected_mime_type );
			} else {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message only.
				throw ResponseException::fromInvalidData(
					$provider_name, // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message only.
					sprintf( 'data[%d]', (int) $index ),
					'The value must contain either a url or b64_json key with a string value.'
				);
			}

			$candidates[] = new Candidate(
				new Message( MessageRoleEnum::model(), [ new MessagePart( $image_file ) ] ),
				FinishReasonEnum::stop()
			);
		}

		return $candidates;
	}

	/**
	 * Prepares OpenAI-compatible image size from orientation or aspect ratio.
	 *
	 * @since 1.0.0
	 *
	 * @param MediaOrientationEnum|null $orientation Media orientation.
	 * @param string|null               $aspect_ratio Aspect ratio.
	 */
	private function prepareSizeParam( ?MediaOrientationEnum $orientation, ?string $aspect_ratio ): string {
		if ( null !== $aspect_ratio ) {
			switch ( $aspect_ratio ) {
				case '1:1':
					return '1024x1024';
				case '3:2':
					return '1536x1024';
				case '7:4':
					return '1792x1024';
				case '2:3':
					return '1024x1536';
				case '4:7':
					return '1024x1792';
				default:
					// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message only.
					throw new InvalidArgumentException( sprintf( 'The aspect ratio "%s" is not supported.', $aspect_ratio ) );
			}
		}

		if ( null !== $orientation ) {
			if ( $orientation->isLandscape() ) {
				return '1536x1024';
			}
			if ( $orientation->isPortrait() ) {
				return '1024x1536';
			}
		}

		return '1024x1024';
	}

	/**
	 * Extracts prompt text from a single user message.
	 *
	 * @since 1.0.0
	 *
	 * @param list<Message> $messages Prompt messages.
	 */
	private function extractPromptText( array $messages ): string {
		if ( 1 !== count( $messages ) ) {
			throw new InvalidArgumentException( 'Image generation requires exactly one user message as the prompt.' );
		}

		$message = $messages[0];
		if ( ! $message->getRole()->isUser() ) {
			throw new InvalidArgumentException( 'Image generation requires a user-role message as the prompt.' );
		}

		foreach ( $message->getParts() as $part ) {
			$text = $part->getText();
			if ( null !== $text ) {
				return $text;
			}
		}

		throw new InvalidArgumentException( 'Image generation requires a text part in the prompt message.' );
	}
}
