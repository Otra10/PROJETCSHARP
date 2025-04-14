<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Classe extends Model
{
    use HasFactory;

    protected $fillable = ['nom', 'batiment_id'];

    public function batiment()
    {
        return $this->belongsTo(Batiment::class);
    }

    public function etudiants()
    {
        return $this->hasMany(Etudiant::class);
    }
}