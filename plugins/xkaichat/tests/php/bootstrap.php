<?php
/**
 * Bootstrap de testes PHP sem dependências WordPress.
 *
 * Fornece stubs mínimos das funções WP usadas pelas classes, uma
 * implementação em memória de $wpdb e uma camada HTTP configurável.
 *
 * @package XKaiChat
 */

error_reporting( E_ALL & ~E_DEPRECATED & ~E_NOTICE );

// Root do plugin (de tests/php/ para o projecto).
define( 'XKC_TEST_ROOT', realpath( dirname( __FILE__ ) . '/../..' ) . '/' );

// ABSPATH aponta para o stub de um ambiente WP mínimo (permite require do upgrade.php).
define( 'ABSPATH', realpath( dirname( __FILE__ ) ) . '/wp-stub/' );
define( 'DAY_IN_SECONDS', 86400 );
define( 'ARRAY_A', 'ARRAY_A' );
define( 'OBJECT', 'OBJECT' );
define( 'OBJECT_K', 'OBJECT_K' );

/**
 * WP_Error.
 */
class WP_Error {
	public $errors = array();
	public $error_data = array();

	public function __construct( $code = '', $message = '', $data = '' ) {
		if ( '' !== $code ) {
			$this->add( $code, $message, $data );
		}
	}

	public function add( $code, $message, $data = '' ) {
		$this->errors[ $code ][] = $message;
		if ( ! empty( $data ) ) {
			$this->error_data[ $code ] = $data;
		}
	}

	public function get_error_code() {
		if ( empty( $this->errors ) ) {
			return '';
		}
		return (string) key( $this->errors );
	}

	public function get_error_message( $code = '' ) {
		$code = '' === $code ? $this->get_error_code() : $code;
		if ( ! isset( $this->errors[ $code ] ) ) {
			return '';
		}
		return (string) $this->errors[ $code ][0];
	}

	public function get_error_data( $code = '' ) {
		$code = '' === $code ? $this->get_error_code() : $code;
		return isset( $this->error_data[ $code ] ) ? $this->error_data[ $code ] : null;
	}

	public function is_wp_error() {
		return true;
	}
}

function is_wp_error( $thing ) {
	return $thing instanceof WP_Error;
}

/**
 * Faux $wpdb — armazenamento em memória com SQL mínimo para os padrões utilizados.
 */
class FauxWpdb {
	public $prefix = 'wp_';
	public $insert_id = 0;
	public $tables = array();
	public $last_args = array();

	public function __construct() {
		$this->tables[ $this->prefix . 'xkaichat_codes' ]    = array();
		$this->tables[ $this->prefix . 'xkaichat_messages' ] = array();
	}

	public function get_charset_collate() {
		return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci';
	}

	public function prepare( $query, ...$args ) {
		$query = str_replace( '%i', '%s', $query );
		foreach ( $args as $arg ) {
			$escaped = str_replace( "'", "''", is_string( $arg ) ? $arg : (string) $arg );
			$query   = preg_replace( '/%[ds]/', "'" . $escaped . "'", $query, 1 );
		}
		return $query;
	}

	public function insert( $table, $data ) {
		$defaults = array(
			$this->prefix . 'xkaichat_codes'    => array(
				'token'       => '',
				'created_at'  => '2026-01-01 00:00:00',
				'phone'       => '',
				'ip'          => '',
				'attempts'    => 0,
				'expires_at'  => '2030-01-01 00:00:00',
			),
			$this->prefix . 'xkaichat_messages' => array(
				'session_key' => '',
				'email'       => '',
				'cache_hit'   => 0,
				'created_at'  => '2026-01-01 00:00:00',
			),
		);
		$data    = array_merge( isset( $defaults[ $table ] ) ? $defaults[ $table ] : array(), $data );
		$data['id']        = count( $this->tables[ $table ] ) + 1;
		$this->tables[ $table ][] = $data;
		$this->insert_id = (int) $data['id'];
		return true;
	}

	public function update( $table, $data, $where ) {
		$specs = array();
		foreach ( $where as $k => $v ) {
			$specs[ $k ] = is_array( $v ) ? $v : array( '=', (string) $v );
		}
		foreach ( $this->tables[ $table ] as $i => $row ) {
			if ( $this->matches( $row, $specs ) ) {
				$this->tables[ $table ][ $i ] = array_merge( $row, $data );
				return 1;
			}
		}
		return 0;
	}

	public function query( $sql ) {
		if ( preg_match( '/^\s*DELETE FROM (\S+)/', $sql, $m ) ) {
			$table = $m[1];
			$this->tables[ $table ] = array_filter(
				$this->tables[ $table ],
				function ( $row ) use ( $sql ) {
					return ! $this->matches( $row, $this->where_of( $sql ) );
				}
			);
			$this->tables[ $table ] = array_values( $this->tables[ $table ] );
		}
		return 1;
	}

	public function get_row( $sql, $output = ARRAY_A ) {
		$rows = $this->select( $sql );
		$row  = empty( $rows ) ? null : end( $rows );
		if ( null === $row ) {
			return null;
		}
		return $output === ARRAY_A ? $row : (object) $row;
	}

