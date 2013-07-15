<?php 
/*
	Plugin Name: Batch Plugin Tools
	Description: Batch plugin tools for multisite installs.
	Version: 0.4
	Author: Daniel Homer
	Author URI: http://danielhomer.me
 */

require_once( 'itm-common.php' );
require_once( 'itm-site.php' );

define( "BATCH_PLUGINS_VERSION", '0.3' );
define( "IS_EY", ItmCommon::is_ey() );
define( "BATCH_PLUGINS_DIR", plugins_url( '', 'batch-plugin-tools/batch-plugin-tools.php' ) );

add_action( 'admin_menu', array( 'BatchPlugins', 'add_submenu' ) );
add_action( 'wp_ajax_get_response', array( 'BatchPlugins', 'ajax_get_response' ) );

if ( isset( $_GET['action'] ) && $_GET['action'] == 'plugin-report' )
	add_action( 'admin_init', array( 'BatchPlugins', 'active_plugin_report' ) );

class BatchPlugins {

	/**
	 * Add the menu item underneath the plugins heading, users must have the activate_plugins
	 * capability to see the menu item.
	 *
	 * @since 0.1
	 */
	public static function add_submenu() {
		$page = add_plugins_page(
				'Batch Plugin Tools',
				'Batch Tools',
				'activate_plugins',
				'batch-plugin-tools',
				array( 'BatchPlugins', 'page_content' )
				);
		add_action( 'admin_print_styles-' . $page, array( 'BatchPlugins', 'page_styles' ) );
		add_action( 'admin_print_scripts-' . $page, array( 'BatchPlugins', 'page_scripts' ) );
	}

	/**
	 * Queue the plugin-specific stylesheet
	 *
	 * @since 0.1
	 */
	public static function page_styles() {
		wp_enqueue_style( 'batch-plugin-tools-css', BATCH_PLUGINS_DIR . "/css/style.css" );
	}

	/**
	 * Queue the plugin specific JavaScript, containing the Ajax functionaility.
	 *
	 * Also uses the wp_localize_script() function in order to make the directory path
	 * accessible from within the script.
	 * 
	 * @since 0.2
	 */
	public static function page_scripts() {
		wp_enqueue_script( 'batch-plugin-tools-script', BATCH_PLUGINS_DIR . "/scripts/ajax.js", array( "jquery" ) );
		wp_localize_script( 'batch-plugin-tools-script', 'ajax_object', array( 'batch_plugins_dir' => BATCH_PLUGINS_DIR ) );
	}

	/**
	 * Output the main plugin page HTML, including the dropdown prefix box and the list placeholder.
	 *
	 * @since 0.1
	 * @todo  Clean this up, maybe using the built-in meta-box functionality
	 */
	public static function page_content() { ?>
		<div class="wrap" id="container">
			<h2>Batch Plugin Tools</h2>
			<div id="message-placeholder"></div>
			<div id="explorer">
				<label for="prefix-dropdown">Site:</label>
				<select id="prefix-dropdown" name="prefix-dropdown"><?php self::prefix_dropdown() ?></select>
				<span id="site-url"></span>
				<span id="response-ajax"></span>
			</div>

			<table class="wp-list-table widefat plugins" id="plugins-ajax" cellspacing="0">
				<thead>
				<tr>
					<th scope="col" id="cb" class="manage-column column-cb check-column" style=""><label class="screen-reader-text" for="cb-select-all-1">Select All</label><input id="cb-select-all-1" type="checkbox"></th><th scope="col" id="name" class="manage-column column-name" style="">Plugin</th><th scope="col" id="description" class="manage-column column-description" style="">Description</th>	</tr>
				</thead>

				<tfoot>
				<tr>
					<th scope="col" class="manage-column column-cb check-column" style=""><label class="screen-reader-text" for="cb-select-all-2">Select All</label><input id="cb-select-all-2" type="checkbox"></th><th scope="col" class="manage-column column-name" style="">Plugin</th><th scope="col" class="manage-column column-description" style="">Description</th>	</tr>
				</tfoot>

				<tbody id="the-list">
					<?php echo self::loading_ajax_row(); ?>
				</tbody>
			</table>
			<?php self::plugin_rows( 'ey_' ); ?>
		</div>
	<?php }

