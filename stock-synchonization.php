<?php
/*
Plugin Name: HYP Stock
Plugin URI:  https://github.com/hypericumimpex/hyp-stock/
Description: Stock Synchronization for WooCommerce lets you import stock information from an external CSV file.
Version:     1.4.3
Author:      Romeo C.
Author URI:  https://github.com/hypericumimpex/
License:     GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: stocksync
*/

defined('ABSPATH') or die('No absolute path allowed.');

// Require Cron file
require_once plugin_dir_path(__FILE__).'/cron.php';

// Check if WooCommerce is activated
if ( in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
	
	// Create a section on the Products tab
	add_filter('woocommerce_get_sections_products', 'stocksync_add_section');
	function stocksync_add_section( $sections ) {

		$sections['stocksync'] = __('Stock Synchonization', 'stocksync');
		return $sections;

	}

// Add Settings
	add_filter('woocommerce_get_settings_products', 'stocksync_all_settings', 10, 2);
	function stocksync_all_settings( $settings, $current_section ) {
		if ($current_section == 'stocksync') {
			
			if(isset($_GET['runnow']))
			{
				if(run_now()) { printf( '<div id="message" class="updated notice is-dismissible"><p>%s</p></div>', 'Succesfully executed the cronjob <strong>stocksync_cron_hook</strong>.'); } else { printf( '<div id="message" class="updated notice is-dismissible"><p>%s</p></div>', '<strong>Error: something went wrong with executing the cronjob.</strong>'); 
				}
			}
			
			$settings_stocksync = array();

			// Title
			$settings_stocksync[] = array('name' => __('Stock Synchronization', 'stocksync'), 'type' => 'title', 'desc' => __('The following options are used for stock synchronization. <br/> Click <a href="'.admin_url('admin.php?page=wc-settings&tab=products&section=stocksync&runnow=true').'">here</a> if you want the synchronization to run now.', 'stocksync'), 'id' => 'stocksync');

			// CSV URL
			$settings_stocksync[] = array(
				'name'     => __('Link to CSV file', 'stocksync'),
				'desc_tip' => __('This is the URL to your external CSV file', 'stocksync'),
				'id'       => 'stocksync_url',
				'type'     => 'text',
				'desc'     => __('The URL to your external CSV file', 'stocksync'),
			);
			
			// Google Drive FileID
			$settings_stocksync[] = array(
				'name'     => __('Google Drive File ID', 'stocksync'),
				'desc_tip' => __('The ID of your Google Drive File, this ID can be found in the link to share your File, the link should look like this: https://drive.google.com/file/d/FILE_ID/edit?usp=sharing', 'stocksync'),
				'id'       => 'stocksync_gdurl',
				'type'     => 'text',
				'desc'     => __('The ID of your Google Drive File', 'stocksync'),
			);
			
			// Check if the file is a Google Drive file
			$settings_stocksync[] = array(
				'name'     => __('Google Drive File', 'stocksync'),
				'desc_tip' => __('If you are using Google Drive to host your file, check this box', 'stocksync'),
				'id'       => 'stocksync_gdrive',
				'type'     => 'checkbox',
				'desc'     => __('Check if the file is on Google Drive', 'stocksync'),
			);

			//Check if the file needs credentials
			$settings_stocksync[] = array(
				'name'     => __('Credentials', 'stocksync'),
				'desc_tip' => __('Check this if your CSV requires username and password.', 'stocksync'),
				'id'       => 'stocksync_credentials',
				'type'     => 'checkbox',
				'label'    => 'My CSV file requires credentials',
				'desc'     => __('My CSV file requires credentials', 'stocksync'),
			);

			// Username 
			$settings_stocksync[] = array(
				'name'     => __('Username', 'stocksync'),
				'desc_tip' => __('This is the username for accessing your CSV file. Leave blank when no credentials are required.', 'stocksync'),
				'id'       => 'stocksync_username',
				'type'     => 'text',
				'desc'     => __('The username to your CSV file', 'stocksync'),
			);

			// Password
			$settings_stocksync[] = array(
				'name'     => __('Password', 'stocksync'),
				'desc_tip' => __('This is the password for accessing your CSV file. Leave blank when no credentials are required.', 'stocksync'),
				'id'       => 'stocksync_password',
				'type'     => 'text',
				'desc'     => __('The password to your CSV file', 'stocksync'),
			);
			
			// Update frequency
			$settings_stocksync[] = array(
				'name'     => __('CSV Delimiter', 'stocksync'),
				'desc_tip' => __('The Delimiter of your CSV-file', 'stocksync'),
				'id'       => 'stocksync_delimiter',
				'type'     => 'select',
				'desc'     => __('Delimiter of your CSV-file'),
				'options' => array( 
					',' => 'Comma (,)',
					';' => 'Semi-colon (;)',
					't' => 'Tab'
				)
			);

			// Field for SKU
			$settings_stocksync[] = array(
				'name'     => __('SKU field', 'stocksync'),
				'desc_tip' => __('This is the name of the field containing the SKU of the products in the CSV file', 'stocksync'),
				'id'       => 'stocksync_sku',
				'type'     => 'text',
				'desc'     => __('The name of the field containing the product SKU', 'stocksync'),
			);

			// Field for Quantity
			$settings_stocksync[] = array(
				'name'     => __('Quantity field', 'stocksync'),
				'desc_tip' => __('This is the name of the field containing the Quantity of the products in the CSV file', 'stocksync'),
				'id'       => 'stocksync_qty',
				'type'     => 'text',
				'desc'     => __('The name of the field containing the product Quantity', 'stocksync'),
			);
			
			// Check if price synchronization is needed
			$settings_stocksync[] = array(
				'name'     => __('Price synchronization', 'stocksync'),
				'desc_tip' => __('Check this if you want to synchronize prices.', 'stocksync'),
				'id'       => 'stocksync_pricesync',
				'type'     => 'checkbox',
				'label'    => 'Synchronize prices',
				'desc'     => __('Synchronize prices', 'stocksync'),
			);
			
			// Field for Price
			$settings_stocksync[] = array(
				'name'     => __('Price field', 'stocksync'),
				'desc_tip' => __('This is the name of the field containing the price of the products in the CSV file', 'stocksync'),
				'id'       => 'stocksync_price',
				'type'     => 'text',
				'desc'     => __('The name of the field containing the product Price', 'stocksync'),
			);
			
			// Check if saleprice synchronization is needed
			$settings_stocksync[] = array(
				'name'     => __('Sale price synchronization', 'stocksync'),
				'desc_tip' => __('Check this if you want to synchronize SALE prices.', 'stocksync'),
				'id'       => 'stocksync_salesync',
				'type'     => 'checkbox',
				'label'    => 'Synchronize Sale prices',
				'desc'     => __('Synchronize Sale prices', 'stocksync'),
			);
			
			// Field for Sale Price
			$settings_stocksync[] = array(
				'name'     => __('Sale price field', 'stocksync'),
				'desc_tip' => __('This is the name of the field containing the sale price of the products in the CSV file', 'stocksync'),
				'id'       => 'stocksync_saleprice',
				'type'     => 'text',
				'desc'     => __('The name of the field containing the product Sale Price', 'stocksync'),
			);

			// Update frequency
			$settings_stocksync[] = array(
				'name'     => __('Update frequency', 'stocksync'),
				'desc_tip' => __('The frequency to update the stock information from the CSV file', 'stocksync'),
				'id'       => 'stocksync_frqcy',
				'type'     => 'select',
				'desc'     => __('Frequency to update stock information'),
				'options' => array( 
					'daily' => 'Daily',
					'twicedaily' => 'Twice a day',
					'hourly' => 'Hourly'
				)
			);

			
			//Use for large files
			$settings_stocksync[] = array(
				'name'     => __('Optimalize for large CSV files', 'stocksync'),
				'desc_tip' => __('If you have a large CSV file (10.000+ lines), check this box for large file optimalization.', 'stocksync'),
				'id'       => 'stocksync_largefiles',
				'type'     => 'checkbox',
				'label'    => 'Large file optimalization',
				'desc'     => __('Large file optimalization', 'stocksync'),
			);
			
			//Use for variations
			$settings_stocksync[] = array(
				'name'     => __('Use for Variations', 'stocksync'),
				'desc_tip' => __('Check this if you want stock synchronization to use for Variations and your Variations have the same SKU as your main product.', 'stocksync'),
				'id'       => 'stocksync_variations',
				'type'     => 'checkbox',
				'label'    => 'Use for Variations',
				'desc'     => __('Use for Variations', 'stocksync'),
			);
			
			//Increment second quantity field
			$settings_stocksync[] = array(
				'name'     => __('Increment 2nd quantity field', 'stocksync'),
				'desc_tip' => __('Check this if you have two quantity field and you want to increment their values (only works with large file optimalization).', 'stocksync'),
				'id'       => 'stocksync_increment',
				'type'     => 'checkbox',
				'label'    => 'Increment second quantity field',
				'desc'     => __('Increment second quantity field', 'stocksync'),
			);
			
			// Field for Second quantity field
			$settings_stocksync[] = array(
				'name'     => __('Second quantity field', 'stocksync'),
				'desc_tip' => __('This is the name of the second quantity field in the CSV file', 'stocksync'),
				'id'       => 'stocksync_secondqty',
				'type'     => 'text',
				'desc'     => __('The name of the second quantity field', 'stocksync'),
			);
			
			$settings_stocksync[] = array( 'type' => 'stocksync_field_table', 'id' => 'stocksync_field_table' );
			

			// Show settings
			$settings_stocksync[] = array('type' => 'sectionend', 'id' => 'stocksync');
			return $settings_stocksync;				

		} 
		else 
		{
			return $settings;
		}
	}
}

