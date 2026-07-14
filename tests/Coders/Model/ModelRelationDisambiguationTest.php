<?php

use Illuminate\Support\Fluent;
use Reliese\Coders\Model\Factory;
use Reliese\Coders\Model\Model;
use Reliese\Meta\Blueprint;

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
