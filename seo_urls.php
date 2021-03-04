<?php
// Always dev with errors on. Save your sanity, code and log files! Disable for prod.
ini_set('display_errors', 1);
error_reporting(-1);

// Make script portable, bring in OC config.
require_once('../config.php');

// Connect to DB or die.
try {
	$dbh = new PDO("mysql:host=".DB_HOSTNAME.";dbname=".DB_DATABASE, DB_USERNAME, DB_PASSWORD);
	$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$dbh->exec("set names utf8");
} catch (PDOException $e) {
	exit($e->getMessage());
}

// Debug to screen.
$output_screen = true;
// Update DB or not. Two separate ones since might want to screen and update, not either or.
$update_db = false;
// Work through products?
$process_products = true;
// Work through categories?
$process_categories = true;
// Generate rewrites at all.
$rewrites_create = true;
// Generate product rewrites.
$rewrites_gen_products = true;
// Generate category rewrites.
$rewrites_gen_categories = false;
// Generate rules for new URLs only.
$rewrites_new = false;
// Test table suffix, set to '' for live tables. Ideally working on a test site first but just in case.
$tbl_suffix = '';

// Empty array to store the rewrites for htaccess.
$rewrites_products = [];
$rewrites_categories = [];

$oc_version = 2;
// Only difference so far is table name for my uses. OC2 uses oc_url_alias and OC3 uses oc_seo_url.
// OC2 seo table also does not have store_id and language_id fields.
$tbl_seo_name = $oc_version === 2 ? 'oc_url_alias' : 'oc_seo_url';


