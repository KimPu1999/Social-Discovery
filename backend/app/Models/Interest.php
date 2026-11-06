<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Interest extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'category', 'is_active'];
    protected function casts(): array { return ['is_active' => 'boolean']; }
}
