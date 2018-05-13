<?php

namespace Dukhanin\Acl\Tests\Unit\Auth;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Dukhanin\Acl\Auth\Access\AccessManager;
use Dukhanin\Acl\Auth\Access\Rule;
use Dukhanin\Acl\Tests\CreatesApplication;
use Dukhanin\Acl\Tests\TestCase;
use Dukhanin\Support\Contracts\Morphable;

class AccessManagerTest extends TestCase
{
    use CreatesApplication, DatabaseMigrations;

    protected $manager;

    public function setUp()
    {
        parent::setUp();

        $this->manager = new AccessManager();

        $this->manager->preloadEnabled(false);

        $this->user = new class implements Morphable
        {
            public function getMorphType()
            {
                return 'magic_user';
            }

            public function getMorphKey()
            {
                return 100;
            }
        };

        $this->userModel = new class implements Morphable
        {
            public function getMorphType()
            {
                return 'magic_user';
            }

            public function getMorphKey()
            {
                return null;
            }
        };

        $this->post = new class implements Morphable
        {
            public function getMorphType()
            {
                return 'magic_post';
            }

            public function getMorphKey()
            {
                return 200;
            }
        };

        $this->postModel = new class implements Morphable
        {
            public function getMorphType()
            {
                return 'magic_post';
            }

            public function getMorphKey()
            {
                return null;
            }
        };
    }

    public function test_set_saves_values()
    {
        $this->manager->set($this->user, 'edit', $this->post, 11);
        $this->manager->set($this->user, 'create', $this->post, 22);
        $this->manager->set($this->user, 'skip', $this->post, 33);
        $this->manager->set($this->user, null, $this->post, 44);

        $this->assertCount(1, Rule::where([
            'object_type' => 'magic_user',
            'object_key' => 100,
            'subject_type' => 'magic_post',
            'subject_key' => 200,
            'ability' => 'edit',
            'value' => 11,
        ])->get());

        $this->assertCount(1, Rule::where([
            'object_type' => 'magic_user',
            'object_key' => 100,
            'subject_type' => 'magic_post',
            'subject_key' => 200,
            'ability' => 'edit',
            'value' => 11,
        ])->get());

        $this->assertCount(1, Rule::where([
            'object_type' => 'magic_user',
            'object_key' => 100,
            'subject_type' => 'magic_post',
            'subject_key' => 200,
            'ability' => 'create',
            'value' => 22,
        ])->get());

        $this->assertCount(1, Rule::where([
            'object_type' => 'magic_user',
            'object_key' => 100,
            'subject_type' => 'magic_post',
            'subject_key' => 200,
            'ability' => null,
            'value' => 44,
        ])->get());

        $this->assertCount(4, Rule::all());
    }

    public function test_set_saves_values_without_morph_keys()
    {
        $this->manager->set($this->userModel, 'edit', $this->postModel, 11);
        $this->manager->set($this->userModel, 'create', $this->postModel, 22);
        $this->manager->set($this->userModel, 'skip', $this->postModel, 33);

        $this->assertCount(1, Rule::where([
            'object_type' => 'magic_user',
            'object_key' => null,
            'subject_type' => 'magic_post',
            'subject_key' => null,
            'ability' => 'edit',
            'value' => 11,
        ])->get());

        $this->assertCount(1, Rule::where([
            'object_type' => 'magic_user',
            'object_key' => null,
            'subject_type' => 'magic_post',
            'subject_key' => null,
            'ability' => 'edit',
            'value' => 11,
        ])->get());

        $this->assertCount(1, Rule::where([
            'object_type' => 'magic_user',
            'object_key' => null,
            'subject_type' => 'magic_post',
            'subject_key' => null,
            'ability' => 'create',
            'value' => 22,
        ])->get());

        $this->assertCount(3, Rule::all());
    }

    public function test_set_removes_rules_if_value_is_null()
    {
        $this->manager->set($this->user, 'edit', $this->post, 1);
        $this->manager->set($this->user, 'edit blog', $this->post, 1);
        $this->assertCount(2, Rule::all());

        $this->manager->set($this->user, 'edit', $this->post, null);
        $this->assertCount(1, Rule::all());
        $this->assertEquals('edit blog', Rule::first()->ability);
    }

    public function test_set_overwrites_rules()
    {
        $this->manager->set($this->user, 'edit', $this->post, 1);
        $this->assertCount(1, Rule::all());
        $this->assertEquals(1, Rule::first()->value);

        $this->manager->set($this->user, 'edit', $this->post, 0);
        $this->assertCount(1, Rule::all());
        $this->assertEquals(0, Rule::first()->value);
    }

