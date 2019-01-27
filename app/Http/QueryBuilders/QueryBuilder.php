<?php

namespace App\Http\QueryBuilders;

use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator as BasePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\Builder;
use App\Exceptions\Api\UnknownColumnException;
use App\Exceptions\Api\UnknownRelationException;
use App\Http\QueryBuilders\Helpers\UriParser;

class QueryBuilder
{
    protected $model;

    protected $uriParser;

    protected $wheres = [];

    protected $processed_wheres = [];

    protected $orderBy = [];

    protected $limit;

    protected $page = 1;

    protected $offset = 0;

    protected $columns = ['*'];

    protected $relationColumns = [];

    protected $includes = [];

    protected $includesDeleted = [];

    protected $groupBy = [];

    protected $excludedParameters = [];

    protected $appends = [];

    protected $query;

    protected $result;

    public function __construct(Model $model, Request $request, Builder $query = null)
    {
        $this->orderBy = config('apiQueryBuilder.orderBy');

        $this->limit = null;

        $this->modelNamespace = config('apiQueryBuilder.modelNamespace');

        $this->excludedParameters = array_merge($this->excludedParameters, config('apiQueryBuilder.excludedParameters'));

        if ($model != null) {
            $this->model = $model;
        }

        $this->uriParser = new UriParser($request);

        if ($query != null) {
            $this->query = $query;
        } else {
            $this->query = $this->model->newQuery();
        }
    }

    public function build($id = null, $include_where = true, $offset = true, $limit = true, $group_by = true, $order_by = true)
    {
        $this->prepare();

        if (isset($id)) {
            $this->query->where($this->model->getTable() . '.id', '=', $id);
        }

        if ($include_where && $this->processed_wheres) {
            $this->applyNestedWheres($this->processed_wheres, $this->query, $this->model);
        }

        if ($this->hasGroupBy() && $this->hasTableColumn($this->groupBy[0])) {
            $this->query->groupBy($this->groupBy);
        }

        if ($this->hasLimit()) {
            $this->query->take($this->limit);
        }

        if ($this->hasOffset()) {
            $this->query->skip($this->offset);
        }

        array_map([$this, 'addOrderByToQuery'], $this->orderBy);

        $this->query->with($this->includes);

        $this->eagerLoadAppendsRelations();

        if (in_array('*', $this->columns)) {
            $this->columns[array_search('*', $this->columns)] = $this->model->getTable() . '.*';
        }

        // Avoid ambiguous select on id fields
        $columns = $this->columns;

        if (in_array('id', $columns)) {
            $columns = array_prepend($columns, $this->model->getTable() . '.id');
            unset($columns[array_search('id', $columns)]);
        }

        $this->query->select($columns);

        return $this;
    }

    public function get()
    {
        $result = $this->query->get();

        if ($this->hasAppends()) {
            $result = $this->addAppendsToModel($result);
        }
        if ($this->hasGroupBy() && !$this->hasTableColumn($this->groupBy[0])) {
            $result = $result->groupBy(implode(',', $this->groupBy));
        }
        if ($this->orderBy && isset($this->orderBy['direction']) && isset($this->orderBy['column'])) {
            if ($this->orderBy['direction'] == 'asc') {
                $result = $result->sortBy($this->orderBy['column']);
            } else {
                $result = $result->sortByDesc($this->orderBy['column']);
            }
        }

        return $result;
    }

    public function paginate()
    {
        if (!$this->hasLimit()) {
            throw new Exception("You can't use unlimited option for pagination", 1);
        }

        $result = $this->basePaginate($this->limit);

        if ($this->hasAppends()) {
            $result = $this->addAppendsToModel($result);
        }

        return $result;
    }

    public function lists($value, $key)
    {
        return $this->query->pluck($value, $key);
    }

    public function query()
    {
        return $this->query;
    }

    public function limit()
    {
        return $this->limit;
    }

    public function includes()
    {
        return $this->includes;
    }

    public function wheres()
    {
        return $this->wheres;
    }

    public function addExcludedParameter($key)
    {
        if (!$this->isExcludedParameter($key)) {
            $this->excludedParameters[] = $key;
        }
    }

