<?php
/**
 * PSR-4 autoloader for the AI Provider for OpenRouter package.
 *
 * @since 1.0.0
 *
 * @package rtCamp\AIProviderForOpenRouterByrtCamp
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}


spl_autoload_register(
	static function ( string $class_name ): void {
		$prefix   = 'rtCamp\\AIProviderForOpenRouterByrtCamp\\';
		$base_dir = __DIR__ . '/';

		$len = strlen( $prefix );
		if ( strncmp( $class_name, $prefix, $len ) !== 0 ) {
			return;
		}

		$relative_class = substr( $class_name, $len );
		$file           = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

		if ( ! file_exists( $file ) ) {
			return;
		}

		// phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable -- PSR-4 autoloading resolves an internal package path before requiring it.
		require $file;
	}
);
