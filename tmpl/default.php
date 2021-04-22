<?php
// No direct access
defined('_JEXEC') or die();
?>
<?php


echo "<button class=\"btn-primary btn-award\"  onclick=\"download_table_as_csv('MembersAwards')\">Export CSV</button>";
echo "<br>";

echo "<input type=\"text\" id=\"search\" placeholder=\"Type here to search the table\">";
echo "<br>";



echo '<div id="awards">';
echo '<table id="MembersAwards" class="table table-striped">';
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
        if (strlen(trim($value)) < 1) {
            $value = "--";
        }
        echo '<td scope="col">' . $value . '</td>';
    }
    echo '</tr>';
}
echo '</tbody>';
echo '</table>';
echo "</div>";
echo '<br><br>';