    protected function prepare()
    {
        $constantParameters = $this->uriParser->constantParameters();

        array_map([$this, 'prepareConstant'], $constantParameters);

        foreach ($this->uriParser->whereParameters() as $raw_where) {
            $this->addWhere($raw_where['key'], $raw_where['operator'], $raw_where['value'], $raw_where['restrictive'], $raw_where['unmatched']);
        }

        $raw_wheres = $this->wheres();

        $with = [];

        foreach ($this->columns as $column) {
            $tables = explode('.', $column);
            $column = array_pop($tables);
            $with_ptr = &$with;
            foreach ($tables as $table) {
                $with_ptr = &$with_ptr['children'][$table];
            }
            $with_ptr['select'][] = $column;
        }

        foreach ($this->includesDeleted as $include) {     //check the includes map to a valid relation
            //special case for top level
            if ($include == 'true') {
                $with['include_deleted'] = true;
                continue;
            }
            $tables = explode('.', $include);
            $with_ptr = &$with;
            foreach ($tables as $table) {
                $with_ptr = &$with_ptr['children'][$table];
            }
            $with_ptr['include_deleted'] = true;
        }

        foreach ($this->includes as $include) {     //check the includes map to a valid relation
            $tables = explode('.', $include);

            $with_ptr = &$with;
            foreach ($tables as $table) {
                $with_ptr = &$with_ptr['children'][$table];
                $with_ptr['include'] = true;
            }
        }

        foreach ($raw_wheres as $raw_where) {
            $tables = explode('.', $raw_where['key']);
            $raw_where['key'] = array_pop($tables);

            $with_ptr = &$with;
            foreach ($tables as $table) {
                $with_ptr = &$with_ptr['children'][$table];
            }
            $with_ptr['wheres'][] = $raw_where;
        }

        $addAsAnd = [];
        if (array_key_exists('children', $with)) {
            foreach ($with['children'] as $i => $child) {
                $counter = 0;
                if (array_key_exists('wheres', $child) && count($child['wheres']) > 1) {
                    $sameWheres = [$child['wheres'][0]];
                    $firstChild = $with['children'][$i];
                    // Loop on all where for the model
                    foreach ($child['wheres'] as $j => $where) {
                        // If the key of this where is the key of the first where
                        // But the value of this where is not the value of the first where
                        // Add it to the sameWheres array and rename it in the wheres array so that [] became [x]
                        if ($where['key'] == $child['wheres'][0]['key'] &&
                            $where['value'] != $child['wheres'][0]['value'])
                        {
                            $sameWheres[] = $where;
                            unset($with['children'][$i]['wheres'][$j]);
                            $with['children'][$i . '[' . $counter++ . ']'] = $firstChild;
                            unset($with['children'][$i]);
                        }
                    }
                    array_shift($sameWheres);
                    foreach ($sameWheres as $sameWhere) {
                        $with['children'][$i . '[' . $counter . ']'] = $with['children'][$i . '[0]'];
                        $with['children'][$i . '[' . $counter++ . ']']['wheres'][0]['value'] = $sameWhere['value'];
                    }
                    foreach ($with['children'] as $name => $newChild) {
                        if (array_key_exists('wheres', $newChild) && count($newChild['wheres']) > 1) {
                            $firstKey = $newChild['wheres'][0]['key'];
                            $firstOperator = $newChild['wheres'][0]['operator'];
                            $firstUnmatched = $newChild['wheres'][0]['unmatched'];
                            $firstRestricitive = $newChild['wheres'][0]['restrictive'];

                            foreach ($newChild['wheres'] as $k => $newWhere) {
                                if ($k == 0 || $newWhere['key'] != $firstKey) {
                                    continue;
                                }
                                unset($with['children'][$name]['wheres'][$k]);
                            }
                        }
                    }

                }
            }
        }

        $this->determineRestrictive($with);

        $this->setProcessedWheres($with);

        if ($this->hasIncludes() && $this->hasRelationColumns()) {
            //$this->fixRelationColumns();
        }

        return $this;
    }

