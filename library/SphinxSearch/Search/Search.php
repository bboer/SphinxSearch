<?php
namespace SphinxSearch\Search;

use Zend\ServiceManager\ServiceLocatorAwareInterface;

class Search implements ServiceLocatorAwareInterface
{
    use \ZendAdditionals\Config\ConfigExtensionTrait;
    use \SphinxSearch\ServiceManager\ServiceTrait;
    use \Zend\ServiceManager\ServiceLocatorAwareTrait;

    /**
     *
     * @param array $filter Search filter like:
     * array(                                 // Filters only support integer values
     *     array(
     *         'key'    => 'some_search_key', // The key to filter on
     *         'values' => array(30,...),     // The values to be filtered
     *     ),
     *     array(                             // This is a range filter
     *         'key'    => 'some_search_key', // The key to filter on
     *         'min'    => 5,                 // Min and Max value to filter between
     *         'max'    => 105,
     *     ),
     *     ...
     * );
     * @param array $queries Search query like:
     * array(
     *     array(
     *         'key'    => 'search_key',     // The key to search on
     *         'values' => array(            // The values to match with
     *             'value_one',
     *             'value_two',
     *         ),
     *         'strict' => true,             // Strict matching? If false then
     *     ),                                // full-text search will be applied
     *     array(
     *         'key'    => 'another_key',    // The key to search on
     *         'values' => array(            // The values to match with
     *             'some_value',
     *         ),
     *         'strict' => false,            // Strict matching? If false then
     *     ),                                // full-text search will be applied
     *     'group_identifier' => array(
     *         // Any queries specified within a string-index array
     *         // will be grouped for sphinx.
     *     ),
     *     ...
     * );
     * @param array $fieldWeights Field weights array like:
     * array(
     *     'field_one' => 5,
     *     'field_two' => 3,
     *     ...
     * );
     * @param array   $sortBy Provide sort info like:
     * array(
     *     'mode'  => SPH_SORT_ATTR_DESC,
     *     'field' => 'profile_created',
     * ),
     * @param integer $limit
     * @param integer $offset
     *
     * @return array like:
     * array(
     *     array(
     *         'id'     => 12345,
     *         'weight' => 30,
     *         'attrs'  => array(...)
     *     ),
     *     array(
     *         'id'     => 23456,
     *         'weight' => 20,
     *         'attrs'  => array(...)
     *     ),
     *     ...
     * );
     *
     * @throws Exception\SphinxQueryFailedException
     */
    public function search(
              $index,
        array $filters      = null,
        array $queries      = null,
        array $fieldWeights = null,
        array $sortBy       = null,
              $limit        = 20,
              $offset       = 0
    ) {
        // Get the SphinxClient service
        $sphinxClient = $this->getSphinxClientService();

        // Reset previous filters
        $sphinxClient->ResetFilters();
        $sphinxClient->ResetGroupBy();
        $sphinxClient->ResetOverrides();

        // Set limit and offset
        $sphinxClient->SetLimits($offset, $limit);

        // Set the query var to empty by default
        $query = '';

        if (null !== $filters) {
            foreach ($filters as $filter) {
                if (!isset($filter['key'])) {
                    // exception here
                }
                if (
                    array_key_exists('min', $filter) &&
                    array_key_exists('max', $filter)
                ) {
                    $sphinxClient->SetFilterRange(
                                  $filter['key'],
                        (integer) $filter['min'],
                        (integer) $filter['max']
                    );
                } else {
                    if (!isset($filter['values']) || !is_array($filter['values'])) {
                        // exception here
                    }
                    $sphinxClient->SetFilter(
                        $filter['key'],
                        $filter['values']
                    );
                }
            }
        }
        if (null !== $queries) {
            foreach ($queries as $key => $queryInfo) {
                // When the key is a string we have ourselves a group
                if (is_string($key)) {
                    $query .= '( ';
                    $first = true;
                    foreach ($queryInfo as $groupedQueryInfo) {
                        $query .= (!$first ? ' |' : '') . PHP_EOL .
                        "  @{$groupedQueryInfo['key']} " . (
                            $groupedQueryInfo['strict'] ?
                            /*'^' . */implode(' ', $groupedQueryInfo['values'])/* . '$'*/ :
                            '*' . implode('* *', $groupedQueryInfo['values']) . '*'
                        );
                        $first = false;
                    }
                    $query .= PHP_EOL . ')' . PHP_EOL;
                } else {
                    $query .= "@{$queryInfo['key']} " . (
                        $queryInfo['strict'] ?
                        /*'^' . */implode(' ', $queryInfo['values'])/* . '$'*/ :
                        '*' . implode('* *', $queryInfo['values']) . '*'
                    ) . PHP_EOL;
                }
            }
        }

        if (null !== $fieldWeights) {
            $sphinxClient->SetFieldWeights($fieldWeights);
        }

        // Apply sorting when requested
        if (null !== $sortBy && isset($sortBy['mode']) && isset($sortBy['field'])) {
            $sphinxClient->SetSortMode($sortBy['mode'], $sortBy['field']);
        }

        $result = $sphinxClient->query($query, $index);

        if (false === $result) {
            throw new Exception\SphinxQueryFailedException(
                $sphinxClient->getLastError()
            );
        }
        if ($sphinxClient->GetLastWarning()) {
            throw new Exception\SphinxQueryFailedException(
                $sphinxClient->GetLastWarning()
            );
        }

        if (!isset($result['matches'])) {
            return array();
        }

        return $result['matches'];
    }
}
