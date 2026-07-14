<?php

use Illuminate\Support\Fluent;
use Reliese\Coders\Model\Factory;
use Reliese\Coders\Model\Model;
use Reliese\Meta\Blueprint;
use Reliese\Meta\Schema;
use Reliese\Meta\SchemaManager;

class ModelRelationDisambiguationTest extends TestCase
{
    /**
     * Two foreign keys on the same table pointing at the same related table
     * (e.g. `manager_id` and `mentor_id`, both referencing `employees.id`)
     * used to collide on the same relation name and silently overwrite each
     * other. The first one found should keep its default name, and the
     * second one should fall back to a name based on its own foreign key.
     */
    public function testCollidingBelongsToRelationsAreBothGenerated()
    {
        $managerRelation = new Fluent([
            'columns' => ['manager_id'],
            'references' => ['id'],
            'on' => ['test', 'employees'],
        ]);

        $mentorRelation = new Fluent([
            'columns' => ['mentor_id'],
            'references' => ['id'],
            'on' => ['test', 'employees'],
        ]);

        $blueprint = Mockery::mock(Blueprint::class);
        $blueprint->shouldReceive('columns')->andReturn([]);
        $blueprint->shouldReceive('schema')->andReturn('test');
        $blueprint->shouldReceive('qualifiedTable')->andReturn('test.employees');
        $blueprint->shouldReceive('connection')->andReturn('test');
        $blueprint->shouldReceive('primaryKey')->andReturn(new Fluent(['columns' => ['id']]));
        $blueprint->shouldReceive('relations')->andReturn([$managerRelation, $mentorRelation]);
        $blueprint->shouldReceive('table')->andReturn('employees');
        $blueprint->shouldReceive('is')->andReturn(true);
        $blueprint->shouldReceive('column')->andReturn(new Fluent(['nullable' => true]));

        $model = new Model(
            $blueprint,
            new Factory(
                Mockery::mock(\Illuminate\Database\DatabaseManager::class),
                Mockery::mock(\Illuminate\Filesystem\Filesystem::class),
                Mockery::mock(\Reliese\Support\Classify::class),
                new \Reliese\Coders\Model\Config()
            )
        );

        $relations = $model->getRelations();

        $this->assertCount(2, $relations, 'Both relations should be generated instead of one overwriting the other.');
        $this->assertArrayHasKey('employee', $relations, 'The first relation should keep its default (related) name.');
        $this->assertArrayHasKey('employee_mentor', $relations, 'The colliding relation should be disambiguated using the related name plus its own foreign key.');

        $this->assertSame('manager_id', $this->readForeignKey($relations['employee']));
        $this->assertSame('mentor_id', $this->readForeignKey($relations['employee_mentor']));
    }

    /**
     * Real-world case: a legacy schema with PascalCase columns without
     * underscores (e.g. `GuestID` and `HostID`, both referencing
     * `zespol_tbl.ID`), while the model itself uses snake_case attributes.
     * Both relations must be generated with distinct, readable names
     * instead of colliding on `zespol_tbl` or falling back to a numeric
     * suffix like `zespol_tbl2`.
     */
    public function testCollidingBelongsToRelationsWithPascalCaseColumnsAreDisambiguated()
    {
        $guestRelation = new Fluent([
            'columns' => ['GuestID'],
            'references' => ['ID'],
            'on' => ['test', 'zespol_tbl'],
        ]);

        $hostRelation = new Fluent([
            'columns' => ['HostID'],
            'references' => ['ID'],
            'on' => ['test', 'zespol_tbl'],
        ]);

        $blueprint = Mockery::mock(Blueprint::class);
        $blueprint->shouldReceive('columns')->andReturn([]);
        $blueprint->shouldReceive('schema')->andReturn('test');
        $blueprint->shouldReceive('qualifiedTable')->andReturn('test.zespol_tbl');
        $blueprint->shouldReceive('connection')->andReturn('test');
        $blueprint->shouldReceive('primaryKey')->andReturn(new Fluent(['columns' => ['ID']]));
        $blueprint->shouldReceive('relations')->andReturn([$guestRelation, $hostRelation]);
        $blueprint->shouldReceive('table')->andReturn('zespol_tbl');
        $blueprint->shouldReceive('is')->andReturn(true);
        $blueprint->shouldReceive('column')->andReturn(new Fluent(['nullable' => true]));

        $model = new Model(
            $blueprint,
            new Factory(
                Mockery::mock(\Illuminate\Database\DatabaseManager::class),
                Mockery::mock(\Illuminate\Filesystem\Filesystem::class),
                Mockery::mock(\Reliese\Support\Classify::class),
                new \Reliese\Coders\Model\Config()
            )
        );

        $relations = $model->getRelations();

        $this->assertCount(2, $relations, 'Both relations should be generated instead of one overwriting the other.');
        $this->assertArrayHasKey('zespol_tbl', $relations, 'The first relation should keep its default (related) name.');
        $this->assertArrayHasKey('zespol_tbl_host', $relations, 'The colliding relation should be disambiguated using the related name plus its own foreign key, not a numeric suffix.');

        $this->assertSame('GuestID', $this->readForeignKey($relations['zespol_tbl']));
        $this->assertSame('HostID', $this->readForeignKey($relations['zespol_tbl_host']));
    }