add_action('woocommerce_admin_field_stocksync_field_table','stocksync_admin_field_stocksync_field_table');
function stocksync_admin_field_stocksync_field_table($value){
	
	?>
	<table class="stocksync wc_input_table sortable widefat">
			<thead>
				<tr>
					<th><?php _e( 'Variation ID', 'stocksync' ); ?></th>
					<th><?php _e( 'Product ID in CSV file', 'stocksync' ); ?></th>
				</tr>
			</thead>
			<tbody id="rates">
				<?php
					$stocksync_settings = get_option('stocksync_settings',array());
					foreach ( $stocksync_settings as $data ) {
						?>
						<tr>
							<td>
								<input type="text" value="<?php echo esc_attr( $data['var'] ) ?>"  name="stocksync_settings[var][]" />
							</td>
							<td>
								<input type="text" value="<?php echo esc_attr( $data['productid'] ) ?>"  name="stocksync_settings[productid][]" />
							</td>
						</tr>
						<?php
					}
				?>
			</tbody>
			<tfoot>
				<tr>
					<th colspan="10">
						<a href="#" class="button plus insert"><?php _e( 'Add row', 'stocksync' ); ?></a>
						<a href="#" class="button minus remove_item"><?php _e( 'Remove selected row(s)', 'stocksync' ); ?></a>
					</th>
				</tr>
			</tfoot>
		</table>
		<script type="text/javascript">
			jQuery( function() {
				
				// Show hide Google Drive ID
				if(jQuery('input[name=stocksync_gdrive]').prop('checked')) 
				{
					jQuery("input[name=stocksync_gdurl]").closest("tr").show();
					jQuery("input[name=stocksync_url]").closest("tr").hide();
				}
				else
				{
					jQuery("input[name=stocksync_gdurl]").closest("tr").hide();
					jQuery("input[name=stocksync_url]").closest("tr").show();
				}
				
				jQuery('input[name=stocksync_gdrive]').click(function() {
					jQuery("input[name=stocksync_gdurl]").closest("tr").toggle("slow");
					jQuery("input[name=stocksync_url]").closest("tr").toggle("slow");
				});
				
				// Show hide Credentials
				if(jQuery('input[name=stocksync_credentials]').prop('checked')) 
				{
					jQuery("input[name=stocksync_username]").closest("tr").show();
					jQuery("input[name=stocksync_password]").closest("tr").show();
				}
				else
				{
					jQuery("input[name=stocksync_username]").closest("tr").hide();
					jQuery("input[name=stocksync_password]").closest("tr").hide();
				}
				
				jQuery('input[name=stocksync_credentials]').click(function() {
					jQuery("input[name=stocksync_username]").closest("tr").toggle("slow");
					jQuery("input[name=stocksync_password]").closest("tr").toggle("slow");
				});
				
				// Show hide Price
				if(jQuery('input[name=stocksync_pricesync]').prop('checked')) 
				{
					jQuery("input[name=stocksync_price]").closest("tr").show();
					jQuery("input[name=stocksync_salesync]").closest("tr").show();
				}
				else
				{
					jQuery("input[name=stocksync_price]").closest("tr").hide();
					jQuery("input[name=stocksync_salesync]").closest("tr").hide();
					jQuery("input[name=stocksync_salesync]").prop("checked", false);
					jQuery("input[name=stocksync_saleprice]").closest("tr").hide();
				}
				
				if(jQuery('input[name=stocksync_pricesync]').prop('checked') && jQuery('input[name=stocksync_salesync]').prop('checked')) 
				{
					jQuery("input[name=stocksync_saleprice]").closest("tr").show();
				}
				else
				{
					jQuery("input[name=stocksync_saleprice]").closest("tr").hide();
				}
				
				jQuery('input[name=stocksync_pricesync]').click(function() {
					jQuery("input[name=stocksync_price]").closest("tr").toggle("slow");
					jQuery("input[name=stocksync_salesync]").closest("tr").toggle("slow");
				});
				
				jQuery('input[name=stocksync_salesync]').click(function() {
					jQuery("input[name=stocksync_saleprice]").closest("tr").toggle("slow");
				});
				
				// Show hide Variation Settings
				if(jQuery('input[name=stocksync_variations]').prop('checked')) 
				{
					jQuery("table.stocksync").show();
				}
				else
				{
					jQuery("table.stocksync").hide();
				}
				
				jQuery('input[name=stocksync_variations]').click(function() {
					jQuery("table.stocksync").toggle("slow");
				});
				
				// Show hide increment option
				if(jQuery('input[name=stocksync_largefiles]').prop('checked')) 
				{
					jQuery("input[name=stocksync_increment]").closest("tr").show();
				}
				else
				{
					jQuery("input[name=stocksync_increment]").closest("tr").hide();
				}
				
				jQuery('input[name=stocksync_largefiles]').click(function() {
					jQuery("input[name=stocksync_increment]").closest("tr").toggle("slow");
				});
				
				// Show hide Second qty field
				if(jQuery('input[name=stocksync_increment]').prop('checked')) 
				{
					jQuery("input[name=stocksync_secondqty]").closest("tr").show();
				}
				else
				{
					jQuery("input[name=stocksync_secondqty]").closest("tr").hide();
				}
				
				jQuery('input[name=stocksync_increment]').click(function() {
					jQuery("input[name=stocksync_secondqty]").closest("tr").toggle("slow");
				});
				
				jQuery('.stocksync .remove_item').click(function() {
					var $tbody = jQuery('.stocksync').find('tbody');
					if ( $tbody.find('tr.current').size() > 0 ) {
						$current = $tbody.find('tr.current');
						$current.remove();
						
					} else {
						alert('<?php echo esc_js( __( 'No row(s) selected', 'stocksync' ) ); ?>');
					}
					return false;
				});
				jQuery('.stocksync .insert').click(function() {
					var $tbody = jQuery('.stocksync').find('tbody');
					var size = $tbody.find('tr').size();
					var code = '<tr class="new">\
							<td><input type="text"  name="stocksync_settings[var][]" /></td>\
							<td><input type="text"  name="stocksync_settings[productid][]" /></td>\
						</tr>';
					if ( $tbody.find('tr.current').size() > 0 ) {
						$tbody.find('tr.current').after( code );
					} else {
						$tbody.append( code );
					}
					return false;
				});
				
			});
		</script>
	<?php
	
}
	
