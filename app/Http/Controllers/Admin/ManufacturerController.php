<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreManufacturerRequest;
use App\Models\Manufacturer;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ManufacturerController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Manufacturers/Index', [
            'manufacturers' => Manufacturer::latest()->get(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Manufacturers/Create');
    }

    public function store(StoreManufacturerRequest $request): RedirectResponse
    {
        $manufacturer = Manufacturer::create($request->validated());

        return redirect()->route('admin.manufacturers.edit', $manufacturer)
            ->with('success', 'Hersteller erstellt.');
    }

    public function edit(Manufacturer $manufacturer): Response
    {
        return Inertia::render('Admin/Manufacturers/Edit', [
            'manufacturer' => $manufacturer,
        ]);
    }

    public function update(StoreManufacturerRequest $request, Manufacturer $manufacturer): RedirectResponse
    {
        $manufacturer->update($request->validated());

        return redirect()->route('admin.manufacturers.index')
            ->with('success', 'Hersteller aktualisiert.');
    }

    public function destroy(Manufacturer $manufacturer): RedirectResponse
    {
        if ($manufacturer->products()->count() > 0) {
            return back()->with('error', 'Hersteller kann nicht gelöscht werden, da noch Produkte zugeordnet sind.');
        }

        $manufacturer->delete();

        return redirect()->route('admin.manufacturers.index')
            ->with('success', 'Hersteller gelöscht.');
    }
}
