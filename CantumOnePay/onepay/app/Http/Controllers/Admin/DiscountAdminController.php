<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\DiscountRequest;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Discount;
use App\Models\Item;
use App\Services\DiscountAdminService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DiscountAdminController extends Controller
{
    public function __construct(private readonly DiscountAdminService $discounts)
    {
    }

    public function index(Request $request): View
    {
        return view('admin.discounts.index', [
            'brand' => Brand::current(),
            'discounts' => $this->discounts->listFor($request->user()),
        ]);
    }

    public function create(Request $request): View
    {
        $this->discounts->listFor($request->user()); // memastikan role boleh masuk
        return $this->form($request, new Discount(['type' => Discount::TYPE_PERCENT, 'scope' => Discount::SCOPE_ORDER, 'all_branches' => $request->user()->role->canManageSettings(), 'is_active' => true]));
    }

    public function store(DiscountRequest $request): RedirectResponse
    {
        $discount = $this->discounts->save($request->user(), null, $request->discountData());
        return redirect()->route('admin.discounts')->with('status', "Promo \"{$discount->name}\" dibuat.");
    }

    public function edit(Request $request, Discount $discount): View
    {
        $this->discounts->authorizeManage($request->user(), $discount);
        return $this->form($request, $discount->load('branches', 'targets'));
    }

    public function update(DiscountRequest $request, Discount $discount): RedirectResponse
    {
        $saved = $this->discounts->save($request->user(), $discount, $request->discountData());
        return redirect()->route('admin.discounts')->with('status', "Promo \"{$saved->name}\" disimpan.");
    }

    public function toggle(Request $request, Discount $discount): RedirectResponse
    {
        $toggled = $this->discounts->toggle($request->user(), $discount);
        return back()->with('status', 'Promo ' . ($toggled->is_active ? 'diaktifkan.' : 'dinonaktifkan.'));
    }

    public function destroy(Request $request, Discount $discount): RedirectResponse
    {
        $result = $this->discounts->delete($request->user(), $discount);
        return redirect()->route('admin.discounts')->with('status', $result === 'deleted' ? 'Promo dihapus.' : 'Promo sudah pernah dipakai, jadi hanya dinonaktifkan.');
    }

    private function form(Request $request, Discount $discount): View
    {
        return view('admin.discounts.form', [
            'brand' => Brand::current(),
            'discount' => $discount,
            'branches' => $this->discounts->branchesFor($request->user()),
            'items' => Item::query()->with('category')->orderBy('category_id')->orderBy('name')->get(),
            'categories' => Category::query()->orderBy('sort_order')->orderBy('name')->get(),
            'isOwner' => $request->user()->role->canManageSettings(),
        ]);
    }
}
