<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndexKbCategoryRequest;
use App\Http\Requests\Admin\StoreKbCategoryRequest;
use App\Http\Requests\Admin\UpdateKbCategoryRequest;
use Core\KnowledgeBase\Enums\KbCategoryStatus;
use Core\KnowledgeBase\Models\KbCategory;
use Core\KnowledgeBase\Services\KbCategoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use InvalidArgumentException;

class KbCategoryController extends Controller
{
    public function __construct(
        private readonly KbCategoryService $categories,
    ) {
    }

    public function index(IndexKbCategoryRequest $request): View
    {
        $filters = $request->filters();

        return view('admin.kb-categories.index', [
            'categories' => $this->categories->paginateForAdmin($filters),
            'filters' => $filters,
            'statuses' => KbCategoryStatus::cases(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', KbCategory::class);

        return view('admin.kb-categories.create', [
            'statuses' => KbCategoryStatus::cases(),
        ]);
    }

    public function store(StoreKbCategoryRequest $request): RedirectResponse
    {
        try {
            $category = $this->categories->create($request->categoryData());
        } catch (InvalidArgumentException $exception) {
            return back()->withInput()->withErrors(['category' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.kb-categories.index')
            ->with('status', __('Category created successfully.'));
    }

    public function edit(KbCategory $kbCategory): View
    {
        Gate::authorize('update', $kbCategory);

        return view('admin.kb-categories.edit', [
            'category' => $kbCategory,
            'statuses' => KbCategoryStatus::cases(),
        ]);
    }

    public function update(UpdateKbCategoryRequest $request, KbCategory $kbCategory): RedirectResponse
    {
        try {
            $this->categories->update($kbCategory, $request->categoryData());
        } catch (InvalidArgumentException $exception) {
            return back()->withInput()->withErrors(['category' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.kb-categories.index')
            ->with('status', __('Category updated successfully.'));
    }

    public function destroy(KbCategory $kbCategory): RedirectResponse
    {
        Gate::authorize('delete', $kbCategory);

        try {
            $this->categories->delete($kbCategory);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['category' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.kb-categories.index')
            ->with('status', __('Category deleted successfully.'));
    }
}
