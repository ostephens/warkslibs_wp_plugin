<?php
/*
 * Functions for the Warwickshire Libraries plugin
 * 
 * File information:
 * Contains functions to query the APIs for the terms provided
 * and other stuff I need to document here!
 */

/*
 * Notes on import adaptations:
 * 
 * Notes for Warwickshire libraries:
 * 
 * 
 * 
 * Import written for: WordPress 3 for generic WordPress installs
 *  
 */

/*
 * Needs adapting for Warwickshire Libraries ###
 * Was: Specialised search box for Tag Duel - asks for period (time) and place [Mia]
 * Might be nice to add optional free-text search if it's likely to return lots of results
 * TO DO: 
 */

function warkslibsPrintSearchForm() {
	?>
	<h3>Find a book so you can share it in a blog post...</h3><form method="post" action="">
	<input type="radio" name="loans" value="history" />Select from Loans History<br />
	<input type="radio" name="loans" value="current" />Select from Current Loans
	<input type="submit" name="search" value="Go!" />
	</form>
	<?php
}


/*
 * Inputs: $terms is the search terms given in the box, $mode is display or import
 */
function warkslibsGetCurrentLoans() {
	require_once('simple_html_dom.php');
	// get barcode and PIN - just guessing how this works here (from warkslibs.php) - need to find out how to do this properly!
	$warkslibs_options = get_option('warkslibs_settings_values');
	$warkslibs_user_barcode = $warkslibs_options['warkslibs_user_barcode'];
	$warkslibs_user_pin = $warkslibs_options['warkslibs_user_pin'];
	$warkslibs_url = "http://library.warwickshire.gov.uk/vs/Pa.csp?OpacLanguage=eng&Profile=Default";
	$login = getLogin($warkslibs_url,$warkslibs_user_barcode,$warkslibs_user_pin);
	$currentLoans = getCurrent($login);
	
	$html = str_get_html($currentLoans);

	foreach($html->find('td.listitemOdd',0)->parent()->parent()->find('tr') as $list) {
		$libItem['title']     = $list->find('td', 1)->plaintext;
		$libItem['barcode']   = $list->find('td', 2)->plaintext;
		$libItem['dateBorrowed'] = $list->find('td', 4)->plaintext;
		$libItem['dateDue']	= $list->find('td', 5)->plaintext;
		$libItem['timesRenewed']	= $list->find('td', 6)->plaintext;
		$libItem['charge']		= $list->find('td', 7)->plaintext;
		if ($libItem['title'] === "Title") {
			continue;
		}
		$libItems[] = $libItem;
	}

	foreach($libItems as $libItem) {
		$title_search = urlencode($libItem['title']);
		if (strlen($title_search) > 255) {
			$title_search = substr($title_search,0,255); 
		}
		$xml = searchAquaB($title_search);
		$doc = new DOMDocument();
		$doc->loadXML($xml);
		$xpath = new DOMXPath($doc);
		$records = $doc->getElementsByTagName("record");
		foreach($records as $record) {
			$title = aquaBTitle($record,$xpath);
			$titlekey = trim(preg_replace("/[^a-zA-Z ]/","",$title));
			$searchtitlekey = trim(preg_replace("/[^a-zA-Z ]/","",$libItem['title']));
			if (strcmp($titlekey,$searchtitlekey) != 0) {
				continue;
			}
			
			$libItem['title']     = $title;
			$libItem['isbn']     = aquaBISBN($record,$xpath);
			$libItem['auth']     = aquaBAuth($record,$xpath);
			$libItem['image']     = aquaBImage($record,$xpath);
			// ID retrieval not work at the moment
			// Why not?
			$libItem['id']     = aquaBID($record,$xpath);
			//Need to output dates etc. as well if want to preserve these?
			
			$libItems1[] = $libItem;
		}
	}
	// At this point we have an array of arrays listing all of the current loans
	// Non-unique titles have been expanded into multiple records
	// The user can (in theory) choose the ones they actually have (or just the ones they want to blog)
	// Then press 'blog these' (or similar) and create multiple blog posts (in draft)
	// So at this point want to output stuff to the screen with all information included in form
	// Then grab this all back when form submitted?
	// Alternative is to just submit the relevant URLs of the form
	// http://librarycatalogue.warwickshire.gov.uk/abwarwick/fullrecordinnerframe.ashx?hreciid=|library/vubissmart-marc|494187&output=xml
	// And do a further retrieve to get the information needed - but this increases network traffic for no gain?
	// See http://www.sitepoint.com/forums/php-34/$_post-ing-checkbox-values-corresponding-form-values-182722.html for how to do this
	
	// So - try outputting items
	echo '<form method="post" action=""><input name="blogem" value="yes" id="blogem" type="hidden">';
	echo '<ul>';
	$no_libItems = count($libItems1);
	for($i=0; $i<$no_libItems; $i++){
		echo '<li>';
		echo '<h3>'.$libItems1[$i]['title'].'</h3>';
		// display cover image
		echo "<img src=".$libItems1[$i]['image']." alt='Coverimage of ".$libItems1[$i]['title']."'>\n"; // need to check if it exists, otherwise?
		// print the item title
		// Do any of these need encoding? URL?
		echo '<input name="libitem['.$i.'][choose]" value="1" id="choose" type="checkbox">'; // is 'id' required?
		echo '<input name="libitem['.$i.'][title]" value="'.$libItems1[$i]['title'].'" id="title" type="hidden">'; // is 'id' required?
		echo '<input name="libitem['.$i.'][isbn]" value="'.$libItems1[$i]['isbn'].'" id="isbn" type="hidden">'; // is 'id' required?
		echo '<input name="libitem['.$i.'][auth]" value="'.$libItems1[$i]['auth'].'" id="auth" type="hidden">'; // is 'id' required?
		echo '<input name="libitem['.$i.'][image]" value="'.$libItems1[$i]['image'].'" id="image" type="hidden">'; // is 'id' required?
		echo '<input name="libitem['.$i.'][id]" value="'.$libItems1[$i]['id'].'" id="id" type="hidden">'; // is 'id' required?
		echo '</li>';
    }
	echo '<input type="submit" name="itemstoblog" value="Blog these..." /></form><br />';
    echo '</ul>';
	
/* This is where the Amazon Enhancement could go
// Some idea of the code might be:
$no_libItems = count($libItems1);
for($i=0; $i<$no_libItems; $i++){
	// Do Amazon lookup and enhance item (via function)
}
*/

}