add_filter( 'woocommerce_admin_settings_sanitize_option_stocksync', 'filter_admin_sanitize_stocksync');
function filter_admin_sanitize_stocksync( $value ){
	$stocksync_settings_new = $_POST['stocksync_settings'];
	$stocksync_settings = array();
	
	// Check if Variations are added in the table
	if(!empty($stocksync_settings_new))
	{
		foreach($stocksync_settings_new as $fields => $stocksync_settings_extra ){
			foreach( $stocksync_settings_extra as $key => $settings ){
				$stocksync_settings[$key][$fields] = $settings;
			}
		}
		update_option('stocksync_settings',$stocksync_settings);
	}
	return $value;
}

function run_now() {
	wp_schedule_single_event( time(), 'stocksync_cron_hook');
	spawn_cron();
	return true;
}

add_action( 'admin_menu', 'menu' );
// Make up menu-item
function menu() {
	add_management_page('Stock Synchronization Log', 'Stock Synchronization Log', 'manage_options', 'stocklog', 'stocklog');
}

function stocklog() {
	?>
	<div class="wrap">
		<h1>Stock Synchronization Logs</h1>
		<form action="?page=stocklog" method="post">
		<select name="log">
		<?php
		$dir = plugin_dir_path(__FILE__).'/log';
		$dir = array_diff(scandir($dir), array('..', '.'));
	
		if(isset($_GET['delete']))
		{
			foreach($dir as $file)
			{
				unlink(plugin_dir_path(__FILE__).'log/'.$file);
			}
		}
	
		$dir = plugin_dir_path(__FILE__).'/log';
		$dir = array_diff(scandir($dir), array('..', '.'));
		
		echo '<option value="" disabled selected>Choose log file</option>';
		foreach($dir as $file)
		{
			echo '<option value="'.plugin_dir_path(__FILE__).'log/'.$file.'">'.$file.'</option>';
		}
		?>
		</select>
		<input type="submit" name="submit" value="Show" />
			<a href="?page=stocklog&delete=true">Delete all log files</a>
		</form>
		<?php
		if(isset($_POST['log'])) 
		{
			$content = file_get_contents(esc_attr($_POST['log']));
			
			echo '<h2>'.esc_attr($_POST['log']).'</h2>';
			echo '<pre>'.$content.'</pre>';
		}
		?>
	</div>
	<?php
}
?>
