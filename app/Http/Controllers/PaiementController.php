<?php

namespace App\Http\Controllers;

use App\Models\Etudiant;
use App\Models\Paiement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class PaiementController extends Controller
{
    // Constantes pour les montants requis
    const MONTANT_INSCRIPTION = 112500;
    const MONTANT_SCOLARITE = 90000;
    const MONTANT_MENSUEL_NORMAL = 90000; // Mois normaux (Nov, Dec, Jan, Avr, Mai, Juin)
    const MONTANT_MENSUEL_AVEC_INSCRIPTION = 202500; // Mois avec inscription (Sep, Oct, Fev, Mar)

    // Définition des seuils de paiement cumulatifs par mois
    const SEUILS_PAIEMENT = [
        9 => 202500,    // Septembre: 112500 (inscription) + 90000 (scolarité)
        10 => 405000,   // Octobre: 202500 (Sep) + 202500 (Oct)
        11 => 495000,   // Novembre: 405000 (Sep+Oct) + 90000 (Nov)
        12 => 585000,   // Décembre: 495000 (Sep+Oct+Nov) + 90000 (Dec)
        1 => 675000,    // Janvier: 585000 (Sep+Oct+Nov+Dec) + 90000 (Jan)
        2 => 877500,    // Février: 675000 (Sep à Jan) + 202500 (Fev)
        3 => 1080000,   // Mars: 877500 (Sep à Fev) + 202500 (Mar)
        4 => 1170000,   // Avril: 1080000 (Sep à Mar) + 90000 (Avr)
        5 => 1260000,   // Mai: 1170000 (Sep à Avr) + 90000 (Mai)
        6 => 1350000,   // Juin: 1260000 (Sep à Mai) + 90000 (Juin)
    ];

    public function index()
    {
        $user = Auth::user();
        $etudiant = Etudiant::where('user_id', $user->id)->first();

        if (!$etudiant) {
            return response()->json(['message' => 'Étudiant non trouvé.'], 404);
        }

        $paiements = Paiement::where('etudiant_id', $etudiant->id)->get();
        $statut = $this->getStatutPaiement($etudiant->id);

        return response()->json([
            'paiements' => $paiements,
            'statut_paiement' => $statut
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'methode' => 'required|string|max:255',
            'montant' => 'required|numeric|min:0',
            'etudiant_id' => 'required|exists:etudiants,id',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        try {
            $etudiant = Etudiant::findOrFail($request->etudiant_id);
            
            // Calcul du montant total existant
            $totalExistant = Paiement::where('etudiant_id', $etudiant->id)
                ->sum('montant');

            // Nouveau montant total
            $montantTotal = $totalExistant + $request->montant;

            $paiement = Paiement::create([
                'methode' => $request->methode,
                'montant' => $request->montant,
                'etudiant_id' => $etudiant->id,
                'MontantTotal' => $montantTotal,
                'status' => true, // Paiement manuel validé
            ]);

            // Vérifier si le paiement est à jour après cette transaction
            $statutPaiement = $this->verifierStatutPaiement($etudiant->id, $montantTotal);

            return response()->json([
                'success' => true,
                'message' => 'Paiement créé avec succès',
                'paiement' => $paiement,
                'statut_paiement' => $statutPaiement
            ], 201);

        } catch (\Exception $e) {
            Log::error('Erreur création paiement manuel: ' . $e->getMessage());
            return response()->json([
                'error' => 'Erreur lors de la création du paiement'
            ], 500);
        }
    }

    public function show($id)
    {
        $paiement = Paiement::with('etudiant')->findOrFail($id);
        return response()->json([
            'paiement' => $paiement
        ]);
    }

    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'methode' => 'sometimes|string|max:255',
            'montant' => 'sometimes|numeric|min:0',
            'status' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        try {
            $paiement = Paiement::findOrFail($id);
            $ancienMontant = $paiement->montant;
            $etudiantId = $paiement->etudiant_id;

            // Mise à jour des champs
            $paiement->fill($request->only(['methode', 'status']));

            // Si le montant change, recalculer tous les totaux
            if ($request->has('montant') && $request->montant != $ancienMontant) {
                $difference = $request->montant - $ancienMontant;
                
                // Mettre à jour le montant total de CE paiement
                $paiement->MontantTotal += $difference;
                $paiement->montant = $request->montant;

                // Mettre à jour les paiements suivants
                Paiement::where('etudiant_id', $paiement->etudiant_id)
                    ->where('created_at', '>', $paiement->created_at)
                    ->increment('MontantTotal', $difference);
            }

            $paiement->save();

            // Recalculer le montant total actuel
            $montantTotal = Paiement::where('etudiant_id', $etudiantId)
                ->sum('montant');

            // Vérifier si le paiement est à jour après cette modification
            $statutPaiement = $this->verifierStatutPaiement($etudiantId, $montantTotal);

            return response()->json([
                'message' => 'Paiement mis à jour avec succès',
                'paiement' => $paiement,
                'statut_paiement' => $statutPaiement
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur mise à jour paiement: ' . $e->getMessage());
            return response()->json([
                'error' => 'Erreur lors de la mise à jour du paiement'
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $paiement = Paiement::findOrFail($id);
            $montantASoustraire = $paiement->montant;
            $etudiantId = $paiement->etudiant_id;
            $dateCreation = $paiement->created_at;

            // Supprimer le paiement
            $paiement->delete();

            // Mettre à jour les totaux des paiements suivants
            Paiement::where('etudiant_id', $etudiantId)
                ->where('created_at', '>', $dateCreation)
                ->decrement('MontantTotal', $montantASoustraire);

            // Recalculer le montant total actuel
            $montantTotal = Paiement::where('etudiant_id', $etudiantId)
                ->sum('montant');

            // Vérifier si le paiement est à jour après cette suppression
            $statutPaiement = $this->verifierStatutPaiement($etudiantId, $montantTotal);

            return response()->json([
                'message' => 'Paiement supprimé avec succès',
                'statut_paiement' => $statutPaiement
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur suppression paiement: ' . $e->getMessage());
            return response()->json([
                'error' => 'Erreur lors de la suppression du paiement'
            ], 500);
        }
    }

    /**
     * Vérifie si le paiement d'un étudiant est à jour par rapport au mois courant
     * 
     * @param int $etudiantId ID de l'étudiant
     * @param float|null $montantTotal Montant total payé (si null, il sera calculé)
     * @return array Statut du paiement
     */
    public function verifierStatutPaiement($etudiantId, $montantTotal = null)
    {
        // Si le montant total n'est pas fourni, le calculer
        if ($montantTotal === null) {
            $montantTotal = Paiement::where('etudiant_id', $etudiantId)
                ->sum('montant');
        }

        // Déterminer le mois actuel
        $moisActuel = Carbon::now()->month;
        $anneeActuelle = Carbon::now()->year;
        
        // Pour les mois de janvier à juin, nous sommes dans la deuxième partie de l'année scolaire
        // donc il faut considérer l'année académique actuelle
        if ($moisActuel >= 1 && $moisActuel <= 6) {
            $anneeAcademique = ($anneeActuelle - 1) . '-' . $anneeActuelle;
        } else {
            // Pour les mois de septembre à décembre, nous sommes dans la première partie de l'année scolaire
            $anneeAcademique = $anneeActuelle . '-' . ($anneeActuelle + 1);
        }

        // Le seuil de paiement requis pour le mois actuel
        $montantRequis = isset(self::SEUILS_PAIEMENT[$moisActuel]) ? self::SEUILS_PAIEMENT[$moisActuel] : 0;
        
        // Si nous sommes dans un mois non couvert (juillet-août), pas de paiement requis
        if ($montantRequis === 0) {
            return [
                'a_jour' => true,
                'montant_requis' => 0,
                'montant_paye' => $montantTotal,
                'montant_manquant' => 0,
                'mois_actuel' => $this->getNomMois($moisActuel),
                'annee_academique' => $anneeAcademique
            ];
        }

        // Vérifier si le montant total payé atteint le seuil requis
        $aJour = $montantTotal >= $montantRequis;
        $montantManquant = $aJour ? 0 : ($montantRequis - $montantTotal);

        return [
            'a_jour' => $aJour,
            'montant_requis' => $montantRequis,
            'montant_paye' => $montantTotal,
            'montant_manquant' => $montantManquant,
            'mois_actuel' => $this->getNomMois($moisActuel),
            'annee_academique' => $anneeAcademique
        ];
    }

    /**
     * Renvoie le nom du mois en français
     * 
     * @param int $mois Numéro du mois (1-12)
     * @return string Nom du mois en français
     */
    private function getNomMois($mois)
    {
        $noms = [
            1 => 'Janvier',
            2 => 'Février',
            3 => 'Mars',
            4 => 'Avril',
            5 => 'Mai',
            6 => 'Juin',
            7 => 'Juillet',
            8 => 'Août',
            9 => 'Septembre',
            10 => 'Octobre',
            11 => 'Novembre',
            12 => 'Décembre'
        ];

        return $noms[$mois] ?? 'Inconnu';
    }

    /**
     * Obtenir le statut complet du paiement pour un étudiant
     * 
     * @param int $etudiantId ID de l'étudiant
     * @return array Statut complet du paiement
     */
    public function getStatutPaiement($etudiantId)
    {
        $statut = $this->verifierStatutPaiement($etudiantId);
        
        // Récupérer l'historique des paiements
        $paiements = Paiement::where('etudiant_id', $etudiantId)
            ->orderBy('created_at', 'asc')
            ->get();
            
        // Ajouter le planning des paiements pour l'année académique
        $planningPaiements = $this->getPlanningPaiements();
        
        return [
            'status' => $statut,
            'historique' => $paiements,
            'planning' => $planningPaiements
        ];
    }
    
    /**
     * Récupérer le planning des paiements pour l'année académique
     * 
     * @return array Planning des paiements
     */
    private function getPlanningPaiements()
    {
        return [
            [
                'mois' => 'Septembre',
                'montant_requis' => self::SEUILS_PAIEMENT[9],
                'detail' => 'Inscription ' . self::MONTANT_INSCRIPTION . ' + Scolarité ' . self::MONTANT_SCOLARITE
            ],
            [
                'mois' => 'Octobre',
                'montant_requis' => self::SEUILS_PAIEMENT[10],
                'detail' => 'Inscription ' . self::MONTANT_INSCRIPTION . ' + Scolarité ' . self::MONTANT_SCOLARITE
            ],
            [
                'mois' => 'Novembre',
                'montant_requis' => self::SEUILS_PAIEMENT[11],
                'detail' => 'Scolarité ' . self::MONTANT_SCOLARITE
            ],
            [
                'mois' => 'Décembre',
                'montant_requis' => self::SEUILS_PAIEMENT[12],
                'detail' => 'Scolarité ' . self::MONTANT_SCOLARITE
            ],
            [
                'mois' => 'Janvier',
                'montant_requis' => self::SEUILS_PAIEMENT[1],
                'detail' => 'Scolarité ' . self::MONTANT_SCOLARITE
            ],
            [
                'mois' => 'Février',
                'montant_requis' => self::SEUILS_PAIEMENT[2],
                'detail' => 'Inscription ' . self::MONTANT_INSCRIPTION . ' + Scolarité ' . self::MONTANT_SCOLARITE
            ],
            [
                'mois' => 'Mars',
                'montant_requis' => self::SEUILS_PAIEMENT[3],
                'detail' => 'Inscription ' . self::MONTANT_INSCRIPTION . ' + Scolarité ' . self::MONTANT_SCOLARITE
            ],
            [
                'mois' => 'Avril',
                'montant_requis' => self::SEUILS_PAIEMENT[4],
                'detail' => 'Scolarité ' . self::MONTANT_SCOLARITE
            ],
            [
                'mois' => 'Mai',
                'montant_requis' => self::SEUILS_PAIEMENT[5],
                'detail' => 'Scolarité ' . self::MONTANT_SCOLARITE
            ],
            [
                'mois' => 'Juin',
                'montant_requis' => self::SEUILS_PAIEMENT[6],
                'detail' => 'Scolarité ' . self::MONTANT_SCOLARITE
            ]
        ];
    }
}