<?php

defined('ABSPATH') or die('No absolute path allowed.');

class StocksyncLog {
	var $startingtime = '';
	
	function setTime($time) {
		$this->startingtime = $time;
	}
	
	function write($message) {
		$file = plugin_dir_path(__FILE__).'/log/'.$this->startingtime.'.log';
		$mess = date("d-m-Y H:i").' | '.$message;
		$handler = file_put_contents($file, $mess.PHP_EOL, FILE_APPEND | LOCK_EX);
	}
	
	public function updated($type, $productid, $quantity) {
		
		switch($type) {
			case 'qty': $message = 'Product #'.$productid.' qty updated to '.$quantity; break;
			case 'price': $message = 'Product #'.$productid.' price updated to '.$quantity; break;
			case 'sale': $message = 'Product #'.$productid.' sale price updated to '.$quantity; break;
			case 'wpmlqty': $message = 'Translation #'.$productid.' qty updated to '.$quantity; break;
			case 'wpmlprice': $message = 'Translation #'.$productid.' price updated to '.$quantity; break;
			case 'wpmlsale': $message = 'Translation #'.$productid.' sale price updated to '.$quantity; break;
		}
		
		$this->write($message);
	}
	
}

function synchronize_wpml_translations($id, $qty, $price, $saleprice) {
		global $log;
		$trid = apply_filters( 'wpml_element_trid', NULL, $id, 'post_product' );
		$translation_ids = apply_filters( 'wpml_get_element_translations', NULL, $trid, 'post_product' );
		foreach( $translation_ids as $lang=>$translation){
			if($translation->element_id !== $id)
			{
				if(update_post_meta( $translation->element_id, '_stock', $qty)) $log->updated('wpmlqty',$translation->element_id,$qty);
				if(isset($price))
				{
					if(update_post_meta($translation->element_id, '_regular_price', (float)$price)) $log->updated('wpmlprice',$translation->element_id,$price);
					update_post_meta($translation->element_id, '_price', (float)$price);
				}
				
				if(isset($saleprice))
				{
					if(update_post_meta($translation->element_id, '_sale_price', (float)$saleprice)) $log->updated('wpmlsale',$translation->element_id,$qty);
				}
			}
		}
}

