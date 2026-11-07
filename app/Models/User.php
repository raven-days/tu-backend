<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /** Daftar modul yang bisa diberi izin kepada petugas TU. */
    public const MODUL = ['pengajuan', 'sk', 'pegawai', 'pengumuman', 'banner'];
    public function adalahAdmin(): bool
    {
        return $this->role === 'tu' && $this->is_admin;
    }

    public function punyaIzin(string $modul): bool
    {
        if ($this->role !== 'tu') {
            return false;
        }

        return $this->is_admin || in_array($modul, $this->izin ?? [], true);
    }

    /** Daftar izin yang dikirim ke website. Admin otomatis punya semuanya. */
    public function daftarIzin(): array
    {
        if ($this->role !== 'tu') {
            return [];
        }

        return $this->is_admin
            ? self::MODUL
            : array_values(array_intersect(self::MODUL, $this->izin ?? []));
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'username',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'wajib_ganti_sandi' => 'boolean',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'is_admin' => 'boolean',
            'izin' => 'array',
            'locked_until' => 'datetime',
            'last_login_at' => 'datetime',
            'password_changed_at' => 'datetime',
        ];
    }
}