    /**
     * The same collision also happens on the reverse (HasMany) side: e.g.
     * `sportmonks_fixture_events.sub_type_id` and
     * `sportmonks_fixture_events.type_id` both reference `sportmonks_type`,
     * so SportmonksType generated two `sportmonks_fixture_events()` methods.
     * The first should keep its default name, and the second should be
     * disambiguated using the existing "Where<Column>" convention already
     * used by HasMany's foreign_key strategy.
     */
    public function testCollidingHasManyRelationsAreDisambiguated()
    {
        $parentBlueprint = Mockery::mock(Blueprint::class);
        $parentBlueprint->shouldReceive('columns')->andReturn([]);
        $parentBlueprint->shouldReceive('schema')->andReturn('test');
        $parentBlueprint->shouldReceive('qualifiedTable')->andReturn('test.sportmonks_type');
        $parentBlueprint->shouldReceive('connection')->andReturn('test');
        $parentBlueprint->shouldReceive('primaryKey')->andReturn(new Fluent(['columns' => ['id']]));
        $parentBlueprint->shouldReceive('relations')->andReturn([]);
        $parentBlueprint->shouldReceive('table')->andReturn('sportmonks_type');
        $parentBlueprint->shouldReceive('is')->andReturnUsing(function ($schema, $table) {
            return $schema === 'test' && $table === 'sportmonks_type';
        });
        $parentBlueprint->shouldReceive('column')->andReturn(new Fluent(['nullable' => true]));

        $childBlueprint = Mockery::mock(Blueprint::class);
        $childBlueprint->shouldReceive('columns')->andReturn([]);
        $childBlueprint->shouldReceive('schema')->andReturn('test');
        $childBlueprint->shouldReceive('qualifiedTable')->andReturn('test.sportmonks_fixture_events');
        $childBlueprint->shouldReceive('connection')->andReturn('test');
        $childBlueprint->shouldReceive('primaryKey')->andReturn(new Fluent(['columns' => ['id']]));
        $childBlueprint->shouldReceive('table')->andReturn('sportmonks_fixture_events');
        $childBlueprint->shouldReceive('isUniqueKey')->andReturn(false);

        $subTypeReference = new Fluent([
            'columns' => ['sub_type_id'],
            'references' => ['id'],
            'on' => ['test', 'sportmonks_type'],
        ]);

        $typeReference = new Fluent([
            'columns' => ['type_id'],
            'references' => ['id'],
            'on' => ['test', 'sportmonks_type'],
        ]);

        $schema = Mockery::mock(Schema::class);
        $schema->shouldReceive('referencing')->andReturn([
            ['blueprint' => $childBlueprint, 'reference' => $subTypeReference],
            ['blueprint' => $childBlueprint, 'reference' => $typeReference],
        ]);
        $schema->shouldReceive('table')->with('sportmonks_fixture_events')->andReturn($childBlueprint);

        $schemaManager = Mockery::mock(SchemaManager::class);
        $schemaManager->shouldReceive('getIterator')->andReturn(new ArrayIterator([$schema]));
        $schemaManager->shouldReceive('make')->with('test')->andReturn($schema);

        $factory = new Factory(
            Mockery::mock(\Illuminate\Database\DatabaseManager::class),
            Mockery::mock(\Illuminate\Filesystem\Filesystem::class),
            Mockery::mock(\Reliese\Support\Classify::class),
            new \Reliese\Coders\Model\Config()
        );

        $schemasProperty = new \ReflectionProperty(Factory::class, 'schemas');
        $schemasProperty->setAccessible(true);
        $schemasProperty->setValue($factory, $schemaManager);

        $model = new Model($parentBlueprint, $factory);

        $relations = $model->getRelations();

        $this->assertCount(2, $relations, 'Both HasMany relations should be generated instead of one overwriting the other.');
        $this->assertArrayHasKey('sportmonks_fixture_events', $relations, 'The first relation should keep its default (related) name.');
        $this->assertArrayHasKey('sportmonks_fixture_events_where_type', $relations, 'The colliding relation should be disambiguated using its own foreign key.');
    }

    /**
     * @param \Reliese\Coders\Model\Relation $relation
     *
     * @return string
     */
    private function readForeignKey($relation)
    {
        $property = new \ReflectionProperty($relation, 'command');
        $property->setAccessible(true);

        return $property->getValue($relation)->columns[0];
    }
}
