<?php
/**
 * OpenRouter Decision Model.
 *
 * @package rtcamp/rtcamps-ai-provider-for-openrouter
 *
 * @since 1.3.0
 */

declare( strict_types=1 );

namespace rtCamp\AIProviderForOpenRouter\Models;

use rtCamp\AIProviderForOpenRouter\Decisions\DecisionAnswer;
use rtCamp\AIProviderForOpenRouter\Decisions\DecisionQuestion;
use rtCamp\AIProviderForOpenRouter\Decisions\DecisionResult;
use rtCamp\AIProviderForOpenRouter\Provider\OpenRouterProvider;
use rtCamp\AIProviderForOpenRouter\Settings\OpenRouterSettings;
use WordPress\AiClient\Common\Exception\InvalidArgumentException;
use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiBasedModel;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\DTO\RequestOptions;
use WordPress\AiClient\Providers\Http\DTO\Response;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\Http\Exception\ResponseException;
use WordPress\AiClient\Providers\Http\Util\ResponseUtil;
use WordPress\AiClient\Results\DTO\TokenUsage;

/**
 * OpenRouter decision model.
 *
 * Answers typed questions about a piece of state via OpenRouter's
 * /api/v1/systemone (default) or /api/alpha/decisions endpoint, returning
 * calibrated probabilities and structured answers instead of generated text.
 *
 * @since 1.3.0
 *
 * @phpstan-type UsageData array{
 *     prompt_tokens?: int,
 *     completion_tokens?: int,
 *     total_tokens?: int,
 *     cost?: float|int
 * }
 * @phpstan-type ResponseData array{
 *     id?: string,
 *     model?: string,
 *     provider?: string,
 *     answers?: array<string, mixed>,
 *     usage?: UsageData,
 *     error?: array{message?: string}|string
 * }
 */
class OpenRouterDecisionModel extends AbstractApiBasedModel {

	/**
	 * Endpoint identifier for OpenRouter's System One API (/api/v1/systemone).
	 *
	 * @since 1.3.0
	 *
	 * @var string
	 */
	public const ENDPOINT_SYSTEM_ONE = 'systemone';

	/**
	 * Endpoint identifier for OpenRouter's Alpha Decisions API (/api/alpha/decisions).
	 *
	 * @since 1.3.0
	 *
	 * @var string
	 */
	public const ENDPOINT_ALPHA_DECISIONS = 'alpha_decisions';

	/**
	 * Custom option key used to select the decision API endpoint.
	 *
	 * @since 1.3.0
	 *
	 * @var string
	 */
	public const OPTION_DECISION_ENDPOINT = 'openrouter.decision_endpoint';

	/**
	 * The maximum number of questions in one request.
	 *
	 * @since 1.3.0
	 *
	 * @var int
	 */
	public const MAX_QUESTIONS = 64;

	/**
	 * Custom options consumed locally rather than sent in the JSON payload.
	 *
	 * @since 1.3.0
	 *
	 * @var list<string>
	 */
	private const NON_PAYLOAD_OPTIONS = [
		'openrouter.request_timeout',
		'openrouter.connect_timeout',
		self::OPTION_DECISION_ENDPOINT,
	];

	/**
	 * Answers typed questions about the given state.
	 *
	 * @since 1.3.0
	 *
	 * @param string|array<mixed>                                                      $state     The content to judge: a non-empty string or an array sent as JSON.
	 * @param array<string, \rtCamp\AIProviderForOpenRouter\Decisions\DecisionQuestion> $questions Between 1 and 64 questions, keyed by the name their answers are returned under.
	 * @return \rtCamp\AIProviderForOpenRouter\Decisions\DecisionResult The answers.
	 * @throws \WordPress\AiClient\Common\Exception\InvalidArgumentException If the state, questions, or options are invalid.
	 * @throws \WordPress\AiClient\Providers\Http\Exception\ResponseException If the request fails or the response is malformed.
	 */
	public function decide( $state, array $questions ): DecisionResult {
		$endpoint = $this->resolveEndpoint( $questions );
		$params   = $this->prepareDecideParams( $state, $questions );
		$url      = self::ENDPOINT_ALPHA_DECISIONS === $endpoint
			? OpenRouterProvider::alphaUrl( '/decisions' )
			: OpenRouterProvider::url( '/systemone' );

		$headers = [
			'Content-Type' => 'application/json',
		];

		if ( function_exists( 'home_url' ) ) {
			$headers['HTTP-Referer'] = home_url();
		}

		if ( function_exists( 'get_bloginfo' ) ) {
			$headers['X-Title'] = get_bloginfo( 'name' );
		}

		$request = new Request(
			HttpMethodEnum::POST(),
			$url,
			$headers,
			$params,
			$this->prepareRequestOptions()
		);

		$request  = $this->getRequestAuthentication()->authenticateRequest( $request );
		$response = $this->getHttpTransporter()->send( $request );
		$this->throwIfNotSuccessful( $response );

		return $this->parseResponseToDecisionResult( $response, $questions );
	}

