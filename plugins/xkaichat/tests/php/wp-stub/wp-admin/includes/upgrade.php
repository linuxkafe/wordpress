<?php
/**
 * Stub do ficheiro wp-admin/includes/upgrade.php (WP) para testes sem WP.
 */

if ( ! function_exists( 'dbDelta' ) ) {
	/**
	 * Stub de dbDelta — em memória, sem efeito.
	 *
	 * @param string $sql SQL.
	 * @return array
	 */
	function dbDelta( $sql ) {
		return array( $sql );
	}
}