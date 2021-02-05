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
// Update DB or not. Two separate ones since might want to screen and update not either or.
$update_db = false;
// Generate rules for new URLs only.
$rewrites_new = true;

// Prepare a check for existing url on product.
$checkHasRewrite = $dbh->prepare("SELECT `keyword` FROM oc_seo_url WHERE `query` = :query");
$checkHasRewrite->bindParam(':query', $seoQuery, PDO::PARAM_STR);

// Prepare a check for existing keyword for new urls.
$checkKeyword = $dbh->prepare("SELECT COUNT(*) FROM oc_seo_url WHERE `keyword` = :keyword");
$checkKeyword->bindParam(':keyword', $seoKeyword, PDO::PARAM_STR);

// Grab all the products.
$prods = $dbh->query("SELECT P.product_id, D.`name` FROM oc_product P LEFT JOIN oc_product_description D ON D.product_id = P.product_id")->fetchAll(PDO::FETCH_ASSOC);

// Prepare INSERT for rewrite.
$ins = $dbh->prepare("INSERT INTO oc_seo_url (store_id, language_id, `query`, `keyword`) VALUES (:store_id, :language_id, :query, :keyword)");
$ins->bindParam(':store_id', $storeID, PDO::PARAM_INT);
$ins->bindParam(':language_id', $languageID, PDO::PARAM_INT);
$ins->bindParam(':query', $seoQuery, PDO::PARAM_STR);
$ins->bindParam(':keyword', $seoKeyword, PDO::PARAM_STR);

// Find and replace non valid chars.
// amp; and quot; on the end are from & replacement.
$find = ['(', ')', '[', ']', ' ', '?', '#', ':', '%', '/', '&', '"', 'amp;', 'quot;', '`', '”', "'", '°', 'Ø'];
$repl = ['', '', '', '', '-', '', '', '', '', '', '', '', '', '', '', '', '', '', ''];

// Empty array to store the rewrites for htaccess.
$rewrites = [];

if ($output_screen) echo '<h4>URLs</h4>';
foreach ($prods as $prod) {
	// Generate our SEO URL, replace all the nasties first.
	$seoURL = str_replace($find, $repl, $prod['name']);
	// Don't want multiple -'s together so make them singular.
	$seoURL = preg_replace('/-{2,}/', '-', $seoURL);
	// And trim any surrounding -'s too
	$seoURL = trim($seoURL, '-');

	// Lower case for uniformity.
	$seoKeyword = strtolower($seoURL);

	// Check if the product already has a rewrite in place.
	$seoQuery = 'product_id='.$prod['product_id'];
	$checkHasRewrite->execute();
	$checkHasRewrite_ans = $checkHasRewrite->fetchColumn();
	$hasRewrite = $checkHasRewrite_ans != '' ? true : false;

	if (!$hasRewrite) {
		// No rewrite, begin the process!
		// First check the keyword is already used or not.
		$checkKeyword->execute();
		$checkProdName = $checkKeyword->fetchColumn();

		$urlFound = $checkProdName != 0 ? true : false;
		$urlIncrement = 1; // Start at 1 since we don't want product-1 we want product-2 on the first duplicate.
		while ($urlFound) {
			// Keyword was found so lets increment that counter and make the new URL to check for.
			++$urlIncrement;
			$seoKeyword = "{$seoURL}-{$urlIncrement}";

			// Recheck.
			$checkKeyword->execute();
			$checkProdName = $checkKeyword->fetchColumn();

			// Loop again?
			$urlFound = $checkProdName != 0 ? true : false;
		}
	}

	// Write the old/new URL to array for the rewrites later.
	if ($rewrites_new) {
		if (!$hasRewrite) $rewrites[] = ['old' => "product_id={$prod['product_id']}", 'new' => $seoKeyword];
	} else {
		$rewrites[] = ['old' => "product_id={$prod['product_id']}", 'new' => $seoKeyword];
	}

	if ($output_screen) {
		// Output to screen for sanity checks.
		echo "Product  ID: {$prod['product_id']}<br>Name: {$prod['name']}<br>Has Rewrite: ".($hasRewrite ? 'Yes' : 'No')."<br>";
		if (!$hasRewrite) {
			echo "URL Increments: $urlIncrement<br>";
			echo "Keyword: $seoKeyword<br>";
		} else {
			echo "Existing Rewrite: $checkHasRewrite_ans<br>";
		}
		echo '<br>';
	}

	if ($update_db) {
		// DB Updates.
		$storeID = 0;
		$languageID = 1;
		$ins->execute();
	}
}

// Last but not least, output the rewrites to screen.
echo '<h4>Rewrites ('.count($rewrites).')</h4>';
foreach ($rewrites as $rewrite) {
	echo 'RewriteCond %{QUERY_STRING} '.$rewrite['old'].'<br>';
	echo 'RewriteRule (.*) ${REQUEST_URI}/'.$rewrite['new'].'? [R=302,L]<br><br>';
}