    private function determineRestrictive(&$wheres)
    {
        $status = -1;

        if (isset($wheres['children'])) {
            foreach ($wheres['children'] as $i => $where) {
                $restrictive = $this->determineRestrictive($wheres['children'][$i]);
                if ($status === -1) {
                    $status = $restrictive;
                } elseif ($status === $restrictive) {
                    //they match, do nothing
                } else {
                    $status = null;
                }
            }
        }

        if (isset($wheres['wheres'])) {
            foreach ($wheres['wheres'] as $where) {
                if ($status === -1) {
                    $status = $where['restrictive'];
                } elseif ($status === $where['restrictive']) {
                    //they match, do nothing
                } else {
                    $status = null;
                }
            }
        }

        $status = ($status === -1 ? false : $status);
        $wheres['restrictive'] = $status;
        return $status;
    }

    private function prepareConstant($parameter)
    {
        if (! $this->uriParser->hasQueryParameter($parameter)) {
            return;
        }

        $callback = [$this, $this->setterMethodName($parameter)];

        $callbackParameter = $this->uriParser->queryParameter($parameter);

        call_user_func($callback, $callbackParameter['value']);
    }

    public function addInclude($include)
    {
        if (!in_array($include, $this->includes)) {
            $this->includes[] = $include;
        }
    }

    public function setIncludes($includes)
    {
        $this->includes = array_filter(is_array($includes) ? $includes : explode(',', $includes));
    }

    private function setIncludesDeleted($includes)
    {
        $this->includesDeleted = array_filter(explode(',', $includes));
    }

    private function setPage($page)
    {
        $this->page = (int) $page;

        $this->offset = ($page - 1) * $this->limit;
    }

    private function setFields($columns)
    {
        $fields = explode(',', $columns);

        foreach ($fields as $idx => $field) {
            if (in_array(trim($field), array_keys($this->model->getAppendsDependencies()))) {
                unset($fields[$idx]);
                foreach ($this->model->getAppendsDependencies()[trim($field)] as $dependency) {
                    $fields[] = $dependency;
                }
            }
        }

        $columns = array_filter(array_unique($fields));

        if (!in_array('id', $columns)) {
            $columns = array_prepend($columns, 'id');
        }

        $this->columns = $this->relationColumns = [];

        array_map([$this, 'setColumn'], $columns);
    }

    private function setColumn($column)
    {
        if ($this->isRelationColumn($column)) {
            return $this->appendRelationColumn($column);
        }

        $this->columns[] = $column;
    }

    private function appendRelationColumn($keyAndColumn)
    {
        list($key, $column) = explode('.', $keyAndColumn);

        $this->relationColumns[$key][] = $column;
    }

    private function fixRelationColumns()
    {
        $keys = array_keys($this->relationColumns);

        $callback = [$this, 'fixRelationColumn'];

        array_map($callback, $keys, $this->relationColumns);
    }

    private function fixRelationColumn($key, $columns)
    {
        $index = array_search($key, $this->includes);

        unset($this->includes[$index]);

        $this->includes[$key] = $this->closureRelationColumns($columns);
    }

    private function closureRelationColumns($columns)
    {
        return function ($q) use ($columns) {
            $q->select($columns);
        };
    }

    private function setOrderBy($order)
    {
        $this->orderBy = [];

        $orders = array_filter(explode('|', $order));

        array_map([$this, 'appendOrderBy'], $orders);
    }

    private function appendOrderBy($order)
    {
        if ($order == 'random') {
            $this->orderBy[] = 'random';
            return;
        }

        list($column, $direction) = explode(',', $order);

        $this->orderBy[] = [
            'column' => $column,
            'direction' => $direction
        ];
    }

    private function setGroupBy($groups)
    {
        $this->groupBy = array_filter(explode(',', $groups));
    }

    private function setLimit($limit)
    {
        $limit = ($limit == 'unlimited') ? null : (int) $limit;

        $this->limit = $limit;
    }

    public function addWhere($key, $operator, $value, $restrictive = true, $unmatched = false)
    {
        if (is_string($value)) {
            if (strtolower($value) == 'true') {
                $value = 1;
            } else if (strtolower($value) == 'false') {
                $value = 0;
            }
        }

        $this->wheres[] = [
          'key' => $key,
          'operator' => $operator,
          'restrictive' => $restrictive,
          'unmatched' => $unmatched,
          'value' => $value
        ];
    }

