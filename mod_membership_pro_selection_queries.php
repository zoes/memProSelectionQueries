<?php
/*********************************************************
 * This module is used to create lists of regional members.
 * It needs to be able to give people in the right access group a list of all 
 * of their members. 
 * It also needs to be able to be produced in a printable form.
 * 
 */
$debugFile = "tmp/memproqsel.txt";
$debug = false;
// No direct access
defined('_JEXEC') or die();

// Add in Javascript helpers
use Joomla\CMS\Factory;
$document = Factory::getDocument();
$document->addScript(JURI::root() . "modules/mod_membership_pro_selection_queries/js/helperFunctions.js");

$document->addStyleSheet(JURI::root() . "modules/mod_membership_pro_selection_queries/css/selection.css");
$user = JFactory::getUser(); // Get the user object
$app = JFactory::getApplication(); // Get the application
$isLoggedIn = false;

if ($user->id != 0) {
    $isLoggedIn = true;
} else {
    $app->enqueueMessage('Please log in to query data');
}

// There are the fields in #__osmembership_subscribers that we might want to query in addition to the custom fields
$standardFields = array(
    'id',
    'first_name',
    'last_name',
    'phone',
    'email',
    'membership_id'
);

$customFields = array(
    'title',
    'region',
    'award'
);

// The fields needed for address (in addition to the address field)
$printHeadings = array(
    'MNo.<br>(Region)',
    'Name',
    'Email',
    'Phone',
    'Award'
);

// Include the helper file
require_once dirname(__FILE__) . '/helper.php';
$helper = new OSMembershipSElectionQueriesHelper($debug, $debugFile);
//$helper->setSubscriberIdsByAward();
$helper->setSubscriberData($standardFields, $customFields);
$printValues = $helper->setDataForDisplay();

require JModuleHelper::getLayoutPath('mod_membership_pro_selection_queries');
