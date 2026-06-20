<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Label;
use Illuminate\Http\Request;

class LabelController extends Controller
{
    public function index()
    {
        return Label::orderBy('name')->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:50|unique:labels',
            'color' => 'required|string|max:20',
        ]);

        $label = Label::create($validated);

        return response()->json($label, 201);
    }

    public function update(Request $request, Label $label)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:50|unique:labels,name,'.$label->id,
            'color' => 'required|string|max:20',
        ]);

        $label->update($validated);

        return response()->json($label);
    }

    public function destroy(Label $label)
    {
        $label->delete();

        return response()->noContent();
    }
}
