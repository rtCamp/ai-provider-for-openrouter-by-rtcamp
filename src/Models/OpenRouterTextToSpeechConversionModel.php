<?php
/**
 * OpenRouter Text To Speech Conversion Model.
 *
 * @package rtcamp/rtcamps-ai-provider-for-openrouter
 *
 * @since 1.2.0
 */

declare( strict_types=1 );

namespace rtCamp\AIProviderForOpenRouter\Models;

use rtCamp\AIProviderForOpenRouter\Provider\OpenRouterProvider;
use rtCamp\AIProviderForOpenRouter\Settings\OpenRouterSettings;
use WordPress\AiClient\Common\Exception\InvalidArgumentException;
use WordPress\AiClient\Files\DTO\File;
use WordPress\AiClient\Messages\DTO\Message;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Messages\Enums\MessageRoleEnum;
use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiBasedModel;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\DTO\Response;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\Http\Exception\ResponseException;
use WordPress\AiClient\Providers\Http\Util\ResponseUtil;
use WordPress\AiClient\Providers\Models\TextToSpeechConversion\Contracts\TextToSpeechConversionModelInterface;
use WordPress\AiClient\Results\DTO\Candidate;
use WordPress\AiClient\Results\DTO\GenerativeAiResult;
use WordPress\AiClient\Results\DTO\TokenUsage;
use WordPress\AiClient\Results\Enums\FinishReasonEnum;

/**
 * Class for an OpenRouter text-to-speech conversion model.
 *
 * Uses OpenRouter's /audio/speech endpoint to synthesize speech from text.
 *
 * @since 1.2.0
 */
