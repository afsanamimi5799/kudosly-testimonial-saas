<?php

namespace App\Spaces;

use App\Models\Space;
use Illuminate\Support\Str;

/**
 * Produces a unique public slug for a space.
 *
 * The slug is the public identity of a space: it appears in the collection
 * URL (/s/{slug}), so it must be unique across every space on the instance
 * and it must not leak anything the owner did not intend to publish.
 */
final class SpaceSlugGenerator
{
    /**
     * The maximum length of a generated slug.
     *
     * The column is a varchar(255), but a shorter slug keeps the public URL
     * readable. The numeric suffix is appended after truncation.
     */
    private const MAX_LENGTH = 100;

    /**
     * Generate a slug that is unique across all spaces.
     *
     * The base slug is derived from the given name. When that slug is taken,
     * a numeric suffix is appended until an unused slug is found. The lookup
     * deliberately includes soft deleted spaces, because their slug stays
     * reserved and must not be handed out a second time.
     */
    public function unique(string $name, ?Space $ignoring = null): string
    {
        $base = Str::slug($name);

        if ($base === '') {
            // A name made entirely of characters that cannot be slugified
            // still needs a usable public URL.
            $base = 'space';
        }

        $base = Str::limit($base, self::MAX_LENGTH, '');

        $slug = $base;
        $suffix = 1;

        while ($this->isTaken($slug, $ignoring)) {
            $suffix++;
            $slug = Str::limit($base, self::MAX_LENGTH - strlen((string) $suffix) - 1, '').'-'.$suffix;
        }

        return $slug;
    }

    /**
     * Determine whether the slug is already in use.
     */
    private function isTaken(string $slug, ?Space $ignoring = null): bool
    {
        $query = Space::withTrashed()->where('slug', $slug);

        if ($ignoring !== null) {
            $query->whereKeyNot($ignoring->getKey());
        }

        return $query->exists();
    }
}