	/**
	 * Resolves and validates the target decision endpoint from custom options.
	 *
	 * @since 1.3.0
	 *
	 * @param array<string, mixed> $questions The questions to validate against endpoint capabilities.
	 * @return string One of ENDPOINT_SYSTEM_ONE or ENDPOINT_ALPHA_DECISIONS.
	 * @throws \WordPress\AiClient\Common\Exception\InvalidArgumentException If the endpoint option or question types are incompatible.
	 */
	private function resolveEndpoint( array $questions ): string {
		$custom_options = $this->getConfig()->getCustomOptions();
		$endpoint       = $custom_options[ self::OPTION_DECISION_ENDPOINT ] ?? self::ENDPOINT_SYSTEM_ONE;

		if ( ! is_string( $endpoint ) || ! in_array( $endpoint, [ self::ENDPOINT_SYSTEM_ONE, self::ENDPOINT_ALPHA_DECISIONS ], true ) ) {
			throw new InvalidArgumentException(
				'The "openrouter.decision_endpoint" custom option must be "systemone" or "alpha_decisions".'
			);
		}

		if ( self::ENDPOINT_SYSTEM_ONE === $endpoint ) {
			foreach ( $questions as $name => $question ) {
				if ( $question instanceof DecisionQuestion && $question->getType()->isBox() ) {
					// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
					throw new InvalidArgumentException(
						sprintf(
							'Decision question "%s" uses the "box" type, which requires "openrouter.decision_endpoint" to be set to "alpha_decisions".',
							(string) $name
						)
					);
				}
			}
		}

		return $endpoint;
	}

	/**
	 * Prepares the state, questions, and model configuration into API request parameters.
	 *
	 * @since 1.3.0
	 *
	 * @param mixed        $state     The content to judge.
	 * @param array<mixed> $questions The questions, keyed by name.
	 * @return array<string, mixed> The parameters for the API request.
	 * @throws \WordPress\AiClient\Common\Exception\InvalidArgumentException If the state, questions, or a custom option are invalid.
	 */
	private function prepareDecideParams( $state, array $questions ): array {
		if ( is_string( $state ) ? '' === trim( $state ) : ( ! is_array( $state ) || [] === $state ) ) {
			throw new InvalidArgumentException( 'Decision state must be a non-empty string or array.' );
		}

		$question_count = count( $questions );
		if ( 0 === $question_count || $question_count > self::MAX_QUESTIONS ) {
			// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new InvalidArgumentException(
				sprintf( 'A decision request needs between 1 and %1$d questions, %2$d given.', self::MAX_QUESTIONS, $question_count )
			);
			// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		$questions_data = [];
		foreach ( $questions as $name => $question ) {
			if ( ! is_string( $name ) || '' === $name ) {
				throw new InvalidArgumentException( 'Decision questions must be keyed by non-empty string names.' );
			}

			if ( ! $question instanceof DecisionQuestion ) {
				// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped
				throw new InvalidArgumentException(
					sprintf( 'The decision question "%s" must be a DecisionQuestion instance.', $name )
				);
				// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
			}

			$questions_data[ $name ] = $question->toArray();
		}

		$selected_model = OpenRouterSettings::get_selected_decision_model();
		$model_id       = '' !== $this->metadata()->getId() ? $this->metadata()->getId() : $selected_model;

		$params = [
			'model'     => $model_id,
			'state'     => $state,
			'questions' => $questions_data,
		];

		foreach ( $this->getConfig()->getCustomOptions() as $key => $value ) {
			if ( in_array( $key, self::NON_PAYLOAD_OPTIONS, true ) ) {
				continue;
			}

			if ( isset( $params[ $key ] ) ) {
				// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped
				throw new InvalidArgumentException(
					sprintf(
						'The custom option "%s" conflicts with an existing parameter.',
						$key
					)
				);
				// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
			}

			$params[ $key ] = $value;
		}

		/**
		 * Filters the OpenRouter decision request parameters.
		 *
		 * @since 1.3.0
		 *
		 * @param array<string, mixed> $params Request parameters.
		 */
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook is prefixed with the full plugin slug.
		return (array) apply_filters( 'rtcamps_ai_provider_for_openrouter_decision_params', $params );
	}

