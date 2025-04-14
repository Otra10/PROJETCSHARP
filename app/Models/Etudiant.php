<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Etudiant extends Model
{
    use HasFactory;

    protected $fillable = ['nom', 'prenom', 'matricule', 'grade_id', 'classe_id','date_naissance','image','user_id'];

    public function grade()
    {
        return $this->belongsTo(Grade::class);
    }

    public function classe()
    {
        return $this->belongsTo(Classe::class);
    }

    public function paiements()
    {
        return $this->hasMany(Paiement::class);
    }
}