<?php
namespace Dukhanin\Acl\Contracts\Auth;

interface RoleResolver
{
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
    public function getRoles($role = null, string $verb = null);

    /**
     * Проверить, удовлетворяют ли перечисленные роли в $compareToRole
     * глаголу $verb по отношению к $role
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
    public function does($role, string $verb, $compareToRole);
}