	/**
	 * Builds request options, honouring optional transport timeout custom options.
	 *
	 * @since 1.3.0
	 *
	 * @return \WordPress\AiClient\Providers\Http\DTO\RequestOptions The request options.
	 */
	private function prepareRequestOptions(): RequestOptions {
		$options        = $this->getRequestOptions();
		$updated        = null !== $options ? clone $options : new RequestOptions();
		$custom_options = $this->getConfig()->getCustomOptions();
		$timeout        = $updated->getTimeout();
		$connect        = $updated->getConnectTimeout();

		if ( isset( $custom_options['openrouter.request_timeout'] ) && is_numeric( $custom_options['openrouter.request_timeout'] ) ) {
			$timeout = (float) $custom_options['openrouter.request_timeout'];
		}

		if ( isset( $custom_options['openrouter.connect_timeout'] ) && is_numeric( $custom_options['openrouter.connect_timeout'] ) ) {
			$connect = (float) $custom_options['openrouter.connect_timeout'];
		}

		if ( null !== $timeout ) {
			$updated->setTimeout( $timeout );
		}
		if ( null !== $connect ) {
			$updated->setConnectTimeout( $connect );
		}

		return $updated;
	}

	/**
	 * Validates the HTTP response and checks for OpenRouter's error envelope.
	 *
	 * @since 1.3.0
	 *
	 * @param \WordPress\AiClient\Providers\Http\DTO\Response $response The HTTP response to check.
	 * @throws \WordPress\AiClient\Providers\Http\Exception\ResponseException On error.
	 */
	private function throwIfNotSuccessful( Response $response ): void {
		ResponseUtil::throwIfNotSuccessful( $response );

		$data = $response->getData();
		if ( is_array( $data ) && isset( $data['error'] ) ) {
			$message = ( is_array( $data['error'] ) && isset( $data['error']['message'] ) )
				? (string) $data['error']['message']
				: 'Unknown OpenRouter error.';
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message, not output.
			throw new ResponseException( $message );
		}
	}

	/**
	 * Parses an OpenRouter decision response into a DecisionResult.
	 *
	 * @since 1.3.0
	 *
	 * @param \WordPress\AiClient\Providers\Http\DTO\Response                          $response  The OpenRouter API response.
	 * @param array<string, \rtCamp\AIProviderForOpenRouter\Decisions\DecisionQuestion> $questions The questions asked.
	 * @return \rtCamp\AIProviderForOpenRouter\Decisions\DecisionResult The parsed result.
	 * @throws \WordPress\AiClient\Providers\Http\Exception\ResponseException If an answer is missing or malformed.
	 */
	private function parseResponseToDecisionResult( Response $response, array $questions ): DecisionResult {
		/** @var ResponseData $response_data */
		$response_data = $response->getData();

		if ( ! isset( $response_data['answers'] ) || ! is_array( $response_data['answers'] ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw ResponseException::fromMissingData( $this->providerMetadata()->getName(), 'answers' );
		}

		$answers = [];
		foreach ( $questions as $name => $question ) {
			if ( ! array_key_exists( $name, $response_data['answers'] ) ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
				throw ResponseException::fromMissingData( $this->providerMetadata()->getName(), 'answers.' . $name );
			}

			$answers[ $name ] = DecisionAnswer::fromResponseData(
				$question->getType(),
				$response_data['answers'][ $name ],
				'answers.' . $name
			);
		}

		$usage             = isset( $response_data['usage'] ) && is_array( $response_data['usage'] ) ? $response_data['usage'] : [];
		$prompt_tokens     = isset( $usage['prompt_tokens'] ) && is_int( $usage['prompt_tokens'] ) ? $usage['prompt_tokens'] : 0;
		$completion_tokens = isset( $usage['completion_tokens'] ) && is_int( $usage['completion_tokens'] ) ? $usage['completion_tokens'] : 0;
		$total_tokens      = isset( $usage['total_tokens'] ) && is_int( $usage['total_tokens'] ) ? $usage['total_tokens'] : ( $prompt_tokens + $completion_tokens );

		return new DecisionResult(
			isset( $response_data['model'] ) && is_string( $response_data['model'] ) ? $response_data['model'] : $this->metadata()->getId(),
			$answers,
			new TokenUsage( $prompt_tokens, $completion_tokens, $total_tokens )
		);
	}
}
