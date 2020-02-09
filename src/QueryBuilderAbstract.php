<?php

declare(strict_types=1);

namespace LaravelRequestToEloquent;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use LaravelJsonApiQueryParams\Fields;
use LaravelJsonApiQueryParams\QueryParamBag;
use LaravelJsonApiQueryParams\Sorts;

abstract class QueryBuilderAbstract
{
    /** @var Request $request */
    private $request;

    /** @var Fields $fields */
    protected $fields;

    /** @var QueryParamBag $includes */
    protected $includes;

    /** @var QueryParamBag $filters */
    protected $filters;

    /** @var Sorts $sorts */
    protected $sorts;

    /** @var EloquentBuilder|QueryBuilder $request */
    private $query;

    private $allowedIncludes = [];

    public function __construct(Request $request)
    {
        $this->request = $request;
        $this->fields = $request->fields();
        $this->includes = $request->includes();
        $this->filters = $request->filters();
        $this->sorts = $request->sorts();
        $this->query = $this->init();
    }

    /**
     * Initialise the query.
     *
     * @return EloquentBuilder|QueryBuilder
     */
    abstract protected function init();

    protected function includesMap(): array
    {
        return [];
    }

    protected function filtersMap(): array
    {
        return [];
    }

    public function parseAllowedIncludes(array $allowedIncludes): self
    {
        foreach ($allowedIncludes as $include) {
            $relation = null;
            foreach (explode('.', $include) as $part) {
                $relation .= is_null($relation) ? $part : ".{$part}";
                array_push($this->allowedIncludes, $relation);
            }
        }

        return $this;
    }

    /**
     * Build and get query.
     *
     * @return EloquentBuilder|QueryBuilder
     */
    public function query()
    {
        if (! empty($this->allowedIncludes) && $this->request->filled(config('query-params.include'))) {
            $this->loadIncludes($this->includes);
        }

        if ($this->request->filled(config('query-params.filter'))) {
            $this->applyFilters($this->filters);
        }

        return $this->query;
    }

    /**
     * Execute the query.
     *
     * @return Collection
     */
    public function get()
    {
        return $this->query()->get();
    }

    /**
     * Execute the query and get the first result.
     *
     * @return Model|object|null
     */
    public function first()
    {
        return $this->query()
            ->take(1)
            ->get()
            ->first();
    }

    private function loadIncludes(QueryParamBag $includes)
    {
        $includes->each(function ($params, $relation) {
            if ($this->isAllowedToInclude($relation)) {
                $this->loadRelation($relation, $params);
            }
        });
    }

    private function isAllowedToInclude($relation): bool
    {
        return in_array($relation, $this->allowedIncludes);
    }

    private function loadRelation($relation, $params): void
    {
        if ($relationAlias = Arr::get($this->includesMap(), $relation)) {
            $this->query->with($relationAlias);

            return;
        }

        $methodName = 'include' . Str::studly(str_replace('.', 'With', $relation));
        if (method_exists($this, $methodName)) {
            $this->{$methodName}($this->query, $params);

            return;
        }

        $this->query->with($relation);
    }

    private function applyFilters(QueryParamBag $filters)
    {
        $filters->each(function ($params, $scope) {
            $this->applyFilter($scope, $params);
        });
    }

    private function applyFilter($scope, $params)
    {
        if ($filterAlias = Arr::get($this->filtersMap(), $scope)) {
            $this->query->{$filterAlias}($params);

            return;
        }

        $methodName = 'filter' . Str::studly($scope);

        if (method_exists($this, $methodName)) {
            $this->{$methodName}($this->query, $params);

            return;
        }

        $this->query->{$scope}($params);
    }
}