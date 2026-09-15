<?php
/**
 * OpenRouter Settings.
 *
 * @package rtcamp/rtcamps-ai-provider-for-openrouter
 *
 * @since 1.0.0
 */

declare( strict_types=1 );

namespace rtCamp\AIProviderForOpenRouterByrtCamp\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Class for OpenRouter settings in the WordPress admin.
 *
 * @since 1.0.0
 */
class OpenRouterSettings {

	private const OPTION_GROUP  = 'ai_provider_for_openrouter_by_rtcamp_settings';
	private const OPTION_NAME   = 'ai_provider_for_openrouter_by_rtcamp_settings';
	private const PAGE_SLUG     = 'rtcamps-ai-provider-for-openrouter';
	private const SECTION_ID    = 'ai_provider_for_openrouter_by_rtcamp_main';
	private const KEY_MODEL     = 'model';
	private const KEY_IMAGE_MODEL = 'image_model';
	private const MODELS_TRANSIENT = 'ai_openrouter_models_v1';
	private const MODELS_IMAGE_TRANSIENT = 'ai_openrouter_image_models_v1';
	private const MODELS_CACHE_TTL = HOUR_IN_SECONDS;
	// phpcs:ignore PluginCheck.CodeAnalysis.AIProvider.DirectIntegration -- AI Provider plugin connects directly to OpenRouter API.
	private const OPENROUTER_MODELS_URL = 'https://openrouter.ai/api/v1/models?output_modality=text';
	// phpcs:ignore PluginCheck.CodeAnalysis.AIProvider.DirectIntegration -- AI Provider plugin connects directly to OpenRouter API.
	private const OPENROUTER_IMAGE_MODELS_URL = 'https://openrouter.ai/api/v1/models?output_modality=image';

