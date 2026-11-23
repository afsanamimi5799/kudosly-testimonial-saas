<?php

namespace App\Auth;

use App\Models\User;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Support\Arrayable;

/**
 * Looks accounts up by email address without caring about the case.
 *
 * The application stores an address in lower case, and every form that
 * takes an address lowers it before it is submitted. Addresses that were
 * stored before that rule was in place may still carry capitals, though, and
 * those rows have to stay reachable.
 *
 * Whether an address is matched with or without regard to case otherwise
 * depends on the collation of the database. MariaDB compares text without
 * regard to case by default, while SQLite and PostgreSQL compare it with
 * regard to case. Relying on that would mean a sign in or a password reset
 * works in one environment and fails in another, so the comparison is made
 * here instead and always behaves the same way.
 */
class CaseInsensitiveEmailUserProvider extends EloquentUserProvider
{
    /**
     * Retrieve a user by their credentials.
     *
     * The email address is compared without regard to case. Every other
     * credential keeps the behaviour of the parent class.
     *
     * @param  array<string, mixed>  $credentials
     */
    public function retrieveByCredentials(array $credentials): ?Authenticatable
    {
        if (! array_key_exists('email', $credentials)) {
            return parent::retrieveByCredentials($credentials);
        }

        $email = $credentials['email'];

        // The password is left out of the query on purpose. It is checked
        // separately against the stored hash, so it never belongs in a where
        // clause.
        $other = array_filter(
            $credentials,
            fn (string $key): bool => $key !== 'email' && ! str_contains($key, 'password'),
            ARRAY_FILTER_USE_KEY,
        );

        $query = $this->newModelQuery();

        if (is_string($email) && $email !== '') {
            $query->whereRaw('lower(email) = ?', [mb_strtolower(trim($email))]);
        } else {
            $query->where('email', $email);
        }

        foreach ($other as $key => $value) {
            if (is_array($value) || $value instanceof Arrayable) {
                $query->whereIn($key, $value);
            } elseif ($value instanceof \Closure) {
                $value($query);
            } else {
                $query->where($key, $value);
            }
        }

        $user = $query->first();

        return $user instanceof Authenticatable ? $user : null;
    }
}