    private function setWheres($parameters)
    {
        $this->wheres = $parameters;
    }

    private function setAppends($appends)
    {
        $this->appends = explode(',', $appends);
    }

    private function addWhereToQuery($where)
    {
        if (strpos($where['key'], '|') === 0) {
            $or = 'or';
            $where['key'] = substr($where['key'], 1);
        } else {
            $or = 'and';
        }

        extract($where);

        // For array values (whereIn, whereNotIn)
        if (isset($values)) {
            $value = $values;
        }
        if (!isset($operator)) {
            $operator = '';
        }

        /** @var mixed $key */
        if ($this->isExcludedParameter($key)) {
            return;
        }

        if ($this->hasCustomFilter($key)) {
            /** @var string $type */
            return $this->applyCustomFilter($key, $operator, $value, $type);
        }

        if (!$this->hasTableColumn($key)) {
            throw new UnknownColumnException("Unknown column '{$key}'");
        }

        /** @var string $type */
        if ($type == 'In') {
            $this->query->whereIn($key, $value, $or);
        } else if ($type == 'NotIn') {
            $this->query->whereNotIn($key, $value, $or);
        } else if ($operator == ';') {
			$this->query->orWhere($key, $value);
        } else {
            if (is_null($value)) {
                if ($operator == '=') {
                    $this->query->whereNull($key, $or);
                } else {
                    $this->query->whereNotNull($key, $or);
                }
            } else {

                $this->query->where($key, $operator, $value, $or);
            }
        }
    }

    private function setProcessedWheres($parameters)
    {
        $this->processed_wheres = $parameters;
    }

    private function applyWhere($where, $query, $model)
    {
        if (strpos($where['key'], '|') === 0) {
            $or = 'or';
            $where['key'] = substr($where['key'], 1);
        } else {
            $or = 'and';
        }


        if ($where['operator'] == 'has') {
            if ($where['value']) {
                $query->has($where['key']);
            } else {
                $query->doesntHave($where['key']);
            }
        } else {
            $column = preg_replace('/(.+)\[\]$/', '${1}', $where['key']);

            if (!$this->hasTableColumn($column, $model)) {
                if ($this->hasAppend($column, $model)) {
                    return $query;
                } else {
                    throw new UnknownColumnException("Unknown column '".$column."'");
                }
            }

            $column = $model->getTable().'.'.$column;
            if ($where['operator'] == 'in') {
                $query->where(function ($query) use ($where, $column, $or) {
                    $values = array_filter($where['value'], function ($var) {
                        return !is_null($var);
                    });

                    if (count($values) > 1) {
                        $query->whereIn($column, $values, $or);
                    } else {
                        $query->where($column, '=', $values, $or);
                    }

                    $null_key = array_search(null, $where['value']);
                    if ($null_key !== false) {
                        $query->orWhereNull($column);
                        unset($where['value'][$null_key]);
                    }
                });
            } elseif ($where['operator'] == 'not in') {
                $query->where(function ($query) use ($where, $column, $or) {
                    $values = array_filter($where['value'], function ($var) {
                        return !is_null($var);
                    });

                    if (count($values) > 1) {
                        $query->whereNotIn($column, $values, $or);
                    } else {
                        $query->where($column, '!=', $values, $or);
                    }

                    $null_key = array_search(null, $where['value']);
                    if ($null_key !== false) {
                        $query->whereNotNull($column, $or);
                        unset($where['value'][$null_key]);
                    }
                });
            } else {
                if (is_null($where['value'])) {
                    if ($where['operator'] == '!=') {
                        $query->whereNotNull($column, $or);
                    } else {
                        $query->whereNull($column, $or);
                    }
                } elseif (count(explode(',', $where['value'])) > 1) {
                    $query->where(function ($query) use ($where, $column, $or) {
                        foreach (explode(',', $where['value']) as $value) {
                            $query->orWhere($column, $where['operator'], $value);
                        }
                    });
                } elseif (is_array($where['value'])) {
                    $query->where(function ($query) use ($where, $column, $or) {
                        foreach ($where['value'] as $value) {
                            $query->orWhere($column, $where['operator'], $value);
                        }
                    });
                } else {
                    $query->where($column, $where['operator'], $where['value'], $or);
                }
            }
        }

        return $query;
    }