	/**
	 * AJAX callback function which echoes all of the plugin rows depending on the site prefix passed
	 * in the POST variable
	 *
	 * @since  0.2
	 */
	public static function ajax_get_response() {
		$site_prefix = isset( $_POST[ 'site_prefix' ] ) ? $_POST[ 'site_prefix' ] : false;
		$site_action = isset( $_POST[ 'site_action' ] ) ? $_POST[ 'site_action' ] : false;

		if ( ! $site_prefix && ! $site_action ) {
			echo json_encode( array( 'message' => 'No params' ) );
			die();
		}

		if ( $site_prefix && ! $site_action ) {
			$site = new ItmSite( $site_prefix );
			$response = array();

			$response['siteurl'] = $site->url();
			$response['siteplugins'] = self::plugin_rows( $site_prefix );

			if ( empty( $response ) )
				echo json_encode( array( 'message' => 'No data' ) );

			echo json_encode( $response );

			die();
		}

		if ( $site_prefix && $site_action == 'activate_plugin' ) {
			$plugin_path = isset( $_POST[ 'plugin_path' ] ) ? $_POST[ 'plugin_path' ] : false;

			if ( ! $plugin_path ) {
				echo json_encode( array( 'message' => 'No plugin path' ) );
				die();
			}

			$site = new ItmSite( $site_prefix );
			$active = $site->activate_plugin( $plugin_path );
			
			if ( ! $active ) {
				echo json_encode( array( 'message' => 'Plugin activation error' ) );
			} else {
				echo json_encode( array( 'message' => 'Plugin activated' ) );
			}

			die();
		}

	}

	/**
	 * Takes a site prefix, grabs the active and inactive plugins for that site and outputs them into
	 * rows to be outputted to the table placeholder.
	 *
	 * Active plugins will be placed at the top of the list with the class "active". Inactive plugins
	 * will be listed below with the class "inactive".
	 * 
	 * @since  0.1
	 * @param  string $prefix The site prefix
	 * @return string         The active and inactive plugins formatted in HTML table rows
	 */
	private static function plugin_rows( $prefix ) {
		$site = new ItmSite( $prefix );
		$active_plugins = $site->plugins( true );
		$inactive_plugins = $site->plugins( false );
		$rows = '';

		if ( ! $active_plugins && ! $inactive_plugins ) return self::no_plugins_row();

		if ( $active_plugins ) {
			foreach ( $active_plugins as $path => $active_plugin ) {
				$checkbox_id =  "checkbox_" . md5( $active_plugin['Name'] );
				$rows .= '<tr id="'. esc_attr( $path ) .'" class="active">
							<th scope="row" class="check-column">
							<label class="screen-reader-text" for="'. $checkbox_id .'">' . sprintf( __( 'Select %s' ), $active_plugin['Name'] ) . '</label>
							<input type="checkbox" name="checked[]" value="'. esc_attr( $path ) .'" id="'. $checkbox_id .'" /></th>
							<td class="plugin-title"><strong>'. $active_plugin['Name'] .'</strong></td>
							<td class="column-decription desc"><div class="plugin-description"><p>'. $active_plugin['Description'] .'</p></div></td></tr>';
			}
		}

		if ( $inactive_plugins ) {
			foreach ( $inactive_plugins as $path => $inactive_plugins ) {
				$checkbox_id =  "checkbox_" . md5( $inactive_plugins['Name'] );
				$rows .= '<tr id="'. esc_attr( $path ) .'" class="inactive">
							<th scope="row" class="check-column">
							<label class="screen-reader-text" for="'. $checkbox_id .'">' . sprintf( __( 'Select %s' ), $inactive_plugins['Name'] ) . '</label>
							<input type="checkbox" name="checked[]" value="'. esc_attr( $path ) .'" id="'. $checkbox_id .'" /></th>
							<td class="plugin-title"><strong>'. $inactive_plugins['Name'] .'</strong></td>
							<td class="column-decription desc"><div class="plugin-description"><p>'. $inactive_plugins['Description'] .'</p></div>
							<a href="#" class="activatebutton" id="'. esc_attr( $path ) .'">Activate Plugin</a>
							</td></tr>';
			}
		}

		return $rows;
	}

