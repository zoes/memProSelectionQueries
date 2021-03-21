<?php
/**
 * @package        Joomla
 * @subpackage     Membership Pro
 * @author         Tuan Pham Ngoc
 * @copyright      Copyright (C) 2012 - 2020 Ossolution Team
 * @license        GNU/GPL, see LICENSE.php
 */

defined('_JEXEC') or die;

use Joomla\Utilities\ArrayHelper;

class OSMembershipModelSubscriptions extends MPFModelList
{
	/**
	 * Determin if we return group members
	 *
	 * @var bool
	 */
	protected $includeGroupMembers = false;

	/**
	 * Exclude subscriptions from certain statuses from being exclude
	 *
	 * @var array
	 */
	protected $excludeStatus = [];

	/**
	 * Instantiate the model.
	 *
	 * @param   array  $config  configuration data for the model
	 */
	public function __construct($config = [])
	{
		$config['table']         = '#__osmembership_subscribers';
		$config['search_fields'] = ['tbl.first_name', 'tbl.last_name', 'tbl.organization', 'tbl.email', 'tbl.subscription_id', 'tbl.membership_id', 'tbl.transaction_id', 'tbl.formatted_invoice_number', 'tbl.formatted_membership_id', 'c.username', 'c.name'];
		$config['clear_join']    = false;

		parent::__construct($config);

		// Dynamic searchable fields
		$db    = $this->getDbo();
		$query = $db->getQuery(true);
		$query->select('name')
			->from('#__osmembership_fields')
			->where('published = 1')
			->where('is_searchable = 1');
		$db->setQuery($query);
		$searchableFields = $db->loadColumn();

		foreach ($searchableFields as $field)
		{
			$field = 'tbl.' . $field;

			if (!in_array($field, $this->searchFields))
			{
				$this->searchFields[] = $field;
			}
		}

		$this->state->insert('plan_id', 'int', 0)
			->insert('subscription_type', 'int', 0)
			->insert('published', 'int', -1)
			->insert('filter_date_field', 'string', 'tbl.created_date')
			->insert('filter_from_date', 'string', '')
			->insert('filter_to_date', 'string', '')
			->insert('filter_fields', 'array', [])
			->setDefault('filter_order', 'tbl.created_date')
			->setDefault('filter_order_Dir', 'DESC');
	}

	/**
	 * Builds SELECT columns list for the query
	 *
	 * @param   JDatabaseQuery  $query
	 *
	 * @return $this
	 */
	protected function buildQueryColumns(JDatabaseQuery $query)
	{
		$query->select(['tbl.*'])
			->select('b.title AS plan_title, b.lifetime_membership, b.activate_member_card_feature')
			->select('b.currency, b.currency_symbol')
			->select('c.username AS username')
			->select('d.id AS coupon_id, d.code AS coupon_code');

		return $this;
	}

	/**
	 * Builds JOINS clauses for the query
	 *
	 * @param   JDatabaseQuery  $query
	 *
	 * @return $this
	 */
	protected function buildQueryJoins(JDatabaseQuery $query)
	{
		$query->leftJoin('#__osmembership_plans AS b ON tbl.plan_id = b.id')
			->leftJoin('#__users AS c ON tbl.user_id = c.id')
			->leftJoin('#__osmembership_coupons AS d ON tbl.coupon_id = d.id');

		return $this;
	}