    private function applyNestedWheres($wheres, $query, $model, $restrictive = null, $isWith = null)
    {
        //include soft deleted items (todo: check model has soft deleted trait?)
        if (isset($wheres['include_deleted']) && $wheres['include_deleted']) {
            $query->withTrashed();
        }

        //get the current column select criteria (null means it will not be applied)
        $select = (isset($wheres['select']) && $wheres['select'] ? $wheres['select'] : null);

        //handle the nested includes / wheres
        if (isset($wheres['children'])) {
            $table = array_keys($wheres['children'])[0];

            // In case the relation is in fact an appended attribute with relation, use its relation
            $relations = $this->model->getAppendsRelations();
            if (in_array($table, array_keys($relations)) &&
                method_exists($model, $this->attributeGetterMethodName($table))
            ) {
                $through = explode(".", $relations[$table][0], 2)[0];

                if (count(explode(".", $relations[$table][0], 2)) > 1) {
                    $right = explode(".", $relations[$table][0], 2)[1];
                    $wheres['children'][$right] = $wheres['children'][$table];
                    unset($wheres['children'][$table]);
                }

                $wheres['children'] = [
                    $through => [
                        "children" => $wheres['children'],
                        "restrictive" => true
                    ]
                ];
            }

            foreach ($wheres['children'] as $table => $where_child) {
                //check relation valid
                $table = preg_replace('/(.+)\[\d+\]$/', '${1}', $table);
                $relationship = $this->getRelationship($table, $model);
                $child_model = $relationship->getRelated();

                //the following automatically adds foriegn and primary keys needed by relations when columns are in use
                $isHasOneOrMany = ($relationship instanceof HasOneOrMany);
                $isHasManyThrough = ($relationship instanceof HasManyThrough);

                if ($select && !(sizeof($select) == 1 && $select[0] == '*')) {    //ignore the select * (all)
            foreach ($select as $i => $select_item) {     //prepend table names to the column if not provided
              $select[$i] = (count(explode('.', $select_item)) == 1 ? $model->getTable().'.' : '').$select_item;
            }

                    $key = null;
                    if (!$isHasManyThrough && method_exists($relationship, 'getQualifiedParentKeyName')) {
                        $key = $relationship->getQualifiedParentKeyName();
                    } elseif ($isHasManyThrough && method_exists($relationship, 'getHasCompareKey')) {
                        $key = $relationship->getHasCompareKey();
                    } elseif (!$isHasOneOrMany && !$isHasManyThrough && method_exists($relationship, 'getForeignKey')) {
                        $key = $relationship->getForeignKey();
                    }

                    if ($key) {
                        if (!in_array('*', $select) && !in_array($key, $select)) {
                            $select[] = $key;
                        }
                    }
                }

                if (isset($where_child['select']) && $where_child['select']) {
                    $key = null;

                    //belongsTo
                    if (method_exists($relationship, 'getOtherKey')) {
                        $key = $relationship->getOtherKey();
                    } elseif (($isHasOneOrMany || $isHasManyThrough) && method_exists($relationship, 'getForeignKey')) {
                        $key = $relationship->getForeignKey();
                    }
                    if ($key) {
                        if (!in_array('*', $where_child['select']) && !in_array($key, $where_child['select'])) {
                            $where_child['select'][] = $key;
                        }
                    }
                }

                //include the relations results
                if (isset($where_child['include']) && $where_child['include']) {
                    $query->with([$table => function ($sub_query) use ($where_child, $child_model) {
                        $this->applyNestedWheres($where_child, $sub_query, $child_model, null, true);
                    }]);
                }
                //limit the parent models results if restrictive is not strict false
                if ($where_child['restrictive'] || is_null($where_child['restrictive'])) {
                    $query->whereHas($table, function ($sub_query) use ($where_child, $child_model) {
                        $this->applyNestedWheres($where_child, $sub_query, $child_model, true, false);
                    });
                }
            }
        }
        //apply the column filters
        if ($select) {
            $query->select(array_map(function ($val) use ($model) {
                //qualify with the model table if its not supplied
                return (count(explode('.', $val)) == 1 ? $model->getTable().'.' : '').$val;
            }, $select));
        }

        //apply the where clauses to the query
        if (isset($wheres['wheres'])) {
            $wheres['wheres'] = $this->determineIn($wheres['wheres']);

            foreach ($wheres['wheres'] as $where) {
                if ($model == $this->model) {	//only check on the top level for excluded params
                    if ($this->isExcludedParameter($where['key'])) {
                        continue;
                    }

                    if ($this->hasCustomFilter($where['key'])) {
                        $this->applyCustomFilter($where['key'], $where['operator'], $where['value']);
                    }
                }

                if (!($isWith && $where['unmatched']) && (is_null($restrictive) || ($restrictive && $where['restrictive']))) {
                    $query = $this->applyWhere($where, $query, $model);
                }
            }
        }

        return $query;
    }