	/**
	 * Get all of the site prefixes for the current WordPress installation and echo them out in
	 * <option></option> tags for use in a dropdown menu.
	 *
	 * @since 0.1
	 */
	private static function prefix_dropdown() {
		$prefixes = ItmCommon::get_site_prefixes();

		if ( ! $prefixes || ! is_array( $prefixes ) )
			echo '<option value="">No sites</option>';

		foreach ( $prefixes as $prefix )
			echo '<option value="' . $prefix . '">' . $prefix . '</option>';

	}

	/**
	 * Returns a row that spans the whole plugin list in order to illustrate that no plugins were
	 * found.
	 *
	 * @since  0.2
	 * @return string The error text formatted as an HTML table row
	 */
	private static function no_plugins_row() {
		return '<tr id="no-plugins"><td colspan="3">No Plugins Found...</td></tr>';
	}

	/**
	 * Returns a row with a spinning indicator that spans the whole plugin list which illustrates
	 * that the plugin list is loading.
	 *
	 * @since 0.2
	 * @return string The spinning indicator formatted as an HTML table row
	 */
	private static function loading_ajax_row() {
		return '<tr id="loading-ajax"><td colspan="3"><img src="' . BATCH_PLUGINS_DIR . '/images/ajax-loader.gif" alt="Loading..." title="Loading..." /></td></tr>';
	}

	/**
	 * Compiles a report of all of the plugins on the server along with the URLs of the sites on
	 * which they are active.
	 *
	 * To get around timout issues on large installations, the export is written to a file in
	 * the 'reports' directory so it can be retrieved manually later.
	 *
	 * @since 0.4
	 */
	public static function active_plugin_report() {
		$prefixes = ItmCommon::get_site_prefixes();
		$plugins = array();
		
		if ( strtoupper( substr( PHP_OS, 0, 3 ) ) === 'WIN' ) {
			$export_dir = dirname( __FILE__ ) . '\\reports\\';
		} else {
			$export_dir = dirname( __FILE__ ) . '/reports/';
		}

				if ( ! $prefixes || ! is_array( $prefixes ) )
			return;

		foreach( $prefixes as $prefix ) {
			set_time_limit( 120 );
			$site = new ItmSite( $prefix );
			$active_plugins = $site->plugins( true );
			$inactive_plugins = $site->plugins( false );
			
			if ( is_array( $active_plugins ) ) {
				foreach ( $active_plugins as $path => $active_plugin )
					$plugins[ $active_plugin['Name'] ][] = $site->url();
			}

			if ( is_array( $inactive_plugins ) ) {
			foreach ( $inactive_plugins as $path => $inactive_plugin ) 
				if ( ! array_key_exists( $inactive_plugin['Name'] , $plugins) )
					$plugins[ $inactive_plugin['Name'] ] = null;
			}
		}

		if ( empty( $plugins ) )
			return;

		$filename = $export_dir . 'plugin_report.csv';

		if ( file_exists( $filename ) ) unset( $filename );

		$file = fopen( $filename, 'w' );

		foreach ( $plugins as $key => $unescaped_value ) {
			$site_list = '';
			if ( is_array( $unescaped_value ) ) {
				foreach ( $unescaped_value as $value  )
					$site_list .= $value . ',';
			}
			fwrite( $file, $key . "," . $site_list . "\n" );
		}

		fclose( $file );

		header( "Content-type: text/csv", true, 200 );
		header( 'Content-Disposition: attachment; filename="plugin_report.csv"' );
		header( "Pragma: no-cache" );
    	header( "Expires: 0" );
    	readfile( $filename );
		
		exit;
	}

}

?>
