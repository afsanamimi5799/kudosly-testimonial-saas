<?php

namespace App\Enums;

/**
 * The configurable collection fields a space can ask for.
 *
 * The slug values are persisted in the `field_key` column on the space_fields
 * table, so they must remain stable once released.
 */
enum SpaceFieldKey: string
{
    case CompanyName = 'company_name';
    case SocialUrl = 'social_url';
    case ProfilePhoto = 'profile_photo';

    /**
     * Human readable field name.
     */
    public function label(): string
    {
        return match ($this) {
            self::CompanyName => 'Company name',
            self::SocialUrl => 'Social profile link',
            self::ProfilePhoto => 'Profile photo',
        };
    }

    /**
     * The testimonials column this field writes to.
     *
     * The customer name and email are always collected and are not
     * configurable, so they have no entry here.
     */
    public function column(): string
    {
        return match ($this) {
            self::CompanyName => 'company_name',
            self::SocialUrl => 'social_url',
            self::ProfilePhoto => 'profile_photo_path',
        };
    }
}
