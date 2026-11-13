<?php
/**
 * An answer from an OpenRouter decision model.
 *
 * @package rtcamp/rtcamps-ai-provider-for-openrouter
 *
 * @since 1.3.0
 */

declare( strict_types=1 );

namespace rtCamp\AIProviderForOpenRouter\Decisions;

use WordPress\AiClient\Providers\Http\Exception\ResponseException;

// phpcs:disable WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- camelCase, matching the AI Client DTOs.

/**
 * Class for the typed answer to a single decision question.
 *
 * Which getters return a value depends on the question type:
 *  - choice: getChoice(), getProbabilities(), getConfidence().
 *  - noul:   getProbability().
 *  - score:  getScore(), getProbabilities(), getConfidence(), getLegend().
 *  - box:    getBox(), getConfidence().
 *
 * @since 1.3.0
 */
class DecisionAnswer {

	/**
	 * The question type.
	 *
	 * @since 1.3.0
	 *
	 */
	private DecisionTypeEnum $type;

	/**
	 * The answer value: option key for choice, probability for noul, position for score, or bounding boxes for box.
	 *
	 * @since 1.3.0
	 *
	 * @var string|float|array<int|string, mixed>
	 */
	private $value;

	/**
	 * The probability of each option or level.
	 *
	 * @since 1.3.0
	 *
	 * @var array<string, float>
	 */
	private array $probabilities;

	/**
	 * How concentrated the distribution is, from 0 to 1, or null when not provided.
	 *
	 * @since 1.3.0
	 *
	 */
	private ?float $confidence;

	/**
	 * The score level descriptions, keyed by level index.
	 *
	 * @since 1.3.0
	 *
	 * @var array<string, string>
	 */
	private array $legend;

	/**
	 * Constructor.
	 *
	 * @since 1.3.0
	 *
	 * @param \rtCamp\AIProviderForOpenRouter\Decisions\DecisionTypeEnum $type          The question type.
	 * @param string|float|array<int|string, mixed>                      $value         The answer value.
	 * @param array<string, float>                                       $probabilities The probability of each option or level.
	 * @param float|null                                                 $confidence    The confidence, or null when not provided.
	 * @param array<string, string>                                      $legend        The score level descriptions.
	 */
	public function __construct(
		DecisionTypeEnum $type,
		$value,
		array $probabilities = [],
		?float $confidence = null,
		array $legend = []
	) {
		$this->type          = $type;
		$this->value         = $value;
		$this->probabilities = $probabilities;
		$this->confidence    = $confidence;
		$this->legend        = $legend;
	}

