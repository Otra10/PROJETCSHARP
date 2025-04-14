<?php

namespace App\Http\Controllers;

use App\Models\Grade;
use Illuminate\Http\Request;

class GradeController extends Controller
{
    // Affiche la liste des grades
    public function index()
    {
        $grades = Grade::all();
        return response()->json($grades);
    }

    // Stocke un nouveau grade
    public function store(Request $request)
    {
        $data = $request->validate([
            'nom' => 'required|string|max:255',
        ]);

        $grade = Grade::create($data);
        return response()->json($grade, 201);
    }

    // Affiche un grade précis
    public function show($id)
    {
        $grade = Grade::findOrFail($id);
        return response()->json($grade);
    }

    // Met à jour un grade existant
    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'nom' => 'required|string|max:255',
        ]);

        $grade = Grade::findOrFail($id);
        $grade->update($data);
        return response()->json($grade);
    }

    // Supprime un grade
    public function destroy($id)
    {
        $grade = Grade::findOrFail($id);
        $grade->delete();
        return response()->json(null, 204);
    }
}