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
pdfMake.fonts= {
Roboto: {
    normal: 'https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.66/fonts/Roboto/Roboto-Regular.ttf',
    bold: 'https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.66/fonts/Roboto/Roboto-Medium.ttf',
    italics: 'https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.66/fonts/Roboto/Roboto-Italic.ttf',
    bolditalics: 'https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.66/fonts/Roboto/Roboto-MediumItalic.ttf'
  }
}
function downloadPDFWithPDFMake() {
  var tableHeaderText = [...document.querySelectorAll('#regionMembers thead tr th')].map(thElement => ({ text: thElement.textContent, style: 'tableHeader' }));

  var tableRowCells = [...document.querySelectorAll('#regionMembers tbody tr td')].map(tdElement => ({ text: tdElement.textContent, style: 'tableData' }));
  var tableDataAsRows = tableRowCells.reduce((rows, cellData, index) => {
    if (index % 5 === 0) {
      rows.push([]);
    }

    rows[rows.length - 1].push(cellData);
    return rows;
  }, []);

  var docDefinition = {
    header: { text: 'Region Members', alignment: 'center' },
    footer: function(currentPage, pageCount) { return ({ text: `Page ${currentPage} of ${pageCount}`, alignment: 'center' }); },
    content: [
      {
        style: 'memberTable',
        table: {
          headerRows: 1,
          body: [
            tableHeaderText,
            ...tableDataAsRows,
          ]
        },
        layout: {
          fillColor: function(rowIndex) {
            if (rowIndex === 0) {
              return '#0f4871';
            }
            return (rowIndex % 2 === 0) ? '#f2f2f2' : null;
          }
        },
      },
    ],
    styles: {
      tableExample: {
        margin: [0, 20, 0, 80],
        fontSize: 10,
      },
      tableHeader: {
        margin: 5,
        color: 'white',
      },
      tableData: {
        margin: 5,
      },
    },
  };
  
  pdfMake.createPdf(docDefinition).download('Region members');
}
window.onload = function() {
   document.querySelector('#pdfmake').addEventListener('click', downloadPDFWithPDFMake);
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
$debug = false;
// No direct access
defined('_JEXEC') or die();

$isPost = false;
// Work out if we are dealing with get or post.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $isPost = true;
    if ($debug) {
        $r = var_export($post, true);
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
    // Check the POST data and if OK find all the subscribers in a requested region
    try {
        $regionNumbersToQuery = $helper->checkPostData();
        $r = var_export($regionNumbersToQuery, true);
        file_put_contents($debugFile, "QUERY\n" . $r, FILE_APPEND);
        $helper->setSubscriberData($regionNumbersToQuery, $standardFields, $customFields);
        $printValues = $helper->setDataForDisplay();
    } catch (Exception $e) {
        echo '<br> ERROR: ' . $e->getMessage();
    }
} else {
    // Set here to display a checklist
    // Allowed Regions are the regions that the logged in user is allowed to look at
    $allowedRegions = $helper->setAllowedRegions();
    // Set Region numbers queries the mempro database for all region values and then 
    //$regions = $helper->setRegionNumbers($allowedRegions);
}

require JModuleHelper::getLayoutPath('mod_membership_pro_region_queries');
