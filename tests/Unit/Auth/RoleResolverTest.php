<?php
namespace Dukhanin\Acl\Tests\Unit\Auth;

use Dukhanin\Acl\Auth\Access\RoleResolver;
use Dukhanin\Acl\Tests\CreatesApplication;
use Dukhanin\Acl\Tests\TestCase;

class RoleResolverTest extends TestCase
{
    use CreatesApplication;

    protected $resolver;

    public function setUp()
    {
        parent::setUp();

        $this->resolver = new RoleResolver;

        // Важно, чтобы вложенные списки были в алфавитном порядке
        $this->cases = [
            'dominate' => [
                ['root', ['admin', 'guest', 'moderator', 'root', 'user']],
                ['admin', ['admin', 'guest', 'moderator', 'user']],
                ['moderator', ['guest', 'user']],
                ['user', []],
                ['guest', []],
            ],
            'obey' => [
                ['root', []],
                ['admin', ['root']],
                ['moderator', ['admin', 'root']],
                ['user', ['admin', 'moderator', 'root']],
                ['guest', ['admin', 'moderator', 'root']],
            ],
        ];
    }

    public function test_getRoles_without_attributes_returns_all_roles()
    {
        $roles = $this->resolver->getRoles()->sort()->values()->toArray();

        $this->assertEquals(['admin', 'guest', 'moderator', 'root', 'user'], $roles);
    }

    public function test_getRoles_returns_obeys_and_dominates_roles()
    {
        foreach ($this->cases as $verb => $cases) {
            foreach ($cases as $case) {
                [$role, $list] = $case;
                $this->assertEquals($this->resolver->getRoles($role, $verb)->sort()->values()->toArray(), $list);
            }
        }
    }

    public function test_getRoles_returns_empty_arrays_for_invalid_roles_and_verbs()
    {
        $this->assertEmpty($this->resolver->getRoles('queen', 'obey'));
        $this->assertEmpty($this->resolver->getRoles('queen', 'dominate'));
        $this->assertEmpty($this->resolver->getRoles('queen'));

        $this->assertEmpty($this->resolver->getRoles('root', 'undress'));
        $this->assertEmpty($this->resolver->getRoles('guest', 'undress'));
        $this->assertEmpty($this->resolver->getRoles('guest'));
    }

    public function test_getRoles_returns_lists_for_role()
    {
        foreach ($this->cases as $verb => $cases) {
            foreach ($cases as $case) {
                [$role, $list] = $case;
                $roleName = is_null($role) ? 'guest(null)' : $role;

                $this->resolver->getRoles($role, 'obey');

                $this->assertEquals(empty($list) ? false : true, $this->resolver->does($role, $verb, $list));
            }
        }
    }

    public function test_does_returns_false_for_invalid_roles_and_verbs()
    {
        $this->assertFalse($this->resolver->does('root', 'undress', 'guest'));
        $this->assertFalse($this->resolver->does('guest', 'undress', 'root'));
        $this->assertFalse($this->resolver->does('guest', 'obey', 'queen'));
        $this->assertFalse($this->resolver->does('guest', 'dominate', 'queen'));
    }

    public function test_does_resolves_roles()
    {
        foreach ($this->cases as $verb => $cases) {
            foreach ($cases as $case) {
                [$role, $list] = $case;
                $roleName = is_null($role) ? 'guest(null)' : $role;

                // check single role
                foreach ($list as $checkRole) {
                    $message = "{$roleName} does not {$verb} {$checkRole} and that is incorrect";
                    $this->assertTrue($this->resolver->does($role, $verb, $checkRole), $message);
                }

                // check group of roles
                $message = "{$roleName} does not {$verb} [".implode(', ', $list)."] and that is incorrect";
                $this->assertEquals(empty($list) ? false : true, $this->resolver->does($role, $verb, $list), $message);
            }
        }
    }
}