function GenerateSeoUrl($str) {
	// Find and replace non valid chars.
	// amp; and quot; are from & replacement.
	$find = ['(', ')', '[', ']', ' ', '?', '#', ':', '%', '/', '&', '"', 'amp;', 'quot;', '`', '”', "'", '°', 'Ø', '™', ',', '|'];
	$repl = ['', '', '', '', '-', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', ''];

	// Generate our SEO URL, replace all the nasties first.
	$seoURL = str_replace($find, $repl, $str);

	// Don't want multiple -'s together so make them singular.
	$seoURL = preg_replace('/-{2,}/', '-', $seoURL);

	// And trim any surrounding -'s too
	$seoURL = trim($seoURL, '-');

	// Lower case for uniformity.
	$seoKeyword = strtolower($seoURL);

	return ['seoURL' => $seoURL, 'seoKeyword' => $seoKeyword];
}



// Prepare a check for existing keyword for new urls.
$checkKeyword = $dbh->prepare("SELECT COUNT(*) FROM {$tbl_seo_name}{$tbl_suffix} WHERE `keyword` = :keyword");
$checkKeyword->bindParam(':keyword', $seoKeyword, PDO::PARAM_STR);

// Prepare INSERT for rewrite.
if ($oc_version === 2) {
	$ins = $dbh->prepare("INSERT INTO {$tbl_seo_name}{$tbl_suffix} (`query`, `keyword`) VALUES (:query, :keyword)");
} else {
	$ins = $dbh->prepare("INSERT INTO {$tbl_seo_name}{$tbl_suffix} (store_id, language_id, `query`, `keyword`) VALUES (:store_id, :language_id, :query, :keyword)");
	$ins->bindParam(':store_id', $storeID, PDO::PARAM_INT);
	$ins->bindParam(':language_id', $languageID, PDO::PARAM_INT);
}
$ins->bindParam(':query', $seoQuery, PDO::PARAM_STR);
$ins->bindParam(':keyword', $seoKeyword, PDO::PARAM_STR);



if ($process_products) {
	// Grab all the products.
	$prods = $dbh->query("SELECT p.product_id, d.`name`, s.`keyword` FROM oc_product{$tbl_suffix} p LEFT JOIN oc_product_description{$tbl_suffix} d ON d.product_id = p.product_id LEFT JOIN {$tbl_seo_name}{$tbl_suffix} s ON s.`query` = CONCAT('product_id=', p.product_id)")->fetchAll(PDO::FETCH_ASSOC);
	
	if ($output_screen) echo '<h4>Product URLs</h4>';
	foreach ($prods as $prod) {
		// For the rewrites and the oc_seo_urls table.
		$seoQuery = "product_id={$prod['product_id']}";
	
		// Check if the product already has a rewrite in place.
		if ($prod['keyword'] == '') {
			// No rewrite, begin the process!
			$generateSEO = GenerateSeoUrl($prod['name']);
			$seoKeyword = $generateSEO['seoKeyword'];
	
			// First check the keyword is already used or not.
			$checkKeyword->execute();
			$checkProdName = $checkKeyword->fetchColumn();
	
			$urlFound = $checkProdName != 0 ? true : false;
			$urlIncrement = 1; // Start at 1 since we don't want product-1 we want product-2 on the first duplicate.
			while ($urlFound) {
				// Keyword was found so lets increment that counter and make the new URL to check for.
				++$urlIncrement;
				$seoKeyword = "{$generateSEO['seoURL']}-{$urlIncrement}";
	
				// Recheck.
				$checkKeyword->execute();
				$checkProdName = $checkKeyword->fetchColumn();
	
				// Loop again?
				$urlFound = $checkProdName != 0 ? true : false;
			}
	
			$added = false;
			if ($update_db) {
				// Insert the new SEO URL into oc_seo_urls table.
				$storeID = 0;
				$languageID = 1;
				$added = $ins->execute();
			}
		} else {
			$seoKeyword = $prod['keyword'];
		}
	
		// Write the old/new URL to array for the rewrites later.
		if ($rewrites_create && $rewrites_gen_products) {
			if ($rewrites_new) {
				if ($prod['keyword'] == '') $rewrites_products[] = ['old' => $seoQuery, 'new' => $seoKeyword];
			} else {
				$rewrites_products[] = ['old' => $seoQuery, 'new' => $seoKeyword];
			}
		}
	
		if ($output_screen) {
			// Output to screen for sanity checks.
			echo "Product ID: {$prod['product_id']}<br>Name: {$prod['name']}<br>Has Rewrite: " . ($prod['keyword'] != '' ? 'Yes' : 'No') . "<br>";
			if ($prod['keyword'] == '') {
				echo "URL Increments: $urlIncrement<br>";
				echo "Keyword: $seoKeyword<br>";
				echo "Added to DB: " . ($added ? 'Yes' : 'No') . ' (DB Insert On/Off: ' . ($update_db ? 'On' : 'Off') . ')<br>';
			} else {
				echo "Existing Rewrite: {$prod['keyword']}<br>";
			}
			echo '<br>';
		}
	}
}



if ($process_categories) {
	// Get all the categories.
	$cats = $dbh->query("SELECT c.category_id, cd.name, s.keyword FROM oc_category{$tbl_suffix} c INNER JOIN oc_category_description{$tbl_suffix} cd ON c.category_id = cd.category_id LEFT JOIN {$tbl_seo_name}{$tbl_suffix} s ON s.query = CONCAT('category_id=',c.category_id)")->fetchAll(PDO::FETCH_ASSOC);
		
	if ($output_screen) echo '<h4>Category URLs</h4>';
	foreach ($cats as $cat) {
		// For the rewrites and the oc_seo_urls table.
		$seoQuery = "category_id={$cat['category_id']}";
	
		// Check if the category already has a rewrite in place.
		if ($cat['keyword'] == '') {
			// No rewrite, begin the process!
			$generateSEO = GenerateSeoUrl($cat['name']);
			$seoKeyword = $generateSEO['seoKeyword'];
	
			// First check the keyword is already used or not.
			$checkKeyword->execute();
			$checkCatName = $checkKeyword->fetchColumn();
	
			$urlFound = $checkCatName != 0 ? true : false;
			$urlIncrement = 1; // Start at 1 since we don't want product-1 we want product-2 on the first duplicate.
			while ($urlFound) {
				// Keyword was found so lets increment that counter and make the new URL to check for.
				++$urlIncrement;
				$seoKeyword = "{$generateSEO['seoURL']}-{$urlIncrement}";
	
				// Recheck.
				$checkKeyword->execute();
				$checkCatName = $checkKeyword->fetchColumn();
	
				// Loop again?
				$urlFound = $checkCatName != 0 ? true : false;
			}
	
			$added = false;
			if ($update_db) {
				// Insert the new SEO URL into oc_seo_urls table.
				$storeID = 0;
				$languageID = 1;
				$added = $ins->execute();
			}
		} else {
			$seoKeyword = $cat['keyword'];
		}
	
		// Write the old/new URL to array for the rewrites later.
		if ($rewrites_create && $rewrites_gen_categories) {
			if ($rewrites_new) {
				if ($cat['keyword'] == '') $rewrites_categories[] = ['old' => $seoQuery, 'new' => $seoKeyword];
			} else {
				$rewrites_categories[] = ['old' => $seoQuery, 'new' => $seoKeyword];
			}
		}
	
		if ($output_screen) {
			// Output to screen for sanity checks.
			echo "Category ID: {$cat['category_id']}<br>Name: {$cat['name']}<br>Has Rewrite: " . ($cat['keyword'] != '' ? 'Yes' : 'No') . "<br>";
			if ($cat['keyword'] == '') {
				echo "URL Increments: $urlIncrement<br>";
				echo "Keyword: $seoKeyword<br>";
				echo "Added to DB: " . ($added ? 'Yes' : 'No') . ' (DB Insert On/Off: ' . ($update_db ? 'On' : 'Off') . ')<br>';
			} else {
				echo "Existing Rewrite: {$cat['keyword']}<br>";
			}
			echo '<br>';
		}
	}
}



// Last but not least, output the rewrites to screen.
if ($rewrites_create) {
	if ($rewrites_gen_products) {
		echo '<h4>Product Rewrites ('.count($rewrites_products).')</h4>';
		foreach ($rewrites_products as $rewrite) {
			//echo 'RewriteCond %{QUERY_STRING} '.$rewrite['old'].'<br>';
			//echo 'RewriteRule (.*) ${REQUEST_URI}/'.$rewrite['new'].'? [R=302,L]<br><br>';
			echo 'RewriteCond %{QUERY_STRING} ^route=product/product&'.$rewrite['old'].'$<br>';
			echo 'RewriteRule ^(.*)$ '.$rewrite['new'] .' [L,R=301,QSD]<br><br>';
		}
	}

	if ($rewrites_gen_categories) {
		echo '<h4>Category Rewrites ('.count($rewrites_categories).')</h4>';
		foreach ($rewrites_categories as $rewrite) {
			echo "RewriteRule ^{$rewrite['old']}$ {$rewrite['new']} [R=301,L]<br>";
		}
	}
}
