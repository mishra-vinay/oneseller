<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case Seller = 'seller';
    case Buyer = 'buyer';

    /**
     * Route name of the login page for this role's area.
     */
    public function loginRoute(): string
    {
        return match ($this) {
            self::Admin => 'admin.login',
            self::Seller => 'seller.login',
            self::Buyer => 'login',
        };
    }

    /**
     * Route name a user of this role lands on after login.
     */
    public function homeRoute(): string
    {
        return match ($this) {
            self::Admin => 'admin.dashboard',
            self::Seller => 'seller.dashboard',
            self::Buyer => 'home',
        };
    }

    /**
     * The role whose area a request path belongs to.
     */
    public static function forPath(string $path): self
    {
        $path = ltrim($path, '/');

        return match (true) {
            $path === 'admin' || str_starts_with($path, 'admin/') => self::Admin,
            $path === 'seller' || str_starts_with($path, 'seller/') => self::Seller,
            default => self::Buyer,
        };
    }
}
