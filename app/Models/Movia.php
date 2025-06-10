<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Movia extends Model
{
    use HasFactory;

    protected $fillable = [ 'name', 'code', 'raw_post'];


}