    private function determineIn($wheres)
    {
        $in_ors = $not_in_ors = $like_ors = $others = [];
        foreach ($wheres as $i => $where) {
            if ($where['operator'] == '=' && substr($where['key'], -strlen('[]')) != '[]') {
                if (!isset($in_ors[$where['key']])) {
                    $in_ors[$where['key']] = [];
                }
                $in_ors[$where['key']][] = $where;
            } elseif ($where['operator'] == '!=') {
                if (!isset($not_in_ors[$where['key']])) {
                    $not_in_ors[$where['key']] = [];
                }
                $not_in_ors[$where['key']][] = $where;
            } elseif ($where['operator'] == 'like') {
                if (!isset($like_ors[$where['key']])) {
                    $like_ors[$where['key']] = [];
                }
                $like_ors[$where['key']][] = $where;
            } else {
                $others[] = $where;
            }
        }

        foreach ($in_ors as $key => $or) {
            if (sizeof($or) == 1) {
                $in_ors[$key] = $or[0];
            } else {
                $values = [];
                foreach ($or as $item) {
                    $values[] = $item['value'];
                }
                $in_ors[$key] = $or[0];
                $in_ors[$key]['operator'] = 'in';
                $in_ors[$key]['value'] = $values;
            }
        }

        foreach ($not_in_ors as $key => $or) {
            if (sizeof($or) == 1) {
                $not_in_ors[$key] = $or[0];
            } else {
                $values = [];
                foreach ($or as $item) {
                    $values[] = $item['value'];
                }
                $not_in_ors[$key] = $or[0];
                $not_in_ors[$key]['operator'] = 'not in';
                $not_in_ors[$key]['value'] = $values;
            }
        }

        foreach ($like_ors as $key => $or) {
            if (sizeof($or) == 1) {
                $like_ors[$key] = $or[0];
            } else {
                $values = [];
                foreach ($or as $item) {
                    $values[] = $item['value'];
                }
                $like_ors[$key] = $or[0];
                $like_ors[$key]['operator'] = 'like';
                $like_ors[$key]['value'] = $values;
            }
        }

        return array_merge(array_values($in_ors), array_values($not_in_ors), array_values($like_ors), $others);
    }

    private function addOrderByToQuery($order)
    {
        if ($order == 'random') {
            return $this->query->orderBy(DB::raw('RAND()'));
        }
        if (strpos($order['column'], ".") !== false) {
            return;
        }
        extract($order);

        /** @var string $column */
        /** @var string $direction */
        $this->query->orderBy($this->model->getTable().'.'.$column, $direction);
    }

    private function applyCustomFilter($key, $operator, $value, $type = 'Basic')
    {
        $callback = [$this, $this->customFilterName($key)];

        $this->query = call_user_func($callback, $this->query, $value, $operator, $type);
    }

    private function isRelationColumn($column)
    {
        return (count(explode('.', $column)) > 1);
    }

    private function isExcludedParameter($key)
    {
        return in_array($key, $this->excludedParameters);
    }

    private function hasWheres()
    {
        return (count($this->wheres) > 0);
    }

    private function hasIncludes()
    {
        return (count($this->includes) > 0);
    }

