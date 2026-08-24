<?php
/**
 * The main plugin class.
 *
 * @since 1.0.0
 * @package rtcamp/ai-provider-for-openrouter-by-rtcamp
 */

declare( strict_types=1 );

namespace rtCamp\AIProviderForOpenRouterByrtCamp;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use rtCamp\AIProviderForOpenRouterByrtCamp\Provider\OpenRouterProvider;
use rtCamp\AIProviderForOpenRouterByrtCamp\Settings\OpenRouterSettings;
use WordPress\AiClient\AiClient;
use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;

/**
 * Plugin class.
 *
 * @since 1.0.0
 */
class Plugin {

	/**
	 * Initializes the plugin.
	 *
	 * @since 1.0.0
	 */
	public function init(): void {
		add_action( 'init', [ $this, 'register_provider' ], 5 );
		add_action( 'init', [ $this, 'register_fallback_auth' ], 15 );
		add_action( 'init', [ $this, 'initialize_settings' ] );
		add_filter( 'ai_experiments_preferred_image_models', [ $this, 'prepend_openrouter_image_model' ], 5 );
		add_filter( 'plugin_action_links_' . plugin_basename( AI_PROVIDER_FOR_OPENROUTER_BY_RTCAMP_PLUGIN_FILE ), [ $this, 'plugin_action_links' ] );
	}

	/**
	 * Registers the OpenRouter provider with the AI Client.
	 *
	 * @since 1.0.0
	 */
	public function register_provider(): void {
		if ( ! class_exists( AiClient::class ) ) {
			return;
		}

		$registry = AiClient::defaultRegistry();

		if ( $registry->hasProvider( OpenRouterProvider::class ) ) {
			return;
		}

		$registry->registerProvider( OpenRouterProvider::class );
	}

	/**
	 * Registers an empty fallback API key only when no credentials have been
	 * configured yet.
	 *
	 * The AI Client requires some form of authentication object to be present
	 * even for providers that do not strictly need one. The actual OpenRouter
	 * API key is set by the user via Settings > Connectors; this fallback
	 * prevents a runtime exception when no key is stored.
	 *
	 * @since 1.0.0
	 */
	public function register_fallback_auth(): void {
		if ( ! class_exists( AiClient::class ) ) {
			return;
		}

		$registry = AiClient::defaultRegistry();

		if ( ! $registry->hasProvider( 'openrouter' ) ) {
			return;
		}

		$auth = $registry->getProviderRequestAuthentication( 'openrouter' );
		if ( null !== $auth ) {
			return;
		}

		$env_key = (string) getenv( 'OPENROUTER_API_KEY' );
		$registry->setProviderRequestAuthentication(
			'openrouter',
			new ApiKeyRequestAuthentication( $env_key )
		);
	}

	/**
	 * Initializes the OpenRouter settings.
	 *
	 * @since 1.0.0
	 */
	public function initialize_settings(): void {
		$settings = new OpenRouterSettings();
		$settings->init();
	}

	/**
	 * Prepends the configured OpenRouter image model to the preferred image models list.
	 *
	 * @since 1.0.0
	 *
	 * @param array<array{string, string}> $models Ordered list of [provider, model] pairs.
	 * @return array<array{string, string}> Modified list with OpenRouter entry at the front.
	 */
	public function prepend_openrouter_image_model( array $models ): array {
		$selected = OpenRouterSettings::get_selected_image_model();
		if ( '' !== $selected ) {
			array_unshift( $models, [ 'openrouter', $selected ] );
		}
		return $models;
	}

	/**
	 * Adds action links to the plugin list table.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string> $links Existing action links.
	 * @return array<string> Modified action links.
	 */
	public function plugin_action_links( array $links ): array {
		$settings_link = sprintf(
			'<a href="%1$s">%2$s</a>',
			admin_url( 'options-general.php?page=ai-provider-for-openrouter-by-rtcamp' ),
			esc_html__( 'Settings', 'ai-provider-for-openrouter-by-rtcamp' )
		);

		array_unshift( $links, $settings_link );

		return $links;
	}
}