	/**
	 * Initializes the settings.
	 *
	 * @since 1.0.0
	 */
	public function init(): void {
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'admin_menu', [ $this, 'register_settings_screen' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_settings_script' ] );
		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
	}

	/**
	 * Registers the setting and fields.
	 *
	 * @since 1.0.0
	 */
	public function register_settings(): void {
		register_setting(
			self::OPTION_GROUP,
			self::OPTION_NAME,
			[
				'type'              => 'array',
				'default'           => [],
				'sanitize_callback' => [ $this, 'sanitize_settings' ],
			]
		);

		add_settings_section(
			self::SECTION_ID,
			'',
			'__return_empty_string',
			self::PAGE_SLUG
		);

		add_settings_field(
			self::OPTION_NAME . '_model',
			__( 'Default Model', 'rtcamps-ai-provider-for-openrouter' ),
			[ $this, 'render_model_field' ],
			self::PAGE_SLUG,
			self::SECTION_ID,
			[ 'label_for' => self::OPTION_NAME . '-model-search' ]
		);

		add_settings_field(
			self::OPTION_NAME . '_image_model',
			__( 'Image Generation Model', 'rtcamps-ai-provider-for-openrouter' ),
			[ $this, 'render_image_model_field' ],
			self::PAGE_SLUG,
			self::SECTION_ID,
			[ 'label_for' => self::OPTION_NAME . '-image-model-search' ]
		);
	}

	/**
	 * Registers the settings screen.
	 *
	 * @since 1.0.0
	 */
	public function register_settings_screen(): void {
		add_options_page(
			__( 'OpenRouter Settings', 'rtcamps-ai-provider-for-openrouter' ),
			__( 'OpenRouter Settings', 'rtcamps-ai-provider-for-openrouter' ),
			'manage_options',
			self::PAGE_SLUG,
			[ $this, 'render_screen' ]
		);
	}

	/**
	 * Sanitizes the settings array.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value The input value.
	 * @return array<string, string> The sanitized settings.
	 */
	public function sanitize_settings( $value ): array {
		if ( ! is_array( $value ) ) {
			return self::get_default_settings();
		}

		$model = isset( $value[ self::KEY_MODEL ] ) ? sanitize_text_field( (string) $value[ self::KEY_MODEL ] ) : '';
		$image_model = isset( $value[ self::KEY_IMAGE_MODEL ] ) ? sanitize_text_field( (string) $value[ self::KEY_IMAGE_MODEL ] ) : '';
		$model = trim( $model );
		$image_model = trim( $image_model );

		return [
			self::KEY_MODEL       => $model,
			self::KEY_IMAGE_MODEL => $image_model,
		];
	}

	/**
	 * Renders the settings screen.
	 *
	 * @since 1.0.0
	 */
	public function render_screen(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>

		<div class="wrap openrouter-settings-wrap">
			<!-- Header Card -->
			<div class="openrouter-header-card">
				<div class="openrouter-header-logo">
					<img src="<?php echo esc_url( plugins_url( 'assets/images/openrouter-logo.svg', RTCAMPS_AI_PROVIDER_FOR_OPENROUTER_PLUGIN_FILE ) ); ?>" alt="<?php esc_attr_e( 'OpenRouter Logo', 'rtcamps-ai-provider-for-openrouter' ); ?>" width="32" height="32" />
				</div>
				<div class="openrouter-header-text">
					<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
					<p class="openrouter-subtitle"><?php esc_html_e( 'Connect and configure default AI intelligence models seamlessly.', 'rtcamps-ai-provider-for-openrouter' ); ?></p>
				</div>
			</div>

			<!-- Notice / Action Cards -->
			<div class="openrouter-dashboard-grid">
				<div class="openrouter-dashboard-card openrouter-card-setup">
					<div class="openrouter-card-icon">
						<span class="dashicons dashicons-admin-generic"></span>
					</div>
					<div class="openrouter-card-body">
						<h3><?php esc_html_e( 'API Key Configuration', 'rtcamps-ai-provider-for-openrouter' ); ?></h3>
						<p>
							<?php
							printf(
								/* translators: 1: opening anchor tag, 2: closing anchor tag */
								esc_html__( 'OpenRouter requires an API key to process requests. Set up your credentials under %1$sSettings > Connectors%2$s.', 'rtcamps-ai-provider-for-openrouter' ),
								'<a href="' . esc_url( admin_url( 'options-connectors.php' ) ) . '" class="openrouter-link">',
								'</a>'
							);
							?>
						</p>
					</div>
				</div>

				<div class="openrouter-dashboard-card openrouter-card-explore">
					<div class="openrouter-card-icon">
						<span class="dashicons dashicons-search"></span>
					</div>
					<div class="openrouter-card-body">
						<h3><?php esc_html_e( 'Explore Hundreds of Models', 'rtcamps-ai-provider-for-openrouter' ); ?></h3>
						<p>
							<?php
							printf(
								/* translators: 1: opening anchor tag, 2: closing anchor tag */
								esc_html__( 'Compare pricing, speed, latency, and context windows dynamically at %1$sopenrouter.ai/models%2$s.', 'rtcamps-ai-provider-for-openrouter' ),
								// phpcs:ignore PluginCheck.CodeAnalysis.AIProvider.DirectIntegration -- Informational documentation link.
								'<a href="https://openrouter.ai/models" target="_blank" rel="noopener noreferrer" class="openrouter-link">',
								'</a>'
							);
							?>
						</p>
					</div>
				</div>
			</div>

			<!-- Settings Form Card -->
			<div class="openrouter-settings-card">
				<form action="options.php" method="post">
					<?php
					settings_fields( self::OPTION_GROUP );
					do_settings_sections( self::PAGE_SLUG );
					submit_button();
					?>
				</form>
			</div>
		</div>

		<?php
	}

	/**
	 * Renders the model autocomplete field.
	 *
	 * Displays a text input with an inline autocomplete dropdown. When the user
	 * types three or more characters the JavaScript layer filters the pre-loaded
	 * model list and shows up to five suggestions that include the model ID,
	 * display name, context length, and per-token pricing.
	 *
	 * @since 1.0.0
	 */
	public function render_model_field(): void {
		$settings      = self::get_settings();
		$current_model = isset( $settings[ self::KEY_MODEL ] ) ? (string) $settings[ self::KEY_MODEL ] : '';
		$input_id      = self::OPTION_NAME . '-model-search';
		$hidden_id     = self::OPTION_NAME . '-model-value';
		$hidden_name   = self::OPTION_NAME . '[' . self::KEY_MODEL . ']';
		?>

		<div class="openrouter-field-group">
			<div id="openrouter-model-container" class="openrouter-field-container">
				<span class="openrouter-search-icon dashicons dashicons-search"></span>
				<input
					type="text"
					id="<?php echo esc_attr( $input_id ); ?>"
					class="regular-text openrouter-search-input"
					placeholder="<?php esc_attr_e( 'Type 3+ letters to search models…', 'rtcamps-ai-provider-for-openrouter' ); ?>"
					value="<?php echo esc_attr( $current_model ); ?>"
					autocomplete="off"
					aria-label="<?php esc_attr_e( 'Search OpenRouter models', 'rtcamps-ai-provider-for-openrouter' ); ?>"
				/>
				<input
					type="hidden"
					id="<?php echo esc_attr( $hidden_id ); ?>"
					name="<?php echo esc_attr( $hidden_name ); ?>"
					value="<?php echo esc_attr( $current_model ); ?>"
				/>
				<div
					id="openrouter-model-dropdown"
					role="listbox"
					class="openrouter-dropdown"
				></div>
			</div>

			<span class="openrouter-tooltip-wrapper" tabindex="0" aria-describedby="openrouter-model-tooltip-desc" aria-label="<?php esc_attr_e( 'Model help description', 'rtcamps-ai-provider-for-openrouter' ); ?>">
				<span class="dashicons dashicons-editor-help"></span>
				<span id="openrouter-model-tooltip-desc" role="tooltip" class="openrouter-tooltip-text"><?php esc_html_e( 'Choose a default OpenRouter model override. Leave empty to use the model requested by AI Client.', 'rtcamps-ai-provider-for-openrouter' ); ?></span>
			</span>
			<span id="openrouter-model-status" class="openrouter-status"></span>
		</div>

		<div id="openrouter-model-info" class="openrouter-info"></div>

		<?php
	}

	/**
	 * Renders the image generation model autocomplete field.
	 *
	 * @since 1.0.0
	 */
	public function render_image_model_field(): void {
		$settings            = self::get_settings();
		$current_image_model = isset( $settings[ self::KEY_IMAGE_MODEL ] ) ? (string) $settings[ self::KEY_IMAGE_MODEL ] : '';
		$input_id            = self::OPTION_NAME . '-image-model-search';
		$hidden_id           = self::OPTION_NAME . '-image-model-value';
		$hidden_name         = self::OPTION_NAME . '[' . self::KEY_IMAGE_MODEL . ']';
		?>

		<div class="openrouter-field-group">
			<div id="openrouter-image-model-container" class="openrouter-field-container">
				<span class="openrouter-search-icon dashicons dashicons-search"></span>
				<input
					type="text"
					id="<?php echo esc_attr( $input_id ); ?>"
					class="regular-text openrouter-search-input"
					placeholder="<?php esc_attr_e( 'Type to search image models…', 'rtcamps-ai-provider-for-openrouter' ); ?>"
					value="<?php echo esc_attr( $current_image_model ); ?>"
					autocomplete="off"
					aria-label="<?php esc_attr_e( 'Search OpenRouter image generation models', 'rtcamps-ai-provider-for-openrouter' ); ?>"
				/>
				<input
					type="hidden"
					id="<?php echo esc_attr( $hidden_id ); ?>"
					name="<?php echo esc_attr( $hidden_name ); ?>"
					value="<?php echo esc_attr( $current_image_model ); ?>"
				/>
				<div
					id="openrouter-image-model-dropdown"
					role="listbox"
					class="openrouter-dropdown"
				></div>
			</div>

			<span class="openrouter-tooltip-wrapper" tabindex="0" aria-describedby="openrouter-image-model-tooltip-desc" aria-label="<?php esc_attr_e( 'Image model help description', 'rtcamps-ai-provider-for-openrouter' ); ?>">
				<span class="dashicons dashicons-editor-help"></span>
				<span id="openrouter-image-model-tooltip-desc" role="tooltip" class="openrouter-tooltip-text"><?php esc_html_e( 'Choose a dedicated image generation model. This autocomplete lists all OpenRouter models that support image output.', 'rtcamps-ai-provider-for-openrouter' ); ?></span>
			</span>
			<span id="openrouter-image-model-status" class="openrouter-status"></span>
		</div>

		<div id="openrouter-image-model-info" class="openrouter-info"></div>

		<hr class="openrouter-hr" />

		<p class="description openrouter-description-italic">
			<?php esc_html_e( 'Suggestions show input price, output price (per 1M tokens), and context length. Be aware that pricing for some models is based on average text and image output, which isn\'t listed here. Please verify the exact pricing at openrouter.ai/models.', 'rtcamps-ai-provider-for-openrouter' ); ?>
		</p>

		<?php
	}

	/**
	 * Enqueues the settings page script.
	 *
	 * @since 1.0.0
	 *
	 * @param string $hook_suffix The current admin page hook suffix.
	 */
	public function enqueue_settings_script( string $hook_suffix ): void {
		if ( 'settings_page_' . self::PAGE_SLUG !== $hook_suffix ) {
			return;
		}

		$plugin_dir = RTCAMPS_AI_PROVIDER_FOR_OPENROUTER_PLUGIN_DIR;
		$asset_file = $plugin_dir . 'build/admin/settings.asset.php';
		$asset      = file_exists( $asset_file ) ? require $asset_file : []; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable -- Asset file path is built from a known constant.

		$dependencies = isset( $asset['dependencies'] ) ? $asset['dependencies'] : [];
		$version      = isset( $asset['version'] ) ? $asset['version'] : false;

		wp_enqueue_script(
			'rtcamps-ai-provider-for-openrouter-settings',
			plugins_url( 'build/admin/settings.js', RTCAMPS_AI_PROVIDER_FOR_OPENROUTER_PLUGIN_FILE ),
			$dependencies,
			$version,
			true
		);

		wp_enqueue_style(
			'rtcamps-ai-provider-for-openrouter-settings',
			plugins_url( 'build/admin/style-settings.css', RTCAMPS_AI_PROVIDER_FOR_OPENROUTER_PLUGIN_FILE ),
			[],
			$version
		);
		wp_style_add_data( 'rtcamps-ai-provider-for-openrouter-settings', 'rtl', 'replace' );

		wp_localize_script(
			'rtcamps-ai-provider-for-openrouter-settings',
			'AIProviderForOpenRouterByrtCampSettings',
			[
				'selectedModel'      => self::get_selected_model(),
				'selectedImageModel' => self::get_selected_image_model(),
				'i18n'               => [
					'loading'     => __( 'Loading models…', 'rtcamps-ai-provider-for-openrouter' ),
					'noResults'   => __( 'No models found.', 'rtcamps-ai-provider-for-openrouter' ),
					'errorLoad'   => __( 'Could not load models.', 'rtcamps-ai-provider-for-openrouter' ),
					'typeMore'    => __( 'Type at least 3 characters to search.', 'rtcamps-ai-provider-for-openrouter' ),
					'modelsCount' => __( ' models available.', 'rtcamps-ai-provider-for-openrouter' ),
					'inPrice'     => __( 'Prompt:', 'rtcamps-ai-provider-for-openrouter' ),
					'outPrice'    => __( 'Completion:', 'rtcamps-ai-provider-for-openrouter' ),
					'ctx'         => __( 'ctx', 'rtcamps-ai-provider-for-openrouter' ),
					'perMillion'  => __( '/1M', 'rtcamps-ai-provider-for-openrouter' ),
					'free'        => __( 'Free', 'rtcamps-ai-provider-for-openrouter' ),
					'na'          => __( 'N/A', 'rtcamps-ai-provider-for-openrouter' ),
				],
			]
		);
	}

	/**
	 * Registers the REST API routes.
	 *
	 * @since 1.1.0
	 */
	public function register_rest_routes(): void {
		register_rest_route(
			'rtcamps-ai-provider-for-openrouter/v1',
			'/models',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_models_endpoint' ],
				'permission_callback' => [ $this, 'check_rest_permissions' ],
			]
		);

		register_rest_route(
			'rtcamps-ai-provider-for-openrouter/v1',
			'/image-models',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_image_models_endpoint' ],
				'permission_callback' => [ $this, 'check_rest_permissions' ],
			]
		);
	}

	/**
	 * Checks permissions for the REST API endpoints.
	 *
	 * @since 1.1.0
	 *
	 * @return bool True if the user has permission, false otherwise.
	 */
	public function check_rest_permissions(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * REST API endpoint to retrieve available models.
	 *
	 * @since 1.1.0
	 *
	 * @return \WP_REST_Response The REST response.
	 */
	public function get_models_endpoint(): \WP_REST_Response {
		$cached = get_transient( self::MODELS_TRANSIENT );
		if ( false !== $cached && is_array( $cached ) ) {
			return new \WP_REST_Response( $cached, 200 );
		}

		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.wp_remote_get_wp_remote_get -- External request to OpenRouter public models endpoint.
		$response = wp_remote_get(
			self::OPENROUTER_MODELS_URL,
			[
				// phpcs:ignore WordPressVIPMinimum.Performance.RemoteRequestTimeout.timeout_timeout -- OpenRouter model listing may be slow on first request.
				'timeout' => 15,
				'headers' => [
					'Accept' => 'application/json',
				],
			]
		);

		if ( is_wp_error( $response ) ) {
			return new \WP_REST_Response(
				[
					// translators: %s: Error message.
					'message' => sprintf( __( 'Could not fetch OpenRouter models. Error: %s', 'rtcamps-ai-provider-for-openrouter' ), $response->get_error_message() ),
				],
				500
			);
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! is_array( $data ) || ! isset( $data['data'] ) || ! is_array( $data['data'] ) ) {
			return new \WP_REST_Response( [ 'message' => __( 'Unexpected response from OpenRouter models endpoint.', 'rtcamps-ai-provider-for-openrouter' ) ], 500 );
		}

		$models = array_values(
			array_filter(
				$data['data'],
				static function ( $model ) {
					return is_array( $model ) && isset( $model['id'] ) && is_string( $model['id'] ) && '' !== trim( $model['id'] );
				}
			)
		);

		set_transient( self::MODELS_TRANSIENT, $models, self::MODELS_CACHE_TTL );

		return new \WP_REST_Response( $models, 200 );
	}

	/**
	 * REST API endpoint to retrieve available image generation models.
	 *
	 * @since 1.1.0
	 *
	 * @return \WP_REST_Response The REST response.
	 */
	public function get_image_models_endpoint(): \WP_REST_Response {
		$cached = get_transient( self::MODELS_IMAGE_TRANSIENT );
		if ( false !== $cached && is_array( $cached ) ) {
			return new \WP_REST_Response( $cached, 200 );
		}

		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.wp_remote_get_wp_remote_get -- External request to OpenRouter public models endpoint.
		$response = wp_remote_get(
			self::OPENROUTER_IMAGE_MODELS_URL,
			[
				// phpcs:ignore WordPressVIPMinimum.Performance.RemoteRequestTimeout.timeout_timeout -- OpenRouter model listing may be slow on first request.
				'timeout' => 15,
				'headers' => [
					'Accept' => 'application/json',
				],
			]
		);

		if ( is_wp_error( $response ) ) {
			return new \WP_REST_Response(
				[
					// translators: %s: Error message.
					'message' => sprintf( __( 'Could not fetch OpenRouter image models. Error: %s', 'rtcamps-ai-provider-for-openrouter' ), $response->get_error_message() ),
				],
				500
			);
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! is_array( $data ) || ! isset( $data['data'] ) || ! is_array( $data['data'] ) ) {
			return new \WP_REST_Response( [ 'message' => __( 'Unexpected response from OpenRouter image models endpoint.', 'rtcamps-ai-provider-for-openrouter' ) ], 500 );
		}

		$models = array_values(
			array_filter(
				$data['data'],
				static function ( $model ) {
					return is_array( $model ) && isset( $model['id'] ) && is_string( $model['id'] ) && '' !== trim( $model['id'] );
				}
			)
		);

		set_transient( self::MODELS_IMAGE_TRANSIENT, $models, self::MODELS_CACHE_TTL );

		return new \WP_REST_Response( $models, 200 );
	}

	/**
	 * Returns the currently selected model from settings.
	 *
	 * @since 1.0.0
	 *
	 * @return string The model identifier, or an empty string if none is set.
	 */
	public static function get_selected_model(): string {
		$settings = self::get_settings();
		$model    = ! empty( $settings[ self::KEY_MODEL ] ) ? (string) $settings[ self::KEY_MODEL ] : 'openrouter/free';
		return trim( $model );
	}

	/**
	 * Returns the currently selected image generation model from settings.
	 *
	 * @since 1.0.0
	 *
	 * @return string The image model identifier, or an empty string if none is set.
	 */
	public static function get_selected_image_model(): string {
		$settings    = self::get_settings();
		$image_model = ! empty( $settings[ self::KEY_IMAGE_MODEL ] ) ? (string) $settings[ self::KEY_IMAGE_MODEL ] : 'openrouter/auto';
		return trim( $image_model );
	}

	/**
	 * Gets settings from the WordPress option.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, string> The settings.
	 */
	public static function get_settings(): array {
		$settings = (array) get_option( self::OPTION_NAME, [] );

		return array_merge( self::get_default_settings(), $settings );
	}

	/**
	 * Returns the default settings array.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, string> Default settings.
	 */
	private static function get_default_settings(): array {
		return [
			self::KEY_MODEL       => '',
			self::KEY_IMAGE_MODEL => '',
		];
	}
}