    private function hasAppends()
    {
        return (count($this->appends) > 0);
    }

    private function hasGroupBy()
    {
        return (count($this->groupBy) > 0);
    }

    private function hasLimit()
    {
        return ($this->limit);
    }

    private function hasOffset()
    {
        return ($this->offset != 0);
    }

    private function hasRelationColumns()
    {
        return (count($this->relationColumns) > 0);
    }

    private function getRelatedModel($tables, $model = null)
    {
        $model = (!is_null($model) ? $model : $this->model);
        while (sizeof($tables) > 0) {
            $method = array_shift($tables);
            $relationship = $this->getRelationship($method, $model);
            $model = $relationship->getRelated();
        }
        return $model;
    }

    public function getRelationship($relation, $model)
    {
        try {
            if (!method_exists($model, $relation)) {
                throw new Exception('Relationship does not exist');
            }
            $relationship = $model->$relation();
            // if (!$relationship instanceof Relation) {
        //   throw new Exception('Relationship method exists but is not an instance of Relation');
        // }
        } catch (Exception $ex) {
            throw new Exception("Unknown relation '".$relation."' on ".$this->get_class_name($model));
        }
        return $relationship;
    }

    public function getAppend($append, $model)
    {
        try {
            if (!method_exists($model, $this->attributeGetterMethodName($append))) {
                throw new Exception('Append does not exist');
            }
            $methodName = $this->attributeGetterMethodName($append);
            // if (!$appendedAttribute instanceof Relation) {
        //   throw new Exception('Append method exists but is not an instance of Relation');
        // }
        } catch (Exception $ex) {
            throw new Exception("Unknown append '".$append."' on ".$this->get_class_name($model));
        }
        return $methodName;
    }

    private function get_class_name($classname)
    {
        $classname = (is_object($classname) ? get_class($classname) : $classname);
        if ($pos = strrpos($classname, '\\')) {
            return substr($classname, $pos + 1);
        }
        return $pos;
    }

    private function hasTableColumn($column, $model = null)
    {
        $model = (!is_null($model) ? $model : $this->model);
        return (Schema::hasColumn($model->getTable(), $column));
    }

    private function hasAppend($column, $model = null)
    {
        $model = (!is_null($model) ? $model : $this->model);

        return (method_exists($model, 'get' . studly_case($column) . 'Attribute'));
    }

    private function hasCustomFilter($key)
    {
        $methodName = $this->customFilterName($key);

        return (method_exists($this, $methodName));
    }

    private function attributeGetterMethodName($key)
    {
        return 'get' . studly_case($key) . 'Attribute';
    }

    private function setterMethodName($key)
    {
        return 'set' . studly_case($key);
    }

    private function customFilterName($key)
    {
        return 'filterBy' . studly_case($key);
    }

    private function addAppendsToModel($result)
    {
        $result->map(function ($item) {
            $item->append($this->appends);
            return $item;
        });

        return $result;
    }

    private function eagerLoadAppendsRelations() {
        $relations = $this->model->getAppendsRelations();

        foreach ($this->appends as $append) {
            if (array_key_exists($append, $relations)) {
                $this->query->with($relations[$append]);
            }
        }
    }

    /**
     * Paginate the given query.
     *
     * @param  int $perPage
     * @param  array $columns
     * @param  string $pageName
     * @param  int|null $page
     * @return Paginator
     *
     * @throws \InvalidArgumentException
     */
    private function basePaginate($perPage = null, $columns = ['*'], $pageName = 'page', $page = null)
    {
        $page = $page ?: BasePaginator::resolveCurrentPage($pageName);

        $perPage = $perPage ?: $this->model->getPerPage();

        if (method_exists($this->query, 'toBase')) {
            $query = $this->query->toBase();
        } else {
            $query = $this->query->getQuery();
        }

        $total = $query->getCountForPagination();

        $results = $total ? $this->query->forPage($page, $perPage)->get($columns) : new Collection;

        return (new Paginator($results, $total, $perPage, $page, [
            'path' => BasePaginator::resolveCurrentPath(),
            'pageName' => $pageName,
        ]))->setQueryUri($this->uriParser->getQueryUri());
    }
}
