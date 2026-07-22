<?php

namespace App\Http\Controllers;

use App\Http\Requests\CategoryRequest;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', Category::class);

        return Inertia::render('Categories/Index', [
            'categories' => Category::query()->orderBy('name')->get(),
            'canManage' => auth()->user()->isOwner(),
        ]);
    }

    public function store(CategoryRequest $request): RedirectResponse
    {
        $this->authorize('create', Category::class);

        Category::create([
            ...$request->validated(),
            'account_id' => $request->user()->account_id,
        ]);

        return back()->with('success', 'Categoria criada com sucesso.');
    }

    public function update(CategoryRequest $request, Category $category): RedirectResponse
    {
        $this->authorize('update', $category);

        $category->update($request->validated());

        return back()->with('success', 'Categoria atualizada com sucesso.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        $this->authorize('delete', $category);

        if ($category->transactions()->exists()) {
            return back()->with('error', 'Não é possível excluir uma categoria com transações.');
        }

        $category->delete();

        return back()->with('success', 'Categoria excluída com sucesso.');
    }
}
