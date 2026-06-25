<?php
/**
 * Plugin Name:       Connector for OpenRouter
 * Plugin URI:        https://github.com/rtcamp/connector-for-openrouter
 * Description:       OpenRouter provider for the WordPress AI Client. Access hundreds of AI models (text and image generation) through a single API.
 * Requires at least: 7.0
 * Requires PHP:      7.4
 * Version:           1.1.1
 * Author:            rtCamp
 * Author URI:        https://rtcamp.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       connector-for-openrouter
 * Domain Path:       /languages
 *
 * @package rtCamp\ConnectorForOpenrouter
 */

declare( strict_types=1 );

namespace rtCamp\ConnectorForOpenrouter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CONNECTOR_FOR_OPENROUTER_MIN_PHP_VERSION', '7.4' );
define( 'CONNECTOR_FOR_OPENROUTER_MIN_WP_VERSION', '7.0' );
define( 'CONNECTOR_FOR_OPENROUTER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CONNECTOR_FOR_OPENROUTER_PLUGIN_FILE', __FILE__ );

require_once CONNECTOR_FOR_OPENROUTER_PLUGIN_DIR . 'src/autoload.php';

/**
 * Displays an admin notice for requirement failures.
 *
 * @since 1.0.0
 *
 * @param string $message The error message to display.
 */
function requirement_notice( string $message ): void {
	if ( ! is_admin() ) {
		return;
	}
	?>

	<div class="notice notice-error">
		<p><?php echo wp_kses_post( $message ); ?></p>
	</div>

	<?php
}

/**
 * Checks if the PHP version meets the minimum requirement.
 *
 * @since 1.0.0
 *
 * @return bool True if PHP version is sufficient, false otherwise.
 */
function check_php_version(): bool {
	if ( version_compare( phpversion(), CONNECTOR_FOR_OPENROUTER_MIN_PHP_VERSION, '<' ) ) {
		add_action(
			'admin_notices',
			static function () {
				requirement_notice(
					sprintf(
						/* translators: 1: Required PHP version, 2: Current PHP version */
						__( 'The OpenRouter Provider plugin requires PHP version %1$s or higher. You are running PHP version %2$s.', 'connector-for-openrouter' ),
						CONNECTOR_FOR_OPENROUTER_MIN_PHP_VERSION,
						PHP_VERSION
					)
				);
			}
		);

		return false;
	}

	return true;
}

/**
 * Checks if the WordPress version meets the minimum requirement.
 *
 * @since 1.0.0
 *
 * @global string $wp_version WordPress version.
 *
 * @return bool True if WordPress version is sufficient, false otherwise.
 */
function check_wp_version(): bool {
	if ( ! is_wp_version_compatible( CONNECTOR_FOR_OPENROUTER_MIN_WP_VERSION ) ) {
		add_action(
			'admin_notices',
			static function () {
				global $wp_version;
				requirement_notice(
					sprintf(
						/* translators: 1: Required WordPress version, 2: Current WordPress version */
						__( 'The OpenRouter Provider plugin requires WordPress version %1$s or higher. You are running WordPress version %2$s.', 'connector-for-openrouter' ),
						CONNECTOR_FOR_OPENROUTER_MIN_WP_VERSION,
						$wp_version
					)
				);
			}
		);

		return false;
	}

	return true;
}

/**
 * Checks if the PHP AI Client SDK is available.
 *
 * @since 1.0.0
 *
 * @return bool True if the SDK is loaded, false otherwise.
 */
function check_ai_client(): bool {
	if ( ! class_exists( \WordPress\AiClient\AiClient::class ) ) {
		add_action(
			'admin_notices',
			static function () {
				requirement_notice(
					__( 'The OpenRouter Provider plugin requires the WordPress AI Client (php-ai-client) to be installed.', 'connector-for-openrouter' )
				);
			}
		);

		return false;
	}

	return true;
}

/**
 * Loads the OpenRouter provider plugin.
 *
 * @since 1.0.0
 */
function load(): void {
	if ( ! check_php_version() || ! check_wp_version() ) {
		return;
	}

	if ( ! check_ai_client() ) {
		return;
	}

	$plugin = new Plugin();
	$plugin->init();
}

load();