	/**
	 * Builds a WHERE clause for the query
	 *
	 * @param   JDatabaseQuery  $query
	 *
	 * @return $this
	 */
	protected function buildQueryWhere(JDatabaseQuery $query)
	{
		parent::buildQueryWhere($query);

		$db     = $this->getDbo();
		$config = OSMembershipHelper::getConfig();
		$state  = $this->getState();

		if (!$this->includeGroupMembers)
		{
			$query->where('group_admin_id = 0');
		}

		if ($state->plan_id)
		{
			$query->where('tbl.plan_id = ' . $state->plan_id);
		}

		if ($state->published != -1)
		{
			$query->where('tbl.published = ' . $state->published);
		}

		if ($this->excludeStatus)
		{
			$query->where('tbl.published NOT IN (' . implode(',', $this->excludeStatus) . ')');
		}

		if (!$config->get('show_incomplete_payment_subscriptions', 1))
		{
			$query->where('(tbl.published != 0 OR gross_amount = 0 OR tbl.payment_method LIKE "os_offline%")');
		}

		switch ($state->subscription_type)
		{
			case 1:
				$query->where('tbl.act = "subscribe"');
				break;
			case 2:
				$query->where('tbl.act = "renew"');
				break;
			case 3:
				$query->where('tbl.act = "upgrade"');
				break;
		}

		$filterDateField = $this->state->filter_date_field ?: 'tbl.created_date';

		if (!in_array($filterDateField, ['tbl.created_date', 'tbl.from_date', 'tbl.to_date']))
		{
			$filterDateField = 'tbl.to_date';
		}

		$config         = OSMembershipHelper::getConfig();
		$dateFormat     = str_replace('%', '', $config->get('date_field_format', '%Y-%m-%d'));
		$dateTimeFormat = $dateFormat . ' H:i:s';

		if ($state->filter_from_date)
		{
			try
			{
				$date = DateTime::createFromFormat($dateTimeFormat, $state->filter_from_date);

				if ($date !== false)
				{
					$this->state->filter_from_date = $date->format('Y-m-d H:i:s');
				}
			}
			catch (Exception $e)
			{
				// Do-nothing
			}

			$date = JFactory::getDate($state->filter_from_date, JFactory::getConfig()->get('offset'));
			$query->where($db->quoteName($filterDateField) . ' >= ' . $db->quote($date->toSql()));
		}

		if ($state->filter_to_date)
		{
			try
			{
				$date = DateTime::createFromFormat($dateTimeFormat, $state->filter_to_date);

				if ($date !== false)
				{
					$this->state->filter_to_date = $date->format('Y-m-d H:i:s');
				}
			}
			catch (Exception $e)
			{
				// Do-nothing
			}

			$date = JFactory::getDate($state->filter_to_date, JFactory::getConfig()->get('offset'));
			$query->where($db->quoteName($filterDateField) . '<= ' . $db->quote($date->toSql()));
		}

		$filterFields = array_filter($this->state->get('filter_fields', []));

		foreach ($filterFields as $fieldName => $fieldValue)
		{
			$pos        = strrpos($fieldName, '_');
			$fieldId    = (int) substr($fieldName, $pos + 1);
			$fieldValue = $db->quote('%' . $db->escape($fieldValue, true) . '%', false);
			$query->where('tbl.id IN (SELECT subscriber_id FROM #__osmembership_field_value WHERE field_id = ' . $fieldId . ' AND field_value LIKE ' . $fieldValue . ')');
		}

		return $this;
	}

	/**
	 * Get registrants custom fields data
	 *
	 * @param   array  $fields
	 *
	 * @return array
	 */
	public function getFieldsData($fields)
	{
		$fieldsData = [];
		$rows       = $this->data;
		if (count($rows) && count($fields))
		{
			$db    = $this->getDbo();
			$query = $db->getQuery(true);

			$subscriptionIds = [];

			foreach ($rows as $row)
			{
				$subscriptionIds[] = $row->id;
			}

			$query->select('subscriber_id, field_id, field_value')
				->from('#__osmembership_field_value')
				->where('subscriber_id IN (' . implode(',', $subscriptionIds) . ')')
				->where('field_id IN (' . implode(',', $fields) . ')');
			$db->setQuery($query);
			$rowFieldValues = $db->loadObjectList();

			foreach ($rowFieldValues as $rowFieldValue)
			{
				$fieldValue = $rowFieldValue->field_value;
				if (is_string($fieldValue) && is_array(json_decode($fieldValue)))
				{
					$fieldValue = implode(', ', json_decode($fieldValue));
				}
				$fieldsData[$rowFieldValue->subscriber_id][$rowFieldValue->field_id] = $fieldValue;
			}
		}

		return $fieldsData;
	}


	/**
	 * Set value for include group member property
	 *
	 * @param   bool  $value
	 */
	public function setIncludeGroupMembers($value)
	{
		$this->includeGroupMembers = $value;
	}

