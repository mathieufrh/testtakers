<?php

namespace App\Http\Resources;

use App;
use Illuminate\Http\Resources\Json\Resource;

class ModelResource extends Resource
{
    /**
     * Transform the resource into an array.
     *
     * The model attributes are directly added to the returned array.
     * The model relations are added only if they're already loaded in order to preserve performances.
     * The model appended attributes, which must be computed, are added only if requested.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        if ($this->resource == null) {
            return [];
        }

        return array_merge(
            $this->modelAttributesToArray($request),
            $this->modelRelationsToArray($request),
            $this->modelAppendAttributesToArray($request)
        );
    }

    /**
     * Transform the resource model attributes into an array.
     *
     * Here we check whether the attribute has been requested (i.e.: exists in the fields query parameter).
     * If it has been requested (or no fields query parameter is defined) we associate its value with its name and add this association in the
     * returned array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function modelAttributesToArray($request)
    {
        $arr = [];

        if (isset($request->fields)) {
            if (gettype($request->fields) == 'string') {
                $fields = explode(',', $request->fields);
            } else {
                $fields = array_flatten($request->fields);
            }
        } else {
            $fields = $this->getVisible();
            $fields = empty($fields) ? array_keys($this->getAttributes()) : $fields;
            $fields = array_merge($fields, $this->getIncludes());
        }

        foreach ($fields as $modelAttribute) {
            $arr[$modelAttribute] = $this[$modelAttribute];
        }

        return $arr;
    }

    /**
     * Transform the resource model relations attributes into an array.
     *
     * Here we check whether the relation has been requested (i.e.: exists in the include query parameter).
     * If it has been requested we associate the relation's resource collection with its name and add this association in the returned array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function modelRelationsToArray($request)
    {
        if ($request->includes === null) {
            return [];
        }

        $arr = [];

        $includesQuery = explode(',', $request->includes);

        /**
         * Here we add the intermediate nested relations.
         */
        foreach ($includesQuery as $modelRelation) {
            $nestedRelations = explode('.', $modelRelation);

            if (count($nestedRelations) > 1) {

                $includesQuery[] = $nestedRelations[0];

                foreach ($nestedRelations as $i => $relation) {
                    if ($i < 1 or $i == count($nestedRelations) - 1) {
                        continue;
                    }

                    $includesQuery[] = end($includesQuery) . '.' . $relation;
                }
            }
        }
        $includesQuery = array_unique($includesQuery);

        foreach ($includesQuery as $modelRelation) {
            $className = 'App\\Http\\Resources\\' . studly_case(str_singular($modelRelation)) . 'Resource';

            if (str_plural($modelRelation) == $modelRelation) {
                if (class_exists($className)) {
                    $arr[$modelRelation] = $className::collection($this->whenLoaded($modelRelation));
                } else {
                    $arr[$modelRelation] = Resource::collection($this->whenLoaded($modelRelation));
                }
            } else {
                if (class_exists($className)) {
                    $arr[$modelRelation] = new $className($this->whenLoaded($modelRelation));
                } else {
                    $arr[$modelRelation] = new Resource($this->whenLoaded($modelRelation));
                }
            }
        }

        return $arr;
    }

    /**
     * Transform the resource model appends attributes into an array.
     *
     * Here we check whether the appended attribute has been requested (i.e.: exists in the append query parameter).
     * If it has been requested we associate the appended attribute with its name and add this association in the
     * returned array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function modelAppendAttributesToArray($request)
    {
        if ($request->appends === null) {
            return [];
        }

        $arr = [];
        $appendsQuery = explode(',', $request->appends);

        foreach ($appendsQuery as $modelAppend) {
            $split = explode('.', $modelAppend);

            if (count($split) > 1) {
                $arr[$modelAppend] = $this[$split[0]];

                array_shift($split);

                foreach($split as $i => $key){
                    $arr[$modelAppend] = $arr[$modelAppend][$key];
                }
            } else {
                $arr[$modelAppend] = $this[$modelAppend];
            }
        }

        if (array_keys($arr) == [""]) {
            return [];
        }

        return $arr;
    }

    /**
     * Get additional data that should be returned with the resource array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function with($request)
    {
        return [
            'links' => [
                'self' => method_exists($this, 'getTable') ? url($this->getTable() . '/' . $this->id . '/edit') : '',
            ],
            'success' => true,
            'status' => 200
        ];
    }
}
