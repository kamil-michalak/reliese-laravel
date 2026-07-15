<?php

/**
 * Created by Cristian.
 * Date: 05/09/16 11:41 PM.
 */

namespace Reliese\Coders\Model\Relations;

use Illuminate\Support\Str;
use Reliese\Support\Dumper;
use Illuminate\Support\Fluent;
use Reliese\Coders\Model\Model;
use Reliese\Coders\Model\Relation;

class BelongsTo implements Relation
{
    /**
     * @var \Illuminate\Support\Fluent
     */
    protected $command;

    /**
     * @var \Reliese\Coders\Model\Model
     */
    protected $parent;

    /**
     * @var \Reliese\Coders\Model\Model
     */
    protected $related;

    /**
     * BelongsToWriter constructor.
     *
     * @param \Illuminate\Support\Fluent $command
     * @param \Reliese\Coders\Model\Model $parent
     * @param \Reliese\Coders\Model\Model $related
     */
    public function __construct(Fluent $command, Model $parent, Model $related)
    {
        $this->command = $command;
        $this->parent = $parent;
        $this->related = $related;
    }

    /**
     * @return string
     */
    public function name()
    {
        if ($this->hasCommentOverride()) {
            return $this->formatName($this->foreignKeyName());
        }

        return $this->nameForStrategy($this->parent->getRelationNameStrategy());
    }

    /**
     * A name that combines the related model's default name with a suffix
     * based on this relation's own foreign key (e.g. "sportmonks_country"
     * + "nationality_id" => "sportmonks_country_nationality"), used to
     * disambiguate this relation when another relation already claimed its
     * default name (e.g. two foreign keys pointing to the same table).
     *
     * A `{"relation": "..."}` comment hint (see `hasCommentOverride()`) is
     * an explicit, order-independent instruction, so it is used verbatim
     * here too rather than only as a disambiguation suffix - in practice
     * `name()` already returns it directly, so this only matters if this
     * relation still collides with something else despite that.
     *
     * @return string
     */
    public function disambiguatedName()
    {
        if ($this->hasCommentOverride()) {
            return $this->formatName($this->foreignKeyName());
        }

        $relatedName = $this->nameForStrategy('related');
        $foreignKeyName = $this->nameForStrategy('foreign_key');

        if ($this->parent->usesSnakeAttributes()) {
            return $relatedName.'_'.$foreignKeyName;
        }

        return $relatedName.Str::studly($foreignKeyName);
    }

    /**
     * @param string $strategy
     *
     * @return string
     */
    private function nameForStrategy($strategy)
    {
        switch ($strategy) {
            case 'foreign_key':
                $relationName = $this->foreignKeyName();
                break;
            default:
            case 'related':
                $relationName = $this->related->getClassName();
                break;
        }

        return $this->formatName($relationName);
    }

    /**
     * @param string $relationName
     *
     * @return string
     */
    private function formatName($relationName)
    {
        if ($this->parent->usesSnakeAttributes()) {
            return Str::snake($relationName);
        }

        return Str::camel($relationName);
    }

    /**
     * @return string
     */
    private function foreignKeyName()
    {
        return RelationHelper::nameFromForeignKeyColumns(
            $this->parent->usesSnakeAttributes(),
            $this->command->columns ?? [],
            $this->command->references ?? [],
            $this->foreignKeyColumnComments()
        );
    }

    /**
     * @return bool
     */
    private function hasCommentOverride()
    {
        return RelationHelper::hasCommentOverride($this->command->columns ?? [], $this->foreignKeyColumnComments());
    }

    /**
     * Comment string of each of this relation's own foreign key columns,
     * keyed by column name, so RelationHelper can look for a `"relation"`
     * naming override (see RelationHelper::relationNameFromComment()).
     *
     * @return array<string, string|null>
     */
    private function foreignKeyColumnComments()
    {
        $blueprint = $this->parent->getBlueprint();

        if (! $blueprint) {
            return [];
        }

        $comments = [];
        foreach ($this->command->columns ?? [] as $column) {
            $comments[$column] = $blueprint->hasColumn($column) ? $blueprint->column($column)->comment : null;
        }

        return $comments;
    }

    /**
     * @return string
     */
    public function body()
    {
        $body = 'return $this->belongsTo(';

        $body .= $this->related->getQualifiedUserClassName().'::class';

        if ($this->needsForeignKey()) {
            $foreignKey = $this->parent->usesPropertyConstants()
                ? $this->parent->getQualifiedUserClassName().'::'.strtoupper($this->foreignKey())
                : $this->foreignKey();
            $body .= ', '.Dumper::export($foreignKey);
        }

        if ($this->needsOtherKey()) {
            $otherKey = $this->related->usesPropertyConstants()
                ? $this->related->getQualifiedUserClassName().'::'.strtoupper($this->otherKey())
                : $this->otherKey();
            $body .= ', '.Dumper::export($otherKey);
        }

        $body .= ')';

        if ($this->hasCompositeOtherKey()) {
            // We will assume that when this happens the referenced columns are a composite primary key
            // or a composite unique key. Otherwise it should be a has-many relationship which is not
            // supported at the moment. @todo: Improve relationship resolution.
            foreach ($this->command->references as $index => $column) {
                $body .= "\n\t\t\t\t\t->where(".
                    Dumper::export($this->qualifiedOtherKey($index)).
                    ", '=', ".
                    Dumper::export($this->qualifiedForeignKey($index)).
                    ')';
            }
        }

        $body .= ';';

        return $body;
    }

    /**
     * @return string
     */
    public function hint()
    {
        $base =  $this->related->getQualifiedUserClassName();

        if ($this->isNullable()) {
            $base .= '|null';
        }

        return $base;
    }

    /**
     * @return string
     */
    public function returnType()
    {
        return \Illuminate\Database\Eloquent\Relations\BelongsTo::class;
    }

    /**
     * @return bool
     */
    protected function needsForeignKey()
    {
        $defaultForeignKey = $this->related->getRecordName().'_id';

        return $defaultForeignKey != $this->foreignKey() || $this->needsOtherKey();
    }

    /**
     * @param int $index
     *
     * @return string
     */
    protected function foreignKey($index = 0)
    {
        return $this->command->columns[$index];
    }

    /**
     * @param int $index
     *
     * @return string
     */
    protected function qualifiedForeignKey($index = 0)
    {
        return $this->parent->getTable().'.'.$this->foreignKey($index);
    }

    /**
     * @return bool
     */
    protected function needsOtherKey()
    {
        $defaultOtherKey = $this->related->getPrimaryKey();

        return $defaultOtherKey != $this->otherKey();
    }

    /**
     * @param int $index
     *
     * @return string
     */
    protected function otherKey($index = 0)
    {
        return $this->command->references[$index];
    }

    /**
     * @param int $index
     *
     * @return string
     */
    protected function qualifiedOtherKey($index = 0)
    {
        return $this->related->getTable().'.'.$this->otherKey($index);
    }

    /**
     * Whether the "other key" is a composite foreign key.
     *
     * @return bool
     */
    protected function hasCompositeOtherKey()
    {
        return count($this->command->references) > 1;
    }

    /**
     * @return bool
     */
    private function isNullable()
    {
        return (bool) $this->parent->getBlueprint()->column($this->foreignKey())->get('nullable');
    }
}
