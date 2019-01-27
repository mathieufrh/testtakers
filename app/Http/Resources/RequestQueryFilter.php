<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class RequestQueryFilter
{
    /**
     * A collection of resources to be filtered.
     *
     * @var  Collection  $resources
     */
    protected $resources;

    /**
     * The HTTP GET request used to filter the collection.
     *
     * @var  Request  $request
     */
    protected $request;

    /**
     * Filter a resource collection based on the request.
     *
     * @param  mix  $resources
     * @param  \Illuminate\Http\Request  $request
     * @return Collection;
     */
    public function attach($resources, Request $request = null)
    {
        $this->resources = $resources;
        $this->request = $request ?? request();

        $this->filterByAttribute();
        $this->offset();
        $this->limit();
        $this->paginate();

        return $this->resources;
    }

    /**
     * Filter the collection by its attributes.
     *
     * @return void;
     */
    protected function filterByAttribute()
    {
        if ($this->resources->count() <= 1) {
            return;
        }

        $attributes = array_keys($this->resources->first()->getAttributes());

        foreach ($attributes as $attribute) {
            if ($this->request->has($attribute)) {
                $this->filterAttributeMatch($attribute);
            }
            if ($this->request->has($attribute . '!')) {
                $this->filterAttributeDontMatches($attribute);
            }
            if ($this->request->has($attribute . '^')) {
                $this->filterAttributeStartsWith($attribute);
            }
            if ($this->request->has($attribute . '$')) {
                $this->filterAttributeEndsWith($attribute);
            }
            if ($this->request->has($attribute . '~')) {
                $this->filterAttributeLike($attribute);
            }
        }
    }

    /**
     * Filter the collection to keep only resources where attribute matches the given attribute.
     *
     * example : /testtakers?firstname=susan
     *
     * @param  string $attribute;
     * @return void;
     */
    protected function filterAttributeMatches(string $attribute)
    {
        $this->resources = $this->resources->where($attribute, $this->request->get($attribute));
    }

    /**
     * Filter the collection to keep only resources where attribute does not matche the given attribute.
     *
     * example : /testtakers?firstname!=susan
     *
     * @param  string $attribute;
     * @return void;
     */
    protected function filterAttributeDontMatches(string $attribute)
    {
        $this->resources = $this->resources->where($attribute, '!=', $this->request->get($attribute . '!'));
    }

    /**
     * Filter the collection to keep only resources where attribute starts with the given attribute.
     *
     * example : /testtakers?firstname^=s
     *
     * @param  string $attribute;
     * @return void;
     */
    protected function filterAttributeStartsWith(string $attribute)
    {
        $this->resources = $this->resources->reject(function ($item, $key) use ($attribute) {
            return !startsWith((string)$item->$attribute, (string)$this->request->get($attribute . '^'));
        });
    }

    /**
     * Filter the collection to keep only resources where attribute ends with the given attribute.
     *
     * example : /testtakers?firstname$=s
     *
     * @param  string $attribute;
     * @return void;
     */
    protected function filterAttributeEndsWith(string $attribute)
    {
        $this->resources = $this->resources->reject(function ($item, $key) use ($attribute) {
            return !endsWith((string)$item->$attribute, (string)$this->request->get($attribute . '$'));
        });
    }

    /**
     * Filter the collection to keep only resources where attribute is like the given attribute.
     *
     * example : /testtakers?firstname~=%om%
     *
     * @param  string $attribute;
     * @return void;
     */
    protected function filterAttributeLike(string $attribute)
    {
        $this->resources = $this->resources->where($attribute, 'LIKE', $this->request->get($attribute . '~'));
    }

    /**
     * Add an offset to the collection.
     *
     * example : /testtakers?offset=5
     *
     * @return void;
     */
    protected function offset()
    {
        if ($this->request->has('offset')) {
            $this->resources = $this->resources->slice($this->request->get('offset'));
        }
    }

    /**
     * Limit the number of resources to the `n` first records.
     *
     * example : /testtakers?limit=60
     *
     * @return void;
     */
    protected function limit()
    {
        if ($this->request->has('limit')) {
            $this->resources = $this->resources->take($this->request->get('limit'));
        }
    }

    /**
     * Paginate the collection using the number of resources on a page and the current page number.
     *
     * example : /testtakers?perPag=5&page=2
     *
     * @return void;
     */
    protected function paginate()
    {
        if ($this->request->has('page') && $this->request->has('perPage')) {
            $this->resources = $this->resources->forPage($this->request->get('page'), $this->request->get('perPage'));
        }
    }
}
