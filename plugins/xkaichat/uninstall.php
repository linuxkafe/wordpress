<?php
/**
 * Uninstall — remove tabelas e opções do plugin.
 *
 * @package XKaiChat
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

$tables = array(
	$wpdb->prefix . 'xkaichat_codes',
	$wpdb->prefix . 'xkaichat_messages',
);

foreach ( $tables as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

delete_option( 'xkaichat_settings' );

$timestamp = wp_next_scheduled( 'xkaichat_daily_cleanup' );
if ( $timestamp ) {
	wp_unschedule_event( $timestamp, 'xkaichat_daily_cleanup' );
}