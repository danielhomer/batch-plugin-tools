<?php

if ( ! class_exists( 'ItmCommon' ) ) :

class ItmCommon {

	/**
	 * Check that a table exists
	 * @param  string $table The table name to check
	 * @return bool|string   False if the table doesn't exist, the table name if it does
	 */
	public static function table_exists( $table ) {
		global $wpdb;
		return $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) );
	}

	/**
	 * Checks if the current installation is running on EY's SaaS system by checking
	 * for a table named domain.
	 * @return bool If the installation is running on EY's SaaS system
	 */
	public static function is_ey() {
		return (bool) self::table_exists( 'domain' );
	}

	/**
	 * Returns a table name with the appropriate prefix structure.
	 *
	 * E.g. test1_options (EY)
	 * 		wp_3_options  (Multisite)
	 * 
	 * @param  string $prefix The site prefix
	 * @param  string $table  The table name
	 * @return bool|string    The table name, false if an error occurred
	 */
	public static function get_table_name( $prefix, $table ) {
		global $wpdb;

		if ( is_multisite() || ! self::is_ey() ) {
			return $wpdb->base_prefix . $prefix . '_' . $table;
		} elseif ( self::is_ey() ) {
			return $prefix . $table;
		}

		return false;
	}

	/**
	 * Returns all of the site prefixes for the current installation
	 * 
	 * @return array The site prefixes
	 */
	public static function get_site_prefixes() {
		global $wpdb;
		$prefixes = array();

		if ( self::is_ey() ) {
			$column = 'prefix';
			$table = 'domain';
		} else {
			$column = 'blog_id';
			$table = $wpdb->base_prefix . 'blogs';
		}

		$prefixes = $wpdb->get_col( "SELECT $column FROM $table" );

		if ( self::is_ey() )
			array_unshift( $prefixes, $wpdb->prefix );

		if ( ! $prefixes ) return false;

		return $prefixes;
	}

	/**
	 * Get an option from a specific site using the prefix
	 * 
	 * @param  string     $prefix      The site prefix
	 * @param  string     $option_name The option name to retrieve
	 * @return string|int              The option value
	 */
	public static function get_option( $prefix, $option_name ) {
		global $wpdb;

		$options_table = self::get_table_name( $prefix, 'options' );

		if ( ! self::table_exists( $options_table ) ) return false;

		$option =  $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM $options_table WHERE option_name = %s", $option_name ) );

		if ( is_serialized( $option ) )
			return unserialize( $option );

		return $option;
	}

	/**
	 * Update an option on a specific site using the prefix 
	 *
	 * @param  string $prefix       The site prefix
	 * @param  string $option_name  The option to update
	 * @param  string $option_value The new option value
	 * @return bool|integer         Number of rows updated, false if an error occurred
	 */
	public static function update_option( $prefix, $option_name, $option_value ) {
		global $wpdb;

		$options_table = self::get_table_name( $prefix, 'options' );
		echo 'Option: ' . $option_table . '<br>';

		if ( ! self::table_exists( $options_table ) ) return false;

		$option =  $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM $options_table WHERE option_name = %s", $option_name ) );

		if ( is_array( $option_value ) )
			$option_value = serialize( $option_value );

		return $update = $wpdb->update( $options_table, array( 'option_value' => $option_value ), array( 'option_name' => $option_name ) );
	}

}

endif;

?>