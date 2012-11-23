<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/
?>
<?php
/**
 * File containing CDCheck class for API.
 * @package API
 */
/**
 * Class containing methods for operations with Discovery checks for discovery rules
 */
class CDCheck extends CZBXAPI {

	protected $tableName = 'dchecks';

	protected $tableAlias = 'dc';

	public function get($options) {
		$result = array();
		$nodeCheck = false;
		$userType = self::$userData['type'];

		// allowed columns for sorting
		$sortColumns = array('dcheckid', 'druleid');

		// allowed output options for [ select_* ] params
		$subselectsAllowedOutputs = array(API_OUTPUT_REFER, API_OUTPUT_EXTEND, API_OUTPUT_CUSTOM);

		$sqlParts = array(
			'select'	=> array('dchecks' => 'dc.dcheckid'),
			'from'		=> array('dchecks' => 'dchecks dc'),
			'where'		=> array(),
			'group'		=> array(),
			'order'		=> array(),
			'limit'		=> null
		);

		$defOptions = array(
			'nodeids'					=> null,
			'dcheckids'					=> null,
			'druleids'					=> null,
			'dhostids'					=> null,
			'dserviceids'				=> null,
			'editable'					=> null,
			'nopermissions'				=> null,
			// filter
			'filter'					=> null,
			'search'					=> null,
			'searchByAny'				=> null,
			'startSearch'				=> null,
			'excludeSearch'				=> null,
			'searchWildcardsEnabled'	=> null,
			// output
			'output'					=> API_OUTPUT_REFER,
			'selectDRules'				=> null,
			'selectDHosts'				=> null,
			'countOutput'				=> null,
			'groupCount'				=> null,
			'preservekeys'				=> null,
			'sortfield'					=> '',
			'sortorder'					=> '',
			'limit'						=> null,
			'limitSelects'				=> null
		);
		$options = zbx_array_merge($defOptions, $options);

// editable + PERMISSION CHECK
		if (USER_TYPE_SUPER_ADMIN == $userType) {
		}
		elseif (is_null($options['editable']) && (self::$userData['type'] == USER_TYPE_ZABBIX_ADMIN)) {
		}
		elseif (!is_null($options['editable']) && (self::$userData['type']!=USER_TYPE_SUPER_ADMIN)) {
			return array();
		}

// nodeids
		$nodeids = !is_null($options['nodeids']) ? $options['nodeids'] : get_current_nodeid();

// dcheckids
		if (!is_null($options['dcheckids'])) {
			zbx_value2array($options['dcheckids']);
			$sqlParts['where']['dcheckid'] = DBcondition('dc.dcheckid', $options['dcheckids']);

			if (!$nodeCheck) {
				$nodeCheck = true;
				$sqlParts['where'][] = DBin_node('dc.dcheckid', $nodeids);
			}
		}

// druleids
		if (!is_null($options['druleids'])) {
			zbx_value2array($options['druleids']);

			$sqlParts['select']['druleid'] = 'dc.druleid';
			$sqlParts['where'][] = DBcondition('dc.druleid', $options['druleids']);

			if (!is_null($options['groupCount'])) {
				$sqlParts['group']['druleid'] = 'dc.druleid';
			}

			if (!$nodeCheck) {
				$nodeCheck = true;
				$sqlParts['where'][] = DBin_node('dc.druleid', $nodeids);
			}
		}

// dhostids
		if (!is_null($options['dhostids'])) {
			zbx_value2array($options['dhostids']);

			$sqlParts = $this->addQuerySelect('dh.dhostid', $sqlParts);
			$sqlParts = $this->addQueryLeftJoin('dhosts dh', 'dc.druleid', 'dh.drulelid', $sqlParts);

			$sqlParts['where']['dh'] = DBcondition('dh.dhostid', $options['dhostids']);

			if (!is_null($options['groupCount'])) {
				$sqlParts['group']['dhostid'] = 'dh.dhostid';
			}
		}


// dserviceids
		if (!is_null($options['dserviceids'])) {
			zbx_value2array($options['dserviceids']);

			$sqlParts['select']['dserviceid'] = 'ds.dserviceid';
			$sqlParts['from']['dhosts'] = 'dhosts dh';
			$sqlParts['from']['dservices'] = 'dservices ds';

			$sqlParts['where']['ds'] = DBcondition('ds.dserviceid', $options['dserviceids']);
			$sqlParts['where']['dcdh'] = 'dc.druleid=dh.druleid';
			$sqlParts['where']['dhds'] = 'dh.hostid=ds.hostid';

			if (!is_null($options['groupCount'])) {
				$sqlParts['group']['dserviceid'] = 'ds.dserviceid';
			}
		}

// filter
		if (is_array($options['filter'])) {
			zbx_db_filter('dchecks dc', $options, $sqlParts);
		}

// search
		if (is_array($options['search'])) {
			zbx_db_search('dchecks dc', $options, $sqlParts);
		}

		// sorting
		zbx_db_sorting($sqlParts, $options, $sortColumns, 'dc');

// limit
		if (zbx_ctype_digit($options['limit']) && $options['limit']) {
			$sqlParts['limit'] = $options['limit'];
		}
//-------

		$sqlParts = $this->applyQueryOutputOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$sqlParts = $this->applyQueryNodeOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$res = DBselect($this->createSelectQueryFromParts($sqlParts), $sqlParts['limit']);

		$relationMap = new CRelationMap();
		while ($dcheck = DBfetch($res)) {
			if (!is_null($options['countOutput'])) {
				if (!is_null($options['groupCount']))
					$result[] = $dcheck;
				else
					$result = $dcheck['rowscount'];
			}
			else{
				$dcheckids[$dcheck['dcheckid']] = $dcheck['dcheckid'];

				if (!isset($result[$dcheck['dcheckid']])) {
					$result[$dcheck['dcheckid']]= array();
				}

				// druleids
				if (isset($dcheck['druleid']) && is_null($options['selectDRules'])) {
					if (!isset($result[$dcheck['dcheckid']]['drules']))
						$result[$dcheck['dcheckid']]['drules'] = array();

					$result[$dcheck['dcheckid']]['drules'][] = array('druleid' => $dcheck['druleid']);
				}

				// dhostids
				if (isset($dcheck['dhostid']) && is_null($options['selectDHosts'])) {
					if (!isset($result[$dcheck['dcheckid']]['dhosts']))
						$result[$dcheck['dcheckid']]['dhosts'] = array();

					$result[$dcheck['dcheckid']]['dhosts'][] = array('dhostid' => $dcheck['dhostid']);
				}

				// populate relation map
				if (isset($dcheck['druleid']) && $dcheck['druleid']) {
					$relationMap->addRelation($dcheck['dcheckid'], 'drules', $dcheck['druleid']);
				}
				if (isset($dcheck['dhostid']) && $dcheck['dhostid']) {
					$relationMap->addRelation($dcheck['dcheckid'], 'dhosts', $dcheck['dhostid']);
				}
				unset($dcheck['dhostid']);

				$result[$dcheck['dcheckid']] += $dcheck;
			}
		}

		if (!is_null($options['countOutput'])) {
			return $result;
		}

		// Adding Objects
		// select_drules
		if ($options['selectDRules'] !== null && $options['selectDRules'] !== API_OUTPUT_COUNT) {
			$drules = API::DRule()->get(array(
				'output' => $options['selectDRules'],
				'druleids' => $relationMap->getRelatedIds('drules'),
				'nodeids' => $nodeids,
				'preservekeys' => 1
			));

			if (!is_null($options['limitSelects'])) {
				order_result($drules, 'name');
			}

			$result = $relationMap->mapMany($result, $drules, 'drules', $options['limitSelects']);
		}

		// selectDHosts
		if ($options['selectDHosts'] !== null && $options['selectDHosts'] !== API_OUTPUT_COUNT) {
			$dhosts = API::DHost()->get(array(
				'output' => $options['selectDHosts'],
				'dhostids' => $relationMap->getRelatedIds('dhosts'),
				'nodeids' => $nodeids,
				'preservekeys' => 1
			));

			if (!is_null($options['limitSelects'])) {
				order_result($dhosts, 'dhostid');
			}
			$result = $relationMap->mapMany($result, $dhosts, 'dhosts', $options['limitSelects']);
		}

// removing keys (hash -> array)
		if (is_null($options['preservekeys'])) {
			$result = zbx_cleanHashes($result);
		}

	return $result;
	}