    public function test_can_returns_null_if_there_is_no_any_rule_set()
    {
        $this->assertNull($this->manager->can($this->user, 'edit', $this->post));
    }

    public function test_cannot_returns_true_if_there_is_no_any_rule_set()
    {
        $this->assertTrue($this->manager->cannot($this->user, 'edit', $this->post));
    }

    public function test_can_and_cannot_handles_nested_abilities()
    {
        //  ability   | rule value | exp | can()       | cannot()
        // =======================================================
        //  edit blog | (no rule)  |     | (goes down) | (goes down)
        //  edit      | (no rule)  |     | (goes down) | (goes down)
        //  (no rule) | (no rule)  |  >  | null        | true
        $this->assertNull($this->manager->can($this->user, 'edit blog', $this->post));
        $this->assertTrue($this->manager->cannot($this->user, 'edit blog', $this->post));

        $this->manager->set($this->user, 'edit', $this->post, 1);
        //  ability   | rule value | exp | can()       | cannot()
        // =======================================================
        //  edit blog | (no rule)  |     | (goes down) | (goes down)
        //  edit      | 1          |  >  | true        | false
        //  (no rule) | (no rule)  |     | null        | true
        $this->assertTrue($this->manager->can($this->user, 'edit blog', $this->post));
        $this->assertFalse($this->manager->cannot($this->user, 'edit blog', $this->post));

        $this->manager->set($this->user, 'edit blog', $this->post, 0);
        //  ability   | rule value | exp | can()       | cannot()
        // =======================================================
        //  edit blog | 0          |     | false       | true
        //  edit      | 1          |  >  | true        | false
        //  (no rule) | (no rule)  |     | null        | true
        $this->assertFalse($this->manager->can($this->user, 'edit blog', $this->post));
        $this->assertTrue($this->manager->cannot($this->user, 'edit blog', $this->post));
    }

    public function test_can_and_cannot_handles_default_values_for_object()
    {
        // rule for          | object_type | object_key | ability   | rule value | exp | can()       | cannot()
        // =====================================================================================================
        // concrete instance | magic_user  | 100        | edit      |            |     | (goes down) | (goes down)
        // any instance      | magic_user  | null       | edit      |            |     | (goes down) | (goes down)
        // any model         | null        | null       | edit      |            |     | (goes down) | (goes down)
        // (no rule)         | (no rule)   | (no rule)  | (no rule) | (no rule)  |  >  | null        | true
        $this->assertNull($this->manager->can($this->user, 'edit'));
        $this->assertTrue($this->manager->cannot($this->user, 'edit'));

        // Дефолтное правило (без указанного $object) разрешает
        $this->manager->set(null, 'edit', null, 1);

        // rule for          | object_type | object_key | ability   | rule value | exp | can()       | cannot()
        // =====================================================================================================
        // concrete instance | magic_user  | 100        | edit      |            |     | (goes down) | (goes down)
        // any instance      | magic_user  | null       | edit      |            |     | (goes down) | (goes down)
        // any model         | null        | null       | edit      |            |  >  | true        | false
        // (no rule)         | (no rule)   | (no rule)  | (no rule) | (no rule)  |     | null        | true
        $this->assertTrue($this->manager->can($this->user, 'edit'));
        $this->assertFalse($this->manager->cannot($this->user, 'edit'));

        // Дефолтное правило для всех моделей типа magic_user (без указанного object_key) запрещает
        $this->manager->set($this->userModel, 'edit', null, 0);

        // rule for          | object_type | object_key | ability   | rule value | exp | can()       | cannot()
        // =====================================================================================================
        // concrete instance | magic_user  | 100        | edit      |            |     | (goes down) | (goes down)
        // any instance      | magic_user  | null       | edit      |            |  >  | false       | true
        // any model         | null        | null       | edit      |            |     | true        | false
        // (no rule)         | (no rule)   | (no rule)  | (no rule) | (no rule)  |     | null        | true
        $this->assertFalse($this->manager->can($this->user, 'edit'));
        $this->assertTrue($this->manager->cannot($this->user, 'edit'));

        // Установим правило для конкретного $object = $this->user, которое будет
        // разрешать
        $this->manager->set($this->user, 'edit', null, 1);

        // rule for          | object_type | object_key | ability   | rule value | exp | can()       | cannot()
        // =====================================================================================================
        // concrete instance | magic_user  | 100        | edit      |            |  >  | true        | false
        // any instance      | magic_user  | null       | edit      |            |     | false       | true
        // any model         | null        | null       | edit      |            |     | true        | false
        // (no rule)         | (no rule)   | (no rule)  | (no rule) | (no rule)  |     | null        | true
        $this->assertTrue($this->manager->can($this->user, 'edit'));
        $this->assertFalse($this->manager->cannot($this->user, 'edit'));

        // В это же время, запрос действия на любую другую модель этого типа
        // должно отдавать false
        $otherConcreteUser = new class implements Morphable
        {
            public function getMorphType()
            {
                return 'magic_user';
            }

            public function getMorphKey()
            {
                return 101;
            }
        };

        // rule for          | object_type | object_key | ability   | rule value | exp | can()       | cannot()
        // =====================================================================================================
        // concrete instance | magic_user  | 100        | edit      |            |     | true        | false
        // any instance      | magic_user  | null       | edit      |            |  >  | false       | true
        // any model         | null        | null       | edit      |            |     | true        | false
        // (no rule)         | (no rule)   | (no rule)  | (no rule) | (no rule)  |     | null        | true
        $this->assertFalse($this->manager->can($otherConcreteUser, 'edit'));
        $this->assertTrue($this->manager->cannot($otherConcreteUser, 'edit'));
    }

