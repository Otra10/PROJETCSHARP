<?php

namespace App\Http\Controllers;

use App\Models\Batiment;
use Illuminate\Http\Request;

class BatimentController extends Controller
{
    public function index()
    {
        $batiments = Batiment::all();
        return response()->json($batiments);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nom' => 'required|string|max:255',
        ]);

        $batiment = Batiment::create($data);
        return response()->json($batiment, 201);
    }

    public function show($id)
    {
        $batiment = Batiment::findOrFail($id);
        return response()->json($batiment);
    }

    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'nom' => 'required|string|max:255',
        ]);

        $batiment = Batiment::findOrFail($id);
        $batiment->update($data);
        return response()->json($batiment);
    }

    public function destroy($id)
    {
        $batiment = Batiment::findOrFail($id);
        $batiment->delete();
        return response()->json(null, 204);
    }
}