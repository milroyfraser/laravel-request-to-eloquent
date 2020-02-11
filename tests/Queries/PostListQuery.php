<?php

namespace LaravelRequestToEloquent\Queries;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use LaravelRequestToEloquent\Dummy\Post;
use LaravelRequestToEloquent\QueryBuilderAbstract;

class PostListQuery extends QueryBuilderAbstract
{
    /**
     * @inheritDoc
     */
    protected function init()
    {
        return Post::query();
    }

    // includes

    protected $availableIncludes = [
        'comments',
        'comments.user',
    ];

    protected function includesMap(): array
    {
        return [
            'subjects' => 'tags',
        ];
    }

    public function includeFeedback($query)
    {
        $query->with('comments');
    }

    public function includeFeedbackWithSubmittedBy($query)
    {
        $query->with('comments.user');
    }

    // filters

    protected $availableFilters = [
        'draft',
    ];

    protected function filtersMap(): array
    {
        return [
            'non_published' => 'draft',
        ];
    }

    public function filterByPublishedBefore($query, $params)
    {
        $query->publishedBefore($params);
    }

    // sorts

    protected $availableSorts = [
        'published_at',
    ];

    protected function sortsMap(): array
    {
        return [
            'published_day' => 'published_at',
        ];
    }

    public function sortByCommentsCount(Builder $query, $direction)
    {
        $query->withCount('comments')->orderBy('comments_count', $direction);
    }
}
