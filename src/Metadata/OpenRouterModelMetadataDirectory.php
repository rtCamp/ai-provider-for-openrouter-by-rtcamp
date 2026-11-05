<?php
/**
 * OpenRouter Model Metadata Directory.
 *
 * @package rtcamp/rtcamps-ai-provider-for-openrouter
 *
 * @since 1.0.0
 */

declare( strict_types=1 );

namespace rtCamp\AIProviderForOpenRouter\Metadata;

use rtCamp\AIProviderForOpenRouter\Settings\OpenRouterSettings;
use WordPress\AiClient\Files\Enums\FileTypeEnum;
use WordPress\AiClient\Messages\Enums\ModalityEnum;
use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiBasedModelMetadataDirectory;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Providers\Models\DTO\SupportedOption;
use WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;
use WordPress\AiClient\Providers\Models\Enums\OptionEnum;

/**
 * Class for the OpenRouter model metadata directory.
 *
 * Returns only the models selected in plugin settings (text + image + speech) as
 * metadata entries used by the AI client for capability matching.
 *
 * @since 1.0.0
 */
class OpenRouterModelMetadataDirectory extends AbstractApiBasedModelMetadataDirectory {

	/**
	 * Returns model metadata map for selected settings models only.
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	protected function sendListModelsRequest(): array {
		$models_map = [];

		$text_model_id = trim( OpenRouterSettings::get_selected_model() );
		if ( '' !== $text_model_id ) {
			$models_map[ $text_model_id ] = $this->createTextModelMetadata( $text_model_id );
		}

		$image_model_id = trim( OpenRouterSettings::get_selected_image_model() );
		if ( '' !== $image_model_id ) {
			if ( isset( $models_map[ $image_model_id ] ) ) {
				$models_map[ $image_model_id ] = $this->createCombinedModelMetadata( $image_model_id );
			} else {
				$models_map[ $image_model_id ] = $this->createImageModelMetadata( $image_model_id );
			}
		}

		$tts_model_id = trim( OpenRouterSettings::get_selected_tts_model() );
		if ( '' !== $tts_model_id ) {
			if ( isset( $models_map[ $tts_model_id ] ) ) {
				$models_map[ $tts_model_id ] = $this->createCombinedTtsModelMetadata( $models_map[ $tts_model_id ], $tts_model_id );
			} else {
				$models_map[ $tts_model_id ] = $this->createTtsModelMetadata( $tts_model_id );
			}
		}

		ksort( $models_map );

		return $models_map;
	}

	/**
	 * Creates metadata for the selected text model.
	 *
	 * @since 1.0.0
	 *
	 * @param string $model_id The model identifier.
	 */
	private function createTextModelMetadata( string $model_id ): ModelMetadata {
		return new ModelMetadata(
			$model_id,
			$model_id,
			[
				CapabilityEnum::textGeneration(),
				CapabilityEnum::chatHistory(),
			],
			[
				new SupportedOption( OptionEnum::systemInstruction() ),
				new SupportedOption( OptionEnum::candidateCount() ),
				new SupportedOption( OptionEnum::maxTokens() ),
				new SupportedOption( OptionEnum::temperature() ),
				new SupportedOption( OptionEnum::topP() ),
				new SupportedOption( OptionEnum::stopSequences() ),
				new SupportedOption( OptionEnum::frequencyPenalty() ),
				new SupportedOption( OptionEnum::presencePenalty() ),
				new SupportedOption( OptionEnum::outputMimeType(), [ 'text/plain', 'application/json' ] ),
				new SupportedOption( OptionEnum::outputSchema() ),
				new SupportedOption( OptionEnum::functionDeclarations() ),
				new SupportedOption( OptionEnum::customOptions() ),
				new SupportedOption( OptionEnum::outputModalities(), [ [ ModalityEnum::text() ] ] ),
				new SupportedOption( OptionEnum::inputModalities(), [ [ ModalityEnum::text() ], [ ModalityEnum::text(), ModalityEnum::image() ] ] ),
			]
		);
	}

	/**
	 * Creates metadata for the selected image model.
	 *
	 * @since 1.0.0
	 *
	 * @param string $model_id The model identifier.
	 */
	private function createImageModelMetadata( string $model_id ): ModelMetadata {
		return new ModelMetadata(
			$model_id,
			$model_id,
			[
				CapabilityEnum::imageGeneration(),
			],
			[
				new SupportedOption( OptionEnum::inputModalities(), array( array( ModalityEnum::text() ) ) ),
				new SupportedOption( OptionEnum::outputModalities(), array( array( ModalityEnum::image() ) ) ),
				new SupportedOption( OptionEnum::candidateCount() ),
				new SupportedOption( OptionEnum::outputMimeType(), array( 'image/png', 'image/jpeg', 'image/webp' ) ),
				new SupportedOption( OptionEnum::outputFileType(), array( FileTypeEnum::inline() ) ),
				new SupportedOption( OptionEnum::customOptions() ),
			]
		);
	}

