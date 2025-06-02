<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    /**
     * Redirige l'utilisateur vers Google pour l'authentification.
     */
//     public function redirectToGoogle(): RedirectResponse
//     {
//         return Socialite::driver('google')->stateless()->redirect();
//     }

//     public function handleGoogleCallback()
// {
//     $user = Socialite::driver('google')->stateless()->user();

//     $existingUser = User::where('google_id', $user->id)->first();
//     if ($existingUser) {
//         auth()->login($existingUser, true);
//         $token = $existingUser->createToken('auth_token')->plainTextToken;
//     } else {
//         $newUser = new User();
//         $newUser->name = $user->name;
//         $newUser->email = $user->email;
//         $newUser->google_id = $user->id;
//         $newUser->password = bcrypt(Str::random()); // Génère un mot de passe aléatoire
//         $newUser->save();
//         auth()->login($newUser, true);
//         $token = $newUser->createToken('auth_token')->plainTextToken;
//     }
    
    
//     // Générez un token pour l'utilisateur
//     // $token = auth()->user()->createToken('auth_token')->plainTextToken;

//     // Redirigez vers le front-end avec le token
//     return redirect()->to(env('BACKOFFICE_URL') . '/?token=' . $token);
// }


    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['error' => 'Identifiant ou mot de passe incorrect.'], 401);
        }

        // Crée un token pour l'utilisateur
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user
        ]);
    }

    /**
     * Fonction de déconnexion.
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Déconnexion réussie.']);
    }

    /**
     * Récupère l'utilisateur authentifié.
     */
    public function me(Request $request)
    {
        return response()->json($request->user());
    }

    /**
     * Fonction d'inscription traditionnelle.
     */
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
        ]);

        return response()->json(['message' => 'Utilisateur créé avec succès.', 'user' => $user], 201);
    }
}