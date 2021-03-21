<?php
// No direct access
defined('_JEXEC') or die();
?>
<?php

if ($isPost == false) {
    echo '<form name="submit" method="post" enctype="multipart/form-data">';
    echo '<p>Select a region number:</p>';
    
    foreach ($allowedRegions as $region) {
        echo '<input type="checkbox" name="region'.$region. '" id="region' . $region . '" value="' . $region . '">';
        echo '<label for="region"'.$region.'>' . "&nbsp;Region&nbsp;" . $region. '</label><br>';
    }
    echo '<input type="submit" name="submit" value="Submit">';
    echo '</form><br><br>';
    
} else {
    
    echo '<button onclick="window.print()">Print this page</button>';

    echo '<form name="submit" method="get">';
    echo '<input type="submit" name="submit" value="Back to query list">';
    echo '</form><br><br>';

echo "<button class=\"btn-primary\" style=\"float:right\" onclick=\"download_table_as_csv('regionMembers')\">Export CSV</button>";
echo "<br><br>";
echo "<button class=\"btn-primary\" style=\"float:right\" id=\"pdfmake\">Export PDF</button>";
echo "<br><br>";
echo "<p>Type in the box below to search the table.</p>";
echo "<input type=\"text\" id=\"search\" placeholder=\"Type to search\">";
echo "<br><br>";

echo '<div id="regions">';
echo '<table id="regionMembers" class="table table-striped">';
echo '<thead>';
echo '<tr>';


foreach ($printHeadings as $name) {
    echo '<th scope="col">' . $name . '</th>';
}
echo '</tr>';
echo '</thead>';
echo '<tbody>';
foreach ($printValues as $id => $array) {
    echo '<tr>';
    foreach ($array as $fid => $value) {
        if(strlen(trim($value)) < 1) {
            $value="--";
        }
        echo '<td scope="col">' . $value . '</td>';
    }
    echo '</tr>';
}
echo '</tbody>';
echo '</table>';
echo "</div>";
echo '<br><br>';
}