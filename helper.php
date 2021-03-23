<?php
/**
 * 
 * 
 */
defined('_JEXEC') or die();

use Joomla\Utilities\ArrayHelper;

class OSMembershipSelectionQueriesHelper
{

    public $debug;

    public $debugFile;

    public $ids = array();

    // array of subscriber IDs
    public $subscriberStandardFields = array();

    // Array of arrays of data for subscribers. Keyed by subscriber ID
    public $subscriberCustomFields = array();

    // Array of arrays of custom field data for subscribers, Keyed by subscriber ID
    public $subscriberFields = array();

    // All requested data for a subscriber keyed by subscriber ID
    public function __construct($debug, $debugFile)
    {
        $this->debug = $debug;
        $this->debugFile = $debugFile;
    }

    /**
     * Get a list of subscriber IDs in a give region
     * $region numbers -s an array of region numbers, eg (1, 2);
     *
     *
     * $sql = "select fv.subscriber_id from jos_osmembership_field_value fv ".
     * " inner join jos_osmembership_fields fs on fv.field_id=fs.id ".
     * "where fs.name='region' and fv.field_value='".$region."'";
     */
    public function setSubscriberIdsByAward()
    {
        $db = JFactory::getDbo();
        $query = $db->getQuery(true);

       
        //Find subscriberer IDs where there is a no-zero string in the award section
        $query->select('fv.subscriber_id')
            ->from($db->quoteName('#__osmembership_field_value', 'fv'))
            ->join('INNER', $db->quoteName('#__osmembership_fields', 'fs') . ' ON ' . $db->quoteName('fv.field_id') . ' = ' . $db->quoteName('fs.id'))
            ->where($db->quoteName('fs.name') . " = " . $db->quote('award'))
            ->where($db->quoteName('fv.field_value') . ' <> ""');

        $db->setQuery($query);
        $this->ids = $db->loadColumn();

        if ($this->debug) {
            $r = var_export($this->ids, true);
            file_put_contents($this->debugFile, $r, FILE_APPEND);
        }
    }

    /**
     * Get all the subscriber information given a set of subscriber IDs.
     *
     * @param
     *            array of subscriber IDs
     * @return Array of arrays - keyed by subscriber ID
     *        
     *         $sql="select id, first_name, last_name, address, address2, city, country, zip, email, phone, membership_id " .
     *         "from jos_osmembership_subscribers " .
     *         "where id IN (" . $idString .")";
     *        
     */
    public function setSubscriberStandardFields($standardFields)
    {
        $db = JFactory::getDbo();
        $query = $db->getQuery(true);

        $query->select(implode(',', $standardFields))
            ->from('#__osmembership_subscribers')
            ->where('id IN ( ' . implode(',', $this->ids) . ')')
            ->order('last_name ASC');

        $db->setQuery($query);

        $rows = $db->loadAssocList();
        foreach ($rows as $row) {
            $this->subscriberStandardFields[$row['id']] = $row;
        }

        if ($this->debug) {
            $r = var_export($rows, true);
            file_put_contents($this->debugFile, $r, FILE_APPEND);
        }
    }

    /**
     * Given a list of subscriber IDs get custom field values associated with them.
     * $ids - array of numerical IDs
     * $custom field names - array of names.
     *
     * $sql = "select fv.subscriber_id, fv.field_value, fs.name from jos_osmembership_field_value fv ".
     * "inner join jos_osmembership_fields fs on fv.field_id=fs.id ".
     * Return array of arrays. Subscriberid (name-> value, name-> value....)
     */
    public function setSubscriberCustomFields($customFieldNames)
    {
        $db = JFactory::getDbo();
        $query = $db->getQuery(true);

        $idString = implode(',', $this->ids);

        $cfString = "'" . implode("', '", $customFieldNames) . "'";

        $query->select(array(
            'fv.subscriber_id',
            'fv.field_value',
            'fs.name'
        ))
            ->from($db->quoteName('#__osmembership_field_value', 'fv'))
            ->join('INNER', $db->quoteName('#__osmembership_fields', 'fs') . ' ON ' . $db->quoteName('fv.field_id') . ' = ' . $db->quoteName('fs.id'))
            ->where($db->quoteName('fv.subscriber_id') . ' IN (' . $idString . ') ')
            ->where($db->quoteName('fs.name') . ' IN (' . $cfString . ') ');

        $db->setQuery($query);

        $rows = $db->loadAssocList();

        foreach ($rows as $row) {
            $this->subscriberCustomFields[$row['subscriber_id']][$row['name']] = $row['field_value'];
        }

        if ($this->debug) {
            $r = var_export($this->subscriberCustomFields, true);
            file_put_contents($this->debugFile, $r, FILE_APPEND);
        }
    }

    /**
     * Set all of the data for subscribers in region(s)
     *
     * @param array $regionNumbers
     * @param array $standardFields
     * @param array $customFields
     */
    public function setSubscriberData($standardFields, $customFields)
    {
        $this->setSubscriberIdsByAward();
        $this->setSubscriberStandardFields($standardFields);
        $this->setSubscriberCustomFields($customFields);

        // merge the standard and custom data
        foreach ($this->subscriberStandardFields as $id => $data) {
            $this->subscriberFields[$id] = array_merge($data, $this->subscriberCustomFields[$id]);
        }
        if ($this->debug) {
            $r = var_export($this->subscriberFields, true);
            file_put_contents($this->debugFile, $r, FILE_APPEND);
        }
    }

    /**
     * We need to consolidate the address fields into a single address and names into a single field
     * to make fewer columns in the table.
     */
    public function setDataForDisplay()
    {
        $printFields = array();
        foreach ($this->subscriberFields as $id => $data) {

           
            $printFields[$id]['membership_id'] = $data['membership_id'] . '<br>(' . $data['region'] . ')';
            $printFields[$id]['name'] = strtoupper($data['last_name']) . ', ' . $data['title'] . " " . $data['first_name'];
            
            $printFields[$id]['email'] = $data['email'];
            $printFields[$id]['phone'] = $data['phone'];
            $printFields[$id]['award'] = $data['award'];
        }
        if ($this->debug) {
            $r = var_export($printFields, true);
            file_put_contents($this->debugFile, $r, FILE_APPEND);
        }
        return $printFields;
    }

  
    
}
