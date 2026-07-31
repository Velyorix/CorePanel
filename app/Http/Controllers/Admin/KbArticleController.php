<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndexKbArticleRequest;
use App\Http\Requests\Admin\StoreKbArticleRequest;
use App\Http\Requests\Admin\UpdateKbArticleRequest;
use Core\KnowledgeBase\Enums\KbArticleStatus;
use Core\KnowledgeBase\Models\KbArticle;
use Core\KnowledgeBase\Services\KbCategoryService;
use Core\KnowledgeBase\Services\KnowledgeBaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use InvalidArgumentException;
use RuntimeException;

class KbArticleController extends Controller
{
    public function __construct(
        private readonly KnowledgeBaseService $articles,
        private readonly KbCategoryService $categories,
    ) {
    }

    public function index(IndexKbArticleRequest $request): View
    {
        $filters = $request->filters();

        return view('admin.kb-articles.index', [
            'articles' => $this->articles->paginateForAdmin($filters),
            'filters' => $filters,
            'statuses' => KbArticleStatus::cases(),
            'categories' => $this->categories->activeCategories(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', KbArticle::class);

        return view('admin.kb-articles.create', $this->formData());
    }

    public function store(StoreKbArticleRequest $request): RedirectResponse
    {
        try {
            $article = $this->articles->create($request->articleData(), $request->user());
        } catch (InvalidArgumentException $exception) {
            return back()->withInput()->withErrors(['article' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.kb-articles.show', $article)
            ->with('status', __('Article created successfully.'));
    }

    public function show(KbArticle $kbArticle): View
    {
        Gate::authorize('view', $kbArticle);

        $kbArticle->load(['category', 'author']);

        return view('admin.kb-articles.show', [
            'article' => $kbArticle,
        ]);
    }

    public function edit(KbArticle $kbArticle): View
    {
        Gate::authorize('update', $kbArticle);

        return view('admin.kb-articles.edit', array_merge($this->formData(), [
            'article' => $kbArticle,
        ]));
    }

    public function update(UpdateKbArticleRequest $request, KbArticle $kbArticle): RedirectResponse
    {
        try {
            $article = $this->articles->update($kbArticle, $request->articleData());
        } catch (InvalidArgumentException|RuntimeException $exception) {
            return back()->withInput()->withErrors(['article' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.kb-articles.show', $article)
            ->with('status', __('Article updated successfully.'));
    }

    public function destroy(KbArticle $kbArticle): RedirectResponse
    {
        Gate::authorize('delete', $kbArticle);

        $this->articles->delete($kbArticle);

        return redirect()
            ->route('admin.kb-articles.index')
            ->with('status', __('Article deleted successfully.'));
    }

    /**
     * @return array{categories: \Illuminate\Database\Eloquent\Collection<int, \Core\KnowledgeBase\Models\KbCategory>}
     */
    private function formData(): array
    {
        return [
            'categories' => $this->categories->activeCategories(),
        ];
    }
}