	public function get_results( $sql, $output = ARRAY_A ) {
		$rows = $this->select( $sql );
		if ( $output !== ARRAY_A ) {
			$rows = array_map( function ( $row ) {
				return (object) $row;
			}, $rows );
		}
		return $rows;
	}

	private function select( $sql ) {
		preg_match( '/FROM (\S+)/', $sql, $m );
		$table = $m[1];
		$where = $this->where_of( $sql );
		$rows  = $this->tables[ $table ];
		$out   = array();
		foreach ( $rows as $row ) {
			if ( $this->matches( $row, $where ) ) {
				$out[] = $row;
			}
		}
		usort( $out, function ( $a, $b ) {
			return (int) $a['id'] - (int) $b['id'];
		} );
		return $out;
	}

	private function where_of( $sql ) {
		$where = array();
		if ( preg_match( '/WHERE (.+?)( ORDER BY|\s*$)/', $sql, $m ) ) {
			foreach ( explode( ' AND ', trim( $m[1] ) ) as $cond ) {
				if ( preg_match( '/^(\w+)\s*(=|<=|>=|<|>)\s*[\'"]([^\'"]*)[\'"]?$/', trim( $cond ), $c ) ) {
					$where[ $c[1] ] = array( $c[2], $c[3] );
				}
			}
		}
		return $where;
	}

	private function matches( $row, $where ) {
		foreach ( $where as $k => $spec ) {
			if ( ! isset( $row[ $k ] ) ) {
				return false;
			}
			$op  = $spec[0];
			$val = $spec[1];
			if ( '=' === $op ) {
				if ( (string) $row[ $k ] !== $val ) {
					return false;
				}
				continue;
			}
			$cmp = strcmp( (string) $row[ $k ], (string) $val );
			if ( '<' === $op && ! ( $cmp < 0 ) ) { return false; }
			if ( '>' === $op && ! ( $cmp > 0 ) ) { return false; }
			if ( '<=' === $op && ! ( $cmp <= 0 ) ) { return false; }
			if ( '>=' === $op && ! ( $cmp >= 0 ) ) { return false; }
		}
		return true;
	}
}

$GLOBALS['wpdb'] = new FauxWpdb();

/* ---------- funções de utilidade WP ---------- */

$GLOBALS['xkc_options']    = array();
$GLOBALS['xkc_transients'] = array();
$GLOBALS['xkc_mailed']     = array();
$GLOBALS['xkc_mail_fail']  = false;
$GLOBALS['xkc_http']       = array(); // url => ['code'=>int,'body'=>string]
$GLOBALS['xkc_http_args']  = array();

function get_option( $option, $default = false ) {
	return array_key_exists( $option, $GLOBALS['xkc_options'] ) ? $GLOBALS['xkc_options'][ $option ] : $default;
}

function add_option( $option, $value ) {
	if ( ! array_key_exists( $option, $GLOBALS['xkc_options'] ) ) {
		$GLOBALS['xkc_options'][ $option ] = $value;
		return true;
	}
	return false;
}

function update_option( $option, $value ) {
	$GLOBALS['xkc_options'][ $option ] = $value;
	return true;
}

function delete_option( $option ) {
	unset( $GLOBALS['xkc_options'][ $option ] );
}

function get_transient( $key ) {
	return array_key_exists( $key, $GLOBALS['xkc_transients'] ) ? $GLOBALS['xkc_transients'][ $key ] : false;
}

function set_transient( $key, $value, $expiration = 0 ) {
	$GLOBALS['xkc_transients'][ $key ] = $value;
	return true;
}

function delete_transient( $key ) {
	unset( $GLOBALS['xkc_transients'][ $key ] );
	return true;
}

function wp_parse_args( $args, $defaults = array() ) {
	$args = (array) $args;
	return array_merge( $defaults, $args );
}

function sanitize_email( $email ) {
	$email = strtolower( trim( (string) $email ) );
	return preg_replace( '/[^a-z0-9_@.\-+]/', '', $email );
}

function is_email( $email ) {
	return (bool) preg_match( '/^[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,}$/i', (string) $email );
}

function sanitize_key( $key ) {
	$key = strtolower( (string) $key );
	return preg_replace( '/[^a-z0-9_\-]/', '', $key );
}

function sanitize_text_field( $str ) {
	return strip_tags( trim( (string) $str ) );
}

function sanitize_textarea_field( $str ) {
	return strip_tags( (string) $str );
}

function sanitize_hex_color( $color ) {
	$color = sanitize_text_field( (string) $color );
	if ( preg_match( '/^#([A-Fa-f0-9]{3}){1,2}$/', $color ) ) {
		return $color;
	}
	return false;
}

function get_theme_mod( $name, $default = false ) {
	return isset( $GLOBALS['xkc_theme_mods'][ $name ] ) ? $GLOBALS['xkc_theme_mods'][ $name ] : $default;
}

