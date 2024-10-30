<?php
/*
	Plugin Name: BulkPress - Export
	Plugin URI: https://blog.meloniq.net/
	Description: Export taxonomies into formatted file compatible with BulkPress plugin.
	Author: MELONIQ.NET
	Author URI: https://blog.meloniq.net
	Version: 0.2
	License: GPLv2 or later

	Requires at least: 4.9
	Requires PHP: 5.6
*/


/**
 * Avoid calling file directly
 */
if ( ! function_exists( 'add_action' ) )
	die( 'Whoops! You shouldn\'t be doing that.' );


/**
 * Plugin version and textdomain constants
 */
define( 'BPE_VERSION', '0.2' );
define( 'BPE_TD', 'bulkpress-export' );


/**
 * Load Text-Domain
 */
load_plugin_textdomain( BPE_TD, false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );


/**
 * Initialize admin menu
 */
if ( is_admin() ) {
	add_action( 'admin_menu', 'bpe_add_menu_links' );
}


/**
 * Populate administration menu of the plugin
 */
function bpe_add_menu_links() {

	add_management_page( __( 'BulkPress - Export', BPE_TD ), __( 'BulkPress - Export', BPE_TD ), 'administrator', 'bulkpress-export', 'bpe_menu_settings' );
}


/**
 * Create settings page in admin
 */
function bpe_menu_settings() {

	include_once( dirname( __FILE__ ) . '/admin-page.php' );
}


/**
 * Creates array of terms paths or slugs.
 *
 * @param string $taxonomy
 * @param string $content
 *
 * @return string
 */
function bpe_get_terms_array( $taxonomy, $content ) {

	if ( ! taxonomy_exists( $taxonomy ) ) {
		return array();
	}

	$terms_args = array(
		'hide_empty' => false,
	);

	// get all terms for the taxonomy
	$terms = get_terms( $taxonomy, $terms_args );
	$paths = array();
	$slugs = array();

	if ( empty( $terms ) ) {
		return array();
	}

	foreach ( $terms as $key => $term ) {
		if ( $term->parent == 0 ) {
			$paths[] = $term->term_id . ';' . bpe_esc_name( $term->name );
		} else {
			$paths[] = $term->term_id . ';' . bpe_walk_terms( $terms, $term, '' );
		}
		$slugs[] = $term->term_id . ';' . $term->slug;
	}
	array_multisort( $paths, $slugs );

	return ( $content == 'names' ) ? $paths : $slugs;
}


/**
 * Creates term path, helper function for bpe_get_taxonomies_array().
 *
 * @param array $terms
 * @param object $current_term
 * @param string $path
 *
 * @return string
 */
function bpe_walk_terms( $terms, $current_term, $path ) {
	$path = ( empty( $path ) ) ? bpe_esc_name( $current_term->name ) : bpe_esc_name( $current_term->name ) . '/' . $path;

	if ( $current_term->parent == 0 ) {
		return $path;
	}

	foreach ( $terms as $term ) {
		if ( $current_term->parent == $term->term_id ) {
			$path = bpe_walk_terms( $terms, $term, $path );
			break;
		}
	}

	return $path;
}


/**
 * Escapes name for use in path.
 *
 * @param string $name
 *
 * @return string
 */
function bpe_esc_name( $name ) {
	$name = str_replace( '&amp;', '&', $name );
	$name = str_replace( '/', '\/', $name );
	return $name;
}


/**
 * Generate and send .txt file to user browser.
 *
 * @param array $content
 */
function bpe_export( $content = array() ) {
	$sitename = sanitize_key( get_bloginfo( 'name' ) );
	$filename = $sitename . '-bulkpress-export-' . date( 'Y-m-d' ) . '.txt';

	header( 'Content-Description: File Transfer' );
	header( 'Content-Disposition: attachment; filename=' . $filename );
	header( 'Content-Type: text/plain; charset=' . get_option( 'blog_charset' ), true );

	echo implode( "\r\n", $content );
}


/**
 * Listener for file download request.
 */
function bpe_listen_export() {
	if ( ! isset( $_POST['bpe-download'] ) ) {
		return;
	}

	if ( empty( $_POST['taxonomy'] ) || empty( $_POST['content'] ) ) {
		return;
	}

	// check given content type value
	$content_types = array( 'names', 'slugs' );
	$content       = wp_kses_data( $_POST['content'] );
	if ( ! in_array( $content, $content_types ) ) {
		return;
	}

	// check given taxonomy value
	$taxonomies = get_taxonomies( array(), 'names' );
	$taxonomy   = wp_kses_data( $_POST['taxonomy'] );
	if ( ! in_array( $taxonomy, $taxonomies ) ) {
		return;
	}

	// output file with terms names or slugs
	$terms = bpe_get_terms_array( $taxonomy, $content );
	bpe_export( $terms );
	die();
}
add_action( 'admin_init', 'bpe_listen_export' );

