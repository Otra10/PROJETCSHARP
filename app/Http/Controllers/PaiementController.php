<?php

namespace App\Http\Controllers;

use App\Models\Etudiant;
use App\Models\Paiement;
use Paydunya\Setup;
use Paydunya\Checkout\Store;
use Paydunya\Checkout\CheckoutInvoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class PaiementController extends Controller
{
    // protected $cancel_url;
    // protected $return_url;
    // protected $callback_url;

    // public function __construct()
    // {
    //     Setup::setMasterKey(config('services.paydunya.master_key'));
    //     Setup::setPrivateKey(config('services.paydunya.private_key'));
    //     Setup::setPublicKey(config('services.paydunya.public_key'));
    //     Setup::setToken(config('services.paydunya.token'));
    //     Setup::setMode(config('services.paydunya.mode'));
    //     Store::setName(config('services.paydunya.store_name'));

    //     $this->cancel_url = route('paydunya.cancel');
    //     $this->return_url = route('paydunya.return');
    //     $this->callback_url = route('paydunya.callback');
    // }

    public function index()
    {
        $user = Auth::user();
        $etudiant = Etudiant::where('user_id', $user->id)->first();

        if (!$etudiant) {
            return response()->json(['message' => 'Étudiant non trouvé.'], 404);
        }

        return response()->json(Paiement::where('etudiant_id', $etudiant->id)->get());
    }

    // PaiementController.php

// ... (méthodes existantes)

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
            'paid_at' => now(),
        ]);

        return response()->json([
            'message' => 'Paiement créé avec succès',
            'paiement' => $paiement
        ], 201);

    } catch (\Exception $e) {
        Log::error('Erreur création paiement manuel: ' . $e->getMessage());
        return response()->json([
            'error' => 'Erreur lors de la création du paiement'
        ], 500);
    }
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

        return response()->json([
            'message' => 'Paiement mis à jour avec succès',
            'paiement' => $paiement
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

        return response()->json([
            'message' => 'Paiement supprimé avec succès'
        ]);

    } catch (\Exception $e) {
        Log::error('Erreur suppression paiement: ' . $e->getMessage());
        return response()->json([
            'error' => 'Erreur lors de la suppression du paiement'
        ], 500);
    }
}

    // public function createInvoice(Request $request)
    // {
    //     $validator = Validator::make($request->all(), [
    //         'methode'         => 'required|string|max:255',
    //         'montant'         => 'required|numeric|min:500',
    //         'etudiant_id'     => 'required|exists:etudiants,id',
    //         'recipient_phone' => 'required_without:recipient_email|string',
    //         'recipient_email' => 'nullable|email',
    //         // otp_code requis uniquement pour OTP (Orange Money)
    //         'otp_code'        => 'required_if:methode,ORANGE MONEY SENEGAL|string',
    //     ]);

    //     if ($validator->fails()) {
    //         return response()->json($validator->errors(), 422);
    //     }

    //     $etudiant = Etudiant::findOrFail($request->etudiant_id);
    //     $invoice = new CheckoutInvoice();

    //     // Configuration de la facture
    //     $invoice->setDescription("Paiement pour l'étudiant {$etudiant->nom} {$etudiant->prenom}");
    //     $invoice->setTotalAmount($request->montant);

    //     // Ajout d'un item obligatoire pour générer un token complet
    //     $invoice->addItem(
    //         "Paiement étudiant",
    //         1,
    //         $request->montant,
    //         $request->montant,
    //         "Paiement pour l'étudiant {$etudiant->nom} {$etudiant->prenom}"
    //     );

    //     // Ajout de données personnalisées
    //     $invoice->addCustomData('etudiant_id', $etudiant->id);
    //     $invoice->addCustomData('payment_method', $request->methode);

    //     // Injection du nœud "customer", et ajout du nœud "store" et "actions" via reflection
    //     try {
    //         $reflection = new \ReflectionClass($invoice);
    //         if ($reflection->hasProperty('data')) {
    //             $property = $reflection->getProperty('data');
    //             $property->setAccessible(true);
    //             $data = $property->getValue($invoice);
    //             // Injecter le nœud "customer" avec les informations du client
    //             $data['customer'] = [
    //                 'name'  => "{$etudiant->nom} {$etudiant->prenom}",
    //                 'email' => Auth::user()->email,
    //                 'phone' => $request->recipient_phone,
    //             ];
    //             // Injection du nœud "store" avec les informations du magasin
    //             $data['store'] = [
    //                 'name'          => config('services.paydunya.store_name'),
    //                 'tagline'       => config('services.paydunya.store_tagline') ?? '',
    //                 'postal_address'=> config('services.paydunya.store_postal_address') ?? '',
    //                 'phone'         => config('services.paydunya.store_phone') ?? '',
    //                 'logo_url'      => config('services.paydunya.store_logo_url') ?? '',
    //                 'website_url'   => config('services.paydunya.store_website_url') ?? '',
    //             ];
    //             // Injection du nœud "actions" pour les redirections
    //             $data['actions'] = [
    //                 'cancel_url'   => $this->cancel_url,
    //                 'return_url'   => $this->return_url,
    //                 'callback_url' => $this->callback_url,
    //             ];
    //             $property->setValue($invoice, $data);
    //         }
    //     } catch (\Exception $e) {
    //         Log::error('Erreur lors de l\'injection des nœuds customer/store/actions: ' . $e->getMessage());
    //     }

    //     // Ajout des canaux selon la méthode
    //     if ($request->methode === 'ORANGE MONEY SENEGAL') {
    //         // Mode OTP
    //         $invoice->addChannel('orange-money-senegal');
    //         $invoice->addCustomData('otp_code', $request->otp_code);
    //     } elseif ($request->methode === 'ORANGE MONEY SENEGAL QR') {
    //         // Mode QR pour Orange Money
    //         $invoice->addChannel('orange-money-senegal');
    //     } elseif ($request->methode === 'WAVE SENEGAL') {
    //         // Mode Wave : ajouter le canal approprié
    //         $invoice->addChannel('wave-senegal');
    //     }

    //     // Configuration des URLs
    //     $invoice->setCancelUrl($this->cancel_url);
    //     $invoice->setReturnUrl($this->return_url);
    //     $invoice->setCallbackUrl($this->callback_url);

    //     // Création de la facture sur PayDunya
    //     if ($invoice->create()) {
    //         if (empty($invoice->token)) {
    //             Log::error('Token non renseigné après création de facture');
    //             return response()->json(['error' => 'Erreur interne de création de facture'], 500);
    //         }

    //         // Traitement selon le mode de paiement
    //         if ($request->methode === 'ORANGE MONEY SENEGAL') {
    //             // Mode OTP
    //             try {
    //                 $user = Auth::user();
    //                 $payload = [
    //                     "customer_name"      => "{$etudiant->nom} {$etudiant->prenom}",
    //                     "customer_email"     => $user->email,
    //                     "phone_number"       => $request->recipient_phone,
    //                     "authorization_code" => $request->otp_code,
    //                     "invoice_token"      => $invoice->token,
    //                     "api_type"           => "OTPCODE"
    //                 ];

    //                 var_dump($payload);

    //                 $otpEndpoint = 'https://app.paydunya.com/api/v1/softpay/new-orange-money-senegal';

    //                 $client = new \GuzzleHttp\Client();
    //                 $otpResponse = $client->post($otpEndpoint, [
    //                     'json'    => $payload,
    //                     'headers' => [
    //                         'PAYDUNYA-MASTER-KEY' => config('services.paydunya.master_key'),
    //                         'PAYDUNYA-PRIVATE-KEY'=> config('services.paydunya.private_key'),
    //                         'PAYDUNYA-PUBLIC-KEY' => config('services.paydunya.public_key'),
    //                         'PAYDUNYA-TOKEN'      => config('services.paydunya.token'),
    //                         'Content-Type'        => 'application/json'
    //                     ],
    //                 ]);

    //                 var_dump("Après appel OTP");

    //                 $otpBody = json_decode($otpResponse->getBody(), true);
    //                 Log::info('Réponse OTP PayDunya', ['payload' => $payload, 'response' => $otpBody]);

    //                 if (isset($otpBody['success']) && $otpBody['success'] === true) {
    //                     $paiement = Paiement::create([
    //                         'methode'         => $request->methode,
    //                         'montant'         => $request->montant,
    //                         'etudiant_id'     => $etudiant->id,
    //                         'MontantTotal'    => $request->montant,
    //                         'status'          => true,
    //                         'invoice_token'   => $invoice->token,
    //                         'transaction_id'  => $otpBody['transaction_id'] ?? null,
    //                         'paid_at'         => now(),
    //                     ]);
    //                     return response()->json([
    //                         'message'  => 'Paiement réussi et montant débité du compte Orange (OTP).',
    //                         'paiement' => $paiement
    //                     ]);
    //                 } else {
    //                     Log::error('Échec de confirmation OTP', ['response' => $otpBody]);
    //                     return response()->json([
    //                         'error'   => 'Confirmation de facture échouée avec OTP',
    //                         'details' => $otpBody
    //                     ], 500);
    //                 }
    //             } catch (\GuzzleHttp\Exception\ClientException $e) {
    //                 $response = $e->getResponse();
    //                 $body = $response ? $response->getBody()->getContents() : null;
    //                 Log::error('Erreur Client lors de la confirmation OTP: ' . $e->getMessage(), ['body' => $body]);
    //                 return response()->json([
    //                     'error'   => 'Erreur de confirmation OTP',
    //                     'details' => $body
    //                 ], 500);
    //             } catch (\Exception $e) {
    //                 Log::error('Erreur lors de la confirmation OTP: ' . $e->getMessage());
    //                 return response()->json([
    //                     'error'   => 'Erreur de confirmation OTP',
    //                     'details' => $e->getMessage()
    //                 ], 500);
    //             }
    //         } elseif ($request->methode === 'ORANGE MONEY SENEGAL QR') {
    //             // Mode QR pour Orange Money
    //             try {
    //                 $user = Auth::user();
    //                 $payload = [
    //                     "customer_name"  => "{$etudiant->nom} {$etudiant->prenom}",
    //                     "customer_email" => $user->email,
    //                     "phone_number"   => $request->recipient_phone,
    //                     "invoice_token"  => $invoice->token,
    //                     "api_type"       => "QRCODE"
    //                 ];

    //                 var_dump($payload);

    //                 $qrEndpoint = 'https://app.paydunya.com/api/v1/softpay/new-orange-money-senegal';

    //                 $client = new \GuzzleHttp\Client();
    //                 $qrResponse = $client->post($qrEndpoint, [
    //                     'json'    => $payload,
    //                     'headers' => [
    //                         'PAYDUNYA-MASTER-KEY' => config('services.paydunya.master_key'),
    //                         'PAYDUNYA-PRIVATE-KEY'=> config('services.paydunya.private_key'),
    //                         'PAYDUNYA-PUBLIC-KEY' => config('services.paydunya.public_key'),
    //                         'PAYDUNYA-TOKEN'      => config('services.paydunya.token'),
    //                         'Content-Type'        => 'application/json'
    //                     ],
    //                 ]);

    //                 var_dump("Après appel QR");

    //                 $qrBody = json_decode($qrResponse->getBody(), true);
    //                 Log::info('Réponse QR PayDunya', ['payload' => $payload, 'response' => $qrBody]);

    //                 if (isset($qrBody['success']) && $qrBody['success'] === true) {
    //                     $paiement = Paiement::create([
    //                         'methode'         => $request->methode,
    //                         'montant'         => $request->montant,
    //                         'etudiant_id'     => $etudiant->id,
    //                         'MontantTotal'    => $request->montant,
    //                         'status'          => false, // Le paiement sera confirmé ultérieurement
    //                         'invoice_token'   => $invoice->token,
    //                     ]);
    //                     return response()->json([
    //                         'payment_url' => $qrBody['url'] ?? $invoice->getInvoiceUrl(),
    //                         'paiement'    => $paiement
    //                     ]);
    //                 } else {
    //                     Log::error('Échec de confirmation QR', ['response' => $qrBody]);
    //                     return response()->json([
    //                         'error'   => 'Confirmation de facture échouée avec QR',
    //                         'details' => $qrBody
    //                     ], 500);
    //                 }
    //             } catch (\GuzzleHttp\Exception\ClientException $e) {
    //                 $response = $e->getResponse();
    //                 $body = $response ? $response->getBody()->getContents() : null;
    //                 Log::error('Erreur Client lors de la confirmation QR: ' . $e->getMessage(), ['body' => $body]);
    //                 return response()->json([
    //                     'error'   => 'Erreur de confirmation QR',
    //                     'details' => $body
    //                 ], 500);
    //             } catch (\Exception $e) {
    //                 Log::error('Erreur lors de la confirmation QR: ' . $e->getMessage());
    //                 return response()->json([
    //                     'error'   => 'Erreur de confirmation QR',
    //                     'details' => $e->getMessage()
    //                 ], 500);
    //             }
    //         } elseif ($request->methode === 'WAVE SENEGAL') {
    //             $invoice->addChannel('wave-senegal');
    //             // Mode Wave : rediriger vers l'application Wave via l'URL fournie par l'API
    //             try {
    //                 $user = Auth::user();
    //                 $payload = [
    //                     "wave_senegal_fullName"      => "{$etudiant->nom} {$etudiant->prenom}",
    //                     "wave_senegal_email"         => $user->email,
    //                     "wave_senegal_phone"         => $request->recipient_phone,
    //                     "wave_senegal_payment_token" => $invoice->token,
    //                 ];

    //                 var_dump($payload);

    //                 $waveEndpoint = 'https://app.paydunya.com/api/v1/softpay/wave-senegal';

    //                 $client = new \GuzzleHttp\Client();
    //                 $waveResponse = $client->post($waveEndpoint, [
    //                     'json'    => $payload,
    //                     'headers' => [
    //                         'PAYDUNYA-MASTER-KEY' => config('services.paydunya.master_key'),
    //                         'PAYDUNYA-PRIVATE-KEY'=> config('services.paydunya.private_key'),
    //                         'PAYDUNYA-PUBLIC-KEY' => config('services.paydunya.public_key'),
    //                         'PAYDUNYA-TOKEN'      => config('services.paydunya.token'),
    //                         'Content-Type'        => 'application/json'
    //                     ],
    //                 ]);

    //                 var_dump("Après appel Wave");

    //                 $waveBody = json_decode($waveResponse->getBody(), true);
    //                 Log::info('Réponse Wave PayDunya', ['payload' => $payload, 'response' => $waveBody]);

    //                 if (isset($waveBody['success']) && $waveBody['success'] === true) {
    //                     $paiement = Paiement::create([
    //                         'methode'         => $request->methode,
    //                         'montant'         => $request->montant,
    //                         'etudiant_id'     => $etudiant->id,
    //                         'MontantTotal'    => $request->montant,
    //                         'status'          => false, // Paiement non encore confirmé
    //                         'invoice_token'   => $invoice->token,
    //                     ]);
    //                     return response()->json([
    //                         'payment_url' => $waveBody['url'] ?? $invoice->getInvoiceUrl(),
    //                         'paiement'    => $paiement
    //                     ]);
    //                 } else {
    //                     Log::error('Échec de confirmation Wave', ['response' => $waveBody]);
    //                     return response()->json([
    //                         'error'   => 'Confirmation de facture échouée avec Wave',
    //                         'details' => $waveBody
    //                     ], 500);
    //                 }
    //             } catch (\GuzzleHttp\Exception\ClientException $e) {
    //                 $response = $e->getResponse();
    //                 $body = $response ? $response->getBody()->getContents() : null;
    //                 Log::error('Erreur Client lors de la confirmation Wave: ' . $e->getMessage(), ['body' => $body]);
    //                 return response()->json([
    //                     'error'   => 'Erreur de confirmation Wave',
    //                     'details' => $body
    //                 ], 500);
    //             } catch (\Exception $e) {
    //                 Log::error('Erreur lors de la confirmation Wave: ' . $e->getMessage());
    //                 return response()->json([
    //                     'error'   => 'Erreur de confirmation Wave',
    //                     'details' => $e->getMessage()
    //                 ], 500);
    //             }
    //         } else {
    //             // Pour les autres méthodes, renvoyer l'URL de paiement pour redirection
    //             $paiement = Paiement::create([
    //                 'methode'        => $request->methode,
    //                 'montant'        => $request->montant,
    //                 'etudiant_id'    => $etudiant->id,
    //                 'MontantTotal'   => $request->montant,
    //                 'status'         => false,
    //                 'invoice_token'  => $invoice->token,
    //             ]);
    //             return response()->json([
    //                 'payment_url' => $invoice->getInvoiceUrl(),
    //                 'paiement'    => $paiement
    //             ]);
    //         }
    //     }

    //     Log::error('Échec de création de facture PayDunya', ['request' => $request->all()]);
    //     return response()->json(['message' => 'Échec de la création de la facture'], 500);
    // }

    // public function callback(Request $request)
    // {
    //     try {
    //         if ($request->header('Content-Type') !== 'application/x-www-form-urlencoded') {
    //             Log::warning('Content-Type invalide dans le callback', $request->all());
    //             return response()->json(['error' => 'Invalid Content-Type'], 415);
    //         }

    //         $data = $request->input('data');
    //         $expectedHash = hash('sha512', config('services.paydunya.master_key'));

    //         if (!isset($data['hash']) || $data['hash'] !== $expectedHash) {
    //             Log::error('Hash invalide dans le callback', $data);
    //             return response()->json(['error' => 'Hash non valide'], 400);
    //         }

    //         $invoiceToken = $data['invoice']['token'] ?? null;
    //         $status = $data['status'] ?? 'pending';
    //         $etudiantId = $data['custom_data']['etudiant_id'] ?? null;
    //         $paymentMethod = $data['custom_data']['payment_method'] ?? null;

    //         if (!$invoiceToken || !$etudiantId) {
    //             Log::error('Données manquantes dans le callback', $data);
    //             return response()->json(['error' => 'Données manquantes'], 400);
    //         }

    //         $invoice = new CheckoutInvoice();
    //         if (!$invoice->confirm($invoiceToken)) {
    //             Log::error('Échec de confirmation de la facture', ['token' => $invoiceToken]);
    //             return response()->json(['error' => 'Confirmation de facture échouée'], 500);
    //         }

    //         $paiement = Paiement::where('invoice_token', $invoiceToken)
    //             ->where('etudiant_id', $etudiantId)
    //             ->firstOrFail();

    //         $paiement->update([
    //             'status' => $status === 'completed',
    //             'transaction_id' => $data['transaction_id'] ?? null,
    //             'paid_at' => now(),
    //         ]);

    //         return response()->json(['message' => 'Statut de paiement mis à jour']);
    //     } catch (\Exception $e) {
    //         Log::error('Erreur dans le callback: ' . $e->getMessage(), [
    //             'trace'   => $e->getTraceAsString(),
    //             'request' => $request->all()
    //         ]);
    //         return response()->json(['error' => 'Erreur interne du serveur'], 500);
    //     }
    // }

    // public function returnUrl(Request $request)
    // {
    //     try {
    //         $token = $request->query('token');
    //         $paiement = Paiement::where('invoice_token', $token)->first();

    //         if (!$paiement) {
    //             Log::warning('Token invalide dans returnUrl', ['token' => $token]);
    //             return redirect()->route('payment.error');
    //         }

    //         return redirect()->route('payment.success', $paiement->id);
    //     } catch (\Exception $e) {
    //         Log::error('Erreur dans returnUrl: ' . $e->getMessage());
    //         return redirect()->route('payment.error');
    //     }
    // }

    // public function cancelUrl(Request $request)
    // {
    //     Log::info('Paiement annulé', ['request' => $request->all()]);
    //     return redirect()->route('payment.cancelled');
    // }
}