function wp_get_attachment_image_url( $attachment_id, $size = 'thumbnail' ) {
	if ( isset( $GLOBALS['xkc_attachment_urls'][ $attachment_id ] ) ) {
		return $GLOBALS['xkc_attachment_urls'][ $attachment_id ];
	}
	return false;
}

function wp_kses_post( $content ) {
	return (string) $content;
}

function wp_rand( $min, $max ) {
	return mt_rand( (int) $min, (int) $max );
}

function wp_salt( $scheme = 'auth' ) {
	return 'xkc-stub-salt-' . $scheme;
}

function wp_generate_password( $length = 12, $special_chars = true ) {
	$chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
	$out   = '';
	for ( $i = 0; $i < $length; $i++ ) {
		$out .= $chars[ wp_rand( 0, strlen( $chars ) - 1 ) ];
	}
	return $out;
}

function wp_mail( $to, $subject, $body ) {
	$GLOBALS['xkc_mailed'][] = array(
		'to'    => $to,
		'subject' => $subject,
		'body'  => $body,
	);
	return ! $GLOBALS['xkc_mail_fail'];
}

function current_time( $type ) {
	return 'mysql' === $type ? gmdate( 'Y-m-d H:i:s' ) : time();
}

function untrailingslashit( $path ) {
	return rtrim( (string) $path, '/\\' );
}

function esc_url_raw( $url ) {
	return (string) $url;
}

function wp_json_encode( $data, $options = 0, $depth = 512 ) {
	return json_encode( $data, $options, $depth );
}

function wp_remote_post( $url, $args ) {
	$GLOBALS['xkc_http_args'][] = array( 'url' => $url, 'args' => $args );
	return xkc_http_transport( $url, $args );
}

function wp_remote_get( $url, $args ) {
	$GLOBALS['xkc_http_args'][] = array( 'url' => $url, 'args' => $args );
	return xkc_http_transport( $url, $args );
}

function xkc_http_transport( $url, $args ) {
	if ( isset( $GLOBALS['xkc_http'][ $url ] ) ) {
		return $GLOBALS['xkc_http'][ $url ];
	}
	return new WP_Error( 'http_request_failed', 'connection refused (stub)' );
}

function wp_remote_retrieve_response_code( $response ) {
	return is_wp_error( $response ) || ! isset( $response['code'] ) ? 0 : (int) $response['code'];
}

function wp_remote_retrieve_body( $response ) {
	return is_wp_error( $response ) || ! isset( $response['body'] ) ? '' : $response['body'];
}

function plugin_basename( $file ) {
	return basename( basename( dirname( $file ) ) ) . '/' . basename( $file );
}

function plugin_dir_path( $file ) {
	return dirname( $file ) . '/';
}

function plugin_dir_url( $file ) {
	return 'http://example.local/wp-content/plugins/xkaichat/';
}

function load_plugin_textdomain( $domain ) {
	return true;
}

function wp_next_scheduled( $hook ) {
	return false;
}

function wp_unschedule_event( $timestamp, $hook ) {
	return true;
}

function wp_schedule_event( $timestamp, $recurrence, $hook ) {
	return true;
}

function check_ajax_referer( $action, $query_arg ) {
	return 1;
}

function wp_using_ext_object_cache() {
	return false;
}

function esc_html( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}

function esc_attr( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}

function esc_html__( $text ) {
	return esc_html( $text );
}

function esc_attr__( $text ) {
	return esc_attr( $text );
}

function __( $text ) {
	return (string) $text;
}

$GLOBALS['xkc_bloginfo'] = array();

function get_bloginfo( $show = 'name', $filter = 'raw' ) {
	return isset( $GLOBALS['xkc_bloginfo'][ $show ] ) ? $GLOBALS['xkc_bloginfo'][ $show ] : '';
}

function wp_trim_words( $text, $num_words = 55, $more = null ) {
	$words = preg_split( '/\s+/', trim( (string) $text ) );
	if ( count( $words ) <= $num_words ) {
		return $text;
	}
	return implode( ' ', array_slice( $words, 0, $num_words ) ) . ( null === $more ? '…' : $more );
}

/* ---------- carrega classes do plugin ---------- */

$BASE = XKC_TEST_ROOT;
define( 'XKAICHAT_VERSION', '1.0.0' );
define( 'XKAICHAT_PLUGIN_DIR', $BASE );
require_once $BASE . 'includes/class-xkaichat.php';
require_once $BASE . 'includes/class-xkaichat-activator.php';
require_once $BASE . 'includes/class-xkaichat-deactivator.php';
require_once $BASE . 'includes/class-xkaichat-i18n.php';
require_once $BASE . 'includes/class-xkaichat-messages.php';
require_once $BASE . 'includes/class-xkaichat-verification.php';
require_once $BASE . 'includes/class-xkaichat-proxy.php';
require_once $BASE . 'includes/class-xkaichat-summary.php';
require_once $BASE . 'admin/class-xkaichat-admin.php';
require_once $BASE . 'public/class-xkaichat-public.php';