	/**
	 * Set exclude status
	 *
	 * @param   int|array
	 */
	public function setExcludeStatus($status)
	{
		$this->excludeStatus = (array) $status;
	}

	/**
	 * Method to get expired subscription records
	 *
	 * @return array
	 */
	public function getExpiredSubscribers()
	{
		$db    = $this->getDbo();
		$query = $db->getQuery(true);
		$query->select(['tbl.*'])
			->select('b.username AS username')
			->from('#__osmembership_subscribers AS tbl')
			->leftJoin('#__users AS b ON tbl.user_id = b.id')
			->where('tbl.is_profile = 1')
			->where('tbl.published = 2')
			->where('tbl.user_id > 0')
			->where('tbl.user_id NOT IN (SELECT DISTINCT s.user_id FROM #__osmembership_subscribers AS s WHERE s.published = 1)');
		$db->setQuery($query);
		$this->data = $db->loadObjectList();

		return $this->data;
	}

	/**
	 * Get statistic data
	 *
	 * @return array
	 */
	public static function getStatisticsData()
	{
		$data   = [];
		$config = JFactory::getConfig();
		$db     = JFactory::getDbo();
		$query  = $db->getQuery(true);

		$query->select('COUNT(id) AS number_subscriptions, SUM(gross_amount) AS total_amount')
			->from('#__osmembership_subscribers');

		// Today
		$date = JFactory::getDate('now', $config->get('offset'));
		$date->setTime(0, 0, 0);
		$date->setTimezone(new DateTimeZone('UCT'));
		$fromDate = $date->toSql(true);
		$date     = JFactory::getDate('now', $config->get('offset'));
		$date->setTime(23, 59, 59);
		$date->setTimezone(new DateTimeZone('UCT'));
		$toDate = $date->toSql(true);

		$query->where('published IN (1,2)')
			->where('group_admin_id = 0')
			->where('created_date >= ' . $db->quote($fromDate))
			->where('created_date <=' . $db->quote($toDate));


		$db->setQuery($query);
		$row = $db->loadObject();

		$data['today'] = [
			'number_subscriptions' => (int) $row->number_subscriptions,
			'total_amount'         => floatval($row->total_amount),
		];

		// Yesterday
		$date = JFactory::getDate('now', $config->get('offset'));
		$date->modify('-1 day');
		$date->setTime(0, 0, 0);
		$date->setTimezone(new DateTimeZone('UCT'));
		$fromDate = $date->toSql(true);
		$date     = JFactory::getDate('now', $config->get('offset'));
		$date->modify('-1 day');
		$date->setTime(23, 59, 59);
		$date->setTimezone(new DateTimeZone('UCT'));
		$toDate = $date->toSql(true);

		$query->clear('where')
			->where('published IN (1,2)')
			->where('group_admin_id = 0')
			->where('created_date >= ' . $db->quote($fromDate))
			->where('created_date <=' . $db->quote($toDate));
		$db->setQuery($query);
		$row = $db->loadObject();

		$data['yesterday'] = [
			'number_subscriptions' => (int) $row->number_subscriptions,
			'total_amount'         => floatval($row->total_amount),
		];

		// This week
		$date   = JFactory::getDate('now', $config->get('offset'));
		$monday = clone $date->modify(('Sunday' == $date->format('l')) ? 'Monday last week' : 'Monday this week');
		$monday->setTime(0, 0, 0);
		$monday->setTimezone(new DateTimeZone('UCT'));
		$fromDate = $monday->toSql(true);
		$sunday   = clone $date->modify('Sunday this week');
		$sunday->setTime(23, 59, 59);
		$sunday->setTimezone(new DateTimeZone('UCT'));
		$toDate = $sunday->toSql(true);

		$query->clear('where')
			->where('published IN (1,2)')
			->where('group_admin_id = 0')
			->where('created_date >= ' . $db->quote($fromDate))
			->where('created_date <=' . $db->quote($toDate));
		$db->setQuery($query);
		$row = $db->loadObject();

		$data['this_week'] = [
			'number_subscriptions' => (int) $row->number_subscriptions,
			'total_amount'         => floatval($row->total_amount),
		];

		// Last week, re-use data from this week
		$monday->modify('-7 day');
		$sunday->modify('-7 day');
		$fromDate = $monday->toSql(true);
		$toDate   = $sunday->toSql(true);

		$query->clear('where')
			->where('published IN (1,2)')
			->where('group_admin_id = 0')
			->where('created_date >= ' . $db->quote($fromDate))
			->where('created_date <=' . $db->quote($toDate));
		$db->setQuery($query);
		$row = $db->loadObject();

		$data['last_week'] = [
			'number_subscriptions' => (int) $row->number_subscriptions,
			'total_amount'         => floatval($row->total_amount),
		];

		// This month
		$date = JFactory::getDate('now', $config->get('offset'));
		$date->setDate($date->year, $date->month, 1);
		$date->setTime(0, 0, 0);
		$date->setTimezone(new DateTimeZone('UCT'));
		$fromDate = $date->toSql(true);
		$date     = JFactory::getDate('now', $config->get('offset'));
		$date->setDate($date->year, $date->month, $date->daysinmonth);
		$date->setTime(23, 59, 59);
		$date->setTimezone(new DateTimeZone('UCT'));
		$toDate = $date->toSql(true);

		$query->clear('where')
			->where('published IN (1,2)')
			->where('group_admin_id = 0')
			->where('created_date >= ' . $db->quote($fromDate))
			->where('created_date <=' . $db->quote($toDate));
		$db->setQuery($query);
		$row = $db->loadObject();

		$data['this_month'] = [
			'number_subscriptions' => (int) $row->number_subscriptions,
			'total_amount'         => floatval($row->total_amount),
		];

		// Last month
		$date = JFactory::getDate('first day of last month', $config->get('offset'));
		$date->setTime(0, 0, 0);
		$date->setTimezone(new DateTimeZone('UCT'));
		$fromDate = $date->toSql(true);
		$date     = JFactory::getDate('last day of last month', $config->get('offset'));
		$date->setTime(23, 59, 59);
		$date->setTimezone(new DateTimeZone('UCT'));
		$toDate = $date->toSql(true);

		$query->clear('where')
			->where('published IN (1,2)')
			->where('group_admin_id = 0')
			->where('created_date >= ' . $db->quote($fromDate))
			->where('created_date <=' . $db->quote($toDate));
		$db->setQuery($query);
		$row = $db->loadObject();

		$data['last_month'] = [
			'number_subscriptions' => (int) $row->number_subscriptions,
			'total_amount'         => floatval($row->total_amount),
		];

		// This year
		$date = JFactory::getDate('now', $config->get('offset'));
		$date->setDate($date->year, 1, 1);
		$date->setTime(0, 0, 0);
		$date->setTimezone(new DateTimeZone('UCT'));
		$fromDate = $date->toSql(true);
		$date     = JFactory::getDate('now', $config->get('offset'));
		$date->setDate($date->year, 12, 31);
		$date->setTime(23, 59, 59);
		$date->setTimezone(new DateTimeZone('UCT'));
		$toDate = $date->toSql(true);

		$query->clear('where')
			->where('published IN (1,2)')
			->where('group_admin_id = 0')
			->where('created_date >= ' . $db->quote($fromDate))
			->where('created_date <=' . $db->quote($toDate));
		$db->setQuery($query);
		$row = $db->loadObject();

		$data['this_year'] = [
			'number_subscriptions' => (int) $row->number_subscriptions,
			'total_amount'         => floatval($row->total_amount),
		];

		// Last year
		$date = JFactory::getDate('now', $config->get('offset'));
		$date->setDate($date->year - 1, 1, 1);
		$date->setTime(0, 0, 0);
		$date->setTimezone(new DateTimeZone('UCT'));
		$fromDate = $date->toSql(true);
		$date     = JFactory::getDate('now', $config->get('offset'));
		$date->setDate($date->year - 1, 12, 31);
		$date->setTime(23, 59, 59);
		$date->setTimezone(new DateTimeZone('UCT'));
		$toDate = $date->toSql(true);

		$query->clear('where')
			->where('published IN (1,2)')
			->where('group_admin_id = 0')
			->where('created_date >= ' . $db->quote($fromDate))
			->where('created_date <=' . $db->quote($toDate));
		$db->setQuery($query);
		$row = $db->loadObject();

		$data['last_year'] = [
			'number_subscriptions' => (int) $row->number_subscriptions,
			'total_amount'         => floatval($row->total_amount),
		];

		// Total subscription
		$query->clear()
			->select('COUNT(*) AS number_subscriptions, SUM(gross_amount) AS total_amount')
			->from('#__osmembership_subscribers')
			->where('group_admin_id = 0')
			->where('(published IN (1,2) OR (published = 0 AND payment_method LIKE "os_offline%"))');
		$db->setQuery($query);
		$row = $db->loadObject();

		$data['total_subscriptions'] = [
			'number_subscriptions' => (int) $row->number_subscriptions,
			'total_amount'         => floatval($row->total_amount),
		];

		// Active subscriptions
		$query->clear()
			->select('COUNT(*) AS number_subscriptions, SUM(gross_amount) AS total_amount')
			->from('#__osmembership_subscribers')
			->where('group_admin_id = 0')
			->where('published = 1');
		$db->setQuery($query);
		$row = $db->loadObject();

		$data['active_subscriptions'] = [
			'number_subscriptions' => (int) $row->number_subscriptions,
			'total_amount'         => floatval($row->total_amount),
		];

		// Active subscribers
		$query->clear()
			->select('DISTINCT profile_id')
			->from('#__osmembership_subscribers')
			->where('group_admin_id = 0')
			->where('published = 1');
		$db->setQuery($query);
		$data['active_subscribers'] = [
			'number_subscriptions' => count($db->loadColumn()),
			'total_amount'         => 0,
		];

		return $data;
	}