	/**
	 * Check if user has read permissions for discovery checks.
	 *
	 * @param array $ids
	 * @return bool
	 */
	public function isReadable(array $ids) {
		if (empty($ids)) {
			return true;
		}

		$ids = array_unique($ids);

		$count = $this->get(array(
			'nodeids' => get_current_nodeid(true),
			'dcheckids' => $ids,
			'countOutput' => true
		));

		return (count($ids) == $count);
	}

	/**
	 * Check if user has write permissions for discovery checks.
	 *
	 * @param array $ids
	 * @return bool
	 */
	public function isWritable(array $ids) {
		if (empty($ids)) {
			return true;
		}

		$ids = array_unique($ids);

		$count = $this->get(array(
			'nodeids' => get_current_nodeid(true),
			'dcheckids' => $ids,
			'editable' => true,
			'countOutput' => true
		));

		return (count($ids) == $count);
	}

	protected function applyQueryOutputOptions($tableName, $tableAlias, array $options, array $sqlParts) {
		$sqlParts = parent::applyQueryOutputOptions($tableName, $tableAlias, $options, $sqlParts);

		if ($options['countOutput'] === null) {
			if ($options['selectDRules'] !== null) {
				$sqlParts = $this->addQuerySelect('dc.druleid', $sqlParts);
			}

			if ($options['selectDHosts'] !== null && $options['selectDHosts'] != API_OUTPUT_COUNT) {
				$sqlParts = $this->addQueryLeftJoin('dhosts dh', 'dc.druleid', 'dh.druleid', $sqlParts);
				$sqlParts = $this->addQuerySelect('dh.dhostid', $sqlParts);
			}
		}

		return $sqlParts;
	}
}
?>
