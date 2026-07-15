<?php

namespace Reliese\Coders\Model\Relations;

use Illuminate\Support\Str;

/**
 * General utility functions for dealing with relationships
 */
class RelationHelper
{
    /**
     * Turns a column name like 'manager_id' into 'manager'; 'lineManagerId'
     * into 'lineManager'; or 'HostID' into 'Host'.
     *
     * An underscore-delimited suffix (e.g. "_id") is always a safe word
     * boundary and is stripped regardless of casing. Without an underscore,
     * the suffix is only stripped when it starts with an uppercase letter
     * (e.g. "HostID", "authorId", "VirtualHostId"), since that is the only
     * signal we have that it is a real suffix and not just a word that
     * happens to end in "id" (e.g. "valid", "grid"). The rest of the
     * suffix is matched case-insensitively, since referencing tables don't
     * always spell the primary key's name the same way the foreign key
     * column does (e.g. primary key "ID" referenced by a "...Id" column).
     *
     * @param bool $usesSnakeAttributes
     * @param string $primaryKey
     * @param string $foreignKey
     * @return string
     */
    public static function stripSuffixFromForeignKey($usesSnakeAttributes, $primaryKey, $foreignKey)
    {
        $studlyPrimaryKey = Str::studly($primaryKey);

        if ($usesSnakeAttributes) {
            $lowerPrimaryKey = strtolower($primaryKey);
            $stripped = preg_replace(
                '/_(' . preg_quote($primaryKey, '/') . '|' . preg_quote($lowerPrimaryKey, '/') . ')$/i',
                '',
                $foreignKey
            );

            if ($stripped !== $foreignKey) {
                return $stripped;
            }
        }

        $suffixLength = strlen($studlyPrimaryKey);
        $candidateSuffix = substr($foreignKey, -$suffixLength);

        if (ctype_upper(substr($candidateSuffix, 0, 1)) && strcasecmp($candidateSuffix, $studlyPrimaryKey) === 0) {
            return substr($foreignKey, 0, -$suffixLength);
        }

        return $foreignKey;
    }

    /**
     * Builds a name from one or more foreign key columns. For a composite
     * foreign key, each column is stripped against its own paired reference
     * column and the results are joined together. A column that strips down
     * to nothing meaningful (e.g. one that is identical across sibling
     * relations, such as a shared "league_id" in a composite key that
     * otherwise differs by "host"/"guest") carries no distinguishing
     * information on its own, so a generic "id" suffix is tried as a
     * fallback before giving up on that column entirely.
     *
     * A column whose DB comment carries a `{"relation": "..."}` JSON hint
     * (see `relationNameFromComment()`) uses that verbatim instead of the
     * stripped suffix, for the cases where the heuristic can't produce a
     * sensible name on its own (e.g. abbreviated or unconventional column
     * names).
     *
     * A leading underscore surviving the strip (e.g. this schema's
     * "_ZespolParentId"-style FK columns, which strip down to
     * "_ZespolParent") is dropped: `Illuminate\Support\Str::snake()`, which
     * every caller of this method runs its result through, treats a
     * character immediately before an uppercase letter as a word boundary
     * and inserts its own separator there regardless of whether one is
     * already present - so a leading "_" ends up doubled into "__", and
     * once joined with another part's separator that becomes a stray
     * triple underscore (e.g. "zespol_tbl___zespol_parent"). The leading
     * underscore is just this schema's "internal" column-naming
     * convention, not meaningful in a relation name, so it is safe to drop.
     *
     * @param bool $usesSnakeAttributes
     * @param string[] $columns
     * @param string[] $references
     * @param array<string, string|null> $columnComments Comment string keyed by column name.
     * @return string
     */
    public static function nameFromForeignKeyColumns($usesSnakeAttributes, array $columns, array $references, array $columnComments = [])
    {
        $parts = [];

        foreach ($columns as $index => $column) {
            $override = self::relationNameFromComment($columnComments[$column] ?? null);

            if ($override !== null) {
                $parts[] = ltrim($override, '_');
                continue;
            }

            $reference = $references[$index] ?? $references[0];
            $stripped = self::stripSuffixFromForeignKey($usesSnakeAttributes, $reference, $column);

            if ($stripped === $column) {
                $stripped = self::stripSuffixFromForeignKey($usesSnakeAttributes, 'id', $column);
            }

            if (trim($stripped, '_') === '') {
                continue;
            }

            $parts[] = ltrim($stripped, '_');
        }

        if (empty($parts)) {
            $parts[] = ltrim($columns[0], '_');
        }

        return implode('_', $parts);
    }

    /**
     * Whether any of the given foreign key columns carries a
     * `{"relation": "..."}` comment hint. Callers use this to decide
     * whether a relation should skip the usual "first one keeps the
     * default name, later ones are disambiguated" ordering entirely and
     * just use the hinted name outright - since the hint is an explicit,
     * order-independent instruction from whoever annotated the column, not
     * a fallback that should only kick in when a collision happens to
     * assign that particular column the "loser" side.
     *
     * @param string[] $columns
     * @param array<string, string|null> $columnComments Comment string keyed by column name.
     * @return bool
     */
    public static function hasCommentOverride(array $columns, array $columnComments = [])
    {
        foreach ($columns as $column) {
            if (self::relationNameFromComment($columnComments[$column] ?? null) !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * A column's DB comment can carry a `{"relation": "..."}` JSON hint to
     * explicitly name the relation this foreign key column produces,
     * overriding the stripped-suffix heuristic. This is meant as an escape
     * hatch for columns the heuristic can't name well on its own (e.g.
     * `HostID`/`GuestID`-style abbreviations), without having to keep
     * growing the heuristic's regex rules for every such case.
     *
     * @param string|null $comment
     * @return string|null
     */
    public static function relationNameFromComment($comment)
    {
        if (empty($comment)) {
            return null;
        }

        $decoded = json_decode($comment, true);

        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded) || empty($decoded['relation'])) {
            return null;
        }

        return $decoded['relation'];
    }
}
