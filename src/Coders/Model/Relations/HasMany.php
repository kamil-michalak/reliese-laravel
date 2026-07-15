<?php

/**
 * Created by Cristian.
 * Date: 11/09/16 09:26 PM.
 */

namespace Reliese\Coders\Model\Relations;

use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Collection;

class HasMany extends HasOneOrMany
{
    /**
     * @return string
     */
    public function hint()
    {
        return '\\'.Collection::class.'|'.$this->related->getQualifiedUserClassName().'[]';
    }

    /**
     * @return string
     */
    public function name()
    {
        return $this->nameForStrategy($this->parent->getRelationNameStrategy());
    }

    /**
     * @return string
     */
    public function disambiguatedName()
    {
        return $this->nameForStrategy('foreign_key');
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
                $relationName = RelationHelper::nameFromForeignKeyColumns(
                    $this->parent->usesSnakeAttributes(),
                    $this->command->columns,
                    $this->command->references,
                    $this->foreignKeyColumnComments()
                );
                if (Str::snake($relationName) === Str::snake($this->parent->getClassName())) {
                    $relationName = Str::plural($this->related->getClassName());
                } else {
                    $relationName = Str::plural($this->related->getClassName()) . 'Where' . ucfirst(Str::singular($relationName));
                }
                break;
            default:
            case 'related':
                $relationName = Str::plural($this->related->getClassName());
                break;
        }

        if ($this->parent->usesSnakeAttributes()) {
            return Str::snake($relationName);
        }

        return Str::camel($relationName);
    }

    /**
     * Comment string of each of this relation's own foreign key columns,
     * keyed by column name, so RelationHelper can look for a `"relation"`
     * naming override (see RelationHelper::relationNameFromComment()).
     * These columns live on the related (child) table, not the parent.
     *
     * @return array<string, string|null>
     */
    private function foreignKeyColumnComments()
    {
        $blueprint = $this->related->getBlueprint();

        if (! $blueprint) {
            return [];
        }

        $comments = [];
        foreach ($this->command->columns as $column) {
            $comments[$column] = $blueprint->hasColumn($column) ? $blueprint->column($column)->comment : null;
        }

        return $comments;
    }

    /**
     * @return string
     */
    public function method()
    {
        return 'hasMany';
    }

    /**
     * @return string
     */
    public function returnType()
    {
        return \Illuminate\Database\Eloquent\Relations\HasMany::class;
    }
}