function getLogin($url,$barcode,$pin) {
	require_once("simpletest/browser.php");
	// Should add in some kind of test?
	$browser = &new SimpleBrowser();
	$browser->get($url);	
	$browser->setField('CardId',$barcode);
	$browser->setField('Pin',$pin);
	$browser->clickSubmitByName('SubmitButton');
	return $browser;
}

function getCurrent($browser) {
	require_once("simpletest/browser.php");
	$browser->click('My loans and renewals');
	$browser->setFrameFocus(3);
	$currentloans_page = $browser->getContent();
	return $currentloans_page;
}

function searchAquaB($search) {
	$opacxml_baseurl = 'http://librarycatalogue.warwickshire.gov.uk/ABwarwick/result.ashx?branch=LEA&output=xml&q=';
	$xml = file_get_contents($opacxml_baseurl.$search);
	return $xml;
}

function aquaBTitle($record,$xpath) {
	$xpath_title = "./fields/title";
	$nodeList_title = $xpath->evaluate($xpath_title,$record);
	if ($nodeList_title->length > 0) {
		$content_title = $nodeList_title->item(0)->nodeValue;
		$title = trim($content_title);
	} else {
		$title = "UNKNOWN TITLE";
	}
	return $title;
}

function aquaBISBN($record,$xpath) {
	$xpath_isbn = "./fields/isbn/text()";
	$nodeList_isbn = $xpath->evaluate($xpath_isbn,$record);
	if ($nodeList_isbn->length > 0) {
		$content_isbn = $nodeList_isbn->item(0)->nodeValue;
		$isbn = $content_isbn;
	} else {
		$isbn = "UNKNOWN ISBN";
	}
	return $isbn;
}

