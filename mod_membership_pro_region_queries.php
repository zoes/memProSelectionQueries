<?php
/*********************************************************
 * This module is used to create lists of regional members.
 * It needs to be able to give people in the right access group a list of all 
 * of their members. 
 * It also needs to be able to be produced in a printable form.
 * 
 */
?>
<script type="text/javascript">
function validateNameForm() {
	  var x = document.forms["namequery"]["last_name"].value;
	  if (x = "") {
	    alert("Name must be filled out");
	    return false;
	  }
	  var x = document.forms["namequery"]["reason"].value;
	  if (x = null || x.length < 10) {
	    alert("You must provide a reason for wnating to access member data");
	    return false;
	  }
	} 

function download_table_as_csv(table_id, separator = ':') {
    // Select rows from table_id
    var rows = document.querySelectorAll('table#' + table_id + ' tr');
    // Construct csv
    var csv = [];
    for (var i = 0; i < rows.length; i++) {
        var row = [], cols = rows[i].querySelectorAll('td, th');
        for (var j = 0; j < cols.length; j++) {
            // Clean innertext to remove multiple spaces and jumpline (break csv)
            var data = cols[j].innerText.replace(/(\r\n|\n|\r)/gm, '').replace(/(\s\s)/gm, ' ')
            // Escape double-quote with double-double-quote (see https://stackoverflow.com/questions/17808511/properly-escape-a-double-quote-in-csv)
            data = data.replace(/"/g, '""');
            // Push escaped string
            row.push('"' + data + '"');
        }
        csv.push(row.join(separator));
    }
    var csv_string = csv.join('\n');
    // Download it
    var filename = 'export_' + table_id + '_' + new Date().toLocaleDateString() + '.csv';
    var link = document.createElement('a');
    link.style.display = 'none';
    link.setAttribute('target', '_blank');
    link.setAttribute('href', 'data:text/csv;charset=utf-8,' + encodeURIComponent(csv_string));
    link.setAttribute('download', filename);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
//This will search any column in the table for a string
jQuery(document).ready(function($){
var $rowstosearch = $('#regionMembers tr');
$('#search').keyup(function() {
    var val = $.trim($(this).val()).replace(/ +/g, ' ').toLowerCase();
    
    $rowstosearch.show().filter(function() {
        var text = $(this).text().replace(/\s+/g, ' ').toLowerCase();
        return !~text.indexOf(val);
    }).hide();
});
});
</script>
<?php
$debugFile = "tmp/memproq.txt";
$debug = true;
// No direct access
defined('_JEXEC') or die();

$isPost = false;
// Work out if we are dealing with get or post.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $isPost = true;
    if ($debug) {
        $r = var_export($_POST, true);
        file_put_contents($debugFile, "POST\n" . $r, FILE_APPEND);
    }
}

// There are the fields in #__osmembership_subscribers that we might want to query in addition to the custom fields
$standardFields = array(
    'id',
    'first_name',
    'last_name',
    'address',
    'address2',
    'city',
    'zip',
    'country',
    'phone',
    'email',
    'membership_id'
);

$customFields = array(
    'address3',
    'county',
    'title',
    'region'
);

// The fields needed for address (in addition to the address field)
$printHeadings = array(
    'MNo.<br>(Region)',
    'Name',
    'Address',
    'Email',
    'Phone'
);

// Include the helper file
require_once dirname(__FILE__) . '/helper.php';
$helper = new OSMembershipRegionQueriesHelper($debug, $debugFile);

if ($isPost) {
    // Check the POST data and if OK find all the subscribers in a requested region or the details of members with
    // a give last_name.
    try {
        if (array_key_exists('query_region', $_POST)) {
            $regionNumbersToQuery = $helper->checkPostData();
            $helper->setSubscriberData($regionNumbersToQuery, $standardFields, $customFields);
            $printValues = $helper->setDataForDisplay();
        } elseif (array_key_exists('query_name', $_POST)) {
            $helper->checkPostDataNameQuery();
            $helper->logReason();
            $helper->setSubscriberDataByName($_POST['last_name'],$standardFields, $customFields);
            $printValues = $helper->setDataForDisplay();          
        } else {
            //unregognised POST key
        }
    } catch (Exception $e) {
        echo '<br> ERROR: ' . $e->getMessage();
    }
} else {
    // Set here to display a checklist
    // Allowed Regions are the regions that the logged in user is allowed to look at
    $allowedRegions = $helper->setAllowedRegions();
    // Set Region numbers queries the mempro database for all region values and then
    // $regions = $helper->setRegionNumbers($allowedRegions);
}

require JModuleHelper::getLayoutPath('mod_membership_pro_region_queries');
