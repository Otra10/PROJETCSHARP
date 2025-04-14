<?php

namespace App\Http\Controllers;

use App\Models\Etudiant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class EtudiantController extends Controller
{

    public function getAuthenticatedEtudiant()
    {
        // Récupérer l'ID de l'utilisateur authentifié
        $userId = Auth::id();

        // Trouver l'étudiant correspondant à cet ID utilisateur avec ses relations
        $etudiant = Etudiant::with(['grade', 'classe.batiment'])
            ->where('user_id', $userId)
            ->first();

        if ($etudiant) {
            return response()->json($etudiant);
        } else {
            return response()->json(['message' => 'Étudiant non trouvé.'], 404);
        }
    }

    public function index()
    {
        $userId = Auth::id();

        // Trouver l'étudiant correspondant à cet ID utilisateur avec ses relations
        $etudiant = Etudiant::with(['grade', 'classe.batiment'])
            ->where('user_id', $userId)
            ->first();

        if ($etudiant) {
            // Affecter l'URL complète à l'attribut image
            $etudiant->image = asset($etudiant->image);
            return response()->json($etudiant);
        } else {
            return response()->json(['message' => 'Étudiant non trouvé.'], 404);
        }
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nom'       => 'required|string|max:255',
            'prenom'    => 'required|string|max:255',
            'matricule' => 'required|string|unique:etudiants,matricule',
            'grade_id'  => 'required|exists:grades,id',
            'classe_id' => 'required|exists:classes,id',
            'user_id' => 'required|exists:users,id',
            'date_naissance' => 'required',
            'image' => 'required',
        ]);

        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $path = $file->store('media/etudiantImage', 'public'); // Stocker dans 'storage/app/public/media/productImage'
            $data['image'] = 'storage/' . $path; // Générer l'URL relative correcte.
        } else {
            return response()->json(['error' => 'Image file is required.'], 400);
        }
        $etudiant = Etudiant::create($data);
        return response()->json($etudiant, 201);
    }

    public function show($id)
    {
        $etudiant = Etudiant::findOrFail($id);
        return response()->json($etudiant);
    }

    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'nom'       => 'required|string|max:255',
            'prenom'    => 'required|string|max:255',
            'matricule' => 'required|string|unique:etudiants,matricule,' . $id,
            'grade_id'  => 'required|exists:grades,id',
            'classe_id' => 'required|exists:classes,id',
        ]);

        $etudiant = Etudiant::findOrFail($id);
        $etudiant->update($data);
        return response()->json($etudiant);
    }

    public function destroy($id)
    {
        $etudiant = Etudiant::findOrFail($id);
        $etudiant->delete();
        return response()->json(null, 204);
    }
}