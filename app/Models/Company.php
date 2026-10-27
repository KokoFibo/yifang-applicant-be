<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    protected $fillable = ['code', 'name', 'primary_color', 'logo_path', 'mail_from_name', 'settings', 'is_active'];

    protected $casts = ['settings' => 'array', 'is_active' => 'boolean'];

    public function domains()
    {
        return $this->hasMany(CompanyDomain::class);
    }

    /** URL relatif (same-origin) ke logo di disk "public" (php artisan storage:link). */
    public function logoUrl(): ?string
    {
        return $this->logo_path ? '/storage/' . ltrim($this->logo_path, '/') : null;
    }
}