    public function test_can_and_cannot_handles_default_values_for_subject()
    {
        // rule for         | ability   | subject_type | subject_key | rule value | exp | can()       | cannot()
        // ========================================================================================================
        // concrete instance| edit      | magic_post   | 100         |            |     | (goes down) | (goes down)
        // any instance     | edit      | magic_post   | null        |            |     | (goes down) | (goes down)
        // any model        | edit      | null         | null        |            |     | (goes down) | (goes down)
        // (no rule)        | (no rule) | (no rule)    | (no rule)   | (no rule)  |  >> | null        | true
        $this->assertNull($this->manager->can($this->user, 'edit', $this->post));
        $this->assertTrue($this->manager->cannot($this->user, 'edit', $this->post));

        $this->assertNull($this->manager->can($this->user, 'edit'));
        $this->assertTrue($this->manager->cannot($this->user, 'edit'));

        // Дефолтное правило (без указанного $subject) разрешает
        $this->manager->set($this->user, 'edit', null, 1);

        // rule for         | ability   | subject_type | subject_key | rule value | exp | can()       | cannot()
        // ========================================================================================================
        // concrete instance| edit      | magic_post   | 100         |            |     | (goes down) | (goes down)
        // any instance     | edit      | magic_post   | null        |            |     | (goes down) | (goes down)
        // any model        | edit      | null         | null        | 1          |  >> | true        | false
        // (no rule)        | (no rule) | (no rule)    | (no rule)   | (no rule)  |     | null        | true
        $this->assertTrue($this->manager->can($this->user, 'edit', $this->post));
        $this->assertFalse($this->manager->cannot($this->user, 'edit', $this->post));

        $this->assertTrue($this->manager->can($this->user, 'edit'));
        $this->assertFalse($this->manager->cannot($this->user, 'edit'));

        // Перенакроем дефолтное правило запрещающее действие
        // для всех объектов типа magic_post
        $this->manager->set($this->user, 'edit', $this->postModel, 0);

        // rule for         | ability   | subject_type | subject_key | rule value | exp | can()       | cannot()
        // ========================================================================================================
        // concrete instance| edit      | magic_post   | 100         |            |     | (goes down) | (goes down)
        // any instance     | edit      | magic_post   | null        | 0          |  >  | false       | true
        // any model        | edit      | null         | null        | 1          |  >  | true        | false
        // (no rule)        | (no rule) | (no rule)    | (no rule)   | (no rule)  |     | null        | true
        $this->assertFalse($this->manager->can($this->user, 'edit', $this->post));
        $this->assertTrue($this->manager->cannot($this->user, 'edit', $this->post));

        $this->assertTrue($this->manager->can($this->user, 'edit'));
        $this->assertFalse($this->manager->cannot($this->user, 'edit'));

        // Установим правило для конкретного $subject = $this->post, которое будет
        // разрешать
        $this->manager->set($this->user, 'edit', $this->post, 1);

        // rule for         | ability   | subject_type | subject_key | rule value | exp | can()       | cannot()
        // ========================================================================================================
        // concrete instance| edit      | magic_post   | 100         | 1          |  >  | true        | false
        // any instance     | edit      | magic_post   | null        | 0          |     | false       | true
        // any model        | edit      | null         | null        | 1          |  >  | true        | false
        // (no rule)        | (no rule) | (no rule)    | (no rule)   | (no rule)  |     | null        | true
        $this->assertTrue($this->manager->can($this->user, 'edit', $this->post));
        $this->assertFalse($this->manager->cannot($this->user, 'edit', $this->post));

        $this->assertTrue($this->manager->can($this->user, 'edit'));
        $this->assertFalse($this->manager->cannot($this->user, 'edit'));

        // В это же время, запрос действия на любую другую модель этого типа
        // должно отдавать false
        $otherConcretePost = new class implements Morphable
        {
            public function getMorphType()
            {
                return 'magic_post';
            }

            public function getMorphKey()
            {
                return 201;
            }
        };

        // rule for         | ability   | subject_type | subject_key | rule value | exp | can()       | cannot()
        // ========================================================================================================
        // concrete instance| edit      | magic_post   | 100         | 1          |     | true        | false
        // any instance     | edit      | magic_post   | null        | 0          |  >  | false       | true
        // any model        | edit      | null         | null        | 1          |     | true        | false
        // (no rule)        | (no rule) | (no rule)    | (no rule)   | (no rule)  |     | null        | true
        $this->assertFalse($this->manager->can($this->user, 'edit', $otherConcretePost));
        $this->assertTrue($this->manager->cannot($this->user, 'edit', $otherConcretePost));
    }