	/**
	 * Creates metadata for a model selected as both text and image model.
	 *
	 * @since 1.0.0
	 *
	 * @param string $model_id The model identifier.
	 */
	private function createCombinedModelMetadata( string $model_id ): ModelMetadata {
		return new ModelMetadata(
			$model_id,
			$model_id,
			[
				CapabilityEnum::textGeneration(),
				CapabilityEnum::chatHistory(),
				CapabilityEnum::imageGeneration(),
			],
			[
				new SupportedOption( OptionEnum::systemInstruction() ),
				new SupportedOption( OptionEnum::candidateCount() ),
				new SupportedOption( OptionEnum::maxTokens() ),
				new SupportedOption( OptionEnum::temperature() ),
				new SupportedOption( OptionEnum::topP() ),
				new SupportedOption( OptionEnum::stopSequences() ),
				new SupportedOption( OptionEnum::frequencyPenalty() ),
				new SupportedOption( OptionEnum::presencePenalty() ),
				new SupportedOption( OptionEnum::outputMimeType(), [ 'text/plain', 'application/json' ] ),
				new SupportedOption( OptionEnum::outputSchema() ),
				new SupportedOption( OptionEnum::functionDeclarations() ),
				new SupportedOption( OptionEnum::customOptions() ),
				new SupportedOption( OptionEnum::outputModalities(), [ [ ModalityEnum::text() ] ] ),
				new SupportedOption( OptionEnum::inputModalities(), [ [ ModalityEnum::text() ], [ ModalityEnum::text(), ModalityEnum::image() ] ] ),
			]
		);
	}

	/**
	 * Creates metadata for the selected text-to-speech model.
	 *
	 * @since 1.2.0
	 *
	 * @param string $model_id The model identifier.
	 * @return ModelMetadata The model metadata.
	 */
	private function createTtsModelMetadata( string $model_id ): ModelMetadata {
		$voices = $this->get_supported_voices_for_model( $model_id );

		$options = [
			new SupportedOption( OptionEnum::inputModalities(), [ [ ModalityEnum::text() ] ] ),
			new SupportedOption( OptionEnum::outputModalities(), [ [ ModalityEnum::audio() ] ] ),
			new SupportedOption(
				OptionEnum::outputMimeType(),
				[
					'audio/mpeg',
					'audio/ogg',
					'audio/wav',
					'audio/flac',
					'audio/aac',
				]
			),
			new SupportedOption( OptionEnum::outputFileType(), [ FileTypeEnum::inline() ] ),
			new SupportedOption( OptionEnum::customOptions() ),
		];

		if ( ! empty( $voices ) ) {
			$options[] = new SupportedOption( OptionEnum::outputSpeechVoice(), $voices );
		} else {
			$options[] = new SupportedOption( OptionEnum::outputSpeechVoice() );
		}

		return new ModelMetadata(
			$model_id,
			$model_id,
			[
				CapabilityEnum::textToSpeechConversion(),
			],
			$options
		);
	}

	/**
	 * Returns supported voices for a TTS model ID from cached models list.
	 *
	 * @since 1.2.0
	 *
	 * @param string $model_id The model ID.
	 * @return list<string> List of supported voice IDs.
	 */
	private function get_supported_voices_for_model( string $model_id ): array {
		$cached_models = OpenRouterSettings::get_cached_tts_models();
		foreach ( $cached_models as $model ) {
			if ( isset( $model['id'] ) && $model['id'] === $model_id ) {
				if ( isset( $model['supported_voices'] ) && is_array( $model['supported_voices'] ) ) {
					return array_values(
						array_filter(
							array_map( 'strval', $model['supported_voices'] ),
							static function ( string $v ): bool {
								return '' !== trim( $v );
							}
						)
					);
				}
				break;
			}
		}

		return [];
	}

	/**
	 * Adds text-to-speech capabilities and options to existing model metadata.
	 *
	 * @since 1.2.0
	 *
	 * @param ModelMetadata $existing Existing model metadata.
	 * @param string        $model_id The model identifier.
	 * @return ModelMetadata Merged model metadata.
	 */
	private function createCombinedTtsModelMetadata( ModelMetadata $existing, string $model_id ): ModelMetadata {
		$capabilities   = $existing->getSupportedCapabilities();
		$capabilities[] = CapabilityEnum::textToSpeechConversion();

		$options  = $existing->getSupportedOptions();
		$tts_meta = $this->createTtsModelMetadata( $model_id );
		foreach ( $tts_meta->getSupportedOptions() as $tts_opt ) {
			$found = false;
			foreach ( $options as $opt ) {
				if ( $opt->getName()->is( $tts_opt->getName() ) ) {
					$found = true;
					break;
				}
			}
			if ( ! $found ) {
				$options[] = $tts_opt;
			}
		}

		return new ModelMetadata(
			$model_id,
			$model_id,
			$capabilities,
			$options
		);
	}
}
