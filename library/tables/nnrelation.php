<?php
/**
 * @package   FOF NNRelation
 * @author    Federico Liva <mail@federicoliva.info>
 * @copyright Copyright (C) 2014 Federico Liva
 * @license   http://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2
 */

defined('F0F_INCLUDED') or die;

class F0FTableBehaviorNnrelation extends F0FTableBehavior
{
	/**
	 * Save fields for many-to-many relations in their pivot tables.
	 *
	 * @param F0FTable $table Current item table.
	 *
	 * @return bool True if the object can be saved successfully, false elsewhere.
	 * @throws Exception The error message get trying to save fields into the pivot tables.
	 */
	public function onAfterStore(&$table)
	{
		// Retrieve the relations configured for this table
		$input     = new F0FInput();
		$key       = $table->getConfigProviderKey() . '.relations';
		$relations = $table->getConfigProvider()->get($key, array());

		// Abandon the process if not a save task
		if (!in_array($input->getWord('task'), array('apply', 'save', 'savenew')))
		{
			return true;
		}

		// For each relation check relative field
		foreach ($relations as $relation)
		{
			// Only if it is a multiple relation, sure!
			if ($relation['type'] == 'multiple')
			{
				// Retrive the fully qualified relation data from F0FTableRelations object
				$relation = array_merge(array(
					'itemName' => $relation['itemName']
				), $table->getRelations()->getRelation($relation['itemName'], $relation['type']));

				// Deduce the name of the field used in the form
				$field_name = F0FInflector::pluralize($relation['itemName']);
				// If field exists we catch its values!
				$field_values = $input->get($field_name, array(), 'array');

				// If the field exists, build the correct pivot couple objects
				$new_couples = array();

				foreach ($field_values as $value)
				{
					$new_couples[] = array(
						$relation['ourPivotKey']   => $table->getId(),
						$relation['theirPivotKey'] => $value
					);
				}

				// Find existent relations in the pivot table
				$query = $table->getDbo()
					->getQuery(true)
					->select($relation['ourPivotKey'] . ', ' . $relation['theirPivotKey'])
					->from($relation['pivotTable'])
					->where($relation['ourPivotKey'] . ' = ' . $table->getId());

				$existent_couples = $table->getDbo()
					->setQuery($query)
					->loadAssocList();

				// Find new couples and create its
				foreach ($new_couples as $couple)
				{
					if (!in_array($couple, $existent_couples))
					{
						$query = $table->getDbo()
							->getQuery(true)
							->insert($relation['pivotTable'])
							->columns($relation['ourPivotKey'] . ', ' . $relation['theirPivotKey'])
							->values($couple[$relation['ourPivotKey']] . ', ' . $couple[$relation['theirPivotKey']]);

						// Use database to create the new record
						if (!$table->getDbo()->setQuery($query)->execute())
						{
							throw new Exception('Can\'t create the relation for the ' . $relation['pivotTable'] . ' table');
						}
					}
				}

				// Now find the couples no more present, that will be deleted
				foreach ($existent_couples as $couple)
				{
					if (!in_array($couple, $new_couples))
					{
						$query = $table->getDbo()
							->getQuery(true)
							->delete($relation['pivotTable'])
							->where($relation['ourPivotKey'] . ' = ' . $couple[$relation['ourPivotKey']])
							->where($relation['theirPivotKey'] . ' = ' . $couple[$relation['theirPivotKey']]);

						// Use database to create the new record
						if (!$table->getDbo()->setQuery($query)->execute())
						{
							throw new Exception('Can\'t delete the relation for the ' . $relation['pivotTable'] . ' table');
						}
					}
				}
			}
		}

		return true;
	}
}
