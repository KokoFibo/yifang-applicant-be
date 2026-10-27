<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Applicantfile extends Model
{
    protected $table = 'applicantfiles';

    protected $fillable = ['id_karyawan', 'type', 'originalName', 'filename'];
}