    public function test_can_handles_default_values_with_nested_abilities()
    {
        // rule for          | ability   | subject_type | subject_key | rule value | exp  | can()        | cannot()
        // =========================================================================================================
        // concrete instance | edit blog | magic_post   | 100         |            |      | (goes down) | (goes down)
        // any instance      | edit blog | magic_post   | null        |            |      | (goes down) | (goes down)
        // any model         | edit blog | null         | null        |            |      | (goes down) | (goes down)
        // concrete instance | edit      | magic_post   | 100         |            |      | (goes down) | (goes down)
        // any instance      | edit      | magic_post   | null        |            |      | (goes down) | (goes down)
        // any model         | edit      | null         | null        |            |      | (goes down) | (goes down)
        // no rules          | (no rule) | (no rule)    | (no rule)   | (no rule)  | >>>> | null        | true
        $this->assertNull($this->manager->can($this->user, 'edit blog', $this->post));
        $this->assertTrue($this->manager->cannot($this->user, 'edit blog', $this->post));

        $this->assertNull($this->manager->can($this->user, 'edit blog'));
        $this->assertTrue($this->manager->cannot($this->user, 'edit blog'));

        $this->assertNull($this->manager->can($this->user, 'edit', $this->post));
        $this->assertTrue($this->manager->cannot($this->user, 'edit', $this->post));

        $this->assertNull($this->manager->can($this->user, 'edit'));
        $this->assertTrue($this->manager->cannot($this->user, 'edit'));

        // Сначала пошагово все разрешаем - от более конкретного к общему
        $this->manager->set($this->user, 'edit blog', null, 1);

        // rule for          | ability   | subject_type | subject_key | rule value | exp  | can()        | cannot()
        // =========================================================================================================
        // concrete instance | edit blog | magic_post   | 100         |            |      | (goes down) | (goes down)
        // any instance      | edit blog | magic_post   | null        |            |      | (goes down) | (goes down)
        // any model         | edit blog | null         | null        | 1          |  >>  | true        | false
        // concrete instance | edit      | magic_post   | 100         |            |      | (goes down) | (goes down)
        // any instance      | edit      | magic_post   | null        |            |      | (goes down) | (goes down)
        // any model         | edit      | null         | null        |            |      | (goes down) | (goes down)
        // no rules          | (no rule) | (no rule)    | (no rule)   | (no rule)  |  >>  | null        | true
        $this->assertTrue($this->manager->can($this->user, 'edit blog'));
        $this->assertFalse($this->manager->cannot($this->user, 'edit blog'));

        $this->assertTrue($this->manager->can($this->user, 'edit blog', $this->post));
        $this->assertFalse($this->manager->cannot($this->user, 'edit blog', $this->post));

        $this->assertNull($this->manager->can($this->user, 'edit', $this->post));
        $this->assertTrue($this->manager->cannot($this->user, 'edit', $this->post));

        $this->assertNull($this->manager->can($this->user, 'edit'));
        $this->assertTrue($this->manager->cannot($this->user, 'edit'));

        $this->manager->set($this->user, 'edit', null, 1);
        // rule for          | ability   | subject_type | subject_key | rule value | exp  | can()        | cannot()
        // =========================================================================================================
        // concrete instance | edit blog | magic_post   | 100         |            |      | (goes down) | (goes down)
        // any instance      | edit blog | magic_post   | null        |            |      | (goes down) | (goes down)
        // any model         | edit blog | null         | null        | 1          |  >>  | true        | false
        // concrete instance | edit      | magic_post   | 100         |            |      | (goes down) | (goes down)
        // any instance      | edit      | magic_post   | null        |            |      | (goes down) | (goes down)
        // any model         | edit      | null         | null        | 1          |  >>  | true        | false
        // no rules          | (no rule) | (no rule)    | (no rule)   | (no rule)  |      | null        | true
        $this->assertTrue($this->manager->can($this->user, 'edit blog'));
        $this->assertFalse($this->manager->cannot($this->user, 'edit blog'));

        $this->assertTrue($this->manager->can($this->user, 'edit blog', $this->post));
        $this->assertFalse($this->manager->cannot($this->user, 'edit blog', $this->post));

        $this->assertTrue($this->manager->can($this->user, 'edit', $this->post));
        $this->assertFalse($this->manager->cannot($this->user, 'edit', $this->post));

        $this->assertTrue($this->manager->can($this->user, 'edit'));
        $this->assertFalse($this->manager->cannot($this->user, 'edit'));

        // Теперь пошагово все запрещаем - от более общего к более конкретному
        $this->manager->set($this->user, 'edit', null, 0);

        // rule for          | ability   | subject_type | subject_key | rule value | exp  | can()        | cannot()
        // =========================================================================================================
        // concrete instance | edit blog | magic_post   | 100         |            |      | (goes down) | (goes down)
        // any instance      | edit blog | magic_post   | null        |            |      | (goes down) | (goes down)
        // any model         | edit blog | null         | null        | 1          |  >>  | true        | false
        // concrete instance | edit      | magic_post   | 100         |            |      | (goes down) | (goes down)
        // any instance      | edit      | magic_post   | null        |            |      | (goes down) | (goes down)
        // any model         | edit      | null         | null        | 0          |  >>  | false       | true
        // no rules          | (no rule) | (no rule)    | (no rule)   | (no rule)  |      | null        | true
        $this->assertTrue($this->manager->can($this->user, 'edit blog'));
        $this->assertFalse($this->manager->cannot($this->user, 'edit blog'));

        $this->assertTrue($this->manager->can($this->user, 'edit blog', $this->post));
        $this->assertFalse($this->manager->cannot($this->user, 'edit blog', $this->post));

        $this->assertFalse($this->manager->can($this->user, 'edit', $this->post));
        $this->assertTrue($this->manager->cannot($this->user, 'edit', $this->post));

        $this->assertFalse($this->manager->can($this->user, 'edit'));
        $this->assertTrue($this->manager->cannot($this->user, 'edit'));

        $this->manager->set($this->user, 'edit blog', null, 0);
        // rule for          | ability   | subject_type | subject_key | rule value | exp  | can()        | cannot()
        // =========================================================================================================
        // concrete instance | edit blog | magic_post   | 100         |            |      | (goes down) | (goes down)
        // any instance      | edit blog | magic_post   | null        |            |      | (goes down) | (goes down)
        // any model         | edit blog | null         | null        | 0          |  >>  | false       | true
        // concrete instance | edit      | magic_post   | 100         |            |      | (goes down) | (goes down)
        // any instance      | edit      | magic_post   | null        |            |      | (goes down) | (goes down)
        // any model         | edit      | null         | null        | 0          |  >>  | false       | true
        // no rules          | (no rule) | (no rule)    | (no rule)   | (no rule)  |      | null        | true
        $this->assertFalse($this->manager->can($this->user, 'edit blog'));
        $this->assertTrue($this->manager->cannot($this->user, 'edit blog'));

        $this->assertFalse($this->manager->can($this->user, 'edit blog', $this->post));
        $this->assertTrue($this->manager->cannot($this->user, 'edit blog', $this->post));

        $this->assertFalse($this->manager->can($this->user, 'edit', $this->post));
        $this->assertTrue($this->manager->cannot($this->user, 'edit', $this->post));

        $this->assertFalse($this->manager->can($this->user, 'edit'));
        $this->assertTrue($this->manager->cannot($this->user, 'edit'));
    }
}
