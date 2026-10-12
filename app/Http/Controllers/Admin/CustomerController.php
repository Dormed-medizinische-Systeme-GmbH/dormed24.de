<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));

        return Inertia::render('Admin/Customers/Index', [
            'customers' => Customer::query()
                ->when($search !== '', function ($query) use ($search): void {
                    $query->where(function ($query) use ($search): void {
                        $query
                            ->whereLike('name', "%{$search}%")
                            ->orWhereLike('email', "%{$search}%");
                    });
                })
                ->latest()
                ->get(),
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    public function show(Customer $customer): Response
    {
        $customer->load(['addresses', 'orders' => fn ($q) => $q->with('items')->latest()->limit(10)]);

        return Inertia::render('Admin/Customers/Show', [
            'customer' => $customer,
        ]);
    }
}
