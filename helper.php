<?php
/**
 * 
 * 
 */
defined('_JEXEC') or die();

use Joomla\Utilities\ArrayHelper;

class OSMembershipRegionQueriesHelper
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
    public function setSubscriberIdsInRegion($regionNumbers)
    {
        $db = JFactory::getDbo();
        $query = $db->getQuery(true);

        $rnString = implode(',', $regionNumbers);

        $query->select('fv.subscriber_id')
            ->from($db->quoteName('#__osmembership_field_value', 'fv'))
            ->join('INNER', $db->quoteName('#__osmembership_fields', 'fs') . ' ON ' . $db->quoteName('fv.field_id') . ' = ' . $db->quoteName('fs.id'))
            ->where($db->quoteName('fs.name') . " = " . $db->quote('region'))
            ->where($db->quoteName('fv.field_value') . ' IN (' . $rnString . ') ');

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
    public function setSubscriberData($regionNumbers, $standardFields, $customFields)
    {
        $this->setSubscriberIdsInRegion($regionNumbers);
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

            $address = $data['address'] . ',';
            if (strlen($data['address2']) > 0) {
                $address .= ' ' . $data['address2'] . ',';
            }
            if (strlen($data['address3']) > 0) {
                $address .= ' ' . $data['address3'] . ',';
            }
            if (strlen($data['city']) > 0) {
                $address .= ' ' . $data['city'] . ',';
            }
            if (strlen($data['county']) > 0) {
                $address .= ' ' . $data['county'] . ',';
            }
            if (strlen($data['country']) > 0) {
                $address .= ' ' . $data['country'] . '.';
            }
            if (strlen($data['zip']) > 0) {
                $address .= " " . $data['zip'];
            }

            $printFields[$id]['membership_id'] = $data['membership_id'] . '<br>(' . $data['region'] . ')';
            $printFields[$id]['name'] = strtoupper($data['last_name']) . ', ' . $data['title'] . " " . $data['first_name'];
            $printFields[$id]['address'] = $address;
            $printFields[$id]['email'] = $data['email'];
            $printFields[$id]['phone'] = $data['phone'];
        }
        if ($this->debug) {
            $r = var_export($printFields, true);
            file_put_contents($this->debugFile, $r, FILE_APPEND);
        }
        return $printFields;
    }

    /**
     * Get all of the possible options for region numbers from the membership database.
     * Not currently used by anthing.
     * select id,fieldtype from jos_osmembership_fields where name='region';
     * select field_value from jos_osmembership_field_value where field_id=16;
     */
    public function setRegionNumbers()
    {
        $db = &JFactory::getDBO();
        $query = $db->getQuery(true);
        
        $query->select(array(
            'distinct fv.field_value'
        ))
            ->from($db->quoteName('#__osmembership_field_value', 'fv'))
            ->join('INNER', $db->quoteName('#__osmembership_fields', 'fs') . ' ON ' . $db->quoteName('fv.field_id') . ' = ' . $db->quoteName('fs.id'))
            ->where($db->quoteName('fs.name') . " = " . $db->quote('region'));
           

        $db->setQuery($query);

        $rvals = $db->loadColumn();
        
        $regions = array();
        foreach ($rvals as $r) {
            $rNum = trim($r);
            if (strlen($rNum) > 0) {
                $regions[] = $r;
            }
        }
        sort($regions);
        return $regions;
    }

    /**
     * Check user's group and limit what they are allowed to check.
     * Region Groups must be called RegionXX
     */
    public function setAllowedRegions()
    {
        $allowedRegions=array();
        $user = JFactory::getUser();
        $groupIds = $user->get('groups');
        
        $db = JFactory::getDBO();

        $groupIdList = '(' . implode(',', $groupIds) . ')';

        $query = $db->getQuery(true);
        $query->select('id, title');
        $query->from('#__usergroups');
        $query->where('id IN ' . $groupIdList);
        $db->setQuery($query);
        $rows = $db->loadRowList();
        $grouplist = '';
        foreach ($rows as $group) {
            if(substr($group[1], 0, 6) == 'Region') {
                $allowedRegions[] = trim(substr($group[1], 6));
            }
        }
        if ($debug) {
            $r = var_export($allowedRegions, true);
            file_put_contents($debugFile, "ALLOWED\n" . $r, FILE_APPEND);
        }
       return $allowedRegions;
    }
    
    /**
     * Sanity check that the POST data is really coming from the logged in user and that the groups match.
     * This might not seem necessary because we have only given them allowed groups to query but it woudl not be impossible for
     * someone to use a CURL post if they worked out the format so checking here too seems a good idea.
     * @param unknown $post
     */
    public function checkPostData() {
        $regionNumbersToQuery = array();
       
        $input = new JInput();
        $post = $input->getArray($_POST);
        
        if ($debug) {
            $r = var_export($post, true);
            file_put_contents($debugFile, "POST\n" . $r, FILE_APPEND);
        }
        
        foreach ($post as $label => $value) {
            if (substr($label, 0, 4) == 'regi') {
                $regionNumbersToQuery[] = $value;
            }
        }
        
        $allowedRegions = $this->setAllowedRegions();
        foreach($regionNumbersToQuery as $regionNumber) {
            if (!in_array($regionNumber, $allowedRegions)) {
                throw new Exception("Current user is not authorised to access data for Region " . $regionNumber);
            }
        }
        return $regionNumbersToQuery;
    }
    /**
     * Given a last name will return all the subscriber details associated with that name
     */
    public function setSubscribersByName($lastName) {
        
    }
    
    /**
     * Set the subscriber fields from a given last_name
     * @param unknown $lastName
     * @param unknown $standardFields
     * @param unknown $customFields
     */
    public function setSubscriberDataByName($lastName, $standardFields, $customFields)
    {
        $db = JFactory::getDbo();
        $query = $db->getQuery(true);
        
        $query->select(implode(',', $standardFields))
        ->from('#__osmembership_subscribers')  
        ->where($db->quoteName('last_name') . " LIKE ". $db->quote('%'.$lastName.'%'));
     
        
        
        $db->setQuery($query);
        
        $rows = $db->loadAssocList();
        foreach ($rows as $row) {
            $this->subscriberStandardFields[$row['id']] = $row;
            $this->ids[] = $row['id'];
        }
        
        if ($this->debug) {
            $r = var_export($rows, true);
            file_put_contents($this->debugFile, $r, FILE_APPEND);
        }
        
        $this->setSubscriberCustomFields($customFields);
        
        // merge the standard and custom data
        foreach ($this->subscriberStandardFields as $id => $data) {
            $this->subscriberFields[$id] = array_merge($data, $this->subscriberCustomFields[$id]);
        }
    }
    /**
     * We will log the reason for access to a file for now. This should be in the log directory
     */
    public function logReason() {
        
    }
    
    /**
     * 
     */
    public function checkPostDataNameQuery() {
        
    }
    
}
