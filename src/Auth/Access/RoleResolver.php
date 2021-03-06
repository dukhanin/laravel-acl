<?php
namespace Dukhanin\Acl\Auth\Access;

use Dukhanin\Acl\Contracts\Auth\RoleResolver as RoleResolverContract;

class RoleResolver implements RoleResolverContract
{
    /**
     * Настройки взаимоотношения ролей
     *
     * @var \Illuminate\Support\Collection
     */
    protected $roles;

    /**
     * AccessManager constructor.
     */
    public function __construct()
    {
        $this->roles = collect(app('acl.roles'));
    }

    /**
     * Возвращает коллекцию ролей (если аргументы не указаны)
     * или возвращает список ролей действия $verb для роли $role
     *
     * @example получить коллекцию ролей, которые которыми может управлять admin
     *          $this->getRoles('admin', 'dominate') // admin, moderator, user, guest
     *
     * @example получить коллекцию ролей, которым подчиняется user
     *          $this->getRoles('guest', 'obey') // root, admin, moderator
     *
     * @param string|null $verb
     * @param string|null $role
     *
     * @return \Illuminate\Support\Collection
     */
    public function getRoles($role = null, string $verb = null)
    {
        if ($role === null && $verb === null) {
            return $this->roles->keys();
        }

        return collect(array_get($this->roles, "{$this->resolveRole($role)}.{$this->resolveVerb($verb)}"));
    }

    /**
     * Проверить, подчиняется ($verb=obey) / доминирует ($verb=dominate) ли
     * роль $role над ролью $compareToRole
     *
     * В случае, если $compareToRole множественный (коллекция или массив),
     * true возвращается только когда каждая их перечисленных ролей удовлетворяет
     * условию $verb.
     *
     * @example является ли роль root управляющей по отношению к user
     *          $this->does('root', 'dominate', 'user')
     *
     * @param string $role
     * @param string $verb
     * @param string|array|\Illuminate\Support\Collection $compareToRole
     *
     * @return bool
     */
    public function does($role, string $verb, $compareToRole)
    {
        if (($list = $this->getRoles($role, $verb))->isEmpty()) {
            return false;
        }

        $compareToRoles = collect($compareToRole)->map(function ($role) {
            return $this->resolveRole($role);
        });

        return $compareToRoles->isNotEmpty() && $compareToRoles->diff($list)->isEmpty();
    }

    /**
     * Возратит строковой идентификатор роли
     *
     * @param string $role
     *
     * @return string
     */
    protected function resolveRole($role)
    {
        return $role ?? 'guest';
    }

    /**
     * Возвратит строковой идентификатор глагола
     * (obey или dominate)
     *
     * @param $verb
     *
     * @return mixed
     */
    protected function resolveVerb($verb)
    {
        return preg_replace('/s$/', '', $verb);
    }
}