function aquaBAuth($record,$xpath) {
	$xpath_auth = "./fields/author/text()";
	$nodeList_auth = $xpath->evaluate($xpath_auth,$record);
	if ($nodeList_auth->length > 0) {
		$content_auth = $nodeList_auth->item(0)->nodeValue;
		$auth = $content_auth;
	} else {
		$auth = "UNKNOWN AUTHORS";
	}
	return $auth;
}

function aquaBImage($record,$xpath) {
	$xpath_image = "./coverimageurl/text()";
	$nodeList_image = $xpath->evaluate($xpath_image,$record);
	if ($nodeList_image->length > 0) {
		$content_image = $nodeList_image->item(0)->nodeValue;
		$image = $content_image;
	} else {
		$image = "NO IMAGE";
	}
	return $image;
}

function aquaBID($record,$xpath) {
	$xpath_id = "@extID";
	$nodeList_id = $xpath->evaluate($xpath_id,$record);
	if ($nodeList_id->length > 0) {
		$content_id = $nodeList_id->item(0)->nodeValue;
		$id = $content_id;
	} else {
		$id = "NO ID";
	}
}

function searchAmazon($search) {
	// Use https://github.com/Exeu/Amazon-ECS-PHP-Library/ or http://www.codediesel.com/php/accessing-amazon-product-advertising-api-in-php/
	// Neither seems to support Power searching - which is what we need for ISBN search
	// The latter doesn't seem to support book searching at all; The former requires SOAP module installed, and doesn't support Power
	// So left with having to extend one, or just take the auth stuff and do the rest by hand
	// This is all basically for price, and sometimes image...
	/*
	defined('AWS_API_KEY') or define('AWS_API_KEY', 'API KEY');
	defined('AWS_API_SECRET_KEY') or define('AWS_API_SECRET_KEY', 'SECRET KEY');

	require '../lib/AmazonECS.class.php';

	try
	{
	    // get a new object with your API Key and secret key. Lang is optional.
	    // if you leave lang blank it will be US.
	    $amazonEcs = new AmazonECS(AWS_API_KEY, AWS_API_SECRET_KEY, 'UK');

	    // from now on you want to have pure arrays as response
	    $amazonEcs->returnType(AmazonECS::RETURN_TYPE_ARRAY);

	    $response = $amazonEcs->responseGroup('ItemAttributes')->category('Books')->search('isbn:PHP 5');;
	    //var_dump($response);

	}
	catch(Exception $e)
	{
	  echo $e->getMessage();
	}
	*/
}

function createWarksLibsPost($libItem) {
	$content = "";
	$image = $libItem['image'];
	$title = $libItem['title'];
	$price = $libItem['price'];
	$link = $libItem['id']; //Need to add other stuff in here to form valid link to OPAC
	$isbn = $libItem['isbn'];
	// 
	
	if (!empty($image)) {
	$content .= "<img src=\"".$image."\" alt=\"".$title."\" />";
	}
	if (!empty($title)) {
	$content .= "<strong>Title:</strong> ".$title."<br />";
	}
	if (!empty($description)) {
	$content .= "<strong>Description:</strong> ".$description."<br />";
	}
	if (!empty($isbn)) {
	$content .= "<strong>ISBN:</strong> ".$isbn."<br />";
	}
	if (!empty($id)) {
	$content .= "<a href=\"http://librarycatalogue.warwickshire.gov.uk/abwarwick/fullrecordinnerframe.ashx?hreciid=|library/vubissmart-marc|\"".$id."Link to item on Warkwickshire Libraries catalogue</a>";
	}
	$new_post = array(
	'post_title' => $title,
	        'post_content' => convert_chars($content)
	        //Default field values will do for the rest - so we don't need to worry about these - see http://codex.wordpress.org/Function_Reference/wp_insert_post
	);

	$post_id = wp_insert_post($new_post);

	if (is_object($post_id)) {
	//error - what to do?
	return false;
	}
	elseif ($post_id == 0) {
	//error - what to do?
	return false;
	}
	else {
		if (!empty($title)) {
			add_post_meta($post_id, 'title', $title);
		}
		if (!empty($isbn)) {
			add_post_meta($post_id, 'isbn', $isbn);
		}
		if (!empty($price)) {
			add_post_meta($post_id, 'price', $price);
		}
		//other custom fields here if required
	
	// What about loan/due date?
	}
	return $post_id;
	
}

?>