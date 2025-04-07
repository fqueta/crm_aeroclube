<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class Qoption extends Model
{
    use HasFactory,Notifiable;
    protected $table = 'qoptions_remoto';
    protected $fillable = [
        'option_name',
        'option_value',
        'obs',
        'config',
        'painel',
        'excluido',
        'reg_excluido',
        'deletado',
        'reg_deletado',
    ];
}
