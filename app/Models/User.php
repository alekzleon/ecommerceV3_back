<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Role;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\Promotion;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */

    public const STATUS_ACTIVO = 'activo';
    public const STATUS_BAJA = 'baja';
    public const STATUS_SUSPENDIDO_CREDITO = 'suspendido_credito';

    public const ROLE_CLIENTE = 6;

    protected $fillable = [
        'name',
        'username',
        'email',
        'microsip_id',
        'contacto1',
        'contacto2',
        'estatus',
        'causa_susp',
        'fecha_susp',
        'cobrar_impuestos',
        'retiene_impuestos',
        'sujeto_ieps',
        'generar_intereses',
        'emitir_edocta',
        'diferir_cfdi_cobros',
        'limite_credito',
        'moneda_id',
        'cond_pago_id',
        'tipo_cliente_id',
        'zona_cliente_id',
        'cobrador_id',
        'vendedor_id',
        'notas',
        'cuenta_cxc',
        'cuenta_anticipos',
        'formatos_email',
        'receptor_cfd',
        'num_prov_cliente',
        'campos_addenda',
        'usuario_creador',
        'fecha_hora_creacion',
        'usuario_aut_creacion',
        'usuario_ult_modif',
        'fecha_hora_ult_modif',
        'usuario_aut_modif',
        'cfdiw_usuario',
        'cfdiw_password',
        'cfdiw_estatus',
        'cdfiw_formato_cfd_ve',
        'cdfiw_formato_cfdi_ve',
        'cdfiw_formato_dev_cfd_ve',
        'cdfiw_formato_dev_cfdi_ve',
        'cdfiw_formato_cfd_pv',
        'cdfiw_formato_cfdi_pv',
        'cdfiw_formato_dev_cfd_pv',
        'cdfiw_formato_dev_cfdi_pv',
        'password',
        'role_id',  
        'must_change_password',
        'invited_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'cfdiw_password',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
            'invited_at' => 'datetime',
            'fecha_susp' => 'date',
            'diferir_cfdi_cobros' => 'boolean',
            'limite_credito' => 'decimal:2',
            'moneda_id' => 'integer',
            'cond_pago_id' => 'integer',
            'tipo_cliente_id' => 'integer',
            'zona_cliente_id' => 'integer',
            'cobrador_id' => 'integer',
            'vendedor_id' => 'integer',
            'fecha_hora_creacion' => 'datetime',
            'fecha_hora_ult_modif' => 'datetime',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function hasRole(string $roleName): bool
    {
        return $this->role?->name === $roleName;
    }

    public function hasModuleAccess(string $moduleName): bool
    {
        if (!$this->relationLoaded('role')) {
            $this->load('role.modules');
        } elseif ($this->role && !$this->role->relationLoaded('modules')) {
            $this->role->load('modules');
        }

        if (!$this->role || !$this->role->is_active) {
            return false;
        }

        return $this->role->modules
            ->where('is_active', true)
            ->contains('name', $moduleName);
    }

    public function isInternalUser(): bool
    {
        return !$this->hasRole('cliente');
    }

    public function promotions()
    {
        return $this->belongsToMany(\App\Models\Promotion::class, 'promotion_user')
            ->withTimestamps();
    }   

    public function favoriteProducts(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_favorites')
            ->withTimestamps();
    }

    public function scopeActiveClients($query)
    {
        return $query->where('role_id', self::ROLE_CLIENTE)
                    ->where('status', self::STATUS_ACTIVO);
    }

    public function customerProfile(): HasOne
    {
        return $this->hasOne(CustomerProfile::class);
    }

    public function customerPfrProfile(): HasOne
    {
        return $this->hasOne(CustomerPfrProfile::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(UserAddress::class);
    }

    public function defaultAddress(): HasOne
    {
        return $this->hasOne(UserAddress::class)->where('is_default', true);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function scopeClients($query)
    {
        return $query->where('role_id', self::ROLE_CLIENTE);
    }
}
