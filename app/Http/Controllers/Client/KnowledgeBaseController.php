<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\IndexClientKnowledgeBaseRequest;
use Core\KnowledgeBase\Services\KbCategoryService;
use Core\KnowledgeBase\Services\KnowledgeBaseService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class KnowledgeBaseController extends Controller
{
    public function __construct(
        private readonly KnowledgeBaseService $articles,
        private readonly KbCategoryService $categories,
    ) {
    }

    public function index(IndexClientKnowledgeBaseRequest $request): View
    {
        $filters = $request->filters();

        return view('client.kb.index', [
            'articles' => $this->articles->searchPublished($filters),
            'filters' => $filters,
            'categories' => $this->categories->activeCategories(),
        ]);
    }

    public function show(Request $request, string $slug): View
    {
        abort_unless($request->user()?->can('client.kb.view') ?? false, 403);

        $article = $this->articles->findPublishedBySlug($slug);

        abort_if($article === null, 404);

        return view('client.kb.show', [
            'article' => $article,
        ]);
    }
}
