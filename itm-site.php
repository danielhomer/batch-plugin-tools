<?php

if ( ! class_exists( 'ItmSite' ) ) :

class ItmSite {

	public $prefix;

	/**
	 * Ensure the passed prefix ends with an underscore and assign it to the $prefix property
	 * @param string The site prefix
	 */
	public function __construct( $prefix ) {
		if ( strpos( $prefix, '_' ) !== strlen( $prefix ) - 1 ) $prefix = $prefix . '_';
		$this->prefix = $prefix;
	}

	/**
	 * Get the active or inactive for the current site / installation.
	 *
	 * The results are returned as an array in the following format:
	 * [path_to_plugin] => [Name]
	 * 				   	   [PluginURI]
	 * 				   	   [Version]
	 * 				   	   [Description]
	 * 				   	   [Author]
	 * 				   	   [AuthorURI]
	 * 				   	   [TextDomain]
	 * 				   	   [DomainPath]
	 * 				   	   [Network]
	 * 
	 * @param  boolean $active  True to get active plugins only, false for inactive plugins
	 * @return array   $plugins The active / inactive plugins
	 */
	public function plugins( $active = true ) {
		if ( $active ) {
			$plugin_files = ItmCommon::get_option( $this->prefix, 'active_plugins' );
		} else {
			$plugin_files = get_plugins();
		}

		$plugins = array();

		if ( ! is_array( $plugin_files ) ) return false;

		foreach ( $plugin_files as $key => $plugin_file ) {
			if ( $active ) {
				$plugins[ $plugin_file ] = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin_file );
				set_transient( 'batch_plugins_active', $plugins, 60 );
			} else {
				$plugins[ $key ] = get_plugin_data( WP_PLUGIN_DIR . '/' . $key );
				$active_plugins = get_transient( 'batch_plugins_active' );
				$filtered_plugins = array();	
				foreach ( $plugins as $key => $plugin_data ) {	
					if ( ! array_key_exists( $key, $active_plugins ) )
						$filtered_plugins[ $key ] = $plugin_data;
				}
			}
		}

		if ( $active )
			return $plugins;

		return $filtered_plugins;
	}

	/**
	 * Activate a plugin on a site using the site prefix
	 * 
	 * @param  string $plugin The path to the plugin file relative to the plugins directory
	 * @return bool           If the plugin was enabled
	 */
	public function activate_plugin( $plugin ) {
		$active_plugins = $this->plugins();
		
		if ( array_key_exists( $plugin, $active_plugins ) )
			return false;

		$active_plugins_option = ItmCommon::get_option( $this->prefix, 'active_plugins' );

		array_push( $active_plugins_option , $plugin );

		$update = ItmCommon::update_option( $this->prefix, 'active_plugins', $active_plugins_option );

		if ( ! $update ) {
			return false;
		}

		return true;
	}

	/**
	 * Gets the site title
	 * @return string The site title
	 */
	public function title() {
		return ItmCommon::get_option( $this->prefix, 'blogname' );
	}

	/**
	 * Gets the site URL
	 * @return string The site URL
	 */
	public function url() {
		return ItmCommon::get_option( $this->prefix, 'siteurl' );
	}

}

endif;

?>