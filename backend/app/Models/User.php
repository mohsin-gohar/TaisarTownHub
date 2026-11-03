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
        'dealer_id',
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
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Check if user has a specific permission.
     */
    public function hasPermission(string $permission): bool
    {
        $rolePermissions = [
            'super_admin' => ['*'],
            'admin' => ['view_properties', 'edit_properties', 'delete_properties', 'view_private_financials', 'view_transactions', 'edit_transactions', 'manage_dealers', 'view_audit', 'manage_projects', 'manage_cms', 'view_inquiries', 'manage_inquiries', 'upload_media', 'verify_documents', 'manage_sellers', 'manage_buyers', 'view_plot_locator', 'manage_plot_locator', 'view_dealers', 'view_commissions', 'export_data', 'manage_settings'],
            'manager' => ['view_properties', 'view_transactions', 'edit_transactions', 'view_inquiries', 'manage_inquiries', 'upload_media', 'verify_documents', 'view_plot_locator', 'view_dealers', 'view_commissions'],
            'dealer' => ['view_properties', 'view_my_deals', 'view_my_commission', 'submit_buyer', 'view_dealers'],
            'operator' => ['view_properties', 'edit_properties', 'upload_media', 'view_inquiries'],
        ];

        $permissions = $rolePermissions[$this->role] ?? [];
        return in_array('*', $permissions) || in_array($permission, $permissions);
    }

    /**
     * Get the dealer associated with this user.
     */
    public function dealer()
    {
        return $this->belongsTo(Dealer::class, 'dealer_id', 'id');
    }
}