class OpenRouterTextToSpeechConversionModel extends AbstractApiBasedModel implements
	TextToSpeechConversionModelInterface {

	/**
	 * Default voice used when none is configured.
	 *
	 * @var string
	 */
	private const DEFAULT_VOICE = 'alloy';

	/**
	 * Default output MIME type used when none is configured.
	 *
	 * @var string
	 */
	private const DEFAULT_MIME_TYPE = 'audio/mpeg';

	/**
	 * Maps supported output MIME types to response_format values.
	 *
	 * @var array<string, string>
	 */
	private const MIME_TYPE_TO_RESPONSE_FORMAT = [
		'audio/mpeg' => 'mp3',
		'audio/ogg'  => 'opus',
		'audio/wav'  => 'wav',
		'audio/flac' => 'flac',
		'audio/aac'  => 'aac',
	];

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.2.0
	 *
	 * @param array $prompt Prompt messages containing text.
	 * @phpstan-param list<\WordPress\AiClient\Messages\DTO\Message> $prompt
	 */
	public function convertTextToSpeechResult( array $prompt ): GenerativeAiResult {
		$http_transporter = $this->getHttpTransporter();

		$mime_type = $this->prepareOutputMimeType();
		$params    = $this->prepareConvertParams( $prompt, $mime_type );

		$request = new Request(
			HttpMethodEnum::POST(),
			OpenRouterProvider::url( 'audio/speech' ),
			[
				'Content-Type' => 'application/json',
				'HTTP-Referer' => home_url(),
				'X-Title'      => get_bloginfo( 'name' ),
			],
			$params,
			$this->getRequestOptions()
		);

		// Add authentication credentials to the request.
		$request = $this->getRequestAuthentication()->authenticateRequest( $request );

		// Send and process the request.
		$response = $http_transporter->send( $request );
		ResponseUtil::throwIfNotSuccessful( $response );

		return $this->parseResponseToGenerativeAiResult( $response, $mime_type );
	}

	/**
	 * Prepares the request parameters for the Audio Speech API.
	 *
	 * @since 1.2.0
	 *
	 * @param list<Message> $prompt    The prompt messages containing the text.
	 * @param string        $mime_type The resolved output MIME type.
	 * @return array<string, mixed> The parameters for the API request.
	 * @throws InvalidArgumentException If custom option conflicts with parameter.
	 */
	protected function prepareConvertParams( array $prompt, string $mime_type ): array {
		$config         = $this->getConfig();
		$selected_model = OpenRouterSettings::get_selected_tts_model();
		$model_id       = '' !== $selected_model ? $selected_model : $this->metadata()->getId();

		$params = [
			'model'           => $model_id,
			'input'           => $this->preparePromptText( $prompt ),
			'voice'           => $this->prepareVoice(),
			'response_format' => self::MIME_TYPE_TO_RESPONSE_FORMAT[ $mime_type ],
		];

		/*
		 * Any custom options are added to the parameters as well.
		 * This allows developers to pass other options such as 'speed' or
		 * 'instructions' that may not yet be first-class options in the SDK.
		 */
		$custom_options = $config->getCustomOptions();
		foreach ( $custom_options as $key => $value ) {
			if ( isset( $params[ $key ] ) ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message, not output.
				throw new InvalidArgumentException(
					sprintf(
						'The custom option "%s" conflicts with an existing parameter.',
						$key
					)
				);
			}
			$params[ $key ] = $value;
		}

		/**
		 * Filters the OpenRouter text-to-speech conversion request parameters.
		 *
		 * @since 1.2.0
		 *
		 * @param array<string, mixed> $params Request parameters.
		 */
		return (array) apply_filters( 'rtcamps_ai_provider_for_openrouter_tts_params', $params );
	}

	/**
	 * Extracts the plain text to synthesize from the prompt.
	 *
	 * @since 1.2.0
	 *
	 * @param list<Message> $prompt The prompt messages.
	 * @return string The text to convert to speech.
	 * @throws InvalidArgumentException If no text is found in the prompt.
	 */
	protected function preparePromptText( array $prompt ): string {
		$text = '';
		foreach ( $prompt as $message ) {
			foreach ( $message->getParts() as $part ) {
				$part_text = $part->getText();
				if ( null !== $part_text ) {
					$text .= ( '' === $text ? '' : "\n" ) . $part_text;
				}
			}
		}

		if ( '' === $text ) {
			throw new InvalidArgumentException(
				'The prompt must contain text to convert to speech.'
			);
		}

		return $text;
	}

	/**
	 * Resolves the voice to use, falling back to configured voice or model default.
	 *
	 * @since 1.2.0
	 *
	 * @return string The voice identifier.
	 */
	protected function prepareVoice(): string {
		$voice = $this->getConfig()->getOutputSpeechVoice();
		if ( null !== $voice && '' !== $voice ) {
			return $voice;
		}

		$configured_voice = OpenRouterSettings::get_selected_tts_voice();
		if ( '' !== $configured_voice ) {
			return $configured_voice;
		}

		$metadata = $this->metadata();
		foreach ( $metadata->getSupportedOptions() as $option ) {
			if ( $option->getName()->isOutputSpeechVoice() ) {
				$supported = $option->getSupportedValues();
				if ( is_array( $supported ) && ! empty( $supported ) ) {
					$first_voice = reset( $supported );
					if ( is_string( $first_voice ) && '' !== $first_voice ) {
						return $first_voice;
					}
				}
			}
		}

		return self::DEFAULT_VOICE;
	}

	/**
	 * Resolves the output MIME type, defaulting and validating against supported types.
	 *
	 * @since 1.2.0
	 *
	 * @return string A supported audio MIME type.
	 * @throws InvalidArgumentException If the configured MIME type is unsupported.
	 */
	protected function prepareOutputMimeType(): string {
		$mime_type = $this->getConfig()->getOutputMimeType();
		if ( null === $mime_type || '' === $mime_type ) {
			return self::DEFAULT_MIME_TYPE;
		}

		if ( ! isset( self::MIME_TYPE_TO_RESPONSE_FORMAT[ $mime_type ] ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message, not output.
			throw new InvalidArgumentException(
				sprintf(
					'The output MIME type "%s" is not supported for text to speech.',
					$mime_type
				)
			);
		}

		return $mime_type;
	}

	/**
	 * Parses the binary audio response into a generative AI result.
	 *
	 * The Audio Speech API returns raw audio bytes, so the body
	 * is base64-encoded into an inline audio file.
	 *
	 * @since 1.2.0
	 *
	 * @param Response $response  The HTTP response containing raw audio bytes.
	 * @param string   $mime_type The MIME type of the returned audio.
	 * @return GenerativeAiResult The generative AI result containing the audio file.
	 * @throws ResponseException If the response body is empty.
	 */
	protected function parseResponseToGenerativeAiResult( Response $response, string $mime_type ): GenerativeAiResult {
		$body = $response->getBody();
		if ( null === $body || '' === $body ) {
			throw ResponseException::fromMissingData(
				$this->providerMetadata()->getName(),
				'body'
			);
		}

		$audio_file = new File( base64_encode( $body ), $mime_type );
		$message    = new Message( MessageRoleEnum::model(), [ new MessagePart( $audio_file ) ] );
		$candidate  = new Candidate( $message, FinishReasonEnum::stop() );

		return new GenerativeAiResult(
			$this->generateResultId(),
			[ $candidate ],
			new TokenUsage( 0, 0, 0 ),
			$this->providerMetadata(),
			$this->metadata()
		);
	}

	/**
	 * Generates a result identifier.
	 *
	 * @since 1.2.0
	 *
	 * @return string The result identifier.
	 */
	protected function generateResultId(): string {
		return uniqid( 'openrouter-tts-', true );
	}
}

