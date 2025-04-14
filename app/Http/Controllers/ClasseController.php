<?php

namespace App\Http\Controllers;

use App\Models\Classe;
use Illuminate\Http\Request;

class ClasseController extends Controller
{
    public function index()
    {
        $classes = Classe::all();
        return response()->json($classes);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nom' => 'required|string|max:255',
            'batiment_id' => 'required|exists:batiments,id',
        ]);

        $classe = Classe::create($data);
        return response()->json($classe, 201);
    }

    public function show($id)
    {
        $classe = Classe::findOrFail($id);
        return response()->json($classe);
    }

    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'nom' => 'required|string|max:255',
            'batiment_id' => 'required|exists:batiments,id',
        ]);

        $classe = Classe::findOrFail($id);
        $classe->update($data);
        return response()->json($classe);
    }

    public function destroy($id)
    {
        $classe = Classe::findOrFail($id);
        $classe->delete();
        return response()->json(null, 204);
    }
}