	/**
	 * Method to get last 12 months sales data
	 *
	 * @param   int  $planId
	 *
	 * @return array
	 */
	public static function getLast12MonthSales($planId = 0)
	{
		$db    = JFactory::getDbo();
		$query = $db->getQuery(true);
		$today = JFactory::getDate('Now', JFactory::getConfig()->get('offset'));
		$sales = [
			'labels' => [],
			'income' => [],
			'count'  => [],
		];

		for ($i = 0; $i < 12; $i++)
		{
			if ($i > 0)
			{
				$today->modify('-1 month');
			}

			$month = $today->format('n', true);
			$year  = $today->format('Y', true);

			$startMonth = clone $today;
			$endMonth   = clone $today;

			$startMonth->setTime(0, 0, 0);
			$startMonth->setDate($year, $month, 1);
			$endMonth->setTime(23, 59, 59);
			$endMonth->setDate($year, $month, $today->format('t', true));

			$query->clear()
				->select('SUM(gross_amount)')
				->from('#__osmembership_subscribers')
				->where('group_admin_id = 0')
				->where('published IN  (1, 2)')
				->where('created_date >= ' . $db->quote($startMonth->toSql()))
				->where('created_date <= ' . $db->quote($endMonth->toSql()));

			if ($planId > 0)
			{
				$query->where('plan_id = ' . $planId);
			}

			$db->setQuery($query);
			$sales['income'][] = (float) $db->loadResult();

			$query->clear()
				->select('COUNT(*)')
				->from('#__osmembership_subscribers')
				->where('group_admin_id = 0')
				->where('(published IN (1,2) OR (published = 0 AND payment_method LIKE "os_offline%"))')
				->where('created_date >= ' . $db->quote($startMonth->toSql()))
				->where('created_date <= ' . $db->quote($endMonth->toSql()));

			$db->setQuery($query);

			$sales['count'][]  = $db->loadResult();
			$sales['labels'][] = $today->format('M') . '/ ' . $year;
		}


		$sales['labels'] = array_reverse($sales['labels']);
		$sales['income'] = array_reverse($sales['income']);
		$sales['count']  = array_reverse($sales['count']);

		return $sales;
	}
}
