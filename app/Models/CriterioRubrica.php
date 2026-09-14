<?php

namespace App\Models;

use Database\Factories\CriterioRubricaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CriterioRubrica extends Model
{
    /** @use HasFactory<CriterioRubricaFactory> */
    use HasFactory;

    protected $table = 'criterios_rubrica';

    protected $fillable = ['nombre', 'desc_nivel_1', 'desc_nivel_2', 'desc_nivel_3', 'orden'];

    public function scopeOrdenados($query)
    {
        return $query->orderBy('orden');
    }
}
