<?php
/**
 * The result of an OpenRouter decision request.
 *
 * @package rtcamp/rtcamps-ai-provider-for-openrouter
 *
 * @since 1.3.0
 */

declare( strict_types=1 );

namespace rtCamp\AIProviderForOpenRouter\Decisions;

use WordPress\AiClient\Common\Exception\InvalidArgumentException;
use WordPress\AiClient\Results\DTO\TokenUsage;

// phpcs:disable WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- camelCase, matching the AI Client DTOs.

/**
 * Class for the answers a decision model gave to a set of questions.
 *
 * @since 1.3.0
 */
class DecisionResult {

	/**
	 * The model that answered.
	 *
	 * @since 1.3.0
	 *
	 */
	private string $model_id;

	/**
	 * The answers, keyed by question name.
	 *
	 * @since 1.3.0
	 *
	 * @var array<string, \rtCamp\AIProviderForOpenRouter\Decisions\DecisionAnswer>
	 */
	private array $answers;

	/**
	 * The token usage.
	 *
	 * @since 1.3.0
	 *
	 */
	private TokenUsage $token_usage;

	/**
	 * Constructor.
	 *
	 * @since 1.3.0
	 *
	 * @param string                                                                  $model_id    The model that answered.
	 * @param array<string, \rtCamp\AIProviderForOpenRouter\Decisions\DecisionAnswer> $answers     The answers, keyed by question name.
	 * @param \WordPress\AiClient\Results\DTO\TokenUsage                              $token_usage The token usage.
	 */
	public function __construct( string $model_id, array $answers, TokenUsage $token_usage ) {
		$this->model_id    = $model_id;
		$this->answers     = $answers;
		$this->token_usage = $token_usage;
	}

	/**
	 * Returns the model that answered.
	 *
	 * @since 1.3.0
	 *
	 * @return string The model ID.
	 */
	public function getModelId(): string {
		return $this->model_id;
	}

	/**
	 * Returns every answer, keyed by question name.
	 *
	 * @since 1.3.0
	 *
	 * @return array<string, \rtCamp\AIProviderForOpenRouter\Decisions\DecisionAnswer> The answers.
	 */
	public function getAnswers(): array {
		return $this->answers;
	}

	/**
	 * Checks whether there is an answer for the given question.
	 *
	 * @since 1.3.0
	 *
	 * @param string $name The question name.
	 * @return bool True if the question was answered.
	 */
	public function hasAnswer( string $name ): bool {
		return isset( $this->answers[ $name ] );
	}

	/**
	 * Returns the answer for the given question.
	 *
	 * @since 1.3.0
	 *
	 * @param string $name The question name.
	 * @return \rtCamp\AIProviderForOpenRouter\Decisions\DecisionAnswer The answer.
	 * @throws \WordPress\AiClient\Common\Exception\InvalidArgumentException If there is no answer for that name.
	 */
	public function getAnswer( string $name ): DecisionAnswer {
		if ( ! isset( $this->answers[ $name ] ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new InvalidArgumentException(
				sprintf( 'No decision answer found for "%s".', $name )
			);
		}

		return $this->answers[ $name ];
	}

	/**
	 * Returns the token usage for the request.
	 *
	 * @since 1.3.0
	 *
	 * @return \WordPress\AiClient\Results\DTO\TokenUsage The token usage.
	 */
	public function getTokenUsage(): TokenUsage {
		return $this->token_usage;
	}

	/**
	 * Returns the result as an associative array.
	 *
	 * @since 1.3.0
	 *
	 * @return array<string, mixed> The result data.
	 */
	public function toArray(): array {
		return [
			'model'       => $this->model_id,
			'answers'     => array_map(
				static function ( DecisionAnswer $answer ): array {
					return $answer->toArray();
				},
				$this->answers
			),
			'token_usage' => $this->token_usage->toArray(),
		];
	}
}