// Add Cron hook
add_action('stocksync_cron_hook', 'stocksync_exec', 15);
		function stocksync_exec() {
			global $log;
			$log = new StocksyncLog;
			$log->setTime(date("Y-m-d-h-i"));
			$log->write("Cron started");
			
			// Check if CSV is classified as 'Large' 
			$largefiles = get_option('stocksync_largefiles');
					
			// CSV file URL, first check if CSV is on GDrive or not
			$googledrive = get_option('stocksync_gdrive');
			if($googledrive == 'yes')
			{
				$host = 'https://drive.google.com/uc?export=download&id='.get_option('stocksync_gdurl');
			}
			else
			{
				$host = get_option('stocksync_url');
			}
			
			// Setting up CURL
			$ch = curl_init();
			
			// Check if credentials are needed
			$credentials = get_option('stocksync_credentials');
			if($credentials == 'yes')
			{
				// Check if the CSV is on FTP
				if(substr($host, 0, 6 ) === "ftp://") {
					   // Set username and password
						$login = '$_FTP['.get_option('stocksync_username').']:$_FTP]['.get_option('stocksync_password').']';
				}
				else {
					// Set username and password
					$login = get_option('stocksync_username').':'.get_option('stocksync_password');
				}
				
				// Set config for CURL
				$curl_config = [
					CURLOPT_URL => $host,
					CURLOPT_USERPWD => $login,
					CURLOPT_VERBOSE => 1,
					CURLOPT_RETURNTRANSFER => 1,
					CURLOPT_AUTOREFERER => false,
					CURLOPT_REFERER => get_site_url(),
					CURLOPT_HEADER => 0,
					CURLOPT_SSL_VERIFYHOST => 0, //do not verify that host matches one in certifica
					CURLOPT_SSL_VERIFYPEER => 0, //do not verify certificate's meta
					CURLOPT_FOLLOWLOCATION => true,
				];
			}
			// No credentials needed
			else
			{
				// Set config for CURL
				$curl_config = [
					CURLOPT_URL => $host,
					CURLOPT_VERBOSE => 1,
					CURLOPT_RETURNTRANSFER => 1,
					CURLOPT_AUTOREFERER => false,
					CURLOPT_REFERER => get_site_url(),
					CURLOPT_HEADER => 0,
					CURLOPT_SSL_VERIFYHOST => 0, //do not verify that host matches one in certifica
					CURLOPT_SSL_VERIFYPEER => 0, //do not verify certificate's meta
					CURLOPT_FOLLOWLOCATION => true,
				];
			}
				
			//End session in case the file is placed on own server
			session_write_close();
			
			// Apply settings for CURL
			curl_setopt_array($ch, $curl_config); 
			
			// Execute CURL
			$result = curl_exec($ch);
			
			// If something went wrong, exit script
			if(empty($result)) {
				$log->write('No connection with remote server, cronjob terminated');
				exit('Something went wrong, no connection with remote server');
			}
			
			if($largefiles == 'yes')
			{
				// Generate or open raw data file
				$destination = plugin_dir_path(__FILE__)."/data_raw.csv";
				$file = fopen($destination, "w+");
				
				// Put results of remote CSV into raw data file
				fputs($file, $result);
				
				// Close file
				fclose($file);			

				// Clear $result from memory when the CPU gets the chance
				unset($result);
				
				$log->write('Large file optimalization enabled');
			}
			
			// Close connection
			curl_close($ch);
			
			
			// Get settings for the CSV fields
			$sku = get_option('stocksync_sku');
			$quantity = get_option('stocksync_qty');
			$pricesync = get_option('stocksync_pricesync');
			$salesync = get_option('stocksync_salesync');
			if($pricesync == 'yes') { $pricesync = true; } else { $pricesync = false; }
			if($salesync == 'yes') { $salesync = true; } else { $salesync = false; }
			$price = get_option('stocksync_price');
			$saleprice = get_option('stocksync_saleprice');
			$delimiter = get_option('stocksync_delimiter');
			$increment = get_option('stocksync_increment');
			if($increment == 'yes') { $increment = true; } else { $increment = false; }
			$secondqty = get_option('stocksync_secondqty');
			// Bug fix for delimiter = Tab
			if($delimiter == 't') { $delimiter = "\t"; }
			$toprow = true;
			
			if($largefiles == 'yes')
			{
				// Check if csv has a top row
				if(substr($sku, 0, 1) === '#')
				{
					// SKU is at collumn number $sku
					$sku = str_replace('#','',$sku);
					// Prepare for array
					$sku = $sku - 1;
					$toprow = false;
				}
				
				if(substr($quantity, 0, 1) === '#')
				{
					// SKU is at collumn number $sku
					$quantity = str_replace('#','',$quantity);
					// Prepare for array
					$quantity = $quantity - 1;
					$toprow = false;
				}
				
				if($pricesync)
				{
					if(substr($price, 0, 1) === '#')
					{
						// SKU is at collumn number $sku
						$price = str_replace('#','',$price);
						// Prepare for array
						$price = $price - 1;
						$toprow = false;
					}
				}
				
				if($salesync)
				{
					if(substr($saleprice, 0, 1) === '#')
					{
						// SKU is at collumn number $sku
						$saleprice = str_replace('#','',$saleprice);
						// Prepare for array
						$saleprice = $saleprice - 1;
						$toprow = false;
					}
				}
				
				if($increment) 
				{
					if(substr($secondqty, 0, 1) === '#')
					{
						// SKU is at collumn number $sku
						$secondqty = str_replace('#','',$secondqty);
						// Prepare for array
						$secondqty = $secondqty - 1;
						$toprow = false;
					}
				}
				
				
				// The clean version of our data_raw.csv
				$output = plugin_dir_path(__FILE__)."/data.csv";

				// Delete columns that we don't need
				if (false !== ($i = fopen($destination, 'r'))) 
				{
					$o = fopen($output, 'w+');

					$c = 1; // The line we're at
					while(false !== ($data = fgetcsv($i, 0, $delimiter)))
					{
						// Get the IDs of the columns we need
							if(!$toprow)
							{
								$id_sku = $sku;
								$id_qty = $quantity;
								if($pricesync)
								{
									$id_price = $price;
								}
								if($salesync) 
								{
									$id_saleprice = $saleprice;
								}
								if($increment)
								{
									$id_increment = $secondqty;
								}
							}
							else
							{
								if($c == 1) 
								{
									$id_sku = array_search($sku, $data);
									$id_qty = array_search($quantity, $data);
									if($pricesync)
									{
										$id_price = array_search($price, $data);
									}
									if($salesync) 
									{
										$id_saleprice = array_search($saleprice, $data);
									}
									if($increment)
									{
										$id_increment = array_search($secondqty, $data);
									}
								}
							}
						
							// Build new row with only the columns that we need
							if($increment && $c != 1)
							{
								$finalqty = $data[$id_qty] + $data[$id_increment];
							}
							elseif($increment && $c == 1)
							{
								$finalqty = $data[$id_qty];
							}
							else
							{
								$finalqty = $data[$id_qty];
							}
						
							if($salesync)
							{
								$outputData = array($data[$id_sku], $finalqty, $data[$id_price], $data[$id_saleprice]);
							}
							elseif($pricesync)
							{
								$outputData = array($data[$id_sku], $finalqty, $data[$id_price]);
							}
							else
							{
								$outputData = array($data[$id_sku], $finalqty);
							}
							// Write new CSV-file
							fputcsv($o, $outputData);
						
						$c++;
					}

					// Close files
					fclose($i);
					fclose($o);

					// Clear raw file
					file_put_contents($destination, "");
				}

				// Open clean file
				$fileHandle = fopen($output, "r");
				if($fileHandle === FALSE)
				{
					$log->write('Failed opening clean CSV file. Try disabling Large file optimalization. Cronjob terminated.');
					die('Error opening '.$filePath);
				}

				$variations_checked = get_option('stocksync_variations');
				$variation_input = get_option('stocksync_settings');
				$variations = array();

				if($variations_checked == 'yes')
				{	
					foreach($variation_input as $key => $input)
					{
						$variations[$input['productid']] = $input['var'];
					}
					
					// Read file line by line
					while(!feof($fileHandle))
					{
						while (($product = fgetcsv($fileHandle)) !== FALSE)
						{
							// $product[0] = Product ID
							// $product[1] = Quantity
							// $product[2] = Price (optional)
							// $product[3] = Sale Price (optional)
							if(array_key_exists($product[0], $variations))
							{
								if(update_post_meta($variations[$product[0]], '_stock', $product[1] )) $log->updated('qty',$product[0],$product[1]);
								if($pricesync) 
								{
									if(update_post_meta($variations[$product[0]], '_regular_price', (float)$product[2])) $log->updated('qty',$product[0],$product[2]);
							    update_post_meta($variations[$product[0]], '_price', (float)$product[2]);
								}
								
								if($salesync) 
								{
									if(update_post_meta($variations[$product[0]], '_sale_price', (float)$product[3])) $log->updated('sale',$product[0],$product[3]);
								}
								
								if ( in_array('sitepress-multilingual-cms/sitepress.php', apply_filters('active_plugins', get_option('active_plugins'))))
								{
									if($salesync) 
									{
										synchronize_wpml_translations(wp_get_post_parent_id($variations[$product[0]]), $product[1], $product[2], $product[3]);
									}
									elseif($pricesync) 
									{
										synchronize_wpml_translations(wp_get_post_parent_id($variations[$product[0]]), $product[1], $product[2]);
									}
									else
									{
										synchronize_wpml_translations(wp_get_post_parent_id($variations[$product[0]]), $product[1]);
									}
								}
							}
						}
					}
				}
				else
				{
					while(!feof($fileHandle))
					{
						// $product[0] = Product SKU
						// $product[1] = Quantity
						// $product[2] = Price (optional)
						// $product[3] = Sale Price (optional)
						while (($product = fgetcsv($fileHandle)) !== FALSE)
						{
							if(wc_get_product_id_by_sku($product[0]) !== 0) 
							{
								if(wc_update_product_stock(wc_get_product_id_by_sku($product[0]), $product[1])) $log->updated('qty',wc_get_product_id_by_sku($product[0]),$product[1]);
									
								if($pricesync) 
								{
									if(update_post_meta(wc_get_product_id_by_sku($product[0]), '_regular_price', (float)$product[2])) $log->updated('price',wc_get_product_id_by_sku($product[0]),$product[2]);
							    	update_post_meta(wc_get_product_id_by_sku($product[0]), '_price', (float)$product[2]);
								}
								if($salesync)
								{
									if(update_post_meta(wc_get_product_id_by_sku($product[0]), '_sale_price', (float)$product[3])) $log->updated('sale',wc_get_product_id_by_sku($product[0]),$product[3]);
								}
								
								if ( in_array('sitepress-multilingual-cms/sitepress.php', apply_filters('active_plugins', get_option('active_plugins')))) 
								{
									if($salesync) 
									{
										synchronize_wpml_translations(wc_get_product_id_by_sku($product[0]), $product[1], $product[2], $product[3]);
									}
									elseif($pricesync) 
									{
										synchronize_wpml_translations(wc_get_product_id_by_sku($product[0]), $product[1], $product[2]);
									}
									else
									{
										synchronize_wpml_translations(wc_get_product_id_by_sku($product[0]), $product[1]);
									}
								}
							}
						}
					}
				}

				//Close the file
				fclose($fileHandle);
				file_put_contents($output, "");
			}
			else // File is not classified as 'Large'
			{
				
			// Default: CSV has a top row
			$toprow = true;
			
			// Check if fields start with #, then the CSV doesn't have a top row. 			
			if(substr($sku, 0, 1) === '#')
			{
				// SKU is at collumn number $sku
				$sku = str_replace('#','',$sku);
				// Prepare for Array
				$sku = $sku -1;
				// No top row 
				$toprow = false;
			}
			
			if(substr($quantity, 0, 1) === '#')
			{
				// Quantity is at collumn number $quantity
				$quantity = str_replace('#','',$quantity);
				// Prepare for Array
				$quantity = $quantity -1;
				// No top row 
				$toprow = false;
			}
				
			if($pricesync)
			{
				if(substr($price, 0, 1) === '#')
				{
					// Price is at collumn number $quantity
					$price = str_replace('#','',$price);
					// Prepare for Array
					$price = $price -1;
					// No top row 
					$toprow = false;
				}
			}
				
			if($salesync) 
			{
				if(substr($price, 0, 1) === '#')
				{
					// Sale price is at collumn number $quantity
					$saleprice = str_replace('#','',$saleprice);
					// Prepare for Array
					$saleprice = $saleprice -1;
					// No top row 
					$toprow = false;
				}
			}
			
			$delimiter = ',';
			// Parse CSV into array
			$rows = array_map(function($a){return str_getcsv($a, ',');}, explode("\n", $result));
			if($toprow)
			{
				$header = array_shift($rows);
				$products = array();
				foreach ($rows as $row) {
				  $products[] = array_combine($header, $row);
				}
			}
			else { $products = $rows; }
			
			
			$variations_checked = get_option('stocksync_variations');
			$variation_input = get_option('stocksync_settings');
			$variations = array();
			
			foreach($variation_input as $key => $input)
			{
				$variations[$input['productid']] = $input['var'];
			}
				
				
			if($variations_checked == 'yes')
			{	
				
				foreach($products as $product)
				{
					if(array_key_exists($product[$sku], $variations))
					{
						if(update_post_meta($variations[$product[$sku]], '_stock', $product[$quantity] )) $log->updated('qty',$variations[$product[$sku]],$product[$quantity]);
						
						if($pricesync) 
						{
							if(update_post_meta($variations[$product[$sku]], '_regular_price',(float)$product[$price])) $log->updated('price',$variations[$product[$sku]],$product[$price]);
						    update_post_meta($variations[$product[$sku]], '_price', (float)$product[$price]);
						}
						
						if($salesync)
						{
						    if(update_post_meta($variations[$product[$sku]], '_sale_price', (float)$product[$saleprice])) $log->updated('sale',$variations[$product[$sku]],$product[$saleprice]);
						}
						
						if ( in_array('sitepress-multilingual-cms/sitepress.php', apply_filters('active_plugins', get_option('active_plugins'))))
						{
							if($salesync) 
							{
								synchronize_wpml_translations(wp_get_post_parent_id($variations[$product[$sku]]), $product[$quantity], $product[$price], $product[$saleprice]);
							}
							elseif($pricesync) 
							{
								synchronize_wpml_translations(wp_get_post_parent_id($variations[$product[$sku]]), $product[$quantity], $product[$price]);
							}
							else
							{
								synchronize_wpml_translations(wp_get_post_parent_id($variations[$product[$sku]]), $product[$quantity]);
							}
						}
					}
				}
			}
			else
			{
				// Update stock quantity 
				foreach($products as $product) {

					if(wc_get_product_id_by_sku($product[$sku]) !== 0) {
					
						if(wc_update_product_stock(wc_get_product_id_by_sku($product[$sku]), $product[$quantity])) $log->updated('qty',wc_get_product_id_by_sku($product[$sku]),$product[$quantity]);
						
						if($pricesync) 
						{
							if(update_post_meta(wc_get_product_id_by_sku($product[$sku]), '_regular_price',(float)$product[$price])) $log->updated('price',wc_get_product_id_by_sku($product[$sku]),$product[$price]);
					    	update_post_meta(wc_get_product_id_by_sku($product[$sku]), '_price', (float)$product[$price]);
						}
						
						if($salesync)
						{
							if(update_post_meta(wc_get_product_id_by_sku($product[$sku]), '_sale_price', (float)$product[$saleprice])) $log->updated('sale',wc_get_product_id_by_sku($product[$sku]),$product[$saleprice]);
						}
						
						if ( in_array('sitepress-multilingual-cms/sitepress.php', apply_filters('active_plugins', get_option('active_plugins')))) 
						{
							if($salesync)
							{
								synchronize_wpml_translations(wc_get_product_id_by_sku($product[$sku]), $product[$quantity], $product[$price], $product[$saleprice]);
							}
							elseif($pricesync)
							{
								synchronize_wpml_translations(wc_get_product_id_by_sku($product[$sku]), $product[$quantity], $product[$price]);
							}
							else
							{
								synchronize_wpml_translations(wc_get_product_id_by_sku($product[$sku]), $product[$quantity]);
							}
						}
					}
				}
			}
		}
		
		$log->write("Cron ended");
	}

		// Add cronjob to WP 
		add_action('wp', 'stocksync_cron');
		function stocksync_cron() { 
		if (!wp_next_scheduled('stocksync_cron_hook') ) {
			// Get frequency setting
			$frequency = get_option('stocksync_frqcy');
			$cron = wp_schedule_event( time(), $frequency, 'stocksync_cron_hook' ); 
			}	
		}

		// Delete cronjob on deactivation
		function cronstarter_deactivate() {	
			$timestamp = wp_next_scheduled ('stocksync_cron_hook');
			wp_unschedule_event ($timestamp, 'stocksync_cron_hook');
		} 
		register_deactivation_hook (__FILE__, 'cronstarter_deactivate');
			
?>