	/**
	 * Creates an answer from the API data for one question.
	 *
	 * @since 1.3.0
	 *
	 * @param \rtCamp\AIProviderForOpenRouter\Decisions\DecisionTypeEnum $type The type of the question asked.
	 * @param mixed                                                      $data The answer data from the response.
	 * @param string                                                     $path Where the answer sits in the response, for error messages.
	 * @return self The answer.
	 * @throws \WordPress\AiClient\Providers\Http\Exception\ResponseException If the data does not match the type.
	 */
	public static function fromResponseData( DecisionTypeEnum $type, $data, string $path ): self {
		if ( ! is_array( $data ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw ResponseException::fromInvalidData( 'OpenRouter', $path, 'The value must be an object.' );
		}

		if ( $type->isNoul() ) {
			$probability_key = isset( $data['noul'] ) ? 'noul' : 'true';
			$confidence      = isset( $data['confidence'] ) && is_numeric( $data['confidence'] ) ? (float) $data['confidence'] : null;

			return new self( $type, self::readProbability( $data, $probability_key, $path ), [], $confidence );
		}

		if ( $type->isBox() ) {
			$confidence = isset( $data['confidence'] ) && is_numeric( $data['confidence'] ) ? (float) $data['confidence'] : null;
			$boxes      = null;

			if ( isset( $data['boxes'] ) && is_array( $data['boxes'] ) ) {
				$boxes = $data['boxes'];
			} elseif ( isset( $data['box'] ) && is_array( $data['box'] ) ) {
				$boxes = $data['box'];
			}

			if ( null === $boxes ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
				throw ResponseException::fromInvalidData( 'OpenRouter', $path . '.box', 'The value must be an array.' );
			}

			return new self( $type, $boxes, [], $confidence );
		}

		$confidence    = self::readProbability( $data, 'confidence', $path );
		$probabilities = self::readProbabilities( $data, $path );

		if ( $type->isChoice() ) {
			if ( ! isset( $data['choice'] ) || ! is_string( $data['choice'] ) ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
				throw ResponseException::fromInvalidData( 'OpenRouter', $path . '.choice', 'The value must be a string.' );
			}

			return new self( $type, $data['choice'], $probabilities, $confidence );
		}

		if ( ! isset( $data['score'] ) || ! is_numeric( $data['score'] ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw ResponseException::fromInvalidData( 'OpenRouter', $path . '.score', 'The value must be a number.' );
		}

		$legend = [];
		if ( isset( $data['legend'] ) && is_array( $data['legend'] ) ) {
			foreach ( $data['legend'] as $level => $description ) {
				if ( ! is_string( $description ) ) {
					continue;
				}

				$legend[ (string) $level ] = $description;
			}
		}

		return new self( $type, (float) $data['score'], $probabilities, $confidence, $legend );
	}

	/**
	 * Returns the question type.
	 *
	 * @since 1.3.0
	 *
	 * @return \rtCamp\AIProviderForOpenRouter\Decisions\DecisionTypeEnum The question type.
	 */
	public function getType(): DecisionTypeEnum {
		return $this->type;
	}

	/**
	 * Returns the raw answer value.
	 *
	 * @since 1.3.0
	 *
	 * @return string|float|array<int|string, mixed> Option key for choice, probability for noul, position for score, or boxes for box.
	 */
	public function getValue() {
		return $this->value;
	}

	/**
	 * Returns the most probable option of a choice answer.
	 *
	 * @since 1.3.0
	 *
	 * @return string|null The option key, or null if this is not a choice answer.
	 */
	public function getChoice(): ?string {
		return $this->type->isChoice() && is_string( $this->value ) ? $this->value : null;
	}

	/**
	 * Returns the probability that the statement of a noul answer is true.
	 *
	 * @since 1.3.0
	 *
	 * @return float|null The probability from 0 to 1, or null if this is not a noul answer.
	 */
	public function getProbability(): ?float {
		return $this->type->isNoul() && is_numeric( $this->value ) ? (float) $this->value : null;
	}

	/**
	 * Returns the probability-weighted position of a score answer.
	 *
	 * @since 1.3.0
	 *
	 * @return float|null The position from 0 to one less than the number of levels, or null if
	 *                    this is not a score answer.
	 */
	public function getScore(): ?float {
		return $this->type->isScore() && is_numeric( $this->value ) ? (float) $this->value : null;
	}

	/**
	 * Returns the bounding box data of a box answer.
	 *
	 * @since 1.3.0
	 *
	 * @return array<int|string, mixed> The bounding box coordinates/entries, or empty array if not a box answer.
	 */
	public function getBox(): array {
		return $this->type->isBox() && is_array( $this->value ) ? $this->value : [];
	}

	/**
	 * Returns the probability of each option (choice) or level index (score).
	 *
	 * @since 1.3.0
	 *
	 * @return array<string, float> The probabilities, empty for noul and box answers.
	 */
	public function getProbabilities(): array {
		return $this->probabilities;
	}

	/**
	 * Returns how concentrated the distribution is, from 0 to 1.
	 *
	 * @since 1.3.0
	 *
	 * @return float|null The confidence from 0 to 1, or null when not provided.
	 */
	public function getConfidence(): ?float {
		return $this->confidence;
	}

	/**
	 * Returns the level descriptions of a score answer, keyed by level index.
	 *
	 * @since 1.3.0
	 *
	 * @return array<string, string> The legend, empty for other answers.
	 */
	public function getLegend(): array {
		return $this->legend;
	}

	/**
	 * Checks whether the answer is confident enough to act on.
	 *
	 * @since 1.3.0
	 *
	 * @param float $threshold The minimum confidence, from 0 to 1.
	 * @return bool True if the answer meets the threshold.
	 */
	public function isConfident( float $threshold ): bool {
		if ( $this->type->isNoul() && null === $this->confidence && is_numeric( $this->value ) ) {
			return abs( (float) $this->value - 0.5 ) * 2 >= $threshold;
		}

		return null !== $this->confidence && $this->confidence >= $threshold;
	}

	/**
	 * Returns the answer as an associative array.
	 *
	 * @since 1.3.0
	 *
	 * @return array<string, mixed> The answer data.
	 */
	public function toArray(): array {
		$data = [
			'type'  => $this->type->value,
			'value' => $this->value,
		];

		if ( ! empty( $this->probabilities ) ) {
			$data['probabilities'] = $this->probabilities;
		}

		if ( null !== $this->confidence ) {
			$data['confidence'] = $this->confidence;
		}

		if ( ! empty( $this->legend ) ) {
			$data['legend'] = $this->legend;
		}

		return $data;
	}

	/**
	 * Reads a required numeric probability field from the API response.
	 *
	 * @since 1.3.0
	 *
	 * @param array<mixed> $data The response data for the answer.
	 * @param string       $key  The key to read.
	 * @param string       $path The base path for error messages.
	 * @return float The probability value.
	 * @throws \WordPress\AiClient\Providers\Http\Exception\ResponseException If the field is missing or non-numeric.
	 */
	private static function readProbability( array $data, string $key, string $path ): float {
		if ( ! isset( $data[ $key ] ) || ! is_numeric( $data[ $key ] ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw ResponseException::fromInvalidData( 'OpenRouter', $path . '.' . $key, 'The value must be a number.' );
		}

		return (float) $data[ $key ];
	}

	/**
	 * Reads the probabilities map from the API response.
	 *
	 * @since 1.3.0
	 *
	 * @param array<mixed> $data The response data for the answer.
	 * @param string       $path The base path for error messages.
	 * @return array<string, float> The probabilities map.
	 * @throws \WordPress\AiClient\Providers\Http\Exception\ResponseException If the field is missing or invalid.
	 */
	private static function readProbabilities( array $data, string $path ): array {
		if ( ! isset( $data['probabilities'] ) || ! is_array( $data['probabilities'] ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw ResponseException::fromInvalidData( 'OpenRouter', $path . '.probabilities', 'The value must be an object.' );
		}

		$probabilities = [];
		foreach ( $data['probabilities'] as $key => $probability ) {
			if ( ! is_numeric( $probability ) ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
				throw ResponseException::fromInvalidData(
					'OpenRouter',
					$path . '.probabilities.' . $key,
					'The value must be a number.'
				);
			}

			$probabilities[ (string) $key ] = (float) $probability;
		}

		return $probabilities;
	}
}
