<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Core\KnowledgeBase\Models\KbArticle;
use Core\KnowledgeBase\Services\KnowledgeBaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

class KbArticleStatusController extends Controller
{
    public function __construct(
        private readonly KnowledgeBaseService $articles,
    ) {
    }

    public function publish(KbArticle $kbArticle): RedirectResponse
    {
        return $this->runTransition($kbArticle, 'publish', __('Article published successfully.'));
    }

    public function unpublish(KbArticle $kbArticle): RedirectResponse
    {
        return $this->runTransition($kbArticle, 'unpublish', __('Article unpublished successfully.'));
    }

    public function archive(KbArticle $kbArticle): RedirectResponse
    {
        return $this->runTransition($kbArticle, 'archive', __('Article archived successfully.'));
    }

    private function runTransition(KbArticle $kbArticle, string $action, string $successMessage): RedirectResponse
    {
        Gate::authorize('update', $kbArticle);

        try {
            $this->articles->{$action}($kbArticle);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['status' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.kb-articles.show', $kbArticle)
            ->with('status', $successMessage);
    }
}
