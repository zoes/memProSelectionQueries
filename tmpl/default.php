<?php
// No direct access
defined('_JEXEC') or die();
?>
<?php

if ($isPost == false) {
    //Check that the user is logged in
    
    $user = JFactory::getUser();        // Get the user object
    $app  = JFactory::getApplication(); // Get the application
    
    if ($user->id != 0)
    {
        // you are logged in
    }
    else
    {
        $app->redirect(JRoute::_('index.php?option=com_users&view=login'));
    }
    
    //If they are not allowed to get lists of region members don't show the form with regions
    if (count($allowedRegions > 0)) {
        echo '<form name="submitregion" method="post" enctype="multipart/form-data">';

        foreach ($allowedRegions as $region) {
            echo '<input type="checkbox" name="region' . $region . '" id="region' . $region . '" value="' . $region . '">';
            echo '<label for="region"' . $region . '>' . "&nbsp;Region&nbsp;" . $region . '</label><br>';
        }

        echo '<input type="submit" name="query_region" value="Submit">';
        echo '</form><br><br>';
    }
    
    //If they are not allowed to query members don't show the form to do so
    echo '<form name="namequery" method="post" onsubmit="return validateNameForm()" enctype="multipart/form-data">';
    echo '<label for="last_name">Enter the last name of the member yuu wish to query:</label><br>';
    echo '<input type="text" name="last_name" id="last_name" size="100">';
    echo '<label for="reason">Enter the reason for your query:</label><br>';
    echo '<input type="text" name="reason" id="reason" size="100">';

    echo '<input type="submit" name="query_name" value="Submit">';
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
}