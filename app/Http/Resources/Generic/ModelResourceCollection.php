<?php

namespace App\Http\Resources;

use App\Http\Resources\ModelResource;
use Illuminate\Http\Resources\Json\ResourceCollection;

class ModelResourceCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        $collection = $this->collection;

        if ($request->has('appends')) {
            foreach (explode(',', $request->appends) as $modelAppend) {
                if (isset($request->$modelAppend)) {
                    $value = $request->$modelAppend;

                    if (is_string($value)) {
                        if (strtolower($value) == 'true') {
                            $value = 1;
                        } else if (strtolower($value) == 'false') {
                            $value = 0;
                        }
                    }

                    $collection = $collection->where($modelAppend, $value)->flatten();
                }
            }
        }

        return ModelResource::collection($collection);
    }

    public function with($request)
    {
        return [
            'links' => [
                // 'self' => url($this->first()->getTable()),
            ],
            'success' => true,
            'status' => 200
        